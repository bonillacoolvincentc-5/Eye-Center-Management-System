<?php
session_start();
include("../connection.php");

// Check if user is logged in as patient
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['date'])) {
    $selectedDate = $_POST['date'];
    
    // Get all doctors
    $doctors_query = "SELECT d.docid, 
                      CONCAT(d.first_name,
                             CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                             ' ', d.last_name,
                             CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                      ) AS docname,
                      s.sname as specialty_name 
                      FROM tbl_doctor d 
                      LEFT JOIN tbl_specialties s ON d.specialties = s.id 
                      ORDER BY d.last_name, d.first_name";
    $doctors_result = $database->query($doctors_query);
    
    $doctorAvailability = array();
    
    while ($doctor = $doctors_result->fetch_assoc()) {
        $doctorId = $doctor['docid'];
        
        // Check if doctor has any available sessions for the selected date
        $availability_sql = "SELECT COUNT(*) as session_count
                            FROM tbl_schedule s
                            WHERE s.docid = ? AND s.scheduledate = ?";
        
        $stmt = $database->prepare($availability_sql);
        $stmt->bind_param("ss", $doctorId, $selectedDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $hasSessions = $result['session_count'] > 0;
        
        // If doctor has sessions, check if any slots are available
        $hasAvailableSlots = false;
        if ($hasSessions) {
            // Check if there are any available slots for this doctor on this date
            $slots_sql = "SELECT COUNT(*) as available_slots
                         FROM tbl_schedule s
                         WHERE s.docid = ? AND s.scheduledate = ?";
            
            $slots_stmt = $database->prepare($slots_sql);
            $slots_stmt->bind_param("ss", $doctorId, $selectedDate);
            $slots_stmt->execute();
            $slots_result = $slots_stmt->get_result()->fetch_assoc();
            
            if ($slots_result['available_slots'] > 0) {
                // Check if any slots are not fully booked
                $checkSlots = checkDoctorSlotAvailability($doctorId, $selectedDate, $database);
                $hasAvailableSlots = $checkSlots;
            }
        }
        
        $doctorAvailability[] = [
            'doctor_id' => $doctorId,
            'doctor_name' => $doctor['docname'],
            'specialty_name' => $doctor['specialty_name'],
            'is_available' => $hasAvailableSlots
        ];
    }
    
    echo json_encode([
        'success' => true,
        'doctor_availability' => $doctorAvailability
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

function checkDoctorSlotAvailability($doctorId, $selectedDate, $database) {
    // Check if end_time column exists
    $checkColumnSql = "SHOW COLUMNS FROM tbl_schedule LIKE 'end_time'";
    $columnResult = $database->query($checkColumnSql);
    $hasEndTime = $columnResult->num_rows > 0;
    
    if ($hasEndTime) {
        $sql = "SELECT s.scheduleid, s.start_time, s.end_time, s.nop
                FROM tbl_schedule s
                WHERE s.docid = ? AND s.scheduledate = ?";
    } else {
        $sql = "SELECT s.scheduleid, s.start_time, 
                       DATE_ADD(s.start_time, INTERVAL 2 HOUR) as end_time, s.nop
                FROM tbl_schedule s
                WHERE s.docid = ? AND s.scheduledate = ?";
    }
    
    $stmt = $database->prepare($sql);
    $stmt->bind_param("ss", $doctorId, $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($session = $result->fetch_assoc()) {
        $startTime = strtotime($session['start_time']);
        $endTime = strtotime($session['end_time']);
        
        // Generate 30-minute intervals and check availability
        $currentTime = $startTime;
        while ($currentTime < $endTime) {
            $slotEndTime = $currentTime + (30 * 60);
            
            if ($slotEndTime <= $endTime) {
                $slotValue = date('H:i:s', $currentTime);
                
                // Check if this slot is available
                if (isSlotAvailable($session['scheduleid'], $slotValue, $session['nop'], $database)) {
                    return true; // Found at least one available slot
                }
            }
            
            $currentTime += (30 * 60);
        }
    }
    
    return false; // No available slots found
}

function isSlotAvailable($scheduleId, $slotTime, $maxPatients, $database) {
    // Only count approved appointments that are not cancelled or done
    $sql = "SELECT COUNT(*) AS booked_count
            FROM tbl_appointment_requests ar
            INNER JOIN tbl_schedule s
              ON s.scheduleid = ?
             AND s.scheduledate = ar.booking_date
             AND ar.appointment_time BETWEEN s.start_time AND s.end_time
            WHERE ar.status = 'approved' 
            AND ar.appointment_progress NOT IN ('cancelled', 'done')";

    $stmt = $database->prepare($sql);
    $stmt->bind_param("s", $scheduleId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $bookedCount = $result ? (int)$result['booked_count'] : 0;
    $maxPatients = $maxPatients ?: 2; // Default to 2 if null

    return $bookedCount < $maxPatients;
}
?>
