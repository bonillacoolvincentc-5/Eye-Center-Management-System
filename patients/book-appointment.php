<?php
session_start();
include("../connection.php");

// Set content type to JSON first to prevent HTML output
header('Content-Type: application/json');

// Check if user is logged in as patient
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$useremail = $_SESSION["user"];

// Get patient info
$stmt = $database->prepare("SELECT Patient_id FROM tbl_patients WHERE Email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();

if (!$userfetch) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

$patient_id = $userfetch["Patient_id"];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    $required_fields = ['selectedDate', 'appointmentTime', 'appointmentType', 'appointmentDuration', 'selectedDoctor', 'idType'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }

    // Get form data
    $selectedDate = $_POST['selectedDate'];
    $appointmentTime = $_POST['appointmentTime'];
    $appointmentType = $_POST['appointmentType'];
    $appointmentDuration = $_POST['appointmentDuration'];
    $selectedDoctor = $_POST['selectedDoctor'];
    $idType = $_POST['idType'];
    
    // Validate doctor selection
    if (empty($selectedDoctor)) {
        echo json_encode(['success' => false, 'message' => 'Please select a doctor']);
        exit();
    }

    // Duplicate per-date booking guard: block if already Approved with progress unavailable/ongoing on same date
    $dupSql = "SELECT COUNT(*) AS cnt
               FROM tbl_appointment_requests
               WHERE patient_id = ?
                 AND booking_date = ?
                 AND status = 'approved'
                 AND appointment_progress IN ('unavailable','ongoing')";
    $dupStmt = $database->prepare($dupSql);
    if ($dupStmt) {
        $dupStmt->bind_param("is", $patient_id, $selectedDate);
        $dupStmt->execute();
        $dup = $dupStmt->get_result()->fetch_assoc();
        if (($dup['cnt'] ?? 0) > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have an approved appointment on this date. Please complete or cancel it before booking another.']);
            exit();
        }
    }
    
    // Check if end_time column exists
    $checkColumnSql = "SHOW COLUMNS FROM tbl_schedule LIKE 'end_time'";
    $columnResult = $database->query($checkColumnSql);
    $hasEndTime = $columnResult->num_rows > 0;
    
    // Updated session query to include doctor filter
    if ($hasEndTime) {
        $sessionSql = "SELECT scheduleid, nop, docid FROM tbl_schedule WHERE scheduledate = ? AND docid = ? AND ? BETWEEN start_time AND end_time";
    } else {
        $sessionSql = "SELECT scheduleid, nop, docid FROM tbl_schedule WHERE scheduledate = ? AND docid = ? AND ? BETWEEN start_time AND DATE_ADD(start_time, INTERVAL 2 HOUR)";
    }
    
    $sessionStmt = $database->prepare($sessionSql);
    if (!$sessionStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $database->error]);
        exit();
    }
    
    $sessionStmt->bind_param("sss", $selectedDate, $selectedDoctor, $appointmentTime);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();
    
    if ($sessionResult->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'No valid session found for the selected doctor, date and time']);
        exit();
    }
    
    $session = $sessionResult->fetch_assoc();
    $schedule_id = $session['scheduleid'];
    $doctor_id = $session['docid']; // Get the doctor ID from the session
    $maxPatients = $session['nop'] ?: 2; // Default to 2 if null
    
    // Capacity is per-session: count all requests within the session window for this schedule
    $availabilitySql = "SELECT COUNT(*) as booked_count
                        FROM tbl_appointment_requests ar
                        INNER JOIN tbl_schedule s
                          ON s.scheduledate = ar.booking_date
                         AND ar.appointment_time BETWEEN s.start_time AND s.end_time
                        WHERE s.scheduleid = ?
                          AND ar.status IN ('pending','approved')";
    $availabilityStmt = $database->prepare($availabilitySql);
    if (!$availabilityStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $database->error]);
        exit();
    }
    
    $availabilityStmt->bind_param("i", $schedule_id);
    $availabilityStmt->execute();
    $availabilityResult = $availabilityStmt->get_result()->fetch_assoc();
    
    if ($availabilityResult['booked_count'] >= $maxPatients) {
        echo json_encode(['success' => false, 'message' => 'This time slot is no longer available. Please choose another time.']);
        exit();
    }
    
    // Handle file uploads
    $idImageFront = '';
    $idImageBack = '';
    
    // Upload front ID image
    if (isset($_FILES['id_image_holding']) && $_FILES['id_image_holding']['error'] == 0) {
        $frontImage = uploadImage($_FILES['id_image_holding'], $patient_id, 'front');
        if ($frontImage['success']) {
            $idImageFront = $frontImage['filename'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Front image upload failed: ' . $frontImage['message']]);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Front ID image is required']);
        exit();
    }
    
    // Upload back ID image
    if (isset($_FILES['id_image_only']) && $_FILES['id_image_only']['error'] == 0) {
        $backImage = uploadImage($_FILES['id_image_only'], $patient_id, 'back');
        if ($backImage['success']) {
            $idImageBack = $backImage['filename'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Back image upload failed: ' . $backImage['message']]);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Back ID image is required']);
        exit();
    }
    
    // FIXED: Insert appointment request WITH docid
    $insertSql = "INSERT INTO tbl_appointment_requests 
                  (patient_id, docid, booking_date, appointment_time, appointment_type, duration, id_type, id_image_holding, id_image_only, status, appointment_progress) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unavailable')";
    
    $insertStmt = $database->prepare($insertSql);
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $database->error]);
        exit();
    }
    
    // FIXED: Added doctor_id parameter
    $insertStmt->bind_param("iisssssss", 
        $patient_id,
        $doctor_id,        // Added doctor ID
        $selectedDate,
        $appointmentTime,
        $appointmentType,
        $appointmentDuration,
        $idType,
        $idImageFront,
        $idImageBack
    );
    
    if ($insertStmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment request submitted successfully. Please wait for admin approval.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $database->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function uploadImage($file, $patient_id, $type) {
    $uploadDir = "../uploads/id_images/";
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable'];
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "patient_{$patient_id}_{$type}_" . time() . "." . $fileExtension;
    $filepath = $uploadDir . $filename;
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size too large (max 5MB)'];
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed'];
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
}
?>