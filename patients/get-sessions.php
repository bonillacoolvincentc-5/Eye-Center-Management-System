<?php
include("../connection.php");

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Updated query to count only ACTIVE appointments (not cancelled or done)
$query = "SELECT 
    s.scheduleid,
    s.scheduledate as date,
    DATE_FORMAT(s.start_time, '%h:%i %p') as start_time_fmt,
    DATE_FORMAT(s.end_time, '%h:%i %p') as end_time_fmt,
    IFNULL(s.nop, 2) as total_slots,
    IFNULL(COUNT(CASE 
        WHEN ar.status = 'approved' 
        AND ar.appointment_progress NOT IN ('cancelled', 'done')
        THEN ar.request_id 
    END), 0) as booked
FROM tbl_schedule s
LEFT JOIN tbl_appointment_requests ar ON s.scheduledate = DATE(ar.booking_date)
WHERE s.scheduledate >= CURDATE()
  AND s.start_time IS NOT NULL
GROUP BY s.scheduleid, s.scheduledate, s.start_time, s.end_time, s.nop
ORDER BY s.scheduledate ASC, s.start_time ASC";

$result = $database->query($query);

if (!$result) {
    die(json_encode([
        'error' => true,
        'message' => $database->error
    ]));
}

$sessions = array();

while($row = $result->fetch_assoc()) {
    $total = (int)$row['total_slots'];
    $booked = (int)$row['booked'];
    $available = max(0, $total - $booked);

    $label = trim(($row['start_time_fmt'] ? $row['start_time_fmt'] : '') . (isset($row['end_time_fmt']) && $row['end_time_fmt'] ? ' - ' . $row['end_time_fmt'] : ''));
    if ($label !== '') {
        $sessions[] = [
            'scheduleid' => (int)$row['scheduleid'],
            'date' => $row['date'],
            'time' => $label,
            'available' => $available,
            'total' => $total,
            'display' => "$available/$total"
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($sessions);
?>