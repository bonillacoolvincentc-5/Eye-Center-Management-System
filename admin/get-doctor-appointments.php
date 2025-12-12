<?php
session_start();
include("../connection.php");

// Check authentication
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    die(json_encode(['error' => 'Unauthorized']));
}

$doctor_id = $_GET['doctor_id'] ?? 0;

if (!$doctor_id) {
    die(json_encode(['error' => 'Doctor ID required']));
}

// Get appointments for specific doctor
$sql = "SELECT 
    ar.request_id,
    ar.booking_date,
    ar.appointment_time,
    ar.appointment_type,
    ar.status,
    ar.appointment_progress,
    CONCAT(p.Fname, 
           CASE WHEN p.Mname IS NOT NULL AND p.Mname != '' THEN CONCAT(' ', p.Mname) ELSE '' END,
           ' ', p.Lname,
           CASE WHEN p.Suffix IS NOT NULL AND p.Suffix != '' THEN CONCAT(' ', p.Suffix) ELSE '' END
    ) as patient_name,
    CASE
        WHEN ar.appointment_progress = 'unavailable' THEN 'Unavailable'
        WHEN ar.appointment_progress = 'ongoing' THEN 'Ongoing'
        WHEN ar.appointment_progress = 'done' THEN 'Completed'
        WHEN ar.appointment_progress = 'cancelled' THEN 'Cancelled'
        ELSE 'Unavailable'
    END as progress_text
    FROM tbl_appointment_requests ar
    INNER JOIN tbl_patients p ON ar.patient_id = p.Patient_id
    INNER JOIN tbl_schedule s ON s.scheduledate = DATE(ar.booking_date)
        AND ar.appointment_time BETWEEN s.start_time AND s.end_time
    WHERE s.docid = ?
    ORDER BY ar.booking_date DESC, ar.appointment_time DESC";

$stmt = $database->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    // Format date and time
    $row['booking_date'] = date('M d, Y', strtotime($row['booking_date']));
    if ($row['appointment_time']) {
        $row['appointment_time'] = date('h:i A', strtotime($row['appointment_time']));
    }
    
    // Format appointment type
    $row['appointment_type'] = ucwords(str_replace('_', ' ', $row['appointment_type']));
    
    $appointments[] = $row;
}

header('Content-Type: application/json');
echo json_encode($appointments);
?>
