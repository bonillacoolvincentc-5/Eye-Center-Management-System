<?php
session_start();
header('Content-Type: application/json');
include("../connection.php");

if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $useremail = $_SESSION["user"];
    
    // Updated SQL query to include doctor information
    $sql = "SELECT 
        ar.*,
        DATE_FORMAT(ar.booking_date, '%M %d, %Y') as formatted_date,
        TIME_FORMAT(ar.appointment_time, '%h:%i %p') as selected_time,
        CONCAT(p.Fname,
               CASE WHEN p.Mname IS NOT NULL AND p.Mname!='' THEN CONCAT(' ', p.Mname) ELSE '' END,
               ' ', p.Lname,
               CASE WHEN p.Suffix IS NOT NULL AND p.Suffix!='' THEN CONCAT(' ', p.Suffix) ELSE '' END
        ) AS composed_fullname,
        p.PhoneNo AS composed_mobile,
        TRIM(BOTH ', ' FROM CONCAT_WS(', ',
            NULLIF(p.Street, ''),
            NULLIF(p.Barangay, ''),
            NULLIF(p.`City/Municipality`, ''),
            NULLIF(p.Province, '')
        )) AS composed_address,
        CONCAT(d.first_name,
               CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
               ' ', d.last_name,
               CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
        ) AS doctor_name,
        s.sname AS doctor_specialty,
        CASE 
            WHEN ar.status = 'pending' THEN 'Pending Review'
            WHEN ar.status = 'approved' THEN 'Approved'
            WHEN ar.status = 'rejected' THEN 'Rejected'
            ELSE ar.status 
        END as status_text,
        CASE
            WHEN ar.appointment_progress = 'unavailable' THEN 'Unavailable'
            WHEN ar.appointment_progress = 'ongoing' THEN 'Ongoing'
            WHEN ar.appointment_progress = 'done' THEN 'Completed'
            WHEN ar.appointment_progress = 'cancelled' THEN 'Cancelled'
            ELSE 'Unavailable'
        END as progress_text
        FROM tbl_appointment_requests ar
        INNER JOIN tbl_patients p ON ar.patient_id = p.Patient_id
        LEFT JOIN tbl_doctor d ON ar.docid = d.docid
        LEFT JOIN tbl_specialties s ON d.specialties = s.id
        WHERE ar.request_id = ? AND p.Email = ?";

    $stmt = $database->prepare($sql);
    $stmt->bind_param("is", $id, $useremail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        // Fix duration display - remove extra "minutes"
        $duration = $row['duration'];
        if (strpos($duration, 'minutes') !== false) {
            $duration = str_replace('minutes', '', $duration);
            $duration = trim($duration);
        }
        
        // Price removed from schema
        $price = '---';
        
        // Prepare appointment details array with fixed formatting
        $appointmentDetails = array(
            'request_id' => $row['request_id'],
            'booking_date' => $row['formatted_date'],
            'appointment_time' => $row['selected_time'],
            'fullname' => $row['composed_fullname'],
            'address' => $row['composed_address'],
            'mobile' => $row['composed_mobile'],
            'appointment_type' => ucwords(str_replace('_', ' ', $row['appointment_type'])),
            'duration' => $duration,
            'price' => $price,
            'id_type' => $row['id_type'],
            'status' => $row['status_text'],
            'progress' => $row['progress_text'],
            'doctor_name' => $row['doctor_name'] ?: 'Not assigned',
            'doctor_specialty' => $row['doctor_specialty'] ?: 'Not specified'
        );
        
        // Add image paths if they exist
        if(!empty($row['id_image_holding'])) {
            $appointmentDetails['id_image_holding'] = '../uploads/id_images/' . $row['id_image_holding'];
        }
        if(!empty($row['id_image_only'])) {
            $appointmentDetails['id_image_only'] = '../uploads/id_images/' . $row['id_image_only'];
        }
        
        echo json_encode($appointmentDetails);
    } else {
        echo json_encode(['error' => 'Appointment not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>