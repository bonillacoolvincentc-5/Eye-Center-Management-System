<?php
session_start();

// Check authentication
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Handle service operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $service_name = trim($_POST['service_name']);
        $service_code = trim($_POST['service_code']);
        $description = trim($_POST['description']);
        $duration_minutes = (int)$_POST['duration_minutes'];
        $price = (float)$_POST['price'];
        $is_active = (int)$_POST['is_active'];
        
        // Check if service code already exists
        $check_sql = "SELECT service_id FROM tbl_services WHERE service_code = ?";
        $check_stmt = $database->prepare($check_sql);
        $check_stmt->bind_param("s", $service_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            header("location: doctors.php?error=service_code_exists");
            exit();
        }
        
        // Insert new service
        $sql = "INSERT INTO tbl_services (service_name, service_code, description, duration_minutes, price, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("sssidi", $service_name, $service_code, $description, $duration_minutes, $price, $is_active);
        
        if ($stmt->execute()) {
            header("location: doctors.php?error=service_added");
        } else {
            header("location: doctors.php?error=service_add_failed");
        }
    } elseif ($action == 'edit') {
        $service_id = (int)$_POST['service_id'];
        $service_name = trim($_POST['service_name']);
        $service_code = trim($_POST['service_code']);
        $description = trim($_POST['description']);
        $duration_minutes = (int)$_POST['duration_minutes'];
        $price = (float)$_POST['price'];
        $is_active = (int)$_POST['is_active'];
        
        // Check if service code already exists (excluding current service)
        $check_sql = "SELECT service_id FROM tbl_services WHERE service_code = ? AND service_id != ?";
        $check_stmt = $database->prepare($check_sql);
        $check_stmt->bind_param("si", $service_code, $service_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            header("location: doctors.php?error=service_code_exists");
            exit();
        }
        
        // Update service
        $sql = "UPDATE tbl_services SET service_name=?, service_code=?, description=?, duration_minutes=?, price=?, is_active=? 
                WHERE service_id=?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("sssidii", $service_name, $service_code, $description, $duration_minutes, $price, $is_active, $service_id);
        
        if ($stmt->execute()) {
            header("location: doctors.php?error=service_updated");
        } else {
            header("location: doctors.php?error=service_update_failed");
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete') {
    $service_id = (int)$_GET['id'];
    
    // Check if service is being used in appointments
    $check_sql = "SELECT COUNT(*) as count FROM tbl_appointment_requests WHERE appointment_type = 
                  (SELECT service_code FROM tbl_services WHERE service_id = ?)";
    $check_stmt = $database->prepare($check_sql);
    $check_stmt->bind_param("i", $service_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    
    if ($check_result['count'] > 0) {
        header("location: doctors.php?error=service_in_use");
    } else {
        // Delete service
        $sql = "DELETE FROM tbl_services WHERE service_id = ?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $service_id);
        
        if ($stmt->execute()) {
            header("location: doctors.php?error=service_deleted");
        } else {
            header("location: doctors.php?error=service_delete_failed");
        }
    }
} else {
    header("location: doctors.php");
}
?>
