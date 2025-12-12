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
    $table_check = $database->query("SHOW TABLES LIKE 'tbl_inventory_logs'");
    if (!$table_check) {
        outputJson(['error' => 'Failed to check table existence: ' . $database->error]);
    }
    
    if ($table_check->num_rows == 0) {
        outputJson(['error' => 'Inventory logs table does not exist. Please run setup-inventory-tables.php first.']);
    }

    // Get inventory activity logs - adapted to match your table structure
    $sql = "SELECT 
                log_id,
                product_id,
                action,
                details,
                created_at
            FROM tbl_inventory_logs 
            ORDER BY created_at DESC 
            LIMIT 100";
    
    $result = $database->query($sql);

    if (!$result) {
        outputJson(['error' => 'Database query failed: ' . $database->error]);
    }

    $logs = [];
    while($row = $result->fetch_assoc()) {
        // Format the data for better display and map to expected structure
        $formatted_row = [
            'log_id' => $row['log_id'],
            'action_type' => $row['action'],
            'product_category' => 'Unknown', // Not available in your table
            'product_id' => $row['product_id'],
            'product_name' => 'Product ID: ' . $row['product_id'], // Not available in your table
            'quantity_change' => null, // Not available in your table
            'new_quantity' => null, // Not available in your table
            'user_id' => null, // Not available in your table
            'user_name' => 'System', // Not available in your table
            'appointment_id' => null, // Not available in your table
            'notes' => $row['details'],
            'log_date' => $row['created_at'],
            'formatted_date' => date('M d, Y', strtotime($row['created_at'])),
            'formatted_time' => date('h:i A', strtotime($row['created_at']))
        ];
        $logs[] = $formatted_row;
    }

    outputJson($logs);
    
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
