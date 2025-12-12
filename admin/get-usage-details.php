<?php
/**
 * Get detailed usage information for a specific usage record
 * Returns JSON data for the usage details modal
 */

session_start();

// Check authentication
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

include("../connection.php");

// Get usage ID from request
$usage_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($usage_id <= 0) {
    echo json_encode(['error' => 'Invalid usage ID']);
    exit();
}

try {
    // Get detailed usage information
    $sql = "SELECT 
                iu.usage_id,
                iu.appointment_id,
                iu.patient_id,
                iu.patient_name,
                iu.service_type,
                iu.product_category,
                iu.product_id,
                COALESCE(
                    NULLIF(NULLIF(CAST(iu.product_name AS CHAR), ''), '0'),
                    se.equipment_name,
                    gf.frame_name,
                    med.medicine_name,
                    'Unknown Product'
                ) AS product_name,
                iu.quantity_used,
                iu.remaining_quantity,
                iu.usage_date,
                iu.doctor_id,
                COALESCE(CONCAT(d.first_name,
                               CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                               ' ', d.last_name,
                               CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                        ), iu.doctor_name, 'Unknown Doctor') as doctor_name,
                ar.booking_date,
                ar.appointment_time,
                ar.status as appointment_status
            FROM tbl_inventory_usage iu
            LEFT JOIN tbl_doctor d ON iu.doctor_id = d.docid
            LEFT JOIN tbl_appointment_requests ar ON iu.appointment_id = ar.request_id
            LEFT JOIN tbl_surgical_equipment se 
                   ON iu.product_category = 'Surgical Equipment' 
                  AND se.equipment_id = iu.product_id
            LEFT JOIN tbl_glass_frames gf 
                   ON iu.product_category = 'Eyeglasses' 
                  AND gf.frame_id = iu.product_id
            LEFT JOIN tbl_medicines med 
                   ON iu.product_category = 'Eye Medication' 
                  AND med.medicine_id = iu.product_id
            WHERE iu.usage_id = ?";
    
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $usage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Usage record not found']);
        exit();
    }
    
    $usage = $result->fetch_assoc();
    
    // Format the data
    $usage['formatted_date'] = date('M d, Y', strtotime($usage['usage_date']));
    $usage['formatted_time'] = date('h:i A', strtotime($usage['usage_date']));
    
    // Set JSON header
    header('Content-Type: application/json');
    echo json_encode($usage);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>