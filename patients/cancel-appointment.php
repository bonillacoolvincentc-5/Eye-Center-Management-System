<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $appointmentId = intval($_POST['id']);
    $useremail = $_SESSION["user"];
    
    // Verify that the appointment belongs to the logged-in user
    $verifyStmt = $database->prepare("
        SELECT ar.request_id 
        FROM tbl_appointment_requests ar 
        INNER JOIN tbl_patients p ON ar.patient_id = p.Patient_id 
        WHERE p.Email = ? AND ar.request_id = ?
    ");
    $verifyStmt->bind_param("si", $useremail, $appointmentId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        exit();
    }
    
    // Update the appointment progress to cancelled
    $updateStmt = $database->prepare("
        UPDATE tbl_appointment_requests 
        SET appointment_progress = 'cancelled' 
        WHERE request_id = ?
    ");
    $updateStmt->bind_param("i", $appointmentId);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
    }
    
    $updateStmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>