<?php
session_start();

// Check authentication first
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Fetch admin profile picture
$useremail = $_SESSION["user"];
$profileImage = "../Images/user.png";

// Check if ProfileImage column exists, if not, add it
$checkColumn = $database->query("SHOW COLUMNS FROM tbl_admin LIKE 'ProfileImage'");
if ($checkColumn->num_rows == 0) {
    // Add ProfileImage column if it doesn't exist
    $database->query("ALTER TABLE tbl_admin ADD COLUMN ProfileImage VARCHAR(255) NULL AFTER password");
}

// Now query with ProfileImage column
$stmt = $database->prepare("SELECT admin_id, ProfileImage FROM tbl_admin WHERE email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$admin_result = $stmt->get_result();
if ($admin_result->num_rows > 0) {
    $admin_row = $admin_result->fetch_assoc();
    $admin_id = $admin_row["admin_id"];
    if (isset($admin_row['ProfileImage']) && !empty($admin_row['ProfileImage'])) {
        $profilePath = '../Images/profiles/' . $admin_row['ProfileImage'];
        if (file_exists($profilePath)) {
            $profileImage = $profilePath;
        }
    } else {
        // Try file-based approach
        $fileBasedPath = "../Images/profiles/admin_{$admin_id}.jpg";
        if (file_exists($fileBasedPath)) {
            $profileImage = $fileBasedPath;
        }
    }
}

// Handle add, edit, delete actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set header for AJAX responses
    header('Content-Type: application/json');
    $response = array('status' => 'error', 'message' => '');
    
    try {
        if(isset($_POST['add_product'])) {
        try {
            $product_category = trim($_POST['product_category'] ?? '');
            $product_name = $_POST['product_name'] ?? '';
            $category = $_POST['category'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            $supplier = $_POST['supplier'] ?? '';
            $purchase_date = $_POST['purchase_date'] ?? '';
            
            if($product_category === 'Surgical Equipment') {
                $sql = "INSERT INTO tbl_surgical_equipment (equipment_name, category, quantity, price, supplier, purchase_date) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $database->prepare($sql);
                $stmt->bind_param("ssidss", $product_name, $category, $quantity, $price, $supplier, $purchase_date);
            } else if($product_category === 'Eyeglasses') {
                $brand = $_POST['brand'] ?? '';
                $model_number = $_POST['model_number'] ?? '';
                $material = $_POST['material'] ?? '';
                $size = $_POST['size'] ?? '';
                
                $sql = "INSERT INTO tbl_glass_frames (frame_name, category, brand, model_number, material, size, quantity, price, supplier, purchase_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $database->prepare($sql);
                $stmt->bind_param("ssssssidss", $product_name, $category, $brand, $model_number, $material, $size, $quantity, $price, $supplier, $purchase_date);
            } else if($product_category === 'Eye Medication') {
                $generic_name = $_POST['generic_name'] ?? '';
                $description = $_POST['description'] ?? '';
                $brand = $_POST['brand'] ?? '';
                $dosage = $_POST['dosage'] ?? '';
                $expiry_date = $_POST['expiry_date'] ?? NULL;
                
                $sql = "INSERT INTO tbl_medicines (medicine_name, generic_name, description, category, brand, dosage, quantity, price, expiration_date, supplier) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $database->prepare($sql);
                $stmt->bind_param("ssssssidss", $product_name, $generic_name, $description, $category, $brand, $dosage, $quantity, $price, $expiry_date, $supplier);
            }
            
            if($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = "Product added successfully!";
            } else {
                throw new Exception("Failed to add product: " . $database->error);
            }
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = "Error: " . $e->getMessage();
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit();
            }
        }
    }
    
    if(isset($_POST['stock_in'])) {
        try {
            $product_id = $_POST['product_id'];
            $product_category = $_POST['product_category'];
            $quantity_to_add = $_POST['quantity_to_add'];
            
            if($product_category === 'Surgical Equipment') {
                $sql = "UPDATE tbl_surgical_equipment SET quantity = quantity + ? WHERE equipment_id = ?";
            } else if($product_category === 'Eyeglasses') {
                $sql = "UPDATE tbl_glass_frames SET quantity = quantity + ? WHERE frame_id = ?";
            } else if($product_category === 'Eye Medication') {
                $sql = "UPDATE tbl_medicines SET quantity = quantity + ? WHERE medicine_id = ?";
            }
            
            $stmt = $database->prepare($sql);
            $stmt->bind_param("ii", $quantity_to_add, $product_id);
            
            if($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = "Stock updated successfully!";
            } else {
                throw new Exception("Failed to update stock: " . $database->error);
            }
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = "Error: " . $e->getMessage();
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit();
            }
        }
    }

    if(isset($_POST['update_product'])) {
        try {
            
            $product_id = $_POST['product_id'] ?? '';
            $product_category = trim($_POST['product_category'] ?? '');
            $product_name = $_POST['product_name'] ?? '';
            $category = $_POST['category'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            $supplier = $_POST['supplier'] ?? '';
            $purchase_date = $_POST['purchase_date'] ?? '';
            
            if(empty($product_id) || empty($product_category)) {
                throw new Exception("Missing required fields: ID='$product_id', Category='$product_category'");
            }
            
            if($product_category === 'Surgical Equipment') {
                $sql = "UPDATE tbl_surgical_equipment SET equipment_name=?, category=?, quantity=?, price=?, supplier=?, purchase_date=? 
                        WHERE equipment_id=?";
                $stmt = $database->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $database->error);
                }
                $stmt->bind_param("ssidssi", $product_name, $category, $quantity, $price, $supplier, $purchase_date, $product_id);
            } else if($product_category === 'Eyeglasses') {
                $brand = $_POST['brand'] ?? '';
                $model_number = $_POST['model_number'] ?? '';
                $material = $_POST['material'] ?? '';
                $size = $_POST['size'] ?? '';
                
                $sql = "UPDATE tbl_glass_frames SET frame_name=?, category=?, brand=?, model_number=?, material=?, size=?, quantity=?, price=?, supplier=?, purchase_date=? 
                        WHERE frame_id=?";
                $stmt = $database->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $database->error);
                }
                $stmt->bind_param("ssssssidssi", $product_name, $category, $brand, $model_number, $material, $size, $quantity, $price, $supplier, $purchase_date, $product_id);
            } else if($product_category === 'Eye Medication') {
                $generic_name = $_POST['generic_name'] ?? '';
                $description = $_POST['description'] ?? '';
                $brand = $_POST['brand'] ?? '';
                $dosage = $_POST['dosage'] ?? '';
                $expiry_date = $_POST['expiry_date'] ?? NULL;
                
                $sql = "UPDATE tbl_medicines SET medicine_name=?, generic_name=?, description=?, category=?, brand=?, dosage=?, quantity=?, price=?, expiration_date=?, supplier=? 
                        WHERE medicine_id=?";
                $stmt = $database->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $database->error);
                }
                $stmt->bind_param("ssssssiissi", $product_name, $generic_name, $description, $category, $brand, $dosage, $quantity, $price, $expiry_date, $supplier, $product_id);
            } else {
                throw new Exception("Unknown product category: $product_category");
            }
            
            if($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = "Product updated successfully!";
            } else {
                throw new Exception("Failed to update product: " . $database->error . " | Statement error: " . $stmt->error);
            }
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = "Error: " . $e->getMessage();
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit();
            }
        }
    }
    
    if(isset($_POST['delete_product'])) {
        try {
            $product_id = $_POST['product_id'] ?? '';
            $product_category = trim($_POST['product_category'] ?? '');
            
            if(empty($product_id) || empty($product_category)) {
                throw new Exception("Missing required fields for deletion: ID='$product_id', Category='$product_category'");
            }
            
            if($product_category === 'Surgical Equipment') {
                $sql = "DELETE FROM tbl_surgical_equipment WHERE equipment_id=?";
            } else if($product_category === 'Eyeglasses') {
                $sql = "DELETE FROM tbl_glass_frames WHERE frame_id=?";
            } else if($product_category === 'Eye Medication') {
                $sql = "DELETE FROM tbl_medicines WHERE medicine_id=?";
            } else {
                throw new Exception("Unknown product category for deletion: $product_category");
            }
            
            $stmt = $database->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $database->error);
            }
            $stmt->bind_param("i", $product_id);
            
            if($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = "Product deleted successfully!";
            } else {
                throw new Exception("Failed to delete product: " . $database->error . " | Statement error: " . $stmt->error);
            }
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = "Error: " . $e->getMessage();
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit();
            }
        }
    }
    
    // Send JSON response for AJAX requests
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
        exit();
    }
    
    } catch (Exception $e) {
        $response['status'] = 'error';
        $response['message'] = "Error: " . $e->getMessage();
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit();
        }
    }
}

