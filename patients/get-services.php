<?php
/**
 * Get services for appointment booking
 * Returns JSON list of active services
 */

session_start();

// Check if user is logged in as patient
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include("../connection.php");

try {
    // Get all active services
    $sql = "SELECT service_id, service_name, service_code, description, duration_minutes, price 
            FROM tbl_services 
            WHERE is_active = 1 
            ORDER BY service_name";
    
    $result = $database->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $database->error);
    }
    
    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'service_id' => (int)$row['service_id'],
            'service_name' => $row['service_name'],
            'service_code' => $row['service_code'],
            'description' => $row['description'],
            'duration_minutes' => (int)$row['duration_minutes'],
            'price' => (float)$row['price']
        ];
    }
    
    // Set JSON header
    header('Content-Type: application/json');
    echo json_encode($services);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
