<?php
session_start();
include("../connection.php");

// Check authentication
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    die(json_encode(['error' => 'Unauthorized']));
}

$date = $_GET['date'] ?? date('Y-m-d');

// Get appointments for specific date
$sql = "SELECT 
    r.request_id,
    r.booking_date,
    r.appointment_time,
    r.appointment_type,
    r.status,
    r.appointment_progress,
    CONCAT(p.Fname, 
           CASE WHEN p.Mname IS NOT NULL AND p.Mname != '' THEN CONCAT(' ', p.Mname) ELSE '' END,
           ' ', p.Lname,
           CASE WHEN p.Suffix IS NOT NULL AND p.Suffix != '' THEN CONCAT(' ', p.Suffix) ELSE '' END
    ) as patient_name,
    p.PhoneNo as mobile
    FROM tbl_appointment_requests r
    LEFT JOIN tbl_patients p ON r.patient_id = p.Patient_id
    WHERE DATE(r.booking_date) = ?
    ORDER BY r.appointment_time ASC";

$stmt = $database->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    // Format appointment type
    $row['appointment_type'] = ucwords(str_replace('_', ' ', $row['appointment_type']));
    
    // Format time
    if ($row['appointment_time']) {
        $row['appointment_time'] = date('h:i A', strtotime($row['appointment_time']));
    }
    
    $appointments[] = $row;
}

header('Content-Type: application/json');
echo json_encode($appointments);
?>