// Get all products from both tables
$surgical_equipment = [];
$glass_frames = [];
$medicine = [];

// Get surgical equipment
$sql = "SELECT *, 'Surgical Equipment' as product_category FROM tbl_surgical_equipment ORDER BY equipment_name";
$result = $database->query($sql);
if($result) {
    while($row = $result->fetch_assoc()) {
        $surgical_equipment[] = $row;
    }
}

// Get glass frames
$sql = "SELECT *, 'Eyeglasses' as product_category FROM tbl_glass_frames ORDER BY frame_name";
$result = $database->query($sql);
if($result) {
    while($row = $result->fetch_assoc()) {
        $glass_frames[] = $row;
    }
}

// Get medicine from tbl_medicines
$sql = "SELECT medicine_id, medicine_name, generic_name, description, category, brand, dosage, quantity, price, expiration_date, supplier, date_added, 'Eye Medication' as product_category FROM tbl_medicines ORDER BY medicine_name";
$result = $database->query($sql);
if($result) {
    while($row = $result->fetch_assoc()) {
        $medicine[] = $row;
    }
}

// Combine all products
$all_products = array_merge($surgical_equipment, $glass_frames, $medicine);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Inventory Management</title>
    <style>
        .popup { animation: transitionIn-Y-bottom 0.5s; }
        .sub-table { animation: transitionIn-Y-bottom 0.5s; }
        .inventory-card {
            background: #fff;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .category-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        .category-medication { background-color: #e3f2fd; color: #0d47a1; }
        .category-eyeglasses { background-color: #e8f5e9; color: #1b5e20; }
        .category-lenses { background-color: #fff3e0; color: #e65100; }
        .category-care { background-color: #f3e5f5; color: #4a148c; }
        .category-surgical { background-color: #ffebee; color: #b71c1c; }
        
        /* Stock level indicators */
        .stock-status {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .stock-status i {
            font-size: 1em;
        }
        .stock-none {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .stock-none:hover {
            background-color: #fecaca;
        }
        .stock-low {
            background-color: #fff7ed;
            color: #ea580c;
            border: 1px solid #fdba74;
        }
        .stock-low:hover {
            background-color: #ffedd5;
        }
        .stock-medium {
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }
        .stock-medium:hover {
            background-color: #fef08a;
        }
        .stock-high {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .stock-high:hover {
            background-color: #bbf7d0;
        }
        .search-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            margin-right: 12px;
        }
        .search-input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 250px;
        }
        .search-btn {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-btn:hover {
            background: #0056b3;
        }
        
        .btn-edit {
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-edit:hover {
            background-color: #1976D2;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .btn-view {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-view:hover {
            background-color: #218838;
        }
        
        /* Enhanced Modal Styles */
        .product-details-container {
            background: white;
            border-radius: 10px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .detail-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .detail-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .detail-item.full-width {
            grid-column: 1 / -1;
        }
        
        .detail-icon {
            font-size: 20px;
            margin-right: 12px;
            min-width: 30px;
            text-align: center;
        }
        
        .detail-content {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .detail-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-size: 14px;
            color: #212529;
            font-weight: 500;
        }
        
        .detail-value.price {
            color: #28a745;
            font-weight: 600;
            font-size: 16px;
        }
        
        .detail-value.quantity-low {
            color: #dc3545;
            font-weight: 600;
        }
        
        .detail-value.quantity-medium {
            color: #ffc107;
            font-weight: 600;
        }
        
        .detail-value.quantity-high {
            color: #28a745;
            font-weight: 600;
        }
        
        .detail-value.expired {
            color: #dc3545;
            font-weight: 600;
            background: #f8d7da;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        /* Modal Header Hover Effects */
        .close:hover {
            opacity: 0.7;
            transform: scale(1.1);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .detail-section {
                grid-template-columns: 1fr;
            }
            
            .detail-item.full-width {
                grid-column: 1;
            }
        }
        
        /* Remove button-icon class styles */
        .button-icon {
            background-image: none !important;
            padding-left: 15px !important;
        }
        
        /* Tab styles */
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
        }
        
        .tab-button {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab-button:hover {
            color: #007bff;
            background-color: #f8f9fa;
        }
        
        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: #f8f9fa;
        }
        
        /* Usage History Styles */
        .service-type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .service-cataract {
            background-color: #d4edda;
            color: #155724;
        }
        
        .service-screening {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .service-examination {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .service-surgery {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .service-default {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .quantity-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        
        .quantity-zero {
            background-color: #dc3545;
        }
        
        .quantity-low {
            background-color: #fd7e14;
        }
        
        .quantity-medium {
            background-color: #ffc107;
            color: #212529;
        }
        
        .quantity-high {
            background-color: #28a745;
        }
        
        /* Usage Details Modal Styles */
        .usage-details-container {
            max-width: 100%;
        }
        
        .usage-details-container .detail-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .usage-details-container .detail-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .usage-details-container .detail-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .usage-details-container .detail-icon {
            font-size: 24px;
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }
        
        .usage-details-container .detail-content {
            flex: 1;
        }
        
        .usage-details-container .detail-label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .usage-details-container .detail-value {
            display: block;
            color: #212529;
            font-size: 16px;
        }
        
        .service-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="menu">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px">
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title">Administrator</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-dashbord">
                    <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-schedule">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Schedule</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointment</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-patient">
                    <a href="patient.php" class="non-style-link-menu"><div><p class="menu-text">Patients</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-inventory menu-active menu-icon-inventory-active">
                    <a href="inventory.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Inventory</p></div></a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-settings">
                    <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a>
                </td>
            </tr>
        </table>
    </div>
    <div class="dash-body">
        <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;margin-top:25px;">
            <tr>
                <td width="13%">
                    <a href="inventory.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Inventory Management</p>
                </td>
                <td width="15%">
                    <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                        Today's Date
                    </p>
                    <p class="heading-sub12" style="padding: 0;margin: 0;">
                        <?php echo date('Y-m-d'); ?>
                    </p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 45px;">
                        <p class="heading-main12" style="font-size:18px;color:rgb(49, 49, 49); margin: 0;">
                            Eye Center Products
                        </p>
                        <div class="search-container" style="margin: 0;">
                            <input type="text" 
                                   id="searchInput" 
                                   class="search-input" 
                                   placeholder="Search products...">
                            <button id="searchBtn" class="search-btn">
                                <i class="fa fa-search"></i>
                                Search
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div class="tab-container" style="padding: 0 45px; margin: 20px 0;">
                        <div class="tabs">
                            <button class="tab-button active" onclick="showCategory('Surgical Equipment')">Surgical Equipment</button>
                            <button class="tab-button" onclick="showCategory('Eye Medication')">Medicine</button>
                            <button class="tab-button" onclick="showCategory('Eyeglasses')">Glass Frames</button>
                            <button class="tab-button" onclick="showCategory('Activity')">Activity</button>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:20px;">
                    <center>
                        <a id="addProductLink" href="?action=add-product" class="non-style-link">
                            <button class="login-btn btn-primary btn button-icon" style="background-image: url('../Images/icons/add.svg');">
                                Add New Product
                            </button>
                        </a>
                    </center>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <center>
                        <div class="abc scroll">
                            <!-- Products Table -->
                            <table width="93%" class="sub-table scrolldown" border="0" id="productsTable">
                                <thead>
                                    <tr>
                                        <th class="table-headin" id="thProductName">Product Name</th>
                                        <th class="table-headin">Category</th>
                                        <th class="table-headin">Quantity</th>
                                        <th class="table-headin">Price</th>
                                        <th class="table-headin" id="thExpiryPurchase">Date</th>
                                        <th class="table-headin">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                if (empty($all_products)) {
                                    echo '<tr>
                                        <td colspan="6">
                                            <center>
                                                <img src="../Images/notfound.svg" width="25%">
                                                <br>
                                                <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                                    No products found in inventory!
                                                </p>
                                            </center>
                                        </td>
                                    </tr>';
                                } else {
                                    foreach($all_products as $row) {
                                        $categoryValue = trim($row["product_category"] ?? '');
                                        $category_class = match($categoryValue) {
                                            'Eyeglasses' => 'category-eyeglasses',
                                            'Surgical Equipment' => 'category-surgical',
                                            'Eye Medication' => 'category-medication',
                                            default => ''
                                        };
                                        $category_label = match($categoryValue) {
                                            'Eyeglasses' => 'Eyeglasses',
                                            'Surgical Equipment' => 'Surgical Equipment',
                                            'Eye Medication' => 'Eye Medication',
                                            default => ($categoryValue !== '' ? $categoryValue : 'N/A')
                                        };
                                        
                                        $stock_class = '';
                                        if ((int)$row["quantity"] === 0) {
                                            $stock_class = 'stock-none';
                                        } elseif ($row["quantity"] <= 5) {
                                            $stock_class = 'stock-low';
                                        } elseif ($row["quantity"] <= 15) {
                                            $stock_class = 'stock-medium';
                                        } else {
                                            $stock_class = 'stock-high';
                                        }
                                        
                                        // Get the correct ID field based on category
                                        $id_field = match($categoryValue) {
                                            'Surgical Equipment' => 'equipment_id',
                                            'Eyeglasses' => 'frame_id',
                                            'Eye Medication' => 'medicine_id',
                                            default => 'product_id'
                                        };
                                        $name_field = match($categoryValue) {
                                            'Surgical Equipment' => 'equipment_name',
                                            'Eyeglasses' => 'frame_name',
                                            'Eye Medication' => 'medicine_name',
                                            default => 'product_name'
                                        };
                                        
                                        echo '<tr data-category="'.htmlspecialchars($row["product_category"]).'" class="inv-row">
                                            <td class="tdProductName">'.htmlspecialchars($row[$name_field] ?? 'N/A').'</td>
                                            <td><span class="category-badge '.$category_class.'">'.htmlspecialchars($category_label).'</span></td>
                                            <td>
                                                <div class="stock-status '.$stock_class.'">
                                                    '.((int)$row["quantity"] === 0 ? '<i class="fas fa-exclamation-circle"></i> Out of Stock' :
                                                      ($row["quantity"] <= 5 ? '<i class="fas fa-exclamation-triangle"></i> Low Stock ('.$row["quantity"].')' :
                                                      '<i class="fas fa-check-circle"></i> In Stock ('.$row["quantity"].')'))
                                                    .'
                                                </div>
                                            </td>
                                            <td>₱'.number_format($row["price"], 2).'</td>
                                            <td class="tdExpiryPurchase">'.(isset($row["purchase_date"]) && $row["purchase_date"] ? date('M d, Y', strtotime($row["purchase_date"])) : (isset($row["expiration_date"]) && $row["expiration_date"] ? date('M d, Y', strtotime($row["expiration_date"])) : 'N/A')).'</td>
                                            <td>
                                                <div style="display:flex;justify-content: center;gap:5px;">
                                                    <button class="btn-primary-soft btn button-icon btn-view" style="width: 90px; padding: 8px 12px; font-size: 14px;" 
                                                            onclick="showProductDetails('.htmlspecialchars(json_encode($row)).')">
                                                        View
                                                    </button>
                                                    <a href="?action=edit-product&id='.($row[$id_field] ?? '').'&category='.$categoryValue.'" class="non-style-link">
                                                        <button class="btn-primary-soft btn button-icon btn-edit" style="width: 90px; padding: 8px 12px; font-size: 14px;">
                                                            Edit
                                                        </button>
                                                    </a>
                                                    <button class="btn-primary-soft btn button-icon" 
                                                            style="width: 90px; padding: 8px 12px; font-size: 14px; background-color: #28a745; color: white;" 
                                                            onclick="showStockInModal('.($row[$id_field] ?? '').', \''.htmlspecialchars($row[$name_field] ?? '').'\', \''.$categoryValue.'\')">
                                                        Stock In
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>';
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                            
                            <!-- Usage History Table (Hidden by default) -->
                            <table width="93%" class="sub-table scrolldown" border="0" id="usageHistoryTable" style="display: none;">
                                <thead>
                                    <tr>
                                        <th class="table-headin">Patient Name</th>
                                        <th class="table-headin">Service Type</th>
                                        <th class="table-headin">Quantity Deducted</th>
                                        <th class="table-headin">Usage Date</th>
                                        <th class="table-headin">Doctor</th>
                                        <th class="table-headin">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="usageHistoryBody">
                                    <tr>
                                        <td colspan="6">
                                            <center>
                                                <div class="loading-spinner" style="padding: 20px;">
                                                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff;"></i>
                                                    <p style="margin-top: 10px;">Loading usage history...</p>
                                                </div>
                                            </center>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </center>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php
// Add/Edit Product Modal
if (isset($_GET['action']) && ($_GET['action'] == 'add-product' || ($_GET['action'] == 'edit-product' && isset($_GET['id'])))) {
    $product = null;
    $title = "Add New Product";
    $button_text = "Add Product";
    $form_action = "#";
    $prefCategory = isset($_GET['category']) ? $_GET['category'] : null;
    
    if ($_GET['action'] == 'edit-product' && isset($_GET['id'])) {
        $product_id = $_GET['id'];
        $product_category = $_GET['category'] ?? '';
        
        if($product_category === 'Surgical Equipment') {
            $sql = "SELECT * FROM tbl_surgical_equipment WHERE equipment_id = ?";
        } else if($product_category === 'Eyeglasses') {
            $sql = "SELECT * FROM tbl_glass_frames WHERE frame_id = ?";
        } else if($product_category === 'Eye Medication') {
            $sql = "SELECT * FROM tbl_medicines WHERE medicine_id = ?";
        }
        
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        $title = "Edit Product";
        $button_text = "Update Product";
    }
?>
<div id="popup1" class="overlay">
    <div class="popup">
        <center>
            <h2><?php echo $title; ?></h2>
            <a class="close" href="inventory.php">&times;</a>
            <div class="content">
                <form action="<?php echo $form_action; ?>" method="POST" class="add-new-form">
                    <input type="hidden" name="product_id" value="<?php echo $product['equipment_id'] ?? $product['frame_id'] ?? $product['medicine_id'] ?? $product['product_id'] ?? ''; ?>">
                    
                    <div class="label-td">
                        <label for="product_category" class="form-label">Product Type: </label>
                        <select name="product_category" id="selectCategory" class="input-text" required>
                            <?php $cat = $product['product_category'] ?? $prefCategory; ?>
                            <option value="Surgical Equipment" <?php echo ($cat == 'Surgical Equipment') ? 'selected' : ''; ?>>Surgical Equipment</option>
                            <option value="Eye Medication" <?php echo ($cat == 'Eye Medication') ? 'selected' : ''; ?>>Medicine</option>
                            <option value="Eyeglasses" <?php echo ($cat == 'Eyeglasses') ? 'selected' : ''; ?>>Glass Frames</option>
                        </select>
                    </div>
                    
                    <div class="label-td">
                        <label for="product_name" id="lblProductName" class="form-label">Product Name: </label>
                        <input type="text" name="product_name" class="input-text" placeholder="Product Name" 
                               value="<?php echo htmlspecialchars($product['equipment_name'] ?? $product['frame_name'] ?? $product['medicine_name'] ?? $product['product_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="label-td">
                        <label for="category" class="form-label">Category: </label>
                        <input type="text" name="category" class="input-text" placeholder="Category" 
                               value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" required>
                    </div>
                    
                    <!-- Medicine specific fields -->
                    <div class="label-td" id="medicineFields">
                        <label for="generic_name" class="form-label">Generic Name: </label>
                        <input type="text" name="generic_name" class="input-text" placeholder="Generic Name" 
                               value="<?php echo htmlspecialchars($product['generic_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="label-td" id="medicineFields2">
                        <label for="description" class="form-label">Description: </label>
                        <textarea name="description" class="input-text" placeholder="Product Description" rows="3"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="label-td" id="medicineFields3">
                        <label for="brand" class="form-label">Brand: </label>
                        <input type="text" name="brand" class="input-text" placeholder="Brand" 
                               value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>">
                    </div>
                    
                    <div class="label-td" id="medicineFields4">
                        <label for="dosage" class="form-label">Dosage: </label>
                        <input type="text" name="dosage" class="input-text" placeholder="Dosage" 
                               value="<?php echo htmlspecialchars($product['dosage'] ?? ''); ?>">
                    </div>
                    
                    <!-- Glass Frames specific fields -->
                    <div class="label-td" id="glassFields">
                        <label for="brand" class="form-label">Brand: </label>
                        <input type="text" name="brand" class="input-text" placeholder="Brand" 
                               value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>">
                    </div>
                    
                    <div class="label-td" id="glassFields2">
                        <label for="model_number" class="form-label">Model Number: </label>
                        <input type="text" name="model_number" class="input-text" placeholder="Model Number" 
                               value="<?php echo htmlspecialchars($product['model_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="label-td" id="glassFields3">
                        <label for="material" class="form-label">Material: </label>
                        <input type="text" name="material" class="input-text" placeholder="Material" 
                               value="<?php echo htmlspecialchars($product['material'] ?? ''); ?>">
                    </div>
                    
                    <div class="label-td" id="glassFields4">
                        <label for="size" class="form-label">Size: </label>
                        <input type="text" name="size" class="input-text" placeholder="Size" 
                               value="<?php echo htmlspecialchars($product['size'] ?? ''); ?>">
                    </div>
                    
                    <div class="label-td">
                        <label for="quantity" class="form-label">Quantity: </label>
                        <input type="number" name="quantity" class="input-text" placeholder="Quantity" 
                               value="<?php echo $product['quantity'] ?? '0'; ?>" min="0" required>
                    </div>
                    
                    <div class="label-td">
                        <label for="price" class="form-label">Price (₱): </label>
                        <input type="number" name="price" class="input-text" placeholder="Price" 
                               value="<?php echo $product['price'] ?? '0'; ?>" min="0" step="0.01" required>
                    </div>
                    
                    <div class="label-td">
                        <label for="supplier" class="form-label">Supplier: </label>
                        <input type="text" name="supplier" class="input-text" placeholder="Supplier" 
                               value="<?php echo htmlspecialchars($product['supplier'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="label-td" id="purchaseDateField">
                        <label for="purchase_date" id="lblPurchaseDate" class="form-label">Purchase Date: </label>
                        <input type="date" name="purchase_date" id="inputPurchaseDate" class="input-text" 
                               value="<?php echo $product['purchase_date'] ?? ''; ?>" required>
                    </div>
                    
                    <!-- Medicine specific expiry date field -->
                    <div class="label-td" id="medicineFields5">
                        <label for="expiry_date" class="form-label">Expiry Date: </label>
                        <input type="date" name="expiry_date" class="input-text" 
                               value="<?php echo $product['expiration_date'] ?? $product['expiry_date'] ?? ''; ?>">
                    </div>
                    
                    <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <div style="display: inline-flex; gap: 10px; align-items: center;">
                        <input type="submit" value="<?php echo $button_text; ?>" class="login-btn btn-primary btn" 
                               name="<?php echo isset($product) ? 'update_product' : 'add_product'; ?>">
                        <?php if ($_GET['action'] == 'edit-product'): ?>
                            <button type="button" onclick="deleteProduct(<?php echo $product_id; ?>, '<?php echo $product_category; ?>')" class="btn-primary-soft btn" style="padding: 12px 20px; font-size: 14px;">
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </center>
    </div>
</div>
<?php if (isset($_GET['action']) && ($_GET['action'] == 'add-product' || ($_GET['action'] == 'edit-product' && isset($_GET['id'])))) { ?>
<script>
// Prevent default behavior of close button and handle modal closing
document.querySelector('.close').addEventListener('click', function(e) {
    e.preventDefault();
    const modal = document.getElementById('popup1');
    modal.style.display = 'none';
    
    // Update URL without reloading the page
    const newUrl = new URL(window.location.href);
    newUrl.searchParams.delete('action');
    newUrl.searchParams.delete('id');
    window.history.pushState({ category: currentCategory }, '', newUrl);
});

function updateModalLabels() {
    var cat = document.getElementById('selectCategory').value;
    var lblName = document.getElementById('lblProductName');
    var nameInput = document.querySelector('input[name="product_name"]');
    var lblPurchase = document.getElementById('lblPurchaseDate');
    var purchaseDateField = document.getElementById('purchaseDateField');
    var purchaseDateInput = document.getElementById('inputPurchaseDate');
    
    // Show/hide glass frames specific fields
    var glassFields = ['glassFields', 'glassFields2', 'glassFields3', 'glassFields4'];
    glassFields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if(field) {
            field.style.display = (cat === 'Eyeglasses') ? 'block' : 'none';
        }
    });
    
    // Show/hide medicine specific fields
    var medicineFields = ['medicineFields', 'medicineFields2', 'medicineFields3', 'medicineFields4', 'medicineFields5'];
    medicineFields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if(field) {
            field.style.display = (cat === 'Eye Medication') ? 'block' : 'none';
        }
    });
    
    // Show/hide purchase date field (hide for medicines)
    if(purchaseDateField) {
        purchaseDateField.style.display = (cat === 'Eye Medication') ? 'none' : 'block';
    }
    
    // Make purchase date required only for non-medicine products
    if(purchaseDateInput) {
        purchaseDateInput.required = (cat !== 'Eye Medication');
    }
    
    if(cat === 'Surgical Equipment'){
        lblName.textContent = 'Equipment Name:';
        nameInput.placeholder = 'Equipment Name';
        lblPurchase.textContent = 'Purchase Date:';
    } else if(cat === 'Eyeglasses'){
        lblName.textContent = 'Frame Name:';
        nameInput.placeholder = 'Frame Name';
        lblPurchase.textContent = 'Purchase Date:';
    } else if(cat === 'Eye Medication'){
        lblName.textContent = 'Product Name:';
        nameInput.placeholder = 'Product Name';
        lblPurchase.textContent = 'Purchase Date:';
    } else {
        lblName.textContent = 'Product Name:';
        nameInput.placeholder = 'Product Name';
        lblPurchase.textContent = 'Purchase Date:';
    }
}
document.getElementById('selectCategory').addEventListener('change', updateModalLabels);
updateModalLabels();

// Delete product function
function deleteProduct(productId, productCategory) {
    if (confirm('Are you sure you want to delete this product?')) {
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('product_category', productCategory);
        formData.append('delete_product', '1');
        
        fetch('inventory.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message || 'Product deleted successfully!');
                // Close modal and refresh
                document.querySelectorAll('.overlay').forEach(overlay => {
                    overlay.style.display = 'none';
                });
                // Refresh the page to show updated data
                window.location.reload();
            } else {
                alert(data.message || 'An error occurred while deleting the product.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

// Handle form submissions without page refresh
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Special validation for stock-in forms
        if (this.querySelector('button[name="stock_in"]')) {
            const quantity = parseInt(document.getElementById('stockInQuantity').value);
            if (quantity < 1) {
                alert('Please enter a valid quantity (minimum 1)');
                return;
            }
        }
        
        let formData = new FormData(this);
        
        // Add the submit button value to FormData
        const submitButton = this.querySelector('input[type="submit"], button[type="submit"]');
        if (submitButton && submitButton.name) {
            formData.append(submitButton.name, submitButton.value);
        }
        
        let url = 'inventory.php';
        
        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Check response status
            if (data.status === 'success') {
                // Show success message
                alert(data.message || 'Operation completed successfully!');
                
                // Close any open modals
                document.querySelectorAll('.overlay').forEach(overlay => {
                    overlay.style.display = 'none';
                });
                
                // Remember the current category
                const currentTab = sessionStorage.getItem('selectedCategory') || currentCategory;
                
                // Refresh the products table without reloading the page
                fetch('inventory.php')
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTable = doc.querySelector('.sub-table');
                        const currentTable = document.querySelector('.sub-table');
                        if (newTable && currentTable) {
                            currentTable.innerHTML = newTable.innerHTML;
                        }
                        
                        // Restore the category and update UI
                        showCategory(currentTab);
                    });
            } else {
                // Show error message from server
                alert(data.message || 'An error occurred. Please try again.');
            }
            
            // Refresh the products table without reloading the page
            fetch('inventory.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTable = doc.querySelector('.sub-table');
                    const currentTable = document.querySelector('.sub-table');
                    if (newTable && currentTable) {
                        currentTable.innerHTML = newTable.innerHTML;
                        filterRowsByCategory(currentCategory);
                    }
                });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please check the console for details and try again.');
        });
    });
});

// Additional event listener specifically for stock-in modal forms
document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation to catch dynamically created forms
    document.addEventListener('submit', function(e) {
        if (e.target.tagName === 'FORM') {
            e.preventDefault();
            
            // Special validation for stock-in forms
            if (e.target.querySelector('button[name="stock_in"]')) {
                const quantity = parseInt(document.getElementById('stockInQuantity').value);
                if (quantity < 1) {
                    alert('Please enter a valid quantity (minimum 1)');
                    return;
                }
            }
            
            let formData = new FormData(e.target);
            
            // Add the submit button value to FormData
            const submitButton = e.target.querySelector('input[type="submit"], button[type="submit"]');
            if (submitButton && submitButton.name) {
                formData.append(submitButton.name, submitButton.value);
            }
            
            let url = 'inventory.php';
            
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Check response status
                if (data.status === 'success') {
                    // Show success message
                    alert(data.message || 'Operation completed successfully!');
                    
                    // Close any open modals
                    document.querySelectorAll('.overlay').forEach(overlay => {
                        overlay.style.display = 'none';
                    });
                    
                    // Remember the current category
                    const currentTab = sessionStorage.getItem('selectedCategory') || currentCategory;
                    
                    // Refresh the products table without reloading the page
                    fetch('inventory.php')
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newTable = doc.querySelector('.sub-table');
                            const currentTable = document.querySelector('.sub-table');
                            if (newTable && currentTable) {
                                currentTable.innerHTML = newTable.innerHTML;
                            }
                            
                            // Restore the category and update UI
                            showCategory(currentTab);
                        });
                } else {
                    // Show error message from server
                    alert(data.message || 'An error occurred. Please try again.');
                }
                
                // Refresh the products table without reloading the page
                fetch('inventory.php')
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTable = doc.querySelector('.sub-table');
                        const currentTable = document.querySelector('.sub-table');
                        if (newTable && currentTable) {
                            currentTable.innerHTML = newTable.innerHTML;
                            filterRowsByCategory(currentCategory);
                        }
                    });
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please check the console for details and try again.');
            });
        }
    });
});
</script>
<?php } ?>

