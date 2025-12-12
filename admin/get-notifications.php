<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include("../connection.php");

// Handle mark as read action
if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $admin_user = $_SESSION["user"];
    
    // Check if tbl_notification_read table exists, create it if it doesn't
    $table_check = $database->query("SHOW TABLES LIKE 'tbl_notification_read'");
    if ($table_check->num_rows == 0) {
        // Create the notification tracking table
        $create_table_sql = "CREATE TABLE IF NOT EXISTS `tbl_notification_read` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `request_id` int(11) NOT NULL,
            `admin_user` varchar(255) NOT NULL,
            `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_notification` (`request_id`, `admin_user`),
            KEY `idx_request_id` (`request_id`),
            KEY `idx_admin_user` (`admin_user`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$database->query($create_table_sql)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create notification tracking table: ' . $database->error]);
            exit();
        }
    }
    
    if (isset($_POST['request_id'])) {
        // Mark specific notification as read
        $request_id = intval($_POST['request_id']);
        $sql = "INSERT IGNORE INTO tbl_notification_read (request_id, admin_user) VALUES (?, ?)";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("is", $request_id, $admin_user);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
        }
    } else {
        // Mark all notifications as read
        $sql = "INSERT IGNORE INTO tbl_notification_read (request_id, admin_user) 
                SELECT r.request_id, ? 
                FROM tbl_appointment_requests r 
                WHERE r.appointment_progress = 'cancelled' 
                AND r.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $admin_user);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark all notifications as read']);
        }
    }
    exit();
}

try {
    $admin_user = $_SESSION["user"];
    
    // Check if tbl_notification_read table exists, create it if it doesn't
    $table_check = $database->query("SHOW TABLES LIKE 'tbl_notification_read'");
    if ($table_check->num_rows == 0) {
        // Create the notification tracking table
        $create_table_sql = "CREATE TABLE IF NOT EXISTS `tbl_notification_read` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `request_id` int(11) NOT NULL,
            `admin_user` varchar(255) NOT NULL,
            `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_notification` (`request_id`, `admin_user`),
            KEY `idx_request_id` (`request_id`),
            KEY `idx_admin_user` (`admin_user`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$database->query($create_table_sql)) {
            throw new Exception("Failed to create notification tracking table: " . $database->error);
        }
    }
    
    // Get cancelled appointments from the last 30 days that haven't been marked as read by this admin
    $sql = "SELECT 
                r.request_id,
                r.booking_date,
                r.appointment_time,
                r.appointment_type,
                r.appointment_progress,
                CONCAT(p.Fname, 
                       CASE WHEN p.Mname IS NOT NULL AND p.Mname != '' THEN CONCAT(' ', p.Mname) ELSE '' END,
                       ' ', p.Lname,
                       CASE WHEN p.Suffix IS NOT NULL AND p.Suffix != '' THEN CONCAT(' ', p.Suffix) ELSE '' END
                ) AS patient_name,
                CONCAT(d.first_name,
                       CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                       ' ', d.last_name,
                       CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                ) AS doctor_name,
                CASE WHEN nr.request_id IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM tbl_appointment_requests r
            LEFT JOIN tbl_patients p ON r.patient_id = p.Patient_id
            LEFT JOIN tbl_doctor d ON r.docid = d.docid
            LEFT JOIN tbl_notification_read nr ON r.request_id = nr.request_id AND nr.admin_user = ?
            WHERE r.appointment_progress = 'cancelled' 
            AND r.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY r.request_id DESC
            LIMIT 10";
    
    $stmt = $database->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $database->error);
    }
    
    $stmt->bind_param("s", $admin_user);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $notifications = [];
    $count = 0;
    $unread_count = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $count++;
            if (!$row['is_read']) {
                $unread_count++;
            }
            
            // Format appointment type
            $appointment_type = ucwords(str_replace('_', ' ', $row['appointment_type']));
            
            // Format booking date
            $booking_date = date('M d, Y', strtotime($row['booking_date']));
            
            // Since we don't have updated_at, we'll use booking_date as reference
            $cancelled_time = 'Cancelled recently';
            
            $notifications[] = [
                'request_id' => $row['request_id'],
                'patient_name' => htmlspecialchars($row['patient_name']),
                'doctor_name' => htmlspecialchars($row['doctor_name']),
                'appointment_type' => $appointment_type,
                'booking_date' => $booking_date,
                'cancelled_time' => $cancelled_time,
                'is_read' => (bool)$row['is_read']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $unread_count, // Only show unread count in badge
        'total_count' => $count,  // Total notifications
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
?>
