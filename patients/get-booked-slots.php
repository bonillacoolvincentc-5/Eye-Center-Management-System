<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date'])) {
    $selectedDate = $_POST['date'];
    
    // Get approved appointments for the selected date from tbl_appointment_requests
    // Only include appointments that are not marked as 'done' or 'cancelled'
    $sql = "SELECT 
                TIME(ar.appointment_time) as scheduletime,
                ar.appointment_type,
                ar.duration,
                ar.status,
                ar.appointment_progress
            FROM tbl_appointment_requests ar
            WHERE DATE(ar.booking_date) = ? 
            AND ar.status = 'approved' 
            AND ar.appointment_progress NOT IN ('done', 'cancelled')";
    
    $stmt = $database->prepare($sql);
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookedSlots = array();
    while ($row = $result->fetch_assoc()) {
        $bookedSlots[] = array(
            'time' => $row['scheduletime'],
            'type' => $row['appointment_type'],
            'duration' => $row['duration'],
            'status' => $row['status'],
            'appointment_progress' => $row['appointment_progress']
        );
    }
    
    echo json_encode(array('bookedSlots' => $bookedSlots));
} else {
    echo json_encode(array('bookedSlots' => array()));
}
?>