<?php // Close the main add/edit modal conditional opened earlier ?>
<?php if(false){ } // no-op to maintain structure ?>
<?php } ?>

<!-- Stock In Modal -->
<div id="stockInModal" class="overlay" style="display: none;">
    <div class="popup" style="max-width: 500px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 15px 15px 0 0; position: relative;">
            <h2 id="stockInTitle" style="margin: 0; font-size: 24px; font-weight: 600; text-align: center;">Add Stock</h2>
            <a class="close" href="#" onclick="closeStockInModal()" style="position: absolute; top: 15px; right: 20px; color: white; font-size: 28px; text-decoration: none; transition: opacity 0.3s;">&times;</a>
        </div>
        <div class="modal-content" style="padding: 30px; background: #f8f9fa;">
            <form action="" method="POST">
                <input type="hidden" id="stockInProductId" name="product_id">
                <input type="hidden" id="stockInProductCategory" name="product_category">
                <input type="hidden" id="stockInProductName">
                
                <div class="label-td" style="margin-bottom: 20px;">
                    <label for="quantity_to_add" class="form-label" style="display: block; margin-bottom: 8px; font-weight: 500;">Quantity to Add:</label>
                    <input type="number" name="quantity_to_add" id="stockInQuantity" class="input-text" min="1" required>
                </div>
                
                <div style="text-align: center;">
                    <button type="button" name="stock_in" class="btn-primary btn" style="background-color: #28a745; padding: 12px 30px;">
                        Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewDetailsModal" class="overlay" style="display: none;">
    <div class="popup" style="max-width: 700px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px 15px 0 0; position: relative;">
            <h2 id="modalTitle" style="margin: 0; font-size: 24px; font-weight: 600; text-align: center;">Product Details</h2>
            <a class="close" href="#" onclick="closeViewModal()" style="position: absolute; top: 15px; right: 20px; color: white; font-size: 28px; text-decoration: none; transition: opacity 0.3s;">&times;</a>
        </div>
        <div class="modal-content" style="padding: 30px; background: #f8f9fa;">
            <div id="productDetails" style="text-align: left;">
                <!-- Product details will be populated here -->
            </div>
        </div>
    </div>
