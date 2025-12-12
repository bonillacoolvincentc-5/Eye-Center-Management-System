<?php
// Service to inventory item mapping
// This defines which inventory items are used for each type of service

function getServiceInventoryMapping() {
    return [
        'examination' => [
            ['product_name' => 'Eye Drops', 'quantity' => 1],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Cotton Swabs', 'quantity' => 3]
        ],
        'cataract_screening' => [
            ['product_name' => 'Eye Drops', 'quantity' => 2],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Screening Solution', 'quantity' => 1]
        ],
        'cataract_surgery' => [
            ['product_name' => 'Surgical Gloves', 'quantity' => 4],
            ['product_name' => 'Surgical Mask', 'quantity' => 2],
            ['product_name' => 'Anesthetic Drops', 'quantity' => 3],
            ['product_name' => 'Surgical Instruments', 'quantity' => 1],
            ['product_name' => 'Bandages', 'quantity' => 2]
        ],
        'cornea' => [
            ['product_name' => 'Eye Drops', 'quantity' => 2],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Corneal Solution', 'quantity' => 1]
        ],
        'lasik_screening' => [
            ['product_name' => 'Eye Drops', 'quantity' => 2],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Screening Solution', 'quantity' => 1]
        ],
        'pediatric' => [
            ['product_name' => 'Eye Drops', 'quantity' => 1],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Cotton Swabs', 'quantity' => 2],
            ['product_name' => 'Pediatric Tools', 'quantity' => 1]
        ],
        'colorvision' => [
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Color Vision Charts', 'quantity' => 1]
        ],
        'emergency' => [
            ['product_name' => 'Eye Drops', 'quantity' => 2],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2],
            ['product_name' => 'Emergency Kit', 'quantity' => 1],
            ['product_name' => 'Bandages', 'quantity' => 2]
        ],
        'followup' => [
            ['product_name' => 'Eye Drops', 'quantity' => 1],
            ['product_name' => 'Disposable Gloves', 'quantity' => 2]
        ]
    ];
}

