<?php
session_start();

// Enhanced session validation
if (!isset($_SESSION["user"]) || !isset($_SESSION['usertype']) || $_SESSION['usertype'] != 'p') {
    // Store the intended destination
    $_SESSION['redirect_url'] = 'patients/schedule.php';
    header("location: ../login.php");
    exit();
}

$useremail = $_SESSION["user"];

if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
        header("location: ../login.php");
        exit();
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: ../login.php");
    exit();
}

// Import database
include("../connection.php");

// Fetch patient info for profile
$stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();

if (!$userfetch) {
    session_destroy();
    header("location: ../login.php");
    exit();
}

$userid = $userfetch["Patient_id"];
$username = $userfetch["Fname"]; // Just first name

// NEW: profile image path
$profileImage = !empty($userfetch['ProfileImage']) ? '../Images/profiles/' . $userfetch['ProfileImage'] : '../Images/user.png';

$fullname = $userfetch["Fname"];
if (!empty($userfetch["Mname"])) {
    $fullname .= ' ' . $userfetch["Mname"];
}
$fullname .= ' ' . $userfetch["Lname"];
if (!empty($userfetch["Suffix"])) {
    $fullname .= ' ' . $userfetch["Suffix"];
}

// Calculate patient age for senior discount
$birthdate = $userfetch["Birthdate"];
$age = 0;
$isEligibleForDiscount = false;
if (!empty($birthdate)) {
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    $isEligibleForDiscount = $age >= 60;
}

$date = date('Y-m-d');

// FIXED: Get all doctors with their specialties (removed the schedule restriction)
$doctors_query = "SELECT d.docid, 
                  CONCAT(d.first_name,
                         CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                         ' ', d.last_name,
                         CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                  ) AS docname,
                  d.docemail, s.sname as specialty_name 
                  FROM tbl_doctor d 
                  LEFT JOIN tbl_specialties s ON d.specialties = s.id 
                  ORDER BY d.last_name, d.first_name";
$doctors_result = $database->query($doctors_query);
$doctors = array();
while ($doctor = $doctors_result->fetch_assoc()) {
    $doctors[] = $doctor;
}

// Debug: Check if doctors are being fetched
error_log("Doctors found: " . count($doctors));