</div>

<!-- Usage Details Modal -->
<div id="usageDetailsModal" class="overlay" style="display: none;">
    <div class="popup" style="max-width: 700px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; border-radius: 15px 15px 0 0; position: relative;">
            <h2 id="usageModalTitle" style="margin: 0; font-size: 24px; font-weight: 600; text-align: center;">Usage Details</h2>
            <a class="close" href="#" onclick="closeUsageDetailsModal()" style="position: absolute; top: 15px; right: 20px; color: white; font-size: 28px; text-decoration: none; transition: opacity 0.3s;">&times;</a>
        </div>
        <div class="modal-content" style="padding: 30px; background: #f8f9fa;">
            <div id="usageDetailsContent" style="text-align: left;">
                <!-- Usage details will be populated here -->
            </div>
        </div>
    </div>
</div>

<script>
// Adjust column headers for specific categories to match table structures
function updateHeadersForCategory(category){
    const thName = document.getElementById('thProductName');
    const thExp = document.getElementById('thExpiryPurchase');
    
    if(!thName || !thExp) return;
    
    if(category === 'Surgical Equipment'){
        thName.textContent = 'Equipment Name';
        thExp.textContent = 'Purchase Date';
    } else if(category === 'Eyeglasses'){
        thName.textContent = 'Frame Name';
        thExp.textContent = 'Purchase Date';
    } else if(category === 'Eye Medication'){
        thName.textContent = 'Medicine Name';
        thExp.textContent = 'Expiry Date';
    } else {
        thName.textContent = 'Product Name';
        thExp.textContent = 'Date';
    }
}
let currentCategory = 'Surgical Equipment';