function deductInventoryForService($service_type, $database, $appointment_id = null, $patient_id = null, $patient_name = null, $doctor_id = null, $doctor_name = null) {
    // Debug: Log the service type being processed
    error_log("INVENTORY DEDUCTION DEBUG - Service type: " . $service_type);
    
    $mapping = getServiceInventoryMapping();
    
    // Debug: Log available mappings
    error_log("INVENTORY DEDUCTION DEBUG - Available mappings: " . implode(', ', array_keys($mapping)));
    
    // Try to find a mapping - first exact match, then try to find similar
    $mapped_service = null;
    if (isset($mapping[$service_type])) {
        $mapped_service = $service_type;
    } else {
        // Try to find a similar service type
        $service_lower = strtolower($service_type);
        foreach ($mapping as $key => $value) {
            if (strpos($service_lower, $key) !== false || strpos($key, $service_lower) !== false) {
                $mapped_service = $key;
                break;
            }
        }
    }
    
    if (!$mapped_service) {
        error_log("INVENTORY DEDUCTION DEBUG - No mapping found for service: " . $service_type);
        return ['success' => true, 'message' => 'No inventory items mapped for this service: ' . $service_type];
    }
    
    $items_to_deduct = $mapping[$mapped_service];
    $deducted_items = [];
    $errors = [];
    
    // Start transaction
    $database->autocommit(false);
    
    try {
        foreach ($items_to_deduct as $item) {
            // Search across all inventory tables
            $product = null;
            $product_category = null;
            $product_id_field = null;
            $table_name = null;
            
            // Check surgical equipment
            $sql = "SELECT equipment_id as product_id, equipment_name as product_name, quantity, 'Surgical Equipment' as category FROM tbl_surgical_equipment 
                    WHERE equipment_name LIKE ? AND quantity >= ? 
                    ORDER BY quantity DESC LIMIT 1";
            $stmt = $database->prepare($sql);
            $search_name = '%' . $item['product_name'] . '%';
            $stmt->bind_param("si", $search_name, $item['quantity']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                $product_category = 'Surgical Equipment';
                $product_id_field = 'equipment_id';
                $table_name = 'tbl_surgical_equipment';
            } else {
                // Check glass frames
                $sql = "SELECT frame_id as product_id, frame_name as product_name, quantity, 'Eyeglasses' as category FROM tbl_glass_frames 
                        WHERE frame_name LIKE ? AND quantity >= ? 
                        ORDER BY quantity DESC LIMIT 1";
                $stmt = $database->prepare($sql);
                $stmt->bind_param("si", $search_name, $item['quantity']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $product = $result->fetch_assoc();
                    $product_category = 'Eyeglasses';
                    $product_id_field = 'frame_id';
                    $table_name = 'tbl_glass_frames';
                } else {
                    // Check medicines
                    $sql = "SELECT medicine_id as product_id, medicine_name as product_name, quantity, 'Eye Medication' as category FROM tbl_medicines 
                            WHERE medicine_name LIKE ? AND quantity >= ? 
                            ORDER BY quantity DESC LIMIT 1";
                    $stmt = $database->prepare($sql);
                    $stmt->bind_param("si", $search_name, $item['quantity']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $product = $result->fetch_assoc();
                        $product_category = 'Eye Medication';
                        $product_id_field = 'medicine_id';
                        $table_name = 'tbl_medicines';
                    }
                }
            }
            
            if ($product) {
                // Deduct the quantity
                $new_quantity = $product['quantity'] - $item['quantity'];
                $update_sql = "UPDATE $table_name SET quantity = ? WHERE $product_id_field = ?";
                $update_stmt = $database->prepare($update_sql);
                $update_stmt->bind_param("ii", $new_quantity, $product['product_id']);
                
                if ($update_stmt->execute()) {
                    $deducted_items[] = [
                        'product_id' => $product['product_id'],
                        'product_name' => $product['product_name'],
                        'product_category' => $product_category,
                        'quantity_used' => $item['quantity'],
                        'remaining_quantity' => $new_quantity,
                        'table_name' => $table_name,
                        'id_field' => $product_id_field
                    ];
                    
                    // Log the usage if appointment details are provided
                    if ($appointment_id && $patient_id && $patient_name) {
                        logInventoryUsage($appointment_id, $patient_id, $patient_name, $service_type, 
                                       $product_category, $product['product_id'], $product['product_name'], 
                                       $item['quantity'], $new_quantity, $doctor_id, $doctor_name, $database);
                    }
                } else {
                    throw new Exception("Failed to update inventory for " . $product['product_name']);
                }
            } else {
                // Item not found or insufficient quantity
                $errors[] = "Insufficient inventory for " . $item['product_name'] . " (needed: " . $item['quantity'] . ")";
            }
        }
        
        // If there are errors, rollback
        if (!empty($errors)) {
            $database->rollback();
            return [
                'success' => false, 
                'message' => 'Inventory deduction failed: ' . implode(', ', $errors),
                'errors' => $errors
            ];
        }
        
        // Commit the transaction
        $database->commit();
        
        return [
            'success' => true,
            'message' => 'Inventory items successfully deducted',
            'deducted_items' => $deducted_items
        ];
        
    } catch (Exception $e) {
        $database->rollback();
        return [
            'success' => false,
            'message' => 'Error during inventory deduction: ' . $e->getMessage()
        ];
    } finally {
        $database->autocommit(true);
    }
}

// Fetch available inventory options for a given service, based on mapping
function getAvailableInventoryForService($service_type, $database) {
    $mapping = getServiceInventoryMapping();
    $mapped_service = null;
    if (isset($mapping[$service_type])) {
        $mapped_service = $service_type;
    } else {
        $service_lower = strtolower($service_type);
        foreach ($mapping as $key => $value) {
            if (strpos($service_lower, $key) !== false || strpos($key, $service_lower) !== false) {
                $mapped_service = $key;
                break;
            }
        }
    }
    if (!$mapped_service) {
        return [];
    }
    $items_to_suggest = $mapping[$mapped_service];
    $available = [];
    foreach ($items_to_suggest as $item) {
        $search_name = '%' . $item['product_name'] . '%';
        // Surgical equipment
        $sql = "SELECT equipment_id as product_id, equipment_name as product_name, quantity, 'Surgical Equipment' as category, 'tbl_surgical_equipment' as table_name, 'equipment_id' as id_field FROM tbl_surgical_equipment WHERE equipment_name LIKE ? AND quantity > 0 ORDER BY quantity DESC LIMIT 1";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $search_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['default_quantity'] = $item['quantity'];
            $available[] = $row;
            continue;
        }
        // Eyeglasses
        $sql = "SELECT frame_id as product_id, frame_name as product_name, quantity, 'Eyeglasses' as category, 'tbl_glass_frames' as table_name, 'frame_id' as id_field FROM tbl_glass_frames WHERE frame_name LIKE ? AND quantity > 0 ORDER BY quantity DESC LIMIT 1";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $search_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['default_quantity'] = $item['quantity'];
            $available[] = $row;
            continue;
        }
        // Eye medication
        $sql = "SELECT medicine_id as product_id, medicine_name as product_name, quantity, 'Eye Medication' as category, 'tbl_medicines' as table_name, 'medicine_id' as id_field FROM tbl_medicines WHERE medicine_name LIKE ? AND quantity > 0 ORDER BY quantity DESC LIMIT 1";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $search_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['default_quantity'] = $item['quantity'];
            $available[] = $row;
            continue;
        }
    }
    return $available;
}

