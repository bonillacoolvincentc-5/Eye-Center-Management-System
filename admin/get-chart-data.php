<?php
session_start();
include("../connection.php");

// Check authentication
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    die(json_encode(['error' => 'Unauthorized']));
}

$filter = $_GET['filter'] ?? 'month';

// Set timezone
date_default_timezone_set('Asia/Manila');

$labels = [];
$values = [];

switch($filter) {
    case 'day':
        // Last 7 days
        for($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            $sql = "SELECT COUNT(*) as count FROM tbl_appointment_requests WHERE DATE(booking_date) = ?";
            $stmt = $database->prepare($sql);
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $values[] = (int)$result['count'];
        }
        break;
        
    case 'week':
        // Last 4 weeks
        for($i = 3; $i >= 0; $i--) {
            $start_date = date('Y-m-d', strtotime("-" . ($i * 7 + 6) . " days"));
            $end_date = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
            $labels[] = 'Week of ' . date('M d', strtotime($start_date));
            
            $sql = "SELECT COUNT(*) as count FROM tbl_appointment_requests WHERE DATE(booking_date) BETWEEN ? AND ?";
            $stmt = $database->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $values[] = (int)$result['count'];
        }
        break;
        
    case 'month':
        // Last 6 months
        for($i = 5; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $labels[] = date('M Y', strtotime($date));
            
            $sql = "SELECT COUNT(*) as count FROM tbl_appointment_requests WHERE YEAR(booking_date) = ? AND MONTH(booking_date) = ?";
            $stmt = $database->prepare($sql);
            $year = date('Y', strtotime($date));
            $month = date('n', strtotime($date));
            $stmt->bind_param("ii", $year, $month);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $values[] = (int)$result['count'];
        }
        break;
        
    case 'year':
        // Last 3 years
        for($i = 2; $i >= 0; $i--) {
            $year = date('Y') - $i;
            $labels[] = $year;
            
            $sql = "SELECT COUNT(*) as count FROM tbl_appointment_requests WHERE YEAR(booking_date) = ?";
            $stmt = $database->prepare($sql);
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $values[] = (int)$result['count'];
        }
        break;
}

header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'values' => $values,
    'filter' => $filter
]);
?>
