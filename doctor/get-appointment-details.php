<?php
session_start();
include("../connection.php");

// Check if user is logged in as doctor
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$useremail = $_SESSION["user"];
$userrow = $database->query("select * from tbl_doctor where docemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["docid"];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['type'])) {
    $appointmentId = $_POST['id'];
    $appointmentType = $_POST['type'];
    
    if ($appointmentType === 'legacy') {
        // Get legacy appointment details
        $sql = "SELECT a.appoid,
                       s.title as session_title,
                       CONCAT(d.first_name,
                              CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                              ' ', d.last_name,
                              CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                       ) AS docname,
                       CONCAT(p.Fname, ' ', p.Lname) AS patient_name,
                       s.scheduledate as appointment_date,
                       s.start_time as appointment_time,
                       a.appodate,
                       NULL AS appointment_progress,
                       'approved' as status,
                       'Eye Examination' as appointment_type
                FROM tbl_schedule s
                INNER JOIN tbl_appointment a ON s.scheduleid = a.scheduleid
                INNER JOIN tbl_patients p ON p.Patient_id = a.pid
                INNER JOIN tbl_doctor d ON s.docid = d.docid
                WHERE d.docid = ? AND a.appoid = ?";
        
        $stmt = $database->prepare($sql);
        $stmt->bind_param("ii", $userid, $appointmentId);
    } else {
        // Get request-based appointment details
        $sql = "SELECT ar.request_id,
                       s.title as session_title,
                       CONCAT(d.first_name,
                              CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                              ' ', d.last_name,
                              CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                       ) AS docname,
                       CONCAT(p.Fname, ' ', p.Lname) AS patient_name,
                       ar.booking_date AS appointment_date,
                       ar.appointment_time AS appointment_time,
                       ar.booking_date AS appodate,
                       ar.appointment_progress,
                       ar.status,
                       ar.appointment_type
                FROM tbl_appointment_requests ar
                INNER JOIN tbl_patients p ON p.Patient_id = ar.patient_id
                INNER JOIN tbl_schedule s ON s.scheduledate = ar.booking_date
                    AND ar.appointment_time BETWEEN s.start_time AND s.end_time
                INNER JOIN tbl_doctor d ON s.docid = d.docid
                WHERE d.docid = ? AND ar.request_id = ?";
        
        $stmt = $database->prepare($sql);
        $stmt->bind_param("ii", $userid, $appointmentId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $progress = $row['appointment_progress'] ?: 'preappointment';
        $progressText = 'N/A';
        
        switch($progress) {
            case 'preappointment': $progressText = 'Pre-appointment'; break;
            case 'ongoing': $progressText = 'Ongoing'; break;
            case 'done': $progressText = 'Completed'; break;
            case 'cancelled': $progressText = 'Cancelled'; break;
            default: $progressText = ucfirst($progress);
        }
        
        $appointment = [
            'id' => $appointmentId,
            'type' => $appointmentType,
            'patient_name' => $row['patient_name'],
            'session_title' => $row['session_title'],
            'appointment_date' => date('M d, Y', strtotime($row['appointment_date'])),
            'appointment_time' => date('h:i A', strtotime($row['appointment_time'])),
            'appointment_type' => $row['appointment_type'],
            'status' => ucfirst($row['status']),
            'progress' => $progressText,
            'progress_value' => $progress
        ];
        
        echo json_encode([
            'success' => true,
            'appointment' => $appointment
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
