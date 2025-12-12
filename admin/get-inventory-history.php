<?php
// Completely disable all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Function to safely output JSON
function outputJson($data) {
    ob_clean(); // Clear any previous output
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    session_start();

    // Check authentication
    if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
        outputJson(['error' => 'Unauthorized access']);
    }

    // Include connection file
    if (!file_exists("../connection.php")) {
        outputJson(['error' => 'Database connection file not found']);
    }
    
    include("../connection.php");

    // Check if database connection is working
    if (!isset($database) || !$database) {
        outputJson(['error' => 'Database connection failed']);
    }

    // Check if table exists
    $table_check = $database->query("SHOW TABLES LIKE 'tbl_inventory_usage'");
    if (!$table_check) {
        outputJson(['error' => 'Failed to check table existence: ' . $database->error]);
    }
    
    if ($table_check->num_rows == 0) {
        // Return empty array if table doesn't exist instead of error
        outputJson([]);
    }

    // Get inventory usage history with proper doctor name
    $sql = "SELECT 
                iu.usage_id,
                iu.appointment_id,
                iu.patient_id,
                iu.patient_name,
                iu.service_type,
                iu.product_category,
                iu.product_id,
                iu.product_name,
                iu.quantity_used,
                iu.remaining_quantity,
                iu.usage_date,
                iu.doctor_id,
                COALESCE(CONCAT(d.first_name,
                               CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                               ' ', d.last_name,
                               CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                        ), iu.doctor_name, 'Unknown Doctor') as doctor_name
            FROM tbl_inventory_usage iu
            LEFT JOIN tbl_doctor d ON iu.doctor_id = d.docid
            ORDER BY iu.usage_date DESC 
            LIMIT 100";
    
    $result = $database->query($sql);

    if (!$result) {
        outputJson(['error' => 'Database query failed: ' . $database->error]);
    }

    $history = [];
    while($row = $result->fetch_assoc()) {
        // Format the data for better display
        $row['formatted_date'] = date('M d, Y', strtotime($row['usage_date']));
        $row['formatted_time'] = date('h:i A', strtotime($row['usage_date']));
        $history[] = $row;
    }

    outputJson($history);
    
} catch (Exception $e) {
    outputJson(['error' => 'PHP Error: ' . $e->getMessage()]);
} catch (Error $e) {
    outputJson(['error' => 'PHP Fatal Error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    outputJson(['error' => 'Unknown Error: ' . $e->getMessage()]);
}

// Fallback - should never reach here
outputJson(['error' => 'Unexpected error occurred']);
?>
