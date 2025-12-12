<?php
// Completely disable all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Function to safely output JSON
function outputJson($data) {
    ob_clean(); // Clear any previous output
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    session_start();

    // Check authentication
    if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
        outputJson(['success' => false, 'error' => 'Unauthorized access']);
    }

    // Include connection file
    if (!file_exists("../connection.php")) {
        outputJson(['success' => false, 'error' => 'Database connection file not found']);
    }
    
    include("../connection.php");

    // Check if database connection exists
    if (!isset($database)) {
        outputJson(['success' => false, 'error' => 'Database connection not available']);
    }

    // Get user information
    $useremail = $_SESSION["user"];
    $userrow = $database->query("select * from tbl_doctor where docemail='$useremail'");
    $userfetch = $userrow->fetch_assoc();
    $userid = $userfetch["docid"];

    // Check if required POST data exists
    if (!isset($_POST['request_id']) || !isset($_POST['progress'])) {
        outputJson(['success' => false, 'error' => 'Missing required parameters']);
    }

    $request_id = (int)$_POST['request_id'];
    $progress = $_POST['progress'];
    $appointment_type = $_POST['appointment_type'] ?? 'request';

    // Validate progress value
    $allowed_progress = ['preappointment', 'ongoing', 'done', 'cancelled'];
    if (!in_array($progress, $allowed_progress)) {
        outputJson(['success' => false, 'error' => 'Invalid progress value']);
    }

    if ($appointment_type === 'legacy') {
        // Handle legacy appointments
        $stmt = $database->prepare(
            "SELECT a.*, s.docid
             FROM tbl_appointment a
             INNER JOIN tbl_schedule s ON s.scheduleid = a.scheduleid
             WHERE a.appoid = ? LIMIT 1"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $ap = $stmt->get_result()->fetch_assoc();
        
        if ($ap && (int)$ap['docid'] === (int)$userid) {
            // For legacy appointments, we can't update progress in the same way
            // Instead, we'll create a note or log the progress change
            error_log("Legacy appointment progress updated - ID: $request_id, Progress: $progress");
            outputJson(['success' => true, 'message' => 'Legacy appointment progress noted']);
        } else {
            error_log("Legacy appointment not found or unauthorized - ID: $request_id, User: $userid");
            outputJson(['success' => false, 'error' => 'Unauthorized or legacy appointment not found']);
        }
    } else {
        // Handle request-based appointments
        $stmt = $database->prepare(
            "SELECT ar.*, s.docid
             FROM tbl_appointment_requests ar
             INNER JOIN tbl_schedule s 
               ON s.scheduledate = ar.booking_date 
              AND ar.appointment_time BETWEEN s.start_time AND s.end_time
             WHERE ar.request_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $ap = $stmt->get_result()->fetch_assoc();
        
        if ($ap && (int)$ap['docid'] === (int)$userid) {
            $upd = $database->prepare("UPDATE tbl_appointment_requests SET appointment_progress=? WHERE request_id = ?");
            $upd->bind_param("si", $progress, $request_id);
            
            if ($upd->execute()) {
                error_log("Request appointment progress updated - ID: $request_id, Progress: $progress");
                outputJson(['success' => true]);
            } else {
                error_log("Database update failed - ID: $request_id, Error: " . $database->error);
                outputJson(['success' => false, 'error' => 'Database update failed: ' . $database->error]);
            }
        } else {
            error_log("Request appointment not found or unauthorized - ID: $request_id, User: $userid");
            outputJson(['success' => false, 'error' => 'Unauthorized or appointment not found']);
        }
    }

} catch (Exception $e) {
    error_log("Progress update error: " . $e->getMessage());
    outputJson(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
