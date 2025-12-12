<?php
session_start();
include("../connection.php");

if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    die(json_encode(['error' => 'Unauthorized']));
}

if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // FIXED SQL query - using docid instead of selected_doctor_id
    $sql = "SELECT 
        r.*,
        p.Fname,
        p.Mname,
        p.Lname,
        p.Suffix,
        p.PhoneNo,
        TRIM(
            BOTH ', ' FROM CONCAT_WS(', ',
                NULLIF(p.Street, ''),
                NULLIF(p.Barangay, ''),
                NULLIF(p.`City/Municipality`, ''),
                NULLIF(p.Province, '')
            )
        ) as Address,
        CONCAT(d.first_name,
               CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
               ' ', d.last_name,
               CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
        ) as selected_doctor_name,
        d.docemail as selected_doctor_email
    FROM tbl_appointment_requests r
    LEFT JOIN tbl_patients p ON r.patient_id = p.Patient_id
    LEFT JOIN tbl_doctor d ON r.docid = d.docid 
    WHERE r.request_id = ?";

    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        // Format the full name
        $fullname = $row['Fname'];
        if (!empty($row['Mname'])) {
            $fullname .= ' ' . $row['Mname'];
        }
        $fullname .= ' ' . $row['Lname'];
        if (!empty($row['Suffix'])) {
            $fullname .= ' ' . $row['Suffix'];
        }
        
        // Format dates
        $row['booking_date'] = date('M d, Y', strtotime($row['booking_date']));

        // Format appointment_time if exists
        if ($row['appointment_time']) {
            $row['appointment_time_formatted'] = date('h:i A', strtotime($row['appointment_time']));
        } else {
            $row['appointment_time_formatted'] = '---';
        }

        // Format approved_at if exists
        if ($row['approved_at']) {
            $row['approved_at'] = date('M d, Y h:i A', strtotime($row['approved_at']));
        } else {
            $row['approved_at'] = null;
        }
        
        // Remove price (no longer in schema)
        $row['price_formatted'] = '---';
        
        // Format appointment type
        $row['appointment_type_formatted'] = $row['appointment_type'] ? 
            ucwords(str_replace('_', ' ', $row['appointment_type'])) : '---';
        
        // Format duration
        $row['duration_formatted'] = $row['duration'] ?: '---';
        
        // Add formatted fullname
        $row['fullname'] = $fullname;
        
        // Add mobile and address from patient table (override if exists in appointment request)
        $row['mobile'] = $row['PhoneNo'] ?: ($row['mobile'] ?? '---');
        $row['address'] = $row['Address'] ?: ($row['address'] ?? '---');
        
        // Add progress text
        $row['progress_text'] = match($row['appointment_progress']) {
            'preappointment' => 'Pre-appointment',
            'ongoing' => 'Ongoing',
            'done' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unavailable'
        };
        
        // Capitalize ID type for display
        if (isset($row['id_type']) && $row['id_type'] !== null) {
            $row['id_type_formatted'] = ucfirst($row['id_type']);
        } else {
            $row['id_type_formatted'] = '---';
        }
        
        // Clean up response by removing unnecessary fields
        unset($row['Fname'], $row['Mname'], $row['Lname'], $row['Suffix'], $row['PhoneNo'], $row['Address']);
        
        // Clean image paths
        $row['id_image_holding'] = $row['id_image_holding'] ?? '';
        $row['id_image_only'] = $row['id_image_only'] ?? '';
        
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Request not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>