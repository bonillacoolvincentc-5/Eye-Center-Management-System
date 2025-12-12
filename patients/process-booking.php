<?php
session_start();
include("../connection.php");

if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit();
}

// Get patient ID from database using the email in session
$useremail = $_SESSION["user"];
$stmt = $database->prepare("SELECT Patient_id FROM tbl_patients WHERE Email = ?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient not found'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create uploads directory if it doesn't exist - FIXED PATH
        $uploadDir = '../uploads/id_images/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Handle file uploads
        $frontImage = '';
        $backImage = '';
        
        if (isset($_FILES['id_image_holding']) && $_FILES['id_image_holding']['error'] === 0) {
            $frontImage = 'id_images/' . uniqid() . '_holding_' . basename($_FILES['id_image_holding']['name']);
            move_uploaded_file($_FILES['id_image_holding']['tmp_name'], '../uploads/' . $frontImage);
        }
        
        if (isset($_FILES['id_image_only']) && $_FILES['id_image_only']['error'] === 0) {
            $backImage = 'id_images/' . uniqid() . '_only_' . basename($_FILES['id_image_only']['name']);
            move_uploaded_file($_FILES['id_image_only']['tmp_name'], '../uploads/' . $backImage);
        }

        // Get form data
        $selectedDate = $_POST['selectedDate'];
        $fullName = $_POST['fullName'];
        $address = $_POST['address'];
        $mobile = $_POST['mobile'];
        $appointmentType = $_POST['appointmentType'];
        $duration = $_POST['appointmentDuration'];
        $idType = $_POST['idType'];

        // Block booking if patient already has an approved request that is not completed or cancelled on the same date
        $chk = $database->prepare("SELECT COUNT(*) AS cnt
                                   FROM tbl_appointment_requests
                                   WHERE patient_id = ?
                                     AND booking_date = ?
                                     AND status = 'approved'
                                     AND (appointment_progress IS NULL OR appointment_progress NOT IN ('done','cancelled'))");
        $chk->bind_param("is", $patient['Patient_id'], $selectedDate);
        $chk->execute();
        $hasActive = ($chk->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
        if($hasActive){
            echo json_encode([
                'success' => false,
                'message' => 'You already have an active approved appointment for this date. Please complete or cancel it before booking another.'
            ]);
            exit();
        }

        // Insert into database with patient_id - FIXED: Removed appointment_progress
        $stmt = $database->prepare("INSERT INTO tbl_appointment_requests 
            (patient_id, booking_date, fullname, address, mobile, appointment_type, duration, id_type, id_image_holding, id_image_only, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        // FIXED: Correct number of parameters (11 instead of 12)
        $stmt->bind_param("isssssssss", 
            $patient['Patient_id'],  // integer
            $selectedDate,           // string
            $fullName,              // string
            $address,               // string
            $mobile,                // string
            $appointmentType,       // string
            $duration,              // string
            $idType,                // string
            $frontImage,            // string
            $backImage             // string
        );

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Your appointment request has been submitted successfully!',
                'details' => 'We will review your request and notify you once it\'s approved.'
            ]);
        } else {
            throw new Exception('Error inserting appointment');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>