// NEW: Enhanced function to check if patient can book appointment with availability check
function canPatientBookAppointment($patient_id, $database, $selectedDate = null) {
    // Check for pending appointments
    $pending_stmt = $database->prepare("SELECT COUNT(*) as pending_count FROM tbl_appointment_requests WHERE patient_id = ? AND status = 'pending'");
    $pending_stmt->bind_param("i", $patient_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result()->fetch_assoc();
    
    if ($pending_result['pending_count'] > 0) {
        return [
            'can_book' => false,
            'reason' => 'You have a pending appointment request. Please wait for it to be approved or rejected before booking another appointment.'
        ];
    }
    
    // Check for approved appointments within last 12 hours
    $approved_stmt = $database->prepare("SELECT approved_at FROM tbl_appointment_requests WHERE patient_id = ? AND status = 'approved' ORDER BY approved_at DESC LIMIT 1");
    $approved_stmt->bind_param("i", $patient_id);
    $approved_stmt->execute();
    $approved_result = $approved_stmt->get_result();
    
    if ($approved_result->num_rows > 0) {
        $approved_appointment = $approved_result->fetch_assoc();
        $approved_time = strtotime($approved_appointment['approved_at']);
        $current_time = time();
        $hours_passed = ($current_time - $approved_time) / 3600; // Convert to hours
        
        if ($hours_passed < 12) {
            $remaining_hours = 12 - $hours_passed;
            return [
                'can_book' => false,
                'reason' => "You recently had an appointment approved. Please wait " . round($remaining_hours, 1) . " hours before booking another appointment."
            ];
        }
    }
    
    // If a specific date is provided, check its availability
    if ($selectedDate) {
        $availability = checkDateAvailability($selectedDate, $database);
        if (!$availability['can_book']) {
            return [
                'can_book' => false,
                'reason' => 'No available slots for the selected date. Please choose another date.'
            ];
        }
    }
    
    return ['can_book' => true, 'reason' => ''];
}

// NEW: Function to check availability for a specific date
function checkDateAvailability($selectedDate, $database) {
    // Get total available slots for the date
    $slots_stmt = $database->prepare("
        SELECT IFNULL(nop, 2) as total_slots 
        FROM tbl_schedule 
        WHERE scheduledate = ? 
        LIMIT 1
    ");
    $slots_stmt->bind_param("s", $selectedDate);
    $slots_stmt->execute();
    $slots_result = $slots_stmt->get_result();
    
    $total_slots = 2; // Default to 2 slots
    if ($slots_result->num_rows > 0) {
        $slots_data = $slots_result->fetch_assoc();
        $total_slots = $slots_data['total_slots'];
    }
    
    // Count active appointments for the date (approved and not cancelled/done)
    $booked_stmt = $database->prepare("
        SELECT COUNT(*) as booked_count 
        FROM tbl_appointment_requests 
        WHERE DATE(booking_date) = ? 
        AND status = 'approved' 
        AND appointment_progress NOT IN ('cancelled', 'done')
    ");
    $booked_stmt->bind_param("s", $selectedDate);
    $booked_stmt->execute();
    $booked_result = $booked_stmt->get_result()->fetch_assoc();
    
    $available_slots = max(0, $total_slots - $booked_result['booked_count']);
    
    return [
        'available' => $available_slots,
        'total' => $total_slots,
        'can_book' => $available_slots > 0
    ];
}

$booking_status = canPatientBookAppointment($userid, $database);
$can_book = $booking_status['can_book'];
$booking_reason = $booking_status['reason'];

// Query: Show scheduled sessions for this patient
$sqlmain = "SELECT 
    tbl_schedule.scheduleid,
    tbl_schedule.title,
    CONCAT(tbl_doctor.first_name,
           CASE WHEN tbl_doctor.middle_name IS NOT NULL AND tbl_doctor.middle_name != '' THEN CONCAT(' ', tbl_doctor.middle_name) ELSE '' END,
           ' ', tbl_doctor.last_name,
           CASE WHEN tbl_doctor.suffix IS NOT NULL AND tbl_doctor.suffix != '' THEN CONCAT(' ', tbl_doctor.suffix) ELSE '' END
    ) AS docname,
    tbl_schedule.scheduledate,
    tbl_schedule.start_time
FROM tbl_schedule
INNER JOIN tbl_appointment ON tbl_schedule.scheduleid = tbl_appointment.scheduleid
INNER JOIN tbl_doctor ON tbl_schedule.docid = tbl_doctor.docid
WHERE tbl_appointment.pid = ?
ORDER BY tbl_schedule.scheduledate ASC, tbl_schedule.start_time ASC";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("i", $userid);
$stmt->execute();
$list11 = $stmt->get_result();

// Get doctor availability status for calendar
$availability_query = "SELECT 
    tbl_schedule.scheduledate,
    COUNT(CASE WHEN tbl_appointment.status = 'approved' THEN 1 END) as booked_slots,
    COUNT(DISTINCT tbl_schedule.scheduleid) * 10 as total_slots
FROM tbl_schedule 
LEFT JOIN tbl_appointment ON tbl_schedule.scheduleid = tbl_appointment.scheduleid
GROUP BY tbl_schedule.scheduledate";

$availability_result = $database->query($availability_query);
$availability_data = array();

while($row = $availability_result->fetch_assoc()) {
    $date = $row['scheduledate'];
    $percentage = ($row['total_slots'] > 0) ? ($row['booked_slots'] / $row['total_slots']) * 100 : 0;
    
    if($percentage >= 100) {
        $status = 'red'; // Fully booked
    } elseif($percentage >= 70) {
        $status = 'yellow'; // Almost fully booked
    } else {
        $status = 'green'; // Available
    }
    
    $availability_data[$date] = $status;
}

// Define time slots (every 30 minutes from 7:00 AM to 5:00 PM)
$timeSlots = array(
    '07:00:00' => '7:00 AM',
    '07:30:00' => '7:30 AM',
    '08:00:00' => '8:00 AM',
    '08:30:00' => '8:30 AM',
    '09:00:00' => '9:00 AM',
    '09:30:00' => '9:30 AM',
    '10:00:00' => '10:00 AM',
    '10:30:00' => '10:30 AM',
    '11:00:00' => '11:00 AM',
    '11:30:00' => '11:30 AM',
    '12:00:00' => '12:00 PM',
    '12:30:00' => '12:30 PM',
    '13:00:00' => '1:00 PM',
    '13:30:00' => '1:30 PM',
    '14:00:00' => '2:00 PM',
    '14:30:00' => '2:30 PM',
    '15:00:00' => '3:00 PM',
    '15:30:00' => '3:30 PM',
    '16:00:00' => '4:00 PM',
    '16:30:00' => '4:30 PM',
    '17:00:00' => '5:00 PM'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment</title>
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <style>
        /* Calendar container */
        .calendar-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin: 20px;
            width: calc(100% - 40px);
            overflow: hidden;
        }

        /* Dashboard container for calendar */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            padding: 20px;
            min-height: 100vh;
            background: #f8f9fa;
        }

        /* Small helper note inside calendar */
        .calendar-note{ 
            display:flex; align-items:center; gap:8px; 
            color:#6b7280; font-size:13px; margin-bottom:10px;
        }
        .calendar-note .dot{ width:8px; height:8px; border-radius:50%; background:#9ca3af; display:inline-block; }

        /* Booking restriction message */
        .booking-restriction {
            background: #fff8e1;
            border: 1px solid #ffe8a1;
            color: #7a5b00;
            padding: 10px 14px;
            border-radius: 10px;
            margin: 12px 20px 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 16px rgba(0,0,0,.06);
        }

        .booking-restriction.warning {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .booking-restriction .close-banner{
            margin-left: 8px;
            border: none;
            background: transparent;
            color: inherit;
            cursor: pointer;
            padding: 2px 6px;
            opacity: .75;
        }
        .booking-restriction .close-banner:hover{ opacity: 1; }

        /* Calendar table */
        .fc-scrollgrid {
            border: 1px solid #ddd !important;
            border-radius: 8px;
            overflow: hidden;
        }

        .fc-scrollgrid-section > td {
            border: 0 !important;
        }

        /* Calendar grid cells */
        .fc .fc-daygrid-day { height: 120px !important; }

        .fc .fc-daygrid-day-frame {
            height: 100% !important;
            border: 1px solid #eee !important;
            margin: 0 !important;
            padding: 0 !important;
            border-radius: 8px;
            transition: box-shadow .2s ease, transform .12s ease, background-color .2s ease, border-color .2s ease;
        }

        .fc-daygrid-day-events {
            min-height: 0 !important;
            margin: 0 !important;
        }

        .fc-daygrid-day-top {
            padding: 4px !important;
        }

        .session-info {
            font-size: 12px;
            padding: 4px;
            overflow: hidden;
        }

        .fc-view-harness {
            margin: 0 !important;
        }

        /* Calendar header */
        .fc-theme-standard thead { background-color: #f8f9fa; }

        .fc .fc-col-header-cell-cushion {
            padding: 8px;
            color: #495057;
        }

        .fc .fc-toolbar-title {
            font-size: 1.8em;
            font-weight: 500;
        }

        .fc .fc-button { background-color: #f7f7f7; border: 1px solid #eee; color: #333; border-radius: 8px; }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #eaeaea;
            border: 1px solid #e0e0e0;
        }

        /* Hover animations for calendar days */
        .fc .fc-daygrid-day:hover .fc-daygrid-day-frame{
            box-shadow: 0 8px 18px rgba(0,0,0,.08);
            border-color: #e2e8f0 !important;
            transform: translateY(-2px);
            background: radial-gradient(200px 120px at 50% 20%, rgba(33,150,243,.06), transparent 70%);
        }
        .fc .fc-daygrid-day:hover .fc-daygrid-day-number{ color:#1f2937; }

        /* Show days from other months with faded appearance */
        .fc .fc-day-other {
            opacity: 54 !important;
            background-color: #f9f9f9 !important;
        }

        .fc .fc-day-other .fc-daygrid-day-number {
            color: #999 !important;
        }

        .fc .fc-day-other .session-info {
            color: #ccc !important;
        }

        /* Status colors */
        .status-green { background-color: rgba(40,167,69,.08) !important; border-color: rgba(40,167,69,.25) !important; }
        .status-yellow { background-color: rgba(255,193,7,.08) !important; border-color: rgba(255,193,7,.25) !important; }
        .status-red { background-color: rgba(220,53,69,.08) !important; border-color: rgba(220,53,69,.25) !important; }

        /* Today & weekend subtle highlight */
        .fc .fc-day-today{ background: linear-gradient(0deg, rgba(33,150,243,.05), rgba(33,150,243,.05)); }
        .fc .fc-day-sat, .fc .fc-day-sun{ background: rgba(249,249,249,.85); }

        /* Event pill tweaks */
        .fc-event{ border: none; border-radius: 6px; box-shadow: 0 4px 10px rgba(0,0,0,.08); transition: transform .15s ease, box-shadow .2s ease; }
        .fc-event:hover{ box-shadow: 0 10px 20px rgba(0,0,0,.16); transform: translateY(-1px) scale(1.01); }

        /* Session info styling - colors handled inline for dynamic states */
        .session-info{ font-size:12px; }

        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background: #fff;
            width: 90%;
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            overflow-y: auto;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-group small {
            color: #666;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }

        /* ID Images container */
        .id-images-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }

        .image-preview {
            max-width: 100%;
            height: 150px;
            object-fit: contain;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Button container */
        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #eee;
        }

        /* Fancy action buttons */
        .btn-primary,
        .btn-secondary{ 
            position: relative; 
            display:inline-flex; 
            align-items:center; 
            justify-content:center; 
            gap:8px; 
            padding: 10px 18px; 
            border-radius: 10px; 
            border: 1px solid transparent; 
            font-weight: 600; 
            letter-spacing: .2px; 
            cursor: pointer; 
            transition: transform .12s ease, box-shadow .2s ease, background-position .2s ease, opacity .2s ease; 
            box-shadow: 0 6px 16px rgba(0,0,0,.08);
        }

        .btn-primary{ 
            color:#fff; 
            background: linear-gradient(135deg, #2b6cb0 0%, #3182ce 50%, #2c5282 100%); 
            background-size: 200% 200%; 
            border-color:#2b6cb0; 
        }
        .btn-primary:hover{ 
            transform: translateY(-1px); 
            box-shadow: 0 10px 22px rgba(49,130,206,.35); 
            background-position: 100% 0; 
        }
        .btn-primary:active{ transform: translateY(0); box-shadow: 0 6px 14px rgba(0,0,0,.16); }
        .btn-primary:disabled{ opacity:.7; cursor:not-allowed; box-shadow:none; }

        .btn-secondary{ 
            color:#374151; 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-color:#e5e7eb; 
        }
        .btn-secondary:hover{ 
            transform: translateY(-1px); 
            box-shadow: 0 10px 22px rgba(0,0,0,.12); 
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%); 
        }
        .btn-secondary:active{ transform: translateY(0); box-shadow: 0 6px 14px rgba(0,0,0,.12); }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
        }

        select.form-control {
            cursor: pointer;
            appearance: auto;
        }

        select.form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        /* Time slot styles */
        .time-slot-option {
            padding: 8px;
        }

        .time-slot-option.available {
            color: #28a745;
        }

        .time-slot-option.unavailable {
            color: #dc3545;
            text-decoration: line-through;
            cursor: not-allowed;
        }

        .time-slot-option.partial {
            color: #ffc107;
        }

        /* Doctor availability styles */
        .availability-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .doctor-unavailable {
            color: #dc3545 !important;
            font-style: italic;
        }
        
        .doctor-unavailable::after {
            content: " (unavailable)";
            color: #dc3545;
            font-weight: bold;
        }
        
        .doctor-available {
            color: #28a745 !important;
        }
        
        .doctor-available::after {
            content: " (available)";
            color: #28a745;
            font-weight: bold;
        }

        /* Success modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            background: transparent;
        }

        .success-modal {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            width: 90%;
            max-width: 400px;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: modalSlideDown 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Update the animation to work with the new positioning */
        @keyframes modalSlideDown {
            from {
                top: 45%;
                opacity: 0;
            }
            to {
                top: 50%;
                opacity: 1;
            }
        }

        /* Update the success button style */
        .success-button {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .success-button:hover {
            background: #218838;
        }

        /* Added styles for appointment duration display */
        .duration-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #dee2e6;
        }

        .duration-label {
            font-weight: 500;
            color: #495057;
            margin-right: 5px;
        }

        /* Added styles for appointment price display */
        .price-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #dee2e6;
        }

        .price-label {
            font-weight: 500;
            color: #495057;
            margin-right: 5px;
        }

        /* Added styles for time selection info */
        .time-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }

        /* Time slot conflict warning */
        .conflict-warning {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        /* Doctor Dropdown Styles */
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    background-color: white;
    margin-bottom: 15px;
}

.form-group select:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
}

/* Duration Display */
.duration-display {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #e9ecef;
    margin-bottom: 15px;
}

.duration-label {
    font-weight: bold;
    color: #495057;
}

#appointmentDuration {
    font-weight: bold;
    color: #007bff;
    margin-left: 10px;
}

/* Doctor Info in Time Slots */
.time-slot-option {
    padding: 8px 12px;
    border-radius: 4px;
    margin: 2px 0;
}

.time-slot-option.available {
    background-color: #e8f5e8;
    border-left: 3px solid #28a745;
}

.time-slot-option.unavailable {
    background-color: #f8d7da;
    border-left: 3px solid #dc3545;
    color: #721c24;
    text-decoration: line-through;
}

/* Time Slot Doctor Info */
.slot-doctor-info {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

/* Modal Form Styling */
.modal-content .form-group {
    margin-bottom: 20px;
}

.modal-content label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-group select {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .duration-display {
        padding: 8px;
        font-size: 14px;
    }
}
        /* General entrance animations */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        .slide-in {
            animation: slideInRight 0.5s ease forwards;
        }

        .pop-in {
            animation: popIn 0.4s ease forwards;
        }

        .bounce-in {
            animation: bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        /* Interactive animations */
        .float-animation {
            animation: floatAnimation 3s ease-in-out infinite;
        }

        .pulse-on-hover:hover {
            animation: pulseAnimation 0.3s ease-in-out;
        }

        /* Shimmer effect for loading states */
        .shimmer {
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.8) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            background-size: 1000px 100%;
            animation: shimmer 2s infinite linear;
        }

        /* Button hover effects */
        .btn-hover-effect {
            transition: all 0.3s ease;
        }

        .btn-hover-effect:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Card hover effects */
        .card-hover-effect {
            transition: all 0.3s ease;
        }

        .card-hover-effect:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        /* Doctor Dropdown Styles */
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background-color: white;
            margin-bottom: 15px;
        }

        .form-group select:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }

        /* Duration Display */
        .duration-display {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
        }

        .duration-label {
            font-weight: bold;
            color: #495057;
        }

        #appointmentDuration {
            font-weight: bold;
            color: #007bff;
            margin-left: 10px;
        }
        /* Mobile Calendar List View Styles */
        .mobile-calendar-container {
            display: none;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin: 20px;
            padding: 20px;
            width: calc(100% - 40px);
            overflow: hidden;
        }

        .mobile-week-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .mobile-week-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        .mobile-week-nav {
            display: flex;
            gap: 10px;
        }

        .mobile-nav-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 16px;
            color: #6b7280;
        }

        .mobile-nav-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
        }

        .mobile-nav-btn:active {
            transform: scale(0.95);
        }

        .mobile-dates-container {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 10px 0;
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .mobile-dates-container::-webkit-scrollbar {
            display: none;
        }

        .mobile-dates-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding: 0 10px;
            max-width: 100%;
        }

        /* Alternative layout for 6 dates (2 rows) */
        .mobile-dates-row.six-dates {
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, 1fr);
        }

        /* Alternative layout for 9 dates (3 rows) */
        .mobile-dates-row.nine-dates {
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
        }

        .mobile-date-item {
            min-width: 60px;
            text-align: center;
            padding: 12px 8px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .mobile-date-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .mobile-date-item:active {
            transform: scale(0.95);
        }

        .mobile-date-day {
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .mobile-date-number {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        .mobile-date-item.available {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-color: #bbf7d0;
        }

        .mobile-date-item.available .mobile-date-number {
            color: #16a34a;
        }

        .mobile-date-item.unavailable {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: #fecaca;
        }

        .mobile-date-item.unavailable .mobile-date-number {
            color: #dc2626;
        }

        .mobile-date-item.today {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #93c5fd;
        }

        .mobile-date-item.today .mobile-date-number {
            color: #2563eb;
        }

        .mobile-date-item.selected {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-color: #f59e0b;
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }

        .mobile-date-item.selected .mobile-date-number {
            color: #d97706;
        }

        /* Loading animation for mobile calendar */
        .mobile-calendar-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 120px;
            color: #6b7280;
            font-size: 14px;
        }

        .mobile-calendar-loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid #e5e7eb;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Smooth transitions for week changes */
        .mobile-dates-row {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .mobile-dates-row.updating {
            opacity: 0.7;
            transform: translateX(10px);
        }

        .mobile-date-status {
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #9ca3af;
        }

        .mobile-date-item.available .mobile-date-status {
            background: #22c55e;
        }

        .mobile-date-item.unavailable .mobile-date-status {
            background: #ef4444;
        }

        .mobile-date-item.today .mobile-date-status {
            background: #3b82f6;
        }

        /* Mobile hamburger + centered layout (match index.php) */
        @media (max-width: 992px){
            .mobile-header{ display:flex !important; align-items:center; justify-content:space-between; padding:12px 16px; background:#fff; border-bottom:1px solid #eaeaea; position:sticky; top:0; z-index:1001; width:100%; box-sizing:border-box; max-width:560px; margin:0 auto; }
            .hamburger{ width:28px; height:22px; position:relative; cursor:pointer; }
            .hamburger span{ position:absolute; left:0; right:0; height:3px; background:#333; border-radius:2px; transition:.3s; }
            .hamburger span:nth-child(1){ top:0; }
            .hamburger span:nth-child(2){ top:9px; }
            .hamburger span:nth-child(3){ bottom:0; }
            .mobile-title{ font-weight:600; color:#161c2d; }
            .container{ height:auto; flex-direction:column; }
            .menu{ width:260px; height:100vh; position:fixed; top:0; left:-280px; background:#fff; z-index:1002; overflow-y:auto; transition:left .3s ease; box-shadow:2px 0 12px rgba(0,0,0,.06); }
            .menu.open{ left:0; }
            .dash-body{ width:100% !important; padding:15px; max-width:560px; margin:0 auto; box-sizing:border-box; }
            .overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1000; }
            .overlay.show{ display:block; }
            .calendar-container{ margin: 15px auto; max-width:560px; }
        }
        @media (max-width: 768px){
            html, body{ overflow-x:hidden; }
            .dashboard-container{ padding:15px; max-width:560px; margin:0 auto; }
            .calendar-container{ padding:15px; }
            
            /* Hide desktop calendar on mobile */
            .calendar-container {
                display: none !important;
            }
            
            /* Show mobile calendar */
            .mobile-calendar-container {
                display: block !important;
            }

            /* Mobile Modal Fixes */
            .modal-content {
                width: 95% !important;
                max-width: none !important;
                margin: 10px auto !important;
                padding: 15px !important;
                max-height: 90vh;
                overflow-y: auto;
            }

            .modal-content h2 {
                font-size: 1.4rem;
                margin-bottom: 20px;
                text-align: center;
            }

            /* Mobile Form Groups */
            .modal-content .form-group {
                margin-bottom: 20px;
            }

            .modal-content .form-group label {
                font-size: 14px;
                margin-bottom: 8px;
                font-weight: 600;
            }

            .modal-content .form-control {
                padding: 12px;
                font-size: 16px;
                border-radius: 8px;
                border: 2px solid #e5e7eb;
            }

            .modal-content .form-control:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            /* Mobile ID Images Container - Stack vertically */
            .id-images-container {
                display: flex !important;
                flex-direction: column !important;
                gap: 20px !important;
            }

            .id-image-front,
            .id-image-back {
                width: 100% !important;
                padding: 15px;
                border: 2px solid #e5e7eb;
                border-radius: 12px;
                background: #f9fafb;
            }

            .id-image-front label,
            .id-image-back label {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                margin-bottom: 10px;
                line-height: 1.4;
            }

            /* Mobile File Input Styling */
            .id-image-front input[type="file"],
            .id-image-back input[type="file"] {
                width: 100%;
                padding: 12px;
                border: 2px dashed #d1d5db;
                border-radius: 8px;
                background: white;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .id-image-front input[type="file"]:hover,
            .id-image-back input[type="file"]:hover {
                border-color: #3b82f6;
                background: #f0f9ff;
            }

            .id-image-front small,
            .id-image-back small {
                display: block;
                margin-top: 8px;
                font-size: 12px;
                color: #6b7280;
                line-height: 1.3;
            }

            /* Mobile Image Preview */
            .image-preview {
                width: 100% !important;
                height: 120px !important;
                object-fit: cover;
                border-radius: 8px;
                margin-top: 10px;
                border: 2px solid #e5e7eb;
            }

            /* Mobile Button Container */
            .btn-container {
                flex-direction: column !important;
                gap: 12px !important;
                margin-top: 25px;
                padding: 20px 0 10px;
            }

            .btn-primary,
            .btn-secondary {
                width: 100% !important;
                padding: 14px 20px !important;
                font-size: 16px !important;
                border-radius: 12px !important;
                font-weight: 600 !important;
            }

            /* Mobile Duration Display */
            .duration-display {
                padding: 15px;
                border-radius: 8px;
                background: #f0f9ff;
                border: 2px solid #e0f2fe;
            }

            .duration-label {
                font-size: 14px;
                color: #0369a1;
            }

            #appointmentDuration {
                font-size: 16px;
                color: #0284c7;
            }

            /* Mobile Time Info */
            .time-info {
                padding: 12px;
                border-radius: 8px;
                font-size: 14px;
                line-height: 1.4;
            }

            /* Mobile Conflict Warning */
            .conflict-warning {
                font-size: 13px;
                margin-top: 8px;
                padding: 8px;
                border-radius: 6px;
                background: #fef2f2;
                color: #dc2626;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-header" style="display:none">
        <div class="hamburger" id="hamburger" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="mobile-title">Book Appointment</div>
        <div style="width:28px;height:22px"></div>
    </div>
    <div class="overlay" id="overlay" style="display:none"></div>
    <div class="container">
    <div class="menu" id="sidebar">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px" >
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title"><?php echo htmlspecialchars($username) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php" ><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-home">
                    <a href="index.php" class="non-style-link-menu">
                        <div><p class="menu-text">Home</p></div>
                    </a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-session menu-active menu-icon-session-active">
                    <a href="schedule.php" class="non-style-link-menu-active">
                        <div><p class="menu-text">Book Appointment</p></div>
                    </a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu">
                        <div><p class="menu-text">My Bookings</p></div>
                    </a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-settings">
                    <a href="settings.php" class="non-style-link-menu">
                        <div><p class="menu-text">Settings</p></div>
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <div class="dash-body" id="content">
        <!-- The rest of your schedule content stays here -->
        <?php if (!$can_book): ?>
        <div class="booking-restriction warning">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <span><?php echo $booking_reason; ?></span>
            <button class="close-banner" aria-label="Dismiss" onclick="this.parentElement.remove()">✕</button>
        </div>
        <?php endif; ?>
        
        <!-- Calendar container -->
        <div class="dashboard-container">
            <div class="calendar-container fade-in">
                <div class="calendar-note">
                    <span class="dot"></span>
                    <span>Select an available date to book an appointment.</span>
                </div>
                <div id="calendar"></div>
            </div>
        </div>

        <!-- Mobile Calendar List View -->
        <div class="mobile-calendar-container fade-in">
            <div class="calendar-note">
                <span class="dot"></span>
                <span>Select an available date to book an appointment.</span>
            </div>
            
            <div class="mobile-week-header">
                <div class="mobile-week-title" id="mobileWeekTitle">Loading...</div>
                <div class="mobile-week-nav">
                    <button class="mobile-nav-btn" id="viewToggleBtn" aria-label="Toggle view" title="Switch between 3, 6, or 9 dates">⧉</button>
                    <button class="mobile-nav-btn" id="prevWeekBtn" aria-label="Previous week">‹</button>
                    <button class="mobile-nav-btn" id="nextWeekBtn" aria-label="Next week">›</button>
                </div>
            </div>
            
            <div class="mobile-dates-container">
                <div class="mobile-dates-row" id="mobileDatesRow">
                    <!-- Mobile dates will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <h2>Book Appointment</h2>
            <!-- Booking form -->
            <form id="bookingForm" class="slide-in">
                <input type="hidden" id="selectedDate" name="selectedDate">

                <!-- FIXED: Doctor Selection Dropdown - Now shows all doctors with availability status -->
                <div class="form-group">
                    <label for="selectedDoctor">Select Doctor</label>
                    <select id="selectedDoctor" name="selectedDoctor" class="form-control" required>
                        <option value="">Select a Doctor</option>
                        <?php if (count($doctors) > 0): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['docid']; ?>" data-doctor-name="<?php echo htmlspecialchars($doctor['docname']); ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['docname']); ?> 
                                    <?php if (!empty($doctor['specialty_name'])): ?>
                                        - <?php echo htmlspecialchars($doctor['specialty_name']); ?>
                                    <?php else: ?>
                                        - General Practice
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No doctors available</option>
                        <?php endif; ?>
                    </select>
                    <div id="doctorAvailabilityInfo" class="availability-info" style="display: none; margin-top: 5px; font-size: 12px; color: #666;"></div>
                </div>
                
                <div class="form-group">
                    <label for="appointmentTime">Select Time</label>
                    <select id="appointmentTime" name="appointmentTime" class="form-control" required onchange="checkTimeAvailability()">
                        <option value="">Select a time slot</option>
                        <?php foreach ($timeSlots as $timeValue => $timeLabel): ?>
                            <option value="<?php echo $timeValue; ?>"><?php echo $timeLabel; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="timeAvailabilityInfo" class="time-info" style="display: none;"></div>
                    <div id="conflictWarning" class="conflict-warning"></div>
                </div>
                
                <div class="form-group">
                    <label for="appointmentType">Type of Appointment</label>
                    <select id="appointmentType" name="appointmentType" class="form-control" required onchange="updateDurationAndCheckAvailability()">
                        <option value="">Select Appointment Type</option>
                        <!-- Services will be loaded dynamically -->
                    </select>
                </div>

                <div class="form-group">
                    <div class="duration-display">
                        <span class="duration-label">Duration mins:</span>
                        <span id="appointmentDuration">Not selected</span>
                        <input type="hidden" name="appointmentDuration" id="appointmentDurationInput">
                    </div>
                </div>

                
                
                <div class="form-group">
                    <label for="idType">Type of ID Presented</label>
                    <select id="idType" name="idType" class="form-control" required>
                        <option value="">Select ID Type</option>
                        <option value="passport">Passport</option>
                        <option value="drivers">Driver's License</option>
                        <option value="postal">Postal ID</option>
                        <option value="national">National ID (PhilSys)</option>
                        <option value="voters">Voter's ID</option>
                        <option value="sss">SSS ID</option>
                        <option value="philhealth">PhilHealth ID</option>
                        <option value="tin">TIN ID</option>
                        <option value="prc">PRC ID</option>
                        <option value="school">School ID</option>
                        <option value="company">Company ID</option>
                        <option value="others">Others</option>
                    </select>
                </div>

                <?php if ($isEligibleForDiscount): ?>
                <div class="form-group">
                    <div style="background: linear-gradient(135deg, #FF6B6B 0%, #FF8E8E 100%); color: white; padding: 15px; border-radius: 8px; text-align: center; margin: 10px 0; box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);">
                        <div style="font-size: 1.2rem; font-weight: 600; margin-bottom: 5px;">
                            🎉 20% discount for senior citizens
                        </div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">
                            You're eligible for a senior citizen discount (age <?php echo $age; ?>)
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>More Informations.</label>
                    <div class="id-images-container">
                        <div class="id-image-front">
                            <label for="id_image_holding">Image of you holding the ID</label>
                            <input type="file" 
                                   id="id_image_holding" 
                                   name="id_image_holding" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif" 
                                   required 
                                   onchange="previewImage(this, 'previewFront')">
                            <small>Maximum file size: 5MB (JPEG, PNG, GIF)</small>
                            <img id="previewFront" class="image-preview" src="#" alt="Front Preview" style="display: none;">
                        </div>
                        <div class="id-image-back">
                            <label for="id_image_only">Just the ID</label>
                            <input type="file" 
                                   id="id_image_only" 
                                   name="id_image_only" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif" 
                                   required 
                                   onchange="previewImage(this, 'previewBack')">
                            <small>Maximum file size: 5MB (JPEG, PNG, GIF)</small>
                            <img id="previewBack" class="image-preview" src="#" alt="Back Preview" style="display: none;">
                        </div>
                    </div>
                </div>

                <div class="btn-container">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitButton" <?php echo !$can_book ? 'disabled' : ''; ?>>
                        <?php echo $can_book ? 'Book Now' : 'Booking Restricted'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        // Define time slots (same as PHP array)
        const timeSlots = {
            '07:00:00': '7:00 AM',
            '07:30:00': '7:30 AM',
            '08:00:00': '8:00 AM',
            '08:30:00': '8:30 AM',
            '09:00:00': '9:00 AM',
            '09:30:00': '9:30 AM',
            '10:00:00': '10:00 AM',
            '10:30:00': '10:30 AM',
            '11:00:00': '11:00 AM',
            '11:30:00': '11:30 AM',
            '12:00:00': '12:00 PM',
            '12:30:00': '12:30 PM',
            '13:00:00': '1:00 PM',
            '13:30:00': '1:30 PM',
            '14:00:00': '2:00 PM',
            '14:30:00': '2:30 PM',
            '15:00:00': '3:00 PM',
            '15:30:00': '3:30 PM',
            '16:00:00': '4:00 PM',
            '16:30:00': '4:30 PM',
            '17:00:00': '5:00 PM'
        };

        // Appointment durations in minutes (using maximum duration for blocking)
        const appointmentDurations = {
            'examination': { min: 30, max: 45 },
            'cataract_screening': { min: 30, max: 45 },
            'cataract_surgery': { min: 60, max: 120, recovery: 180 },
            'cornea': { min: 30, max: 60 },
            'lasik_screening': { min: 60, max: 60 },
            'pediatric': { min: 30, max: 45 },
            'colorvision': { min: 10, max: 15 },
            'emergency': { min: 30, max: 60 },
            'followup': { min: 15, max: 30 }
        };

        // Store booked time slots (only approved appointments that are not done)
        let bookedSlots = [];
        const canBook = <?php echo $can_book ? 'true' : 'false'; ?>;
        
        // Store services data
        let servicesData = {};

        // Load services from database
        function loadServices() {
            fetch('get-services.php')
                .then(response => response.json())
                .then(services => {
                    const appointmentTypeSelect = document.getElementById('appointmentType');
                    
                    // Clear existing options except the first one
                    appointmentTypeSelect.innerHTML = '<option value="">Select Appointment Type</option>';
                    
                    // Store services data for duration calculation
                    servicesData = {};
                    
                    // Add service options
                    services.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service.service_code;
                        option.textContent = service.service_name;
                        option.setAttribute('data-price', service.price);
                        option.setAttribute('data-duration', service.duration_minutes);
                        appointmentTypeSelect.appendChild(option);
                        
                        // Store service data
                        servicesData[service.service_code] = {
                            duration: service.duration_minutes,
                            price: service.price,
                            name: service.service_name
                        };
                    });
                })
                .catch(error => {
                    console.error('Error loading services:', error);
                    // Fallback to hardcoded services if API fails
                    loadFallbackServices();
                });
        }
        
        // Fallback services if API fails
        function loadFallbackServices() {
            const appointmentTypeSelect = document.getElementById('appointmentType');
            appointmentTypeSelect.innerHTML = `
                <option value="">Select Appointment Type</option>
                <option value="examination">Eye Examination</option>
                <option value="cataract_screening">Cataract Screening</option>
                <option value="cataract_surgery">Cataract Surgery</option>
                <option value="cornea">External Eye Disease & Cornea Treatment</option>
                <option value="lasik_screening">Refractive Surgery Pre-screening</option>
                <option value="pediatric">Pediatric Eye Care</option>
                <option value="colorvision">Color Vision Testing</option>
                <option value="emergency">Eye Emergencies / Foreign Body Removal</option>
                <option value="followup">Follow-up & Post-operative Care</option>
            `;
            
            // Use hardcoded durations as fallback
            servicesData = appointmentDurations;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Load services first
            loadServices();
            
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                selectable: canBook, // Disable selection if cannot book
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                // Show days from other months
                showNonCurrentDates: true,
                fixedWeekCount: false,
                dayCellContent: function(arg) {
                    // Check if this is a day from another month
                    const isOtherMonth = arg.date.getMonth() !== calendar.getDate().getMonth();
                    
                    return {
                        html: `
                            <div style="text-align: right; font-size: 1.2em; margin-bottom: 5px;">
                                ${arg.dayNumberText}
                            </div>
                            <div style="font-size: 0.9em; color: ${isOtherMonth ? '#ccc' : '#dc3545'};" class="session-info">
                                <!-- Empty div for session info to be populated later -->
                            </div>
                        `
                    };
                },
                eventDidMount: function(info) {
                    // Hide the blue time slot boxes by making them invisible
                    info.el.style.display = 'none';
                    
                    const sessionInfo = info.el.closest('.fc-daygrid-day').querySelector('.session-info');
                    if (sessionInfo) {
                        const available = info.event.extendedProps.available;
                        const total = info.event.extendedProps.total;
                        
                        if (available > 0) {
                            sessionInfo.innerHTML = `
                                <span style="color: #28a745; font-weight: bold;">Available: ${available}/${total}</span>
                            `;
                        } else {
                            sessionInfo.innerHTML = `
                                <span style="color: #dc3545; font-weight: bold;">Full</span>
                            `;
                        }
                    }
                },
                dateClick: function(info) {
                    if (!canBook) {
                        alert('<?php echo addslashes($booking_reason); ?>');
                        return;
                    }
                    
                    const clickedDate = info.date;
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    // Check if the clicked date is from another month
                    const isOtherMonth = clickedDate.getMonth() !== calendar.getDate().getMonth();
                    
                    // Get the session info element
                    const sessionInfo = info.dayEl.querySelector('.session-info');

                    // Compute total availability for this date from calendar events
                    const events = calendar.getEvents();
                    const dateStr = info.dateStr; // YYYY-MM-DD (local)
                    let totalAvailableForDate = 0;
                    // Helper to format a Date to local YYYY-MM-DD without timezone shift
                    function toLocalYMD(d){
                        const y = d.getFullYear();
                        const m = String(d.getMonth()+1).padStart(2,'0');
                        const day = String(d.getDate()).padStart(2,'0');
                        return `${y}-${m}-${day}`;
                    }
                    events.forEach(function(ev){
                        if (!ev.start) return;
                        const evDateLocal = toLocalYMD(ev.start);
                        if (evDateLocal === dateStr && typeof ev.extendedProps?.available === 'number') {
                            totalAvailableForDate += ev.extendedProps.available;
                        }
                    });
                    
                    // Only allow booking if there's a session, it's not "No sessions" or "Full",
                    // it's not from another month, it's not in the past, and availability > 0
                    if (!isOtherMonth && sessionInfo && 
                        !sessionInfo.innerHTML.includes('No sessions') && 
                        !sessionInfo.innerHTML.includes('Full') && 
                        clickedDate >= today && totalAvailableForDate > 0) {
                        // Fetch booked slots for this date (only approved appointments that are not done)
                        fetchBookedSlots(info.dateStr).then(() => {
                            openBookingModal(info.dateStr);
                        });
                    } else if (totalAvailableForDate <= 0 || sessionInfo.innerHTML.includes('Full')) {
                        alert('No available slots for this date. Please choose another date.');
                    }
                }
            });

            // Add the session events
            fetch('get-sessions.php')
                .then(response => response.json())
                .then(sessions => {
                    sessions.forEach(session => {
                        calendar.addEvent({
                            title: session.time,
                            start: session.date,
                            available: session.available,
                            total: session.total
                        });
                    });
                });

            calendar.render();
        });

        // Mobile Calendar Implementation
        let currentWeekStart = new Date();
        let mobileAvailabilityData = {};
        let currentViewMode = 1; // 1 = 3 dates, 2 = 6 dates, 3 = 9 dates

        // Initialize mobile calendar
        function initMobileCalendar() {
            // Set current week start to Monday
            const today = new Date();
            const dayOfWeek = today.getDay();
            const daysToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
            currentWeekStart = new Date(today);
            currentWeekStart.setDate(today.getDate() + daysToMonday);
            currentWeekStart.setHours(0, 0, 0, 0);

            // Load availability data
            loadMobileAvailabilityData();
            
            // Initialize view toggle button
            updateViewToggleButton();
            
            // Render mobile calendar
            renderMobileCalendar();
            
            // Add event listeners
            document.getElementById('prevWeekBtn').addEventListener('click', () => {
                currentWeekStart.setDate(currentWeekStart.getDate() - 7);
                renderMobileCalendar();
            });
            
            document.getElementById('nextWeekBtn').addEventListener('click', () => {
                currentWeekStart.setDate(currentWeekStart.getDate() + 7);
                renderMobileCalendar();
            });

            // Add view toggle functionality
            document.getElementById('viewToggleBtn').addEventListener('click', () => {
                currentViewMode = (currentViewMode % 3) + 1; // Cycle through 1, 2, 3
                updateViewToggleButton();
                renderMobileCalendar();
            });

            // Add touch gesture support for swiping
            addTouchGestures();
        }

        // Add touch gesture support for swiping between weeks
        function addTouchGestures() {
            const datesContainer = document.querySelector('.mobile-dates-container');
            let startX = 0;
            let startY = 0;
            let isScrolling = false;

            datesContainer.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                isScrolling = false;
            }, { passive: true });

            datesContainer.addEventListener('touchmove', (e) => {
                if (!startX || !startY) return;

                const currentX = e.touches[0].clientX;
                const currentY = e.touches[0].clientY;
                const diffX = Math.abs(currentX - startX);
                const diffY = Math.abs(currentY - startY);

                // Determine if this is a horizontal swipe
                if (diffX > diffY && diffX > 50) {
                    isScrolling = true;
                    e.preventDefault();
                }
            }, { passive: false });

            datesContainer.addEventListener('touchend', (e) => {
                if (!startX || !startY || !isScrolling) return;

                const endX = e.changedTouches[0].clientX;
                const diffX = startX - endX;

                // Swipe threshold
                if (Math.abs(diffX) > 50) {
                    if (diffX > 0) {
                        // Swipe left - next week
                        currentWeekStart.setDate(currentWeekStart.getDate() + 7);
                        renderMobileCalendar();
                    } else {
                        // Swipe right - previous week
                        currentWeekStart.setDate(currentWeekStart.getDate() - 7);
                        renderMobileCalendar();
                    }
                }

                startX = 0;
                startY = 0;
                isScrolling = false;
            }, { passive: true });
        }

        // Load availability data for mobile calendar
        function loadMobileAvailabilityData() {
            fetch('get-sessions.php')
                .then(response => response.json())
                .then(sessions => {
                    mobileAvailabilityData = {};
                    sessions.forEach(session => {
                        const dateStr = session.date;
                        if (!mobileAvailabilityData[dateStr]) {
                            mobileAvailabilityData[dateStr] = {
                                available: 0,
                                total: 0
                            };
                        }
                        mobileAvailabilityData[dateStr].available += session.available;
                        mobileAvailabilityData[dateStr].total += session.total;
                    });
                    renderMobileCalendar();
                })
                .catch(error => {
                    console.error('Error loading mobile availability data:', error);
                });
        }

        // Update view toggle button
        function updateViewToggleButton() {
            const toggleBtn = document.getElementById('viewToggleBtn');
            const icons = ['⧉', '⧈', '⧇']; // Different grid icons
            const titles = ['3 dates', '6 dates', '9 dates'];
            toggleBtn.textContent = icons[currentViewMode - 1];
            toggleBtn.title = `Switch to ${titles[currentViewMode - 1]} view`;
        }

        // Render mobile calendar
        function renderMobileCalendar() {
            const weekTitle = document.getElementById('mobileWeekTitle');
            const datesRow = document.getElementById('mobileDatesRow');
            
            // Show loading state
            datesRow.classList.add('updating');
            
            // Update week title based on view mode
            const daysToShow = currentViewMode * 3; // 3, 6, or 9 days
            const weekEnd = new Date(currentWeekStart);
            weekEnd.setDate(currentWeekStart.getDate() + daysToShow - 1);
            
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                              'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            if (currentWeekStart.getMonth() === weekEnd.getMonth()) {
                weekTitle.textContent = `${monthNames[currentWeekStart.getMonth()]} ${currentWeekStart.getDate()} – ${weekEnd.getDate()}`;
            } else {
                weekTitle.textContent = `${monthNames[currentWeekStart.getMonth()]} ${currentWeekStart.getDate()} – ${monthNames[weekEnd.getMonth()]} ${weekEnd.getDate()}`;
            }
            
            // Update grid layout classes
            datesRow.className = 'mobile-dates-row';
            if (currentViewMode === 2) {
                datesRow.classList.add('six-dates');
            } else if (currentViewMode === 3) {
                datesRow.classList.add('nine-dates');
            }
            
            // Clear existing dates
            datesRow.innerHTML = '';
            
            // Generate dates based on current view mode
            const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let i = 0; i < daysToShow; i++) {
                const date = new Date(currentWeekStart);
                date.setDate(currentWeekStart.getDate() + i);
                
                const dateStr = date.toISOString().split('T')[0];
                const dayName = dayNames[i % 7]; // Cycle through day names
                const dayNumber = date.getDate();
                
                // Determine availability status
                const availability = mobileAvailabilityData[dateStr] || { available: 0, total: 0 };
                const isAvailable = availability.available > 0;
                const isToday = date.getTime() === today.getTime();
                const isPast = date < today;
                
                // Create date item
                const dateItem = document.createElement('div');
                dateItem.className = 'mobile-date-item';
                dateItem.setAttribute('data-date', dateStr);
                
                // Add status classes
                if (isToday) {
                    dateItem.classList.add('today');
                } else if (isPast || !isAvailable) {
                    dateItem.classList.add('unavailable');
                } else {
                    dateItem.classList.add('available');
                }
                
                // Add click handler
                dateItem.addEventListener('click', () => {
                    if (!canBook) {
                        alert('<?php echo addslashes($booking_reason); ?>');
                        return;
                    }
                    
                    if (isPast || !isAvailable) {
                        alert('No available slots for this date. Please choose another date.');
                        return;
                    }
                    
                    // Remove previous selection
                    document.querySelectorAll('.mobile-date-item.selected').forEach(item => {
                        item.classList.remove('selected');
                    });
                    
                    // Add selection to clicked item
                    dateItem.classList.add('selected');
                    
                    // Open booking modal
                    fetchBookedSlots(dateStr).then(() => {
                        openBookingModal(dateStr);
                    });
                });
                
                // Add content
                dateItem.innerHTML = `
                    <div class="mobile-date-day">${dayName}</div>
                    <div class="mobile-date-number">${dayNumber}</div>
                    <div class="mobile-date-status"></div>
                `;
                
                datesRow.appendChild(dateItem);
            }
            
            // Remove loading state after a short delay
            setTimeout(() => {
                datesRow.classList.remove('updating');
            }, 200);
        }

        // Initialize mobile calendar when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Only initialize mobile calendar on mobile devices
            if (window.innerWidth <= 768) {
                initMobileCalendar();
            }
            
            // Re-initialize on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    if (!document.querySelector('.mobile-calendar-container').hasAttribute('data-initialized')) {
                        initMobileCalendar();
                        document.querySelector('.mobile-calendar-container').setAttribute('data-initialized', 'true');
                    }
                }
            });
        });

        function fetchBookedSlots(date) {
            return fetch('get-booked-slots.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'date=' + encodeURIComponent(date)
            })
            .then(response => response.json())
            .then(data => {
                bookedSlots = data.bookedSlots || [];
                updateTimeDropdown();
            })
            .catch(error => {
                console.error('Error fetching booked slots:', error);
                bookedSlots = [];
            });
        }

        function updateDoctorAvailability(selectedDate) {
            const doctorDropdown = document.getElementById('selectedDoctor');
            const availabilityInfo = document.getElementById('doctorAvailabilityInfo');
            
            // Show loading state
            availabilityInfo.style.display = 'block';
            availabilityInfo.innerHTML = 'Checking doctor availability...';
            
            // Fetch doctor availability for the selected date
            const formData = new FormData();
            formData.append('date', selectedDate);
            
            fetch('get-doctor-availability.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update doctor options with availability status
                    const options = doctorDropdown.querySelectorAll('option[value]');
                    let availableCount = 0;
                    let unavailableCount = 0;
                    
                    options.forEach(option => {
                        const doctorId = option.value;
                        const doctorData = data.doctor_availability.find(d => d.doctor_id === doctorId);
                        
                        if (doctorData) {
                            // Remove existing availability classes
                            option.classList.remove('doctor-available', 'doctor-unavailable');
                            
                            if (doctorData.is_available) {
                                option.classList.add('doctor-available');
                                availableCount++;
                            } else {
                                option.classList.add('doctor-unavailable');
                                unavailableCount++;
                            }
                        }
                    });
                    
                    // Update availability info
                    if (availableCount > 0 && unavailableCount > 0) {
                        availabilityInfo.innerHTML = `${availableCount} doctor(s) available, ${unavailableCount} unavailable`;
                    } else if (availableCount > 0) {
                        availabilityInfo.innerHTML = `All ${availableCount} doctor(s) are available`;
                    } else {
                        availabilityInfo.innerHTML = 'No doctors available for this date';
                    }
                } else {
                    availabilityInfo.innerHTML = 'Error checking doctor availability';
                }
            })
            .catch(error => {
                console.error('Error fetching doctor availability:', error);
                availabilityInfo.innerHTML = 'Error checking doctor availability';
            });
        }

        function updateTimeDropdown(selectedDate) {
            const timeDropdown = document.getElementById('appointmentTime');
            const selectedDoctor = document.getElementById('selectedDoctor').value;
            timeDropdown.innerHTML = '<option value="">Select a time slot</option>';
            
            // Prepare form data with both date and doctor
            const formData = new FormData();
            formData.append('date', selectedDate);
            if (selectedDoctor) {
                formData.append('doctor_id', selectedDoctor);
            }
            
            // Fetch available slots from server with session time filtering
            fetch('get-session-slots.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.slots.length > 0) {
                    data.slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.value;
                        option.setAttribute('data-session-id', slot.session_id);
                        option.textContent = `${slot.doctor_name} - ${slot.display} (${slot.specialty})`;
                        timeDropdown.appendChild(option);
                    });
                } else {
                    const option = document.createElement('option');
                    option.value = "";
                    option.textContent = "No available slots for this date and doctor";
                    timeDropdown.appendChild(option);
                }
            })
            .catch(error => {
                console.error('Error fetching time slots:', error);
                timeDropdown.innerHTML = '<option value="">Error loading time slots</option>';
            });
        }

        // Add event listener to refresh time slots when doctor changes
        document.getElementById('selectedDoctor').addEventListener('change', function() {
            const selectedDate = document.getElementById('selectedDate').value;
            if (selectedDate) {
                updateTimeDropdown(selectedDate);
            }
        });

        // Renamed to avoid overriding the slot-fetching function above
        function updateTimeDropdownAvailability() {
            const timeSelect = document.getElementById('appointmentTime');
            const selectedTime = timeSelect.value;
            
            // Clear existing options except the first one
            while (timeSelect.options.length > 1) {
                timeSelect.remove(1);
            }
            
            // Add time slots with availability status
            Object.keys(timeSlots).forEach(timeValue => {
                const option = document.createElement('option');
                option.value = timeValue;
                option.textContent = timeSlots[timeValue];
                
                // Check if this time slot is available
                if (isTimeSlotAvailable(timeValue)) {
                    option.className = 'time-slot-option available';
                } else {
                    option.className = 'time-slot-option unavailable';
                    option.disabled = true;
                }
                
                timeSelect.appendChild(option);
            });
            
            // Restore selected time if it's still available
            if (selectedTime && isTimeSlotAvailable(selectedTime)) {
                timeSelect.value = selectedTime;
            } else {
                timeSelect.value = '';
                document.getElementById('timeAvailabilityInfo').style.display = 'none';
            }
            
            checkTimeAvailability();
        }

        function isTimeSlotAvailable(selectedTime) {
            const selectedType = document.getElementById('appointmentType').value;
            
            // If no type selected, we can't check duration-based conflicts
            if (!selectedType) {
                return true;
            }
            
            const duration = getAppointmentDuration(selectedType);
            const selectedDateTime = new Date(document.getElementById('selectedDate').value + ' ' + selectedTime);
            const endTime = new Date(selectedDateTime.getTime() + duration * 60000);
            
            // Check if the selected time slot conflicts with any booked slots
            for (const bookedSlot of bookedSlots) {
                const bookedStart = new Date(document.getElementById('selectedDate').value + ' ' + bookedSlot.start_time);
                const bookedEnd = new Date(document.getElementById('selectedDate').value + ' ' + bookedSlot.end_time);
                
                // Check for overlap
                if (selectedDateTime < bookedEnd && endTime > bookedStart) {
                    return false;
                }
            }
            
            return true;
        }

        function getAppointmentDuration(appointmentType) {
            // Check if we have service data from database
            if (servicesData[appointmentType]) {
                return servicesData[appointmentType].duration;
            }
            
            // Fallback to hardcoded durations
            const durations = appointmentDurations[appointmentType];
            if (!durations) return 30; // Default to 30 minutes
            
            // Use the maximum duration for blocking purposes
            return durations.max || 30;
        }

        function checkTimeAvailability() {
            const selectedTime = document.getElementById('appointmentTime').value;
            const selectedType = document.getElementById('appointmentType').value;
            const infoDiv = document.getElementById('timeAvailabilityInfo');
            const warningDiv = document.getElementById('conflictWarning');
            
            if (!selectedTime) {
                infoDiv.style.display = 'none';
                warningDiv.style.display = 'none';
                return;
            }
            
            if (!selectedType) {
                infoDiv.style.display = 'block';
                infoDiv.innerHTML = 'Please select an appointment type first to check availability.';
                warningDiv.style.display = 'none';
                return;
            }
            
            const duration = getAppointmentDuration(selectedType);
            const selectedDateTime = new Date(document.getElementById('selectedDate').value + ' ' + selectedTime);
            const endTime = new Date(selectedDateTime.getTime() + duration * 60000);
            
            // Check for conflicts
            let hasConflict = false;
            let conflictDetails = '';
            
            for (const bookedSlot of bookedSlots) {
                const bookedStart = new Date(document.getElementById('selectedDate').value + ' ' + bookedSlot.start_time);
                const bookedEnd = new Date(document.getElementById('selectedDate').value + ' ' + bookedSlot.end_time);
                
                if (selectedDateTime < bookedEnd && endTime > bookedStart) {
                    hasConflict = true;
                    conflictDetails = `Conflicts with existing appointment from ${bookedSlot.start_time} to ${bookedSlot.end_time}`;
                    break;
                }
            }
            
            // Helper to format to 12-hour time
            function formatTime12h(input) {
                let d;
                if (input instanceof Date) {
                    d = input;
                } else if (typeof input === 'string') {
                    const parts = input.split(':');
                    d = new Date();
                    d.setHours(parseInt(parts[0] || '0', 10), parseInt(parts[1] || '0', 10), 0, 0);
                } else {
                    d = new Date(input);
                }
                let h = d.getHours();
                const m = d.getMinutes();
                const ampm = h >= 12 ? 'AM' : 'PM';
                h = h % 12; if (h === 0) h = 12;
                return `${h}:${String(m).padStart(2,'0')} ${ampm}`;
            }

            if (hasConflict) {
                infoDiv.style.display = 'block';
                infoDiv.innerHTML = `Selected time slot is not available. ${conflictDetails}`;
                infoDiv.style.backgroundColor = '#f8d7da';
                infoDiv.style.color = '#721c24';
                warningDiv.style.display = 'block';
                warningDiv.textContent = conflictDetails;
                document.getElementById('submitButton').disabled = true;
            } else {
                infoDiv.style.display = 'block';
                infoDiv.innerHTML = `Time slot is available. Appointment will run from ${formatTime12h(selectedTime)} to ${formatTime12h(endTime)}`;
                infoDiv.style.backgroundColor = '#d4edda';
                infoDiv.style.color = '#155724';
                warningDiv.style.display = 'none';
                document.getElementById('submitButton').disabled = false;
            }
        }

        function updateDurationAndCheckAvailability() {
            const selectedType = document.getElementById('appointmentType').value;
            const durationDisplay = document.getElementById('appointmentDuration');
            const durationInput = document.getElementById('appointmentDurationInput');
            
            if (selectedType) {
                // Check if we have service data from database
                if (servicesData[selectedType]) {
                    const service = servicesData[selectedType];
                    durationDisplay.textContent = `${service.duration} minutes`;
                    durationInput.value = service.duration;
                } else {
                    // Fallback to hardcoded durations
                    const durations = appointmentDurations[selectedType];
                    if (durations) {
                        durationDisplay.textContent = `${durations.min} - ${durations.max} minutes`;
                        durationInput.value = durations.max;
                    }
                }
            } else {
                durationDisplay.textContent = 'Not selected';
                durationInput.value = '';
            }
            
            // Recheck availability when type changes
            checkTimeAvailability();
        }

        function openBookingModal(date) {
            const modal = document.getElementById('bookingModal');
            modal.style.display = "block";
            
            // Set the selected date
            document.getElementById('selectedDate').value = date;
            
            // Update doctor availability and time slots based on selected date
            updateDoctorAvailability(date);
            updateTimeDropdown(date);
        }

        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                // Check file size (5MB = 5 * 1024 * 1024 bytes)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Check file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF)');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        // Handle form submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!canBook) {
                alert('<?php echo addslashes($booking_reason); ?>');
                return;
            }
            
            const formData = new FormData(this);
            
            // Show loading state
            const submitButton = document.getElementById('submitButton');
            submitButton.disabled = true;
            submitButton.textContent = 'Booking...';
            
            fetch('book-appointment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(data.message);
                } else {
                    alert('Error: ' + data.message);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Book Now';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while booking the appointment. Please try again.');
                submitButton.disabled = false;
                submitButton.textContent = 'Book Now';
            });
        });

        function showSuccessModal(message) {
            // Create success modal overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;
            
            // Create success modal content
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 90%;
            `;
            
            modal.innerHTML = `
                <h2 style="color: #28a745; margin-bottom: 15px;">Success!</h2>
                <p style="margin-bottom: 20px;">${message}</p>
                <button onclick="closeSuccessModal()" style="
                    background: #28a745;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                ">OK</button>
            `;
            
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Store overlay reference for later removal
            window.successOverlay = overlay;
        }

        function closeSuccessModal() {
            if (window.successOverlay) {
                document.body.removeChild(window.successOverlay);
                window.successOverlay = null;
            }
            closeModal();
            location.reload(); // Refresh the page to update the calendar
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
<script>
// Ensure content visible after nav on mobile
(function(){
  function scrollToContent(){
    var el=document.getElementById('content');
    if(!el) return; if (window.matchMedia('(max-width: 768px)').matches){ el.scrollIntoView({behavior:'smooth',block:'start'});} }
  window.addEventListener('load',scrollToContent);
  document.querySelectorAll('.menu a').forEach(function(a){a.addEventListener('click',function(){setTimeout(scrollToContent,150);});});
})();
// Mobile hamburger toggle (match index.php)
(function(){
  var header=document.querySelector('.mobile-header');
  var hamburger=document.getElementById('hamburger');
  var sidebar=document.getElementById('sidebar');
  var overlay=document.getElementById('overlay');
  function syncHeader(){
    if (window.matchMedia('(max-width: 992px)').matches){ if(header) header.style.display='flex'; if(sidebar) sidebar.classList.remove('open'); if(overlay){ overlay.classList.remove('show'); overlay.style.display='none'; } document.body.style.overflow=''; }
    else { if(header) header.style.display='none'; if(sidebar) sidebar.classList.remove('open'); if(overlay){ overlay.classList.remove('show'); overlay.style.display='none'; } }
  }
  function openMenu(){ if(!sidebar) return; sidebar.classList.add('open'); if(overlay){ overlay.classList.add('show'); overlay.style.display='block'; } if(hamburger) hamburger.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
  function closeMenu(){ if(!sidebar) return; sidebar.classList.remove('open'); if(overlay){ overlay.classList.remove('show'); overlay.style.display='none'; } if(hamburger) hamburger.setAttribute('aria-expanded','false'); document.body.style.overflow=''; }
  if(hamburger){ hamburger.addEventListener('click', function(){ sidebar.classList.contains('open')?closeMenu():openMenu(); }); }
  if(overlay){ overlay.addEventListener('click', closeMenu); }
  window.addEventListener('resize', syncHeader); window.addEventListener('load', syncHeader);
  document.querySelectorAll('.menu a').forEach(function(a){ a.addEventListener('click', function(){ if (window.matchMedia('(max-width: 992px)').matches) closeMenu(); }); });
})();
</script>
</body>
</html>