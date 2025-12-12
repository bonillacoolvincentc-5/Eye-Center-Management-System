<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get cancelled appointments
$sql = "(
    SELECT a.appoid as id,
           NULL AS request_id,
           s.scheduleid,
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
           'cancelled' as appointment_progress,
           'legacy' as type,
           'cancelled' as status,
           'Eye Examination' as appointment_type,
           a.appodate as cancelled_date
    FROM tbl_schedule s
    INNER JOIN tbl_appointment a ON s.scheduleid = a.scheduleid
    INNER JOIN tbl_patients p ON p.Patient_id = a.pid
    INNER JOIN tbl_doctor d ON s.docid = d.docid
    WHERE d.docid = ? AND a.appodate < CURDATE()
)
UNION ALL
(
    SELECT ar.request_id as id,
           ar.request_id,
           s.scheduleid,
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
           'request' as type,
           ar.status,
           ar.appointment_type,
           ar.booking_date as cancelled_date
    FROM tbl_appointment_requests ar
    INNER JOIN tbl_patients p ON p.Patient_id = ar.patient_id
    INNER JOIN tbl_schedule s ON s.scheduledate = ar.booking_date
        AND ar.appointment_time BETWEEN s.start_time AND s.end_time
    INNER JOIN tbl_doctor d ON s.docid = d.docid
    WHERE d.docid = ? 
    AND (ar.status = 'rejected' OR ar.appointment_progress = 'cancelled')
)
ORDER BY cancelled_date DESC";

try {
    $stmt = $database->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $database->error);
    }
    
    $stmt->bind_param("ii", $userid, $userid);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $appointments[] = [
                'id' => $row['id'],
                'patient_name' => $row['patient_name'],
                'appointment_type' => $row['appointment_type'],
                'appointment_date' => date('M d, Y', strtotime($row['appointment_date'])),
                'appointment_time' => date('h:i A', strtotime($row['appointment_time'])),
                'session_title' => $row['session_title'],
                'cancelled_date' => date('M d, Y h:i A', strtotime($row['cancelled_date'])),
                'status' => ucfirst($row['status']),
                'type' => $row['type']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'appointments' => $appointments
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
