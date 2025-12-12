<?php

include("../connection.php");

$date = $_POST['date'];

// Query to get booking count for the date
$sql = "SELECT COUNT(*) as booking_count FROM tbl_appointment WHERE DATE(appodate) = ?";
$stmt = $database->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['booking_count'];

// Define thresholds
$max_bookings = 10; // Maximum bookings per day
$almost_full_threshold = 7; // 70% capacity

// Check if there are any sessions scheduled for this date
$sessions_sql = "SELECT COUNT(*) as session_count FROM tbl_schedule WHERE DATE(scheduledate) = ?";
$sessions_stmt = $database->prepare($sessions_sql);
$sessions_stmt->bind_param("s", $date);
$sessions_stmt->execute();
$sessions_result = $sessions_stmt->get_result();
$session_count = $sessions_result->fetch_assoc()['session_count'];

if ($session_count == 0) {
    echo json_encode(['availability' => '']); // Return empty string instead of "No sessions"
} else if ($count >= $max_bookings) {
    echo json_encode(['availability' => 'fully-booked']);
} else if ($count >= $almost_full_threshold) {
    echo json_encode(['availability' => 'almost-full']);
} else {
    echo json_encode(['availability' => 'Available']);
}