function searchProducts() {
    let input = document.getElementById('searchInput');
    let filter = input.value.toLowerCase();
    let tbody = document.querySelector('.sub-table tbody');
    let rows = tbody.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        let visible = false;
        let cells = rows[i].getElementsByTagName('td');
        
        // Skip the "No products found" row
        if (cells.length === 1 && cells[0].querySelector('center')) {
            continue;
        }

        // Check category filter first
        let categoryMatch = rows[i].getAttribute('data-category') === currentCategory;
        
        if (categoryMatch) {
            for (let j = 0; j < cells.length - 1; j++) { // Exclude the Actions column
                let cell = cells[j];
                if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
                    visible = true;
                    break;
                }
            }
        }
        
        rows[i].style.display = visible ? '' : 'none';
    }
}

function filterRowsByCategory(category) {
    let tbody = document.querySelector('.sub-table tbody');
    let rows = tbody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let cells = rows[i].getElementsByTagName('td');
        
        // Skip the "No products found" row
        if (cells.length === 1 && cells[0].querySelector('center')) {
            continue;
        }
        
        let rowCategory = rows[i].getAttribute('data-category');
        rows[i].style.display = rowCategory === category ? '' : 'none';
    }
}

function showCategory(category) {
    currentCategory = category;
    
    // Update active tab
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    const targetButton = document.querySelector(`.tab-button[onclick*="${category}"]`);
    if (targetButton) {
        targetButton.classList.add('active');
    }
    
    // Show/hide appropriate table based on category
    const productsTable = document.getElementById('productsTable');
    const usageHistoryTable = document.getElementById('usageHistoryTable');
    
    if (category === 'Activity') {
        // Show usage history table and hide products table
        if (productsTable) productsTable.style.display = 'none';
        if (usageHistoryTable) {
            usageHistoryTable.style.display = 'table';
            loadUsageHistory();
        }
        
        // Hide Add Product button for Activity tab
        const addProductLink = document.getElementById('addProductLink');
        if (addProductLink) {
            addProductLink.style.display = 'none';
        }
    } else {
        // Show products table and hide usage history table
        if (productsTable) productsTable.style.display = 'table';
        if (usageHistoryTable) usageHistoryTable.style.display = 'none';
        
        // Show Add Product button for other tabs
        const addProductLink = document.getElementById('addProductLink');
        if (addProductLink) {
            addProductLink.style.display = 'block';
        }
        
        // Filter products
        filterRowsByCategory(category);
        
        // Update column headers to match category-specific schemas
        updateHeadersForCategory(category);
        // Update Add Product link
        syncAddLink();
    }
    
    // Store the selected category in sessionStorage
    sessionStorage.setItem('selectedCategory', category);
    
    // Update URL without reloading the page
    const url = new URL(window.location.href);
    url.searchParams.set('tab', category);
    window.history.pushState({ category: category }, '', url);

    // Clear search input and reapply search if there's text
    let searchInput = document.getElementById('searchInput');
    if (searchInput.value.trim() !== '') {
        searchProducts();
    }
}

