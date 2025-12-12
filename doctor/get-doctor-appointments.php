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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start']) && isset($_POST['end'])) {
    $startDate = $_POST['start'];
    $endDate = $_POST['end'];
    
    // Get appointments for the date range
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
               NULL AS appointment_progress,
               'legacy' as type,
               'approved' as status,
               'Eye Examination' as appointment_type
        FROM tbl_schedule s
        INNER JOIN tbl_appointment a ON s.scheduleid = a.scheduleid
        INNER JOIN tbl_patients p ON p.Patient_id = a.pid
        INNER JOIN tbl_doctor d ON s.docid = d.docid
        WHERE d.docid = ? AND s.scheduledate BETWEEN ? AND ?
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
               ar.appointment_type
        FROM tbl_appointment_requests ar
        INNER JOIN tbl_patients p ON p.Patient_id = ar.patient_id
        INNER JOIN tbl_schedule s ON s.scheduledate = ar.booking_date
            AND ar.appointment_time BETWEEN s.start_time AND s.end_time
        INNER JOIN tbl_doctor d ON s.docid = d.docid
        WHERE d.docid = ? AND ar.booking_date BETWEEN ? AND ? 
        AND ar.status = 'approved' 
        AND ar.appointment_progress NOT IN ('cancelled', 'done')
    )
    ORDER BY appointment_date ASC, appointment_time ASC";
    
    $stmt = $database->prepare($sql);
    $stmt->bind_param("isssis", $userid, $startDate, $endDate, $userid, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = array();
    $appointmentsByDate = array();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $date = $row['appointment_date'];
            if (!isset($appointmentsByDate[$date])) {
                $appointmentsByDate[$date] = array();
            }
            
            $progress = $row['appointment_progress'] ?: 'preappointment';
            $progressText = 'N/A';
            
            switch($progress) {
                case 'preappointment': $progressText = 'Pre-appointment'; break;
                case 'ongoing': $progressText = 'Ongoing'; break;
                case 'done': $progressText = 'Completed'; break;
                case 'cancelled': $progressText = 'Cancelled'; break;
                default: $progressText = ucfirst($progress);
            }
            
            $appointmentsByDate[$date][] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'patient_name' => $row['patient_name'],
                'session_title' => $row['session_title'],
                'appointment_type' => $row['appointment_type'],
                'appointment_time' => date('h:i A', strtotime($row['appointment_time'])),
                'appointment_date' => date('M d, Y', strtotime($row['appointment_date'])),
                'status' => ucfirst($row['status']),
                'progress' => $progress,
                'progress_text' => $progressText
            ];
        }
    }
    
    // Create count-based events
    foreach ($appointmentsByDate as $date => $appointments) {
        $count = count($appointments);
        $timeSlots = array_map(function($apt) {
            return $apt['appointment_time'];
        }, $appointments);
        
        $events[] = [
            'id' => 'date-' . $date,
            'title' => $count . ' appointment' . ($count > 1 ? 's' : ''),
            'start' => $date,
            'extendedProps' => [
                'date' => $date,
                'appointments' => $appointments,
                'count' => $count,
                'timeSlots' => implode(', ', $timeSlots)
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
