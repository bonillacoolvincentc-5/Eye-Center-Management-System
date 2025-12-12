<?php
session_start();
include("../connection.php");

// Check authentication
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    die(json_encode(['error' => 'Unauthorized']));
}

// Get appointment counts grouped by date and status
$sql = "SELECT 
    r.booking_date,
    r.status,
    COUNT(*) as appointment_count
    FROM tbl_appointment_requests r
    WHERE r.booking_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY r.booking_date, r.status
    ORDER BY r.booking_date DESC";

$result = $database->query($sql);
$appointments = [];

// Group by date
$dateGroups = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['booking_date'];
    if (!isset($dateGroups[$date])) {
        $dateGroups[$date] = [];
    }
    $dateGroups[$date][$row['status']] = $row['appointment_count'];
}

// Convert to calendar format
foreach ($dateGroups as $date => $statusCounts) {
    $totalCount = array_sum($statusCounts);
    $statusText = [];
    
    foreach ($statusCounts as $status => $count) {
        $statusText[] = ucfirst($status) . ': ' . $count;
    }
    
    $appointments[] = [
        'request_id' => 'group_' . $date,
        'booking_date' => $date,
        'appointment_time' => null,
        'appointment_type' => 'Multiple Services',
        'status' => 'mixed',
        'appointment_progress' => 'unavailable',
        'patient_name' => $totalCount . ' appointment(s)',
        'mobile' => implode(', ', $statusText)
    ];
}

header('Content-Type: application/json');
echo json_encode($appointments);
?>