// Search when button is clicked
document.getElementById('searchBtn').addEventListener('click', searchProducts);

// Search when Enter key is pressed
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchProducts();
    }
});

// Real-time search as user types
document.getElementById('searchInput').addEventListener('keyup', searchProducts);

// Initialize the page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize with the category from URL, sessionStorage, or default to Surgical Equipment
    const urlParams = new URLSearchParams(window.location.search);
    const sessionCategory = sessionStorage.getItem('selectedCategory');
    const initialCategory = urlParams.get('tab') || sessionCategory || 'Surgical Equipment';
    currentCategory = initialCategory;

    // Store selected category in sessionStorage
    sessionStorage.setItem('selectedCategory', currentCategory);

    // Initialize the correct tab based on URL parameter
    if (initialCategory === 'Activity') {
        // Show usage history table and hide products table
        const productsTable = document.getElementById('productsTable');
        const usageHistoryTable = document.getElementById('usageHistoryTable');
        
        if (productsTable) productsTable.style.display = 'none';
        if (usageHistoryTable) {
            usageHistoryTable.style.display = 'table';
            loadUsageHistory();
        }
        
        // Hide Add Product button for Activity tab
        const addProductLink = document.getElementById('addProductLink');
        if (addProductLink) {
            addProductLink.style.display = 'none';
        }
    } else {
        // Initialize headers and filter for product categories
        updateHeadersForCategory(currentCategory);
        filterRowsByCategory(currentCategory);
    }

    // Set the correct tab button as active
    const initialTabButton = document.querySelector(`.tab-button[onclick*="${currentCategory}"]`);
    if (initialTabButton) {
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        initialTabButton.classList.add('active');
    }
});

// Keep Add Product link in sync with selected tab
const addLink = document.getElementById('addProductLink');
function syncAddLink(){
    // Don't show Add Product link for Activity tab
    if (currentCategory === 'Activity') {
        if (addLink) {
            addLink.style.display = 'none';
        }
        return;
    }
    
    // Show Add Product link for other tabs
    if (addLink) {
        addLink.style.display = 'block';
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('action', 'add-product');
        currentUrl.searchParams.set('category', currentCategory);
        currentUrl.searchParams.set('tab', currentCategory);
        addLink.setAttribute('href', currentUrl.search);
    }
}
syncAddLink();

// View Details Modal Functions
// Function to get stock status label and class
function getStockStatus(quantity) {
    if (quantity <= 0) {
        return {
            label: 'Out of Stock',
            class: 'stock-none'
        };
    } else if (quantity <= 5) {
        return {
            label: 'Low Stock',
            class: 'stock-low'
        };
    } else {
        return {
            label: 'In Stock',
            class: 'stock-high'
        };
    }
}

