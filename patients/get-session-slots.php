<?php
session_start();
include("../connection.php");

// Check if user is logged in as patient
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
    header("location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['date'])) {
    $selectedDate = $_POST['date'];
    $selectedDoctor = isset($_POST['doctor_id']) ? $_POST['doctor_id'] : null;
    
    // Per-date duplicate booking guard (hide slots if patient already has approved + unavailable/ongoing on the date)
    if (isset($_SESSION['user'])) {
        $useremail = $_SESSION['user'];
        $pstmt = $database->prepare("SELECT Patient_id FROM tbl_patients WHERE Email = ?");
        if ($pstmt) {
            $pstmt->bind_param("s", $useremail);
            $pstmt->execute();
            $prow = $pstmt->get_result()->fetch_assoc();
            if ($prow) {
                $pid = (int)$prow['Patient_id'];
                $dupSql = "SELECT COUNT(*) AS cnt
                           FROM tbl_appointment_requests
                           WHERE patient_id = ?
                             AND booking_date = ?
                             AND status = 'approved'
                             AND appointment_progress IN ('preappointment','ongoing')";
                $dupStmt = $database->prepare($dupSql);
                if ($dupStmt) {
                    $dupStmt->bind_param("is", $pid, $selectedDate);
                    $dupStmt->execute();
                    $dup = $dupStmt->get_result()->fetch_assoc();
                    if (($dup['cnt'] ?? 0) > 0) {
                        echo json_encode(['success' => true, 'slots' => []]);
                        exit();
                    }
                }
            }
        }
    }
    
    // First, check if end_time column exists
    $checkColumnSql = "SHOW COLUMNS FROM tbl_schedule LIKE 'end_time'";
    $columnResult = $database->query($checkColumnSql);
    $hasEndTime = $columnResult->num_rows > 0;
    
    // UPDATED: Include doctor filter in the query
    if ($hasEndTime) {
        $sql = "SELECT 
                    s.scheduleid,
                    s.title,
                    s.scheduledate,
                    s.start_time,
                    s.end_time,
                    s.nop,
                    s.docid,
                    CONCAT(d.first_name,
                           CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                           ' ', d.last_name,
                           CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                    ) AS docname,
                    sp.sname as specialty
                FROM tbl_schedule s
                INNER JOIN tbl_doctor d ON s.docid = d.docid
                LEFT JOIN tbl_specialties sp ON d.specialties = sp.id
                WHERE s.scheduledate = ?";
    } else {
        $sql = "SELECT 
                    s.scheduleid,
                    s.title,
                    s.scheduledate,
                    s.start_time,
                    DATE_ADD(s.start_time, INTERVAL 2 HOUR) as end_time,
                    s.nop,
                    s.docid,
                    CONCAT(d.first_name,
                           CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                           ' ', d.last_name,
                           CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                    ) AS docname,
                    sp.sname as specialty
                FROM tbl_schedule s
                INNER JOIN tbl_doctor d ON s.docid = d.docid
                LEFT JOIN tbl_specialties sp ON d.specialties = sp.id
                WHERE s.scheduledate = ?";
    }
    
    // Add doctor filter if provided
    if ($selectedDoctor) {
        $sql .= " AND s.docid = ?";
        $sql .= " ORDER BY s.start_time ASC";
        $stmt = $database->prepare($sql);
        // both date and doctor id are strings (docid is varchar)
        $stmt->bind_param("ss", $selectedDate, $selectedDoctor);
    } else {
        $sql .= " ORDER BY s.start_time ASC";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $selectedDate);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $availableSlots = array();
    
    if ($result->num_rows > 0) {
        while ($session = $result->fetch_assoc()) {
            // Generate time slots for this session
            $sessionSlots = generateTimeSlotsForSession($session, $database);
            $availableSlots = array_merge($availableSlots, $sessionSlots);
        }
    }
    
    if (empty($availableSlots)) {
        echo json_encode([
            'success' => false,
            'message' => 'No sessions available for this date'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'slots' => $availableSlots
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

// ... rest of the functions remain the same ...
function generateTimeSlotsForSession($session, $database) {
    $slots = array();
    
    $startTime = strtotime($session['start_time']);
    $endTime = strtotime($session['end_time']);
    
    // Generate 30-minute intervals between start and end time
    $currentTime = $startTime;
    
    while ($currentTime < $endTime) {
        $slotEndTime = $currentTime + (30 * 60); // 30 minutes
        
        if ($slotEndTime <= $endTime) {
            $slotValue = date('H:i:s', $currentTime);
            $slotDisplay = date('g:i A', $currentTime);
            
            // Check if this slot is available (not fully booked)
            if (isSlotAvailable($session['scheduleid'], $slotValue, $session['nop'], $database)) {
                $slots[] = [
                    'value' => $slotValue,
                    'display' => $slotDisplay,
                    'session_id' => $session['scheduleid'],
                    'session_title' => $session['title'],
                    'doctor_id' => $session['docid'],
                    'doctor_name' => $session['docname'],
                    'specialty' => $session['specialty']
                ];
            }
        }
        
        $currentTime += (30 * 60); // Move to next 30-minute slot
    }
    
    return $slots;
}

function isSlotAvailable($scheduleId, $slotTime, $maxPatients, $database) {
    // Treat capacity as per-session total within the session window, not per individual slot
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