// Deduct explicitly selected items from inventory
function deductSelectedInventoryItems($selected_items, $database, $appointment_id = null, $patient_id = null, $patient_name = null, $service_type = null, $doctor_id = null, $doctor_name = null) {
    if (!is_array($selected_items) || empty($selected_items)) {
        return ['success' => true, 'message' => 'No items selected'];
    }
    $database->autocommit(false);
    $deducted_items = [];
    try {
        foreach ($selected_items as $item) {
            $table = $item['table_name'];
            $id_field = $item['id_field'];
            $product_id = (int)$item['product_id'];
            $qty_to_deduct = max(0, (int)$item['quantity']);
            if ($qty_to_deduct === 0) { continue; }
            // Fetch current row
            $sql = "SELECT $id_field as product_id, quantity FROM $table WHERE $id_field = ? LIMIT 1";
            $stmt = $database->prepare($sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) { throw new Exception('Product not found'); }
            $row = $res->fetch_assoc();
            if ((int)$row['quantity'] < $qty_to_deduct) { throw new Exception('Insufficient quantity'); }
            $new_qty = (int)$row['quantity'] - $qty_to_deduct;
            $upd = $database->prepare("UPDATE $table SET quantity = ? WHERE $id_field = ?");
            $upd->bind_param("ii", $new_qty, $product_id);
            if (!$upd->execute()) { throw new Exception('Failed to update quantity'); }
            // Determine product name and category for logging
            $product_name = isset($item['product_name']) ? $item['product_name'] : '';
            $product_category = isset($item['product_category']) ? $item['product_category'] : '';
            if ($appointment_id && $patient_id && $patient_name) {
                logInventoryUsage($appointment_id, $patient_id, $patient_name, $service_type ?: '', $product_category, $product_id, $product_name, $qty_to_deduct, $new_qty, $doctor_id, $doctor_name, $database);
            }
            $deducted_items[] = [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'product_category' => $product_category,
                'quantity_used' => $qty_to_deduct,
                'remaining_quantity' => $new_qty,
                'table_name' => $table,
                'id_field' => $id_field
            ];
        }
        $database->commit();
        return [ 'success' => true, 'message' => 'Selected items deducted', 'deducted_items' => $deducted_items ];
    } catch (Exception $e) {
        $database->rollback();
        return [ 'success' => false, 'message' => 'Error during selected deduction: ' . $e->getMessage() ];
    } finally {
        $database->autocommit(true);
    }
}

// Log inventory usage to database
function logInventoryUsage($appointment_id, $patient_id, $patient_name, $service_type, $product_category, $product_id, $product_name, $quantity_used, $remaining_quantity, $doctor_id = null, $doctor_name = null, $database) {
    $sql = "INSERT INTO tbl_inventory_usage (appointment_id, patient_id, patient_name, service_type, product_category, product_id, product_name, quantity_used, remaining_quantity, doctor_id, doctor_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("iisssiisiss", $appointment_id, $patient_id, $patient_name, $service_type, $product_category, $product_id, $product_name, $quantity_used, $remaining_quantity, $doctor_id, $doctor_name);
    $stmt->execute();
}

// Log general inventory activities
function logInventoryActivity($action_type, $product_category, $product_id, $product_name, $quantity_change = null, $new_quantity = null, $user_id = null, $user_name = null, $appointment_id = null, $notes = null, $database) {
    $sql = "INSERT INTO tbl_inventory_logs (action_type, product_category, product_id, product_name, quantity_change, new_quantity, user_id, user_name, appointment_id, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("ssiisiiiss", $action_type, $product_category, $product_id, $product_name, $quantity_change, $new_quantity, $user_id, $user_name, $appointment_id, $notes);
    $stmt->execute();
}

// Legacy function for backward compatibility
function logInventoryDeduction($appointment_id, $service_type, $deducted_items, $database) {
    $log_entry = json_encode([
        'appointment_id' => $appointment_id,
        'service_type' => $service_type,
        'deducted_items' => $deducted_items,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // You can create a separate log table or add to existing appointment record
    // For now, we'll just log to a simple text file
    $log_file = '../logs/inventory_deductions.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir('../logs')) {
        mkdir('../logs', 0755, true);
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $log_entry . "\n", FILE_APPEND | LOCK_EX);
}
?>