function showProductDetails(product) {
    const modal = document.getElementById('viewDetailsModal');
    const modalTitle = document.getElementById('modalTitle');
    const productDetails = document.getElementById('productDetails');
    
    // Set modal title based on product category
    const category = product.product_category;
    if (category === 'Surgical Equipment') {
        modalTitle.textContent = 'Surgical Equipment Details';
    } else if (category === 'Eye Medication') {
        modalTitle.textContent = 'Medicine Details';
    } else if (category === 'Eyeglasses') {
        modalTitle.textContent = 'Glass Frame Details';
    } else {
        modalTitle.textContent = 'Product Details';
    }
    
    // Generate details HTML based on product category
    let detailsHTML = '';
    
    if (category === 'Surgical Equipment') {
        detailsHTML = `
            <div class="product-details-container">
                <div class="detail-section">
                    <div class="detail-item">
                        <div class="detail-icon">🔧</div>
                        <div class="detail-content">
                            <span class="detail-label">Equipment Name</span>
                            <span class="detail-value">${product.equipment_name || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📂</div>
                        <div class="detail-content">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">${product.category || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📦</div>
                        <div class="detail-content">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value quantity-${product.quantity <= 5 ? 'low' : product.quantity <= 20 ? 'medium' : 'high'}" style="display: inline-block; padding: 4px 8px; border-radius: 4px; background-color: ${product.quantity <= 5 ? '#f8d7da' : product.quantity <= 20 ? '#fff3cd' : '#d4edda'}; color: ${product.quantity <= 5 ? '#721c24' : product.quantity <= 20 ? '#856404' : '#155724'}; font-weight: 600;">${product.quantity || '0'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">💰</div>
                        <div class="detail-content">
                            <span class="detail-label">Price</span>
                            <span class="detail-value price">₱${parseFloat(product.price || 0).toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🏢</div>
                        <div class="detail-content">
                            <span class="detail-label">Supplier</span>
                            <span class="detail-value">${product.supplier || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📅</div>
                        <div class="detail-content">
                            <span class="detail-label">Purchase Date</span>
                            <span class="detail-value">${product.purchase_date ? new Date(product.purchase_date).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">⏰</div>
                        <div class="detail-content">
                            <span class="detail-label">Date Added</span>
                            <span class="detail-value">${product.created_at ? new Date(product.created_at).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (category === 'Eye Medication') {
        detailsHTML = `
            <div class="product-details-container">
                <div class="detail-section">
                    <div class="detail-item">
                        <div class="detail-icon">💊</div>
                        <div class="detail-content">
                            <span class="detail-label">Medicine Name</span>
                            <span class="detail-value">${product.medicine_name || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🧬</div>
                        <div class="detail-content">
                            <span class="detail-label">Generic Name</span>
                            <span class="detail-value">${product.generic_name || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📂</div>
                        <div class="detail-content">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">${product.category || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🏷️</div>
                        <div class="detail-content">
                            <span class="detail-label">Brand</span>
                            <span class="detail-value">${product.brand || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">⚖️</div>
                        <div class="detail-content">
                            <span class="detail-label">Dosage</span>
                            <span class="detail-value">${product.dosage || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item full-width">
                        <div class="detail-icon">📝</div>
                        <div class="detail-content">
                            <span class="detail-label">Description</span>
                            <span class="detail-value">${product.description || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📦</div>
                        <div class="detail-content">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value quantity-${product.quantity <= 5 ? 'low' : product.quantity <= 20 ? 'medium' : 'high'}" style="display: inline-block; padding: 4px 8px; border-radius: 4px; background-color: ${product.quantity <= 5 ? '#f8d7da' : product.quantity <= 20 ? '#fff3cd' : '#d4edda'}; color: ${product.quantity <= 5 ? '#721c24' : product.quantity <= 20 ? '#856404' : '#155724'}; font-weight: 600;">${product.quantity || '0'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">💰</div>
                        <div class="detail-content">
                            <span class="detail-label">Price</span>
                            <span class="detail-value price">₱${parseFloat(product.price || 0).toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🏢</div>
                        <div class="detail-content">
                            <span class="detail-label">Supplier</span>
                            <span class="detail-value">${product.supplier || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">⚠️</div>
                        <div class="detail-content">
                            <span class="detail-label">Expiration Date</span>
                            <span class="detail-value ${product.expiration_date && new Date(product.expiration_date) < new Date() ? 'expired' : ''}">${product.expiration_date ? new Date(product.expiration_date).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">⏰</div>
                        <div class="detail-content">
                            <span class="detail-label">Date Added</span>
                            <span class="detail-value">${product.date_added ? new Date(product.date_added).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (category === 'Eyeglasses') {
        detailsHTML = `
            <div class="product-details-container">
                <div class="detail-section">
                    <div class="detail-item">
                        <div class="detail-icon">👓</div>
                        <div class="detail-content">
                            <span class="detail-label">Frame Name</span>
                            <span class="detail-value">${product.frame_name || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📂</div>
                        <div class="detail-content">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">${product.category || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🏷️</div>
                        <div class="detail-content">
                            <span class="detail-label">Brand</span>
                            <span class="detail-value">${product.brand || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🔢</div>
                        <div class="detail-content">
                            <span class="detail-label">Model Number</span>
                            <span class="detail-value">${product.model_number || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🔧</div>
                        <div class="detail-content">
                            <span class="detail-label">Material</span>
                            <span class="detail-value">${product.material || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📏</div>
                        <div class="detail-content">
                            <span class="detail-label">Size</span>
                            <span class="detail-value">${product.size || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📦</div>
                        <div class="detail-content">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value quantity-${product.quantity <= 5 ? 'low' : product.quantity <= 20 ? 'medium' : 'high'}" style="display: inline-block; padding: 4px 8px; border-radius: 4px; background-color: ${product.quantity <= 5 ? '#f8d7da' : product.quantity <= 20 ? '#fff3cd' : '#d4edda'}; color: ${product.quantity <= 5 ? '#721c24' : product.quantity <= 20 ? '#856404' : '#155724'}; font-weight: 600;">${product.quantity || '0'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">💰</div>
                        <div class="detail-content">
                            <span class="detail-label">Price</span>
                            <span class="detail-value price">₱${parseFloat(product.price || 0).toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">🏢</div>
                        <div class="detail-content">
                            <span class="detail-label">Supplier</span>
                            <span class="detail-value">${product.supplier || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">📅</div>
                        <div class="detail-content">
                            <span class="detail-label">Purchase Date</span>
                            <span class="detail-value">${product.purchase_date ? new Date(product.purchase_date).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">⏰</div>
                        <div class="detail-content">
                            <span class="detail-label">Date Added</span>
                            <span class="detail-value">${product.created_at ? new Date(product.created_at).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    productDetails.innerHTML = detailsHTML;
    modal.style.display = 'block';
}

function closeViewModal() {
    const modal = document.getElementById('viewDetailsModal');
    modal.style.display = 'none';
}

// Stock In Modal Functions
function showStockInModal(productId, productName, category) {
    const modal = document.getElementById('stockInModal');
    document.getElementById('stockInProductId').value = productId;
    document.getElementById('stockInProductCategory').value = category;
    document.getElementById('stockInProductName').value = productName;
    document.getElementById('stockInQuantity').value = '1';
    modal.style.display = 'block';
    
    // Add direct event listener to the stock-in button
    const stockInButton = modal.querySelector('button[name="stock_in"]');
    if (stockInButton) {
        // Remove any existing event listeners
        stockInButton.replaceWith(stockInButton.cloneNode(true));
        const newButton = modal.querySelector('button[name="stock_in"]');
        
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const quantity = parseInt(document.getElementById('stockInQuantity').value);
            if (quantity < 1) {
                alert('Please enter a valid quantity (minimum 1)');
                return;
            }
            
            const formData = new FormData();
            formData.append('product_id', document.getElementById('stockInProductId').value);
            formData.append('product_category', document.getElementById('stockInProductCategory').value);
            formData.append('quantity_to_add', quantity);
            formData.append('stock_in', '1');
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message || 'Stock updated successfully!');
                    modal.style.display = 'none';
                    // Refresh the page to show updated data
                    window.location.reload();
                } else {
                    alert(data.message || 'An error occurred while updating stock.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }
}

function closeStockInModal() {
    const modal = document.getElementById('stockInModal');
    modal.style.display = 'none';
}

function validateStockIn() {
    const quantity = parseInt(document.getElementById('stockInQuantity').value);
    if (quantity < 1) {
        alert('Please enter a valid quantity (minimum 1)');
        return false;
    }
    return true;
}

// Load Usage History Data
function loadUsageHistory() {
    const usageHistoryBody = document.getElementById('usageHistoryBody');
    if (!usageHistoryBody) return;
    
    // Show loading state
    usageHistoryBody.innerHTML = `
        <tr>
            <td colspan="7">
                <center>
                    <div class="loading-spinner" style="padding: 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff;"></i>
                        <p style="margin-top: 10px;">Loading usage history...</p>
                    </div>
                </center>
            </td>
        </tr>
    `;
    
    fetch('get-inventory-history.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                usageHistoryBody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <center>
                                <img src="../Images/notfound.svg" width="25%">
                                <br>
                                <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                    ${data.error}
                                </p>
                            </center>
                        </td>
                    </tr>
                `;
                return;
            }
            
            if (data.length === 0) {
                usageHistoryBody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <center>
                                <img src="../Images/notfound.svg" width="25%">
                                <br>
                                <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                    No usage history found!
                                </p>
                            </center>
                        </td>
                    </tr>
                `;
                return;
            }
            
            // Generate table rows
            let tableHTML = '';
            data.forEach(item => {
                const serviceTypeClass = getServiceTypeClass(item.service_type);
                const quantityClass = getQuantityClass(item.quantity_used);
                
                tableHTML += `
                    <tr>
                        <td>${item.patient_name || 'N/A'}</td>
                        <td>
                            <span class="service-type-badge ${serviceTypeClass}">
                                ${formatServiceType(item.service_type)}
                            </span>
                        </td>
                        <td>
                            <span class="quantity-badge ${quantityClass}">
                                ${item.quantity_used || '0'}
                            </span>
                        </td>
                        <td>${item.formatted_date || 'N/A'}</td>
                        <td>${item.doctor_name || 'N/A'}</td>
                        <td>
                            <button class="btn-primary-soft btn button-icon btn-view" 
                                    style="width: 90px; padding: 8px 12px; font-size: 14px; background-color: #28a745; color: white;" 
                                    onclick="showUsageDetails(${item.usage_id})">
                                View Details
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            usageHistoryBody.innerHTML = tableHTML;
        })
        .catch(error => {
            console.error('Error loading usage history:', error);
            usageHistoryBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <center>
                            <img src="../Images/notfound.svg" width="25%">
                            <br>
                            <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                Error loading usage history!
                            </p>
                        </center>
                    </td>
                </tr>
            `;
        });
}

// Get service type CSS class
function getServiceTypeClass(serviceType) {
    if (!serviceType) return 'service-default';
    
    const type = serviceType.toLowerCase();
    if (type.includes('cataract')) return 'service-cataract';
    if (type.includes('screening')) return 'service-screening';
    if (type.includes('examination')) return 'service-examination';
    if (type.includes('surgery')) return 'service-surgery';
    return 'service-default';
}

// Format service type to display name
function formatServiceType(serviceType) {
    if (!serviceType) return 'N/A';
    
    const type = serviceType.toLowerCase().trim();
    const serviceTypeMap = {
        'emergency': 'Eye Emergency',
        'cataract_screening': 'Cataract Screening',
        'cataract_surgery': 'Cataract Surgery',
        'examination': 'Eye Examination',
        'cornea': 'Cornea Treatment',
        'lasik_screening': 'LASIK Screening',
        'pediatric': 'Pediatric Eye Care',
        'colorvision': 'Color Vision Test',
        'followup': 'Follow-up Appointment'
    };
    
    // Check if exact match exists
    if (serviceTypeMap[type]) {
        return serviceTypeMap[type];
    }
    
    // Fallback: capitalize and replace underscores
    return serviceType.replace(/_/g, ' ')
                      .split(' ')
                      .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                      .join(' ');
}

// Get quantity CSS class
function getQuantityClass(quantity) {
    const qty = parseInt(quantity) || 0;
    if (qty === 0) return 'quantity-zero';
    if (qty <= 2) return 'quantity-low';
    if (qty <= 5) return 'quantity-medium';
    return 'quantity-high';
}

// Show Usage Details Modal
function showUsageDetails(usageId) {
    const modal = document.getElementById('usageDetailsModal');
    const content = document.getElementById('usageDetailsContent');
    
    // Show loading state
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="loading-spinner" style="padding: 20px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff;"></i>
                <p style="margin-top: 10px;">Loading usage details...</p>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
    
    // Fetch usage details from the server
    fetch(`get-usage-details.php?id=${usageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <div style="color: #dc3545; font-size: 18px; margin-bottom: 10px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <p style="color: #dc3545;">Error loading usage details: ${data.error}</p>
                    </div>
                `;
                return;
            }
            
            // Display usage details
            content.innerHTML = `
                <div class="usage-details-container">
                    <div class="detail-section">
                        <div class="detail-item">
                            <div class="detail-icon">👤</div>
                            <div class="detail-content">
                                <span class="detail-label">Patient Name</span>
                                <span class="detail-value">${data.patient_name || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon">🏥</div>
                            <div class="detail-content">
                                <span class="detail-label">Service Type</span>
                                <span class="detail-value service-badge ${getServiceTypeClass(data.service_type)}">${formatServiceType(data.service_type)}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon">📦</div>
                            <div class="detail-content">
                                <span class="detail-label">Product Name</span>
                                <span class="detail-value">${data.product_name || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon">🔢</div>
                            <div class="detail-content">
                                <span class="detail-label">Quantity Deducted</span>
                                <span class="detail-value quantity-badge ${getQuantityClass(data.quantity_used)}">${data.quantity_used || '0'}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon">👨‍⚕️</div>
                            <div class="detail-content">
                                <span class="detail-label">Doctor</span>
                                <span class="detail-value">${data.doctor_name || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon">📅</div>
                            <div class="detail-content">
                                <span class="detail-label">Usage Date</span>
                                <span class="detail-value">${data.formatted_date || 'N/A'}</span>
                            </div>
                        </div>
                        
                    </div>
                </div>
            `;
        })
        .catch(error => {
            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="color: #dc3545; font-size: 18px; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p style="color: #dc3545;">Error loading usage details: ${error.message}</p>
                </div>
            `;
        });
}

// Close Usage Details Modal
function closeUsageDetailsModal() {
    const modal = document.getElementById('usageDetailsModal');
    modal.style.display = 'none';
}

// Close modals when clicking outside of them
window.onclick = function(event) {
    const viewModal = document.getElementById('viewDetailsModal');
    const stockInModal = document.getElementById('stockInModal');
    const usageModal = document.getElementById('usageDetailsModal');
    
    if (event.target === viewModal) {
        viewModal.style.display = 'none';
    }
    if (event.target === stockInModal) {
        stockInModal.style.display = 'none';
    }
    if (event.target === usageModal) {
        usageModal.style.display = 'none';
    }
}
</script>
</body>
</html>