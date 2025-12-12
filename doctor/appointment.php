<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
        header("location: ../login.php");
        exit();
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
    exit();
}

//import database
include("../connection.php");
require_once('../admin/send-email-notification.php');
require_once('../admin/service-inventory-mapping.php');
$userrow = $database->query("select * from tbl_doctor where docemail='$useremail'");
$userfetch=$userrow->fetch_assoc();
$userid= $userfetch["docid"] ?? 0;
// Compose full name from parts
$first = $userfetch["first_name"] ?? '';
$middle = $userfetch["middle_name"] ?? '';
$last = $userfetch["last_name"] ?? '';
$suffix = $userfetch["suffix"] ?? '';
$username = $first;
if ($middle) $username .= ' ' . $middle;
$username .= ' ' . $last;
if ($suffix) $username .= ' ' . $suffix;
if (empty($username) || trim($username) === '') $username = 'Unknown Doctor';
$profilePath = "../Images/profiles/doctor_{$userid}.jpg";
$profileImage = file_exists($profilePath) ? $profilePath : "../Images/user.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
        
    <title>Appointments</title>
    <style>
        .popup{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
        /* Progress dropdown styling for Events column */
        .events-cell{ display:flex; justify-content:center; gap:10px; align-items:center; }
        .progress-select{
            min-width: 150px;
            padding: 8px 10px;
            border: 1px solid #cbd5e1; /* slate-300 */
            border-radius: 6px;
            background: #ffffff;
            color: #111827; /* gray-900 */
        }
        .progress-select:disabled{
            background: #f3f4f6; /* gray-100 */
            color: #6b7280; /* gray-500 */
            cursor: not-allowed;
            opacity: 1; /* keep text readable */
        }

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

        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Button styles */
        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #eee;
        }

        .btn-primary {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Appointment info styles */
        .appointment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .appointment-info h3 {
            margin-top: 0;
            color: #333;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: bold;
            width: 120px;
            color: #666;
        }

        .info-value {
            color: #333;
        }

        /* Tab styles */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-button {
            transition: all 0.3s ease;
        }
        
        .tab-button:hover {
            background: #e9ecef !important;
        }
        
        .tab-button.active {
            background: #007bff !important;
            color: white !important;
        }

        /* Enhanced calendar styling */
        .fc-event {
            border-radius: 6px !important;
            border: none !important;
            padding: 4px 8px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-align: center !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        
        .fc-event-title {
            font-weight: 700 !important;
            color: white !important;
        }
        
        .fc-daygrid-event {
            margin: 2px 0 !important;
        }
        
        .fc-daygrid-day-events {
            margin-top: 3px !important;
        }
        
        .fc-event {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer !important;
        }
        
        .fc-event:hover {
            transform: scale(1.08) translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(0,123,255,0.3) !important;
            filter: brightness(1.1) !important;
            z-index: 10 !important;
        }
        
        .fc-event:active {
            transform: scale(1.02) !important;
            transition: all 0.1s ease !important;
        }
        
        /* Enhanced calendar day hover effects */
        .fc-daygrid-day:hover {
            background-color: rgba(0,123,255,0.05) !important;
            transition: background-color 0.3s ease !important;
        }
        
        .fc-daygrid-day-number:hover {
            background-color: rgba(0,123,255,0.1) !important;
            border-radius: 50% !important;
            transition: all 0.3s ease !important;
        }
        
        /* Calendar header hover effects */
        .fc-col-header-cell:hover {
            background-color: rgba(0,123,255,0.08) !important;
            transition: background-color 0.3s ease !important;
        }
        
        /* Event title animation */
        .fc-event-title {
            transition: all 0.3s ease !important;
        }
        
        .fc-event:hover .fc-event-title {
            font-weight: 700 !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
        }
        
        /* Calendar navigation button animations */
        .fc-button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border-radius: 6px !important;
        }
        
        .fc-button:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            filter: brightness(1.05) !important;
        }
        
        .fc-button:active {
            transform: translateY(0) !important;
            transition: all 0.1s ease !important;
        }
        
        /* Calendar today button special animation */
        .fc-today-button:hover {
            background-color: #0056b3 !important;
            transform: translateY(-1px) scale(1.02) !important;
        }
        
        /* Month/year display hover effect */
        .fc-toolbar-title:hover {
            color: #007bff !important;
            transition: color 0.3s ease !important;
        }
        
        /* Calendar grid cell animations */
        .fc-daygrid-day-frame {
            transition: all 0.2s ease !important;
        }
        
        .fc-daygrid-day-frame:hover {
            background-color: rgba(0,123,255,0.03) !important;
        }
        
        /* Event container animations */
        .fc-daygrid-event-harness {
            transition: all 0.3s ease !important;
        }
        
        .fc-daygrid-event-harness:hover {
            transform: translateY(-1px) !important;
        }
        
        /* Loading animation for calendar */
        .fc-loading {
            animation: pulse 1.5s ease-in-out infinite !important;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @keyframes pulse-glow {
            0% { 
                box-shadow: 0 8px 25px rgba(0,123,255,0.4);
                transform: scale(1.08) translateY(-2px);
            }
            50% { 
                box-shadow: 0 12px 35px rgba(0,123,255,0.6);
                transform: scale(1.1) translateY(-3px);
            }
            100% { 
                box-shadow: 0 8px 25px rgba(0,123,255,0.4);
                transform: scale(1.08) translateY(-2px);
            }
        }
        
        /* Smooth transitions for all calendar elements */
        .fc-daygrid-day-events,
        .fc-daygrid-day-bg,
        .fc-daygrid-day-frame {
            transition: all 0.3s ease !important;
        }

        /* Cancelled appointments list styling */
        .cancelled-appointment-item {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .cancelled-appointment-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .appointment-patient {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .appointment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
</style>
</head>
<body>
    <?php
    // Handle cancel (for request-based appointments)
    if(isset($_POST['cancel_source']) && isset($_POST['cancel_id'])){
        $cancelSource = $_POST['cancel_source'];
        $cancelId = (int)$_POST['cancel_id'];
        if($cancelSource === 'request'){
            $stmt = $database->prepare(
                "SELECT r.*, p.Email AS patient_email, p.Fname, p.Mname, p.Lname, p.Suffix
                 FROM tbl_appointment_requests r
                 LEFT JOIN tbl_patients p ON p.Patient_id = r.patient_id
                 WHERE r.request_id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $cancelId);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            if($req){
                $upd = $database->prepare("UPDATE tbl_appointment_requests SET appointment_progress='cancelled' WHERE request_id = ?");
                $upd->bind_param("i", $cancelId);
                if($upd->execute()){
                    $patientName = $req['Fname']
                        .(!empty($req['Mname']) ? (' '.$req['Mname']) : '')
                        .' '.$req['Lname']
                        .(!empty($req['Suffix']) ? (' '.$req['Suffix']) : '');
                    $details = [
                        'service' => $req['appointment_type'],
                        'date' => date('F d, Y', strtotime($req['booking_date'])),
                        'time' => $req['appointment_time'] ? date('h:i A', strtotime($req['appointment_time'])) : 'To be determined'
                    ];
                    sendEmailNotification($req['patient_email'], $patientName, 'cancelled', $details);
                }
            }
        } else if ($cancelSource === 'legacy') {
            header('Location: delete-appointment.php?id=' . $cancelId);
            exit();
        }
        header('Location: appointment.php');
        exit();
    }
    ?>
    <?php
    // Handle progress updates for request-based appointments
    if(isset($_POST['update_progress']) && isset($_POST['request_id']) && isset($_POST['progress'])){
        $request_id = (int)$_POST['request_id'];
        $progress = $_POST['progress'];
        $allowed = ['preappointment','ongoing','done'];
        if(in_array($progress, $allowed, true)){
            // verify this request belongs to a schedule owned by this doctor
            $stmt = $database->prepare(
                "SELECT s.docid
                 FROM tbl_appointment_requests r
                 INNER JOIN tbl_schedule s 
                   ON s.scheduledate = r.booking_date 
                  AND r.appointment_time BETWEEN s.start_time AND s.end_time
                 WHERE r.request_id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if($row && (int)$row['docid'] === (int)$userid){
                $upd = $database->prepare("UPDATE tbl_appointment_requests SET appointment_progress = ? WHERE request_id = ?");
                $upd->bind_param("si", $progress, $request_id);
                $upd->execute();
            }
        }
        header('Location: appointment.php');
        exit();
    }
    ?>
    <?php
    // Handle AJAX progress updates
    if(isset($_POST['ajax']) && isset($_POST['request_id']) && isset($_POST['progress'])){
        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        $request_id = (int)$_POST['request_id'];
        $progress = $_POST['progress'];
        $appointment_type = $_POST['appointment_type'] ?? 'request'; // Default to request type
        
        // Debug logging
        error_log("Progress update request - ID: $request_id, Progress: $progress, Type: $appointment_type");
        
        try {
            if($appointment_type === 'legacy') {
                // Handle legacy appointments
                $stmt = $database->prepare(
                    "SELECT a.*, s.docid
                     FROM tbl_appointment a
                     INNER JOIN tbl_schedule s ON s.scheduleid = a.scheduleid
                     WHERE a.appoid = ? LIMIT 1"
                );
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $ap = $stmt->get_result()->fetch_assoc();
                
                if($ap && (int)$ap['docid'] === (int)$userid){
                    // For legacy appointments, we can't update progress in the same way
                    // Instead, we'll create a note or log the progress change
                    error_log("Legacy appointment progress updated - ID: $request_id, Progress: $progress");
                    echo json_encode(['success' => true, 'message' => 'Legacy appointment progress noted']);
                } else {
                    error_log("Legacy appointment not found or unauthorized - ID: $request_id, User: $userid");
                    echo json_encode(['success' => false, 'error' => 'Unauthorized or legacy appointment not found']);
                }
            } else {
                // Handle request-based appointments
                $stmt = $database->prepare(
                    "SELECT ar.*, s.docid
                     FROM tbl_appointment_requests ar
                     INNER JOIN tbl_schedule s 
                       ON s.scheduledate = ar.booking_date 
                      AND ar.appointment_time BETWEEN s.start_time AND s.end_time
                     WHERE ar.request_id = ? LIMIT 1"
                );
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $ap = $stmt->get_result()->fetch_assoc();
                
                if($ap && (int)$ap['docid'] === (int)$userid){
                    $upd = $database->prepare("UPDATE tbl_appointment_requests SET appointment_progress=? WHERE request_id = ?");
                    $upd->bind_param("si", $progress, $request_id);
                    
                    if($upd->execute()){
                        error_log("Request appointment progress updated - ID: $request_id, Progress: $progress");
                        echo json_encode(['success' => true]);
                    } else {
                        error_log("Database update failed - ID: $request_id, Error: " . $database->error);
                        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $database->error]);
                    }
                } else {
                    error_log("Request appointment not found or unauthorized - ID: $request_id, User: $userid");
                    echo json_encode(['success' => false, 'error' => 'Unauthorized or appointment not found']);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        
        // Ensure no further output
        exit();
    }
    
    // Handle approve/reject from doctor side
    if(isset($_POST['action']) && isset($_POST['request_id'])){
        $request_id = (int)$_POST['request_id'];

        // Fetch appointment with patient, and verify this doctor owns the schedule
        $stmt = $database->prepare(
            "SELECT r.*, p.Email AS patient_email, p.Fname, p.Mname, p.Lname, p.Suffix,
                    s.docid
             FROM tbl_appointment_requests r
             LEFT JOIN tbl_patients p ON p.Patient_id = r.patient_id
             INNER JOIN tbl_schedule s 
               ON s.scheduledate = r.booking_date 
              AND r.appointment_time BETWEEN s.start_time AND s.end_time
             WHERE r.request_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $ap = $stmt->get_result()->fetch_assoc();
        
        if($ap && (int)$ap['docid'] === (int)$userid){
            $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
            if($action === 'approved'){
                $upd = $database->prepare("UPDATE tbl_appointment_requests SET status='approved', approved_at = NOW() WHERE request_id = ?");
            } else {
                $upd = $database->prepare("UPDATE tbl_appointment_requests SET status='rejected' WHERE request_id = ?");
            }
            $upd->bind_param("i", $request_id);
            if($upd->execute()){
                // inventory auto-deduct on approval
                if($action === 'approved'){
                    $deduct = deductInventoryForService($ap['appointment_type'], $database, $request_id, $ap['patient_id'], $patientName, $userid, 'Doctor');
                    if($deduct['success']){
                        logInventoryDeduction($request_id, $ap['appointment_type'], $deduct['deducted_items'] ?? [], $database);
                    }
                }
                // email notify patient
                $patientName = $ap['Fname']
                    .(!empty($ap['Mname']) ? (' '.$ap['Mname']) : '')
                    .' '.$ap['Lname']
                    .(!empty($ap['Suffix']) ? (' '.$ap['Suffix']) : '');
                $details = [
                    'service' => $ap['appointment_type'],
                    'date' => date('F d, Y', strtotime($ap['booking_date'])),
                    'time' => $ap['appointment_time'] ? date('h:i A', strtotime($ap['appointment_time'])) : 'To be determined',
                    'duration' => $ap['duration'] ?? 'To be determined'
                ];
                sendEmailNotification($ap['patient_email'], $patientName, $action, $details);
            }
        }
        header('Location: appointment.php');
        exit();
    }
    ?>
    <div class="container">
        <div class="menu">
        <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px" >
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Dr. <?php echo htmlspecialchars(explode(' ', trim($username))[0]); ?></p>
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
                    <td class="menu-btn menu-icon-dashbord " >
                        <a href="index.php" class="non-style-link-menu "><div><p class="menu-text">Dashboard</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment  menu-active menu-icon-appoinment-active">
                        <a href="appointment.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">My Appointments</p></div></a>
                    </td>
                </tr>
                
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">My Sessions</p></div></a>
                    </td>
                </tr>
                
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a>
                    </td>
                </tr>
                
            </table>
        </div>
        <div class="dash-body">
            <table border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;margin-top:25px; ">
                <tr >
                    <td width="13%" >
                    <a href="appointment.php" ><button  class="login-btn btn-primary-soft btn btn-icon-back"  style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                    </td>
                    <td>
                        <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Appointment Manager</p>
                                           
                    </td>
                    <td width="15%">
                        <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                            Today's Date
                        </p>
                        <p class="heading-sub12" style="padding: 0;margin: 0;">
                            <?php 

                        date_default_timezone_set('Asia/Kolkata');

                        $today = date('Y-m-d');
                        echo $today;

                        // Count combined legacy appointments and approved requests mapped to this doctor
                        $countSql = "SELECT COUNT(*) AS cnt FROM (
                            SELECT a.appoid
                            FROM tbl_schedule s
                            INNER JOIN tbl_appointment a ON s.scheduleid = a.scheduleid
                            INNER JOIN tbl_doctor d ON s.docid = d.docid
                            WHERE d.docid = '$userid'
                            UNION ALL
                            SELECT ar.request_id AS appoid
                            FROM tbl_appointment_requests ar
                            INNER JOIN tbl_schedule s ON s.scheduledate = ar.booking_date AND ar.appointment_time BETWEEN s.start_time AND s.end_time
                            INNER JOIN tbl_doctor d ON s.docid = d.docid
                            WHERE d.docid = '$userid' AND ar.status = 'approved'
                        ) t";
                        $list110 = $database->query($countSql);
                        $totalAppointments = 0;
                        if ($list110 && ($rowCnt = $list110->fetch_assoc())) { $totalAppointments = (int)$rowCnt['cnt']; }


                        ?>
                        </p>
                    </td>
                    <td width="10%">
                        <button  class="btn-label"  style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                    </td>


                </tr>
               
                <!-- <tr>
                    <td colspan="4" >
                        <div style="display: flex;margin-top: 40px;">
                        <div class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49);margin-top: 5px;">Schedule a Session</div>
                        <a href="?action=add-session&id=none&error=0" class="non-style-link"><button  class="login-btn btn-primary btn button-icon"  style="margin-left:25px;background-image: url('../Images/icons/add.svg');">Add a Session</font></button>
                        </a>
                        </div>
                    </td>
                </tr> -->
                <tr>
                    <td colspan="4" style="padding-top:10px;width: 100%;" >
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-left: 45px; margin-right: 45px;">
                            <p class="heading-main12" style="font-size:18px;color:rgb(49, 49, 49); margin: 0;">My Appointments (<?php echo $totalAppointments; ?>)</p>
                            <div class="tab-container" style="display: flex; gap: 5px;">
                                <button class="tab-button active" onclick="showTab('calendar')" style="padding: 8px 16px; border: 1px solid #ddd; background: #007bff; color: white; border-radius: 4px; cursor: pointer;">Calendar View</button>
                                <button class="tab-button" onclick="showTab('cancelled')" style="padding: 8px 16px; border: 1px solid #ddd; background: #f8f9fa; color: #333; border-radius: 4px; cursor: pointer;">Cancelled Appointments</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding-top:0px;width: 100%;" >
                        <!-- Calendar Tab Content -->
                        <div id="calendarTab" class="tab-content active">
                            <div class="dashboard-container">
                                <!-- Enhanced Calendar View -->
                                <div class="calendar-container">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="margin: 0; color: #333;">Appointment Calendar</h3>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <div style="width: 12px; height: 12px; background: #28a745; border-radius: 2px;"></div>
                                                <span style="font-size: 12px;">Scheduled</span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <div style="width: 12px; height: 12px; background: #ffc107; border-radius: 2px;"></div>
                                                <span style="font-size: 12px;">Ongoing</span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <div style="width: 12px; height: 12px; background: #17a2b8; border-radius: 2px;"></div>
                                                <span style="font-size: 12px;">Completed</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="appointmentCalendar"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cancelled Appointments Tab Content -->
                        <div id="cancelledTab" class="tab-content" style="display: none;">
                            <div class="dashboard-container">
                                <div class="calendar-container">
                                    <h3 style="margin-top: 0; margin-bottom: 20px; color: #333;">Cancelled Appointments</h3>
                                    <div id="cancelledAppointmentsList">
                                        <!-- Cancelled appointments will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                       
                        
                        
            </table>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <h2>Appointment Details</h2>
            <div id="appointmentDetails" class="appointment-info">
                <!-- Appointment details will be loaded here -->
            </div>
            
            <div class="form-group">
                <label for="appointmentProgress">Update Progress:</label>
                <select id="appointmentProgress" class="form-control">
                    <option value="preappointment">Pre-appointment</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="done">Completed</option>
                </select>
            </div>
            
            <div class="btn-container">
                <button type="button" class="btn-secondary" onclick="closeAppointmentModal()">Close</button>
                <button type="button" class="btn-danger" id="cancelAppointmentBtn" onclick="cancelAppointment()">Cancel Appointment</button>
                <button type="button" class="btn-primary" onclick="updateAppointmentProgress()">Update Progress</button>
            </div>
        </div>
    </div>
    
    <!-- Cancel Confirmation Modal (works for legacy or request-based) -->
    <div id="cancelModal" class="overlay" style="display:none;">
        <div class="popup">
            <a class="close" href="#" onclick="closeCancelModal()">&times;</a>
            <div class="cancel-confirm-modal">
                <h3>Cancel Appointment</h3>
                <p>Are you sure you want to cancel this appointment?</p>
                <form id="cancelForm" method="POST" style="display:inline;">
                    <input type="hidden" name="cancel_source" id="cancel_source">
                    <input type="hidden" name="cancel_id" id="cancel_id">
                    <button type="submit" class="btn-confirm-cancel">Yes, Cancel</button>
                </form>
                <button class="btn-keep" onclick="closeCancelModal()">Keep Appointment</button>
            </div>
        </div>
    </div>
    <?php
    
    if($_GET){
        $id=$_GET["id"];
        $action=$_GET["action"];
        if($action=='add-session'){

            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                    
                    
                        <a class="close" href="schedule.php">&times;</a> 
                        <div style="display: flex;justify-content: center;">
                        <div class="abc">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        <tr>
                                <td class="label-td" colspan="2">'.
                                   ""
                                
                                .'</td>
                            </tr>

                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Add New Session.</p><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                <form action="add-session.php" method="POST" class="add-new-form">
                                    <label for="title" class="form-label">Session Title : </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <input type="text" name="title" class="input-text" placeholder="Name of this Session" required><br>
                                </td>
                            </tr>
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="docid" class="form-label">Select Doctor: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <select name="docid" id="" class="box" >
                                    <option value="" disabled selected hidden>Choose Doctors Name from the list</option><br/>';
                                        

                                        $list11 = $database->query("SELECT * FROM tbl_doctor;");

                                        for ($y=0;$y<$list11->num_rows;$y++){
                                            $row00=$list11->fetch_assoc();
                                            // Compose full name from parts
                                            $first = $row00["first_name"] ?? '';
                                            $middle = $row00["middle_name"] ?? '';
                                            $last = $row00["last_name"] ?? '';
                                            $suffix = $row00["suffix"] ?? '';
                                            $sn = $first;
                                            if ($middle) $sn .= ' ' . $middle;
                                            $sn .= ' ' . $last;
                                            if ($suffix) $sn .= ' ' . $suffix;
                                            $id00=$row00["docid"];
                                            echo "<option value=".$id00.">$sn</option><br/>";
                                        };



                                        
                        echo     '       </select><br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="nop" class="form-label">Number of Patients/Appointment Numbers : </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <input type="number" name="nop" class="input-text" min="0"  placeholder="The final appointment number for this session depends on this number" required><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="date" class="form-label">Session Date: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <input type="date" name="date" class="input-text" min="'.date('Y-m-d').'" required><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="time" class="form-label">Schedule Time: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <input type="time" name="time" class="input-text" placeholder="Time" required><br>
                                </td>
                            </tr>
                           
                            <tr>
                                <td colspan="2">
                                    <input type="reset" value="Reset" class="login-btn btn-primary-soft btn" >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                
                                    <input type="submit" value="Place this Session" class="login-btn btn-primary btn" name="shedulesubmit">
                                </td>
                
                            </tr>
                           
                            </form>
                            </tr>
                        </table>
                        </div>
                        </div>
                    </center>
                    <br><br>
            </div>
            </div>
            ';
        }elseif($action=='session-added'){
            $titleget=$_GET["title"];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                    <br><br>
                        <h2>Session Placed.</h2>
                        <a class="close" href="schedule.php">&times;</a>
                        <div class="content">
                        '.substr($titleget,0,40).' was scheduled.<br><br>
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        
                        <a href="schedule.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                        <br><br><br><br>
                        </div>
                    </center>
            </div>
            </div>
            ';
        }elseif($action=='drop'){
            $nameget=$_GET["name"];
            $session=$_GET["session"];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2>Are you sure?</h2>
                        <a class="close" href="appointment.php">&times;</a>
                        <div class="content">
                            You want to delete this record<br><br>
                            Patient Name: &nbsp;<b>'.substr($nameget,0,40).'</b><br>
                            
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <a href="delete-appointment.php?id='.$id.'" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Yes&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;
                        <a href="appointment.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;No&nbsp;&nbsp;</font></button></a>

                        </div>
                    </center>
            </div>
            </div>
            '; 
        }elseif($action=='view'){
            $sqlmain= "select * from tbl_doctor where docid='$id'";
            $result= $database->query($sqlmain);
            $row=$result->fetch_assoc();
            // Compose full name from parts
            $first = $row["first_name"] ?? '';
            $middle = $row["middle_name"] ?? '';
            $last = $row["last_name"] ?? '';
            $suffix = $row["suffix"] ?? '';
            $name = $first;
            if ($middle) $name .= ' ' . $middle;
            $name .= ' ' . $last;
            if ($suffix) $name .= ' ' . $suffix;
            $email=$row["docemail"];
            $spe=$row["specialties"];
            
            $spcil_res= $database->query("select sname from tbl_specialties where id='$spe'");
            $spcil_array= $spcil_res->fetch_assoc();
            $spcil_name=$spcil_array["sname"];
            $nic=$row['docnic'];
            $tele=$row['doctel'];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2></h2>
                        <a class="close" href="doctors.php">&times;</a>
                        <div class="content">
                            Details<br>
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details.</p><br><br>
                                </td>
                            </tr>
                            
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Name: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    '.$name.'<br><br>
                                </td>
                                
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Email" class="form-label">Email: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$email.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="nic" class="form-label">NIC: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$nic.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Tele" class="form-label">Contact No: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$tele.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="spec" class="form-label">Specialties: </label>
                                    
                                </td>
                            </tr>
                            <tr>
                            <td class="label-td" colspan="2">
                            '.$spcil_name.'<br><br>
                            </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="doctors.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn" ></a>
                                
                                    
                                </td>
                
                            </tr>
                           

                        </table>
                        </div>
                    </center>
                    <br><br>
            </div>
            </div>
            ';  
    }
}

    ?>
    </div>

</body>
</html>

<!-- FullCalendar JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script>
// Global variables for appointment management
let currentAppointmentId = null;
let currentAppointmentType = null; // 'legacy' or 'request'

// Initialize calendar when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCalendar();
    loadCancelledAppointments();
});

function initializeCalendar() {
    const calendarEl = document.getElementById('appointmentCalendar');
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: function(info, successCallback, failureCallback) {
            // Fetch appointments for the current view
            fetch('get-doctor-appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'start=' + info.startStr + '&end=' + info.endStr
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successCallback(data.events);
                } else {
                    failureCallback(data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching appointments:', error);
                failureCallback('Error loading appointments');
            });
        },
        eventClick: function(info) {
            const event = info.event;
            const appointments = event.extendedProps.appointments;
            const date = event.extendedProps.date;
            const count = event.extendedProps.count;
            
            // Show date appointments modal
            showDateAppointmentsModal(date, appointments, count);
        },
        eventDidMount: function(info) {
            const eventEl = info.el;
            const count = info.event.extendedProps.count;
            
            // Apply count-based styling with enhanced animations
            eventEl.style.backgroundColor = '#007bff';
            eventEl.style.color = 'white';
            eventEl.style.border = 'none';
            eventEl.style.borderRadius = '6px';
            eventEl.style.padding = '4px 8px';
            eventEl.style.fontSize = '12px';
            eventEl.style.fontWeight = '700';
            eventEl.style.textAlign = 'center';
            eventEl.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            eventEl.style.cursor = 'pointer';
            eventEl.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            eventEl.style.position = 'relative';
            eventEl.style.overflow = 'hidden';
            
            // Add a subtle glow effect
            eventEl.style.boxShadow = '0 2px 8px rgba(0,123,255,0.2)';
            
            // Enhanced hover effects with smooth animations
            eventEl.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.08) translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0,123,255,0.4)';
                this.style.backgroundColor = '#0056b3';
                this.style.filter = 'brightness(1.1)';
                this.style.zIndex = '10';
                
                // Add a subtle pulse animation
                this.style.animation = 'pulse-glow 0.6s ease-in-out';
            });
            
            eventEl.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) translateY(0)';
                this.style.boxShadow = '0 2px 8px rgba(0,123,255,0.2)';
                this.style.backgroundColor = '#007bff';
                this.style.filter = 'brightness(1)';
                this.style.zIndex = '1';
                this.style.animation = 'none';
            });
            
            // Add click animation
            eventEl.addEventListener('mousedown', function() {
                this.style.transform = 'scale(1.02) translateY(-1px)';
                this.style.transition = 'all 0.1s ease';
            });
            
            eventEl.addEventListener('mouseup', function() {
                this.style.transform = 'scale(1.08) translateY(-2px)';
                this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            });
        }
    });
    
    calendar.render();
}

function loadAppointmentDetails(appointmentId, appointmentType) {
    const detailsContainer = document.getElementById('appointmentDetails');
    
    // Show loading state
    detailsContainer.innerHTML = '<p>Loading appointment details...</p>';
    
    // Fetch appointment details
    fetch('get-appointment-details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + appointmentId + '&type=' + appointmentType
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const appointment = data.appointment;
            detailsContainer.innerHTML = `
                <h3>${appointment.patient_name}</h3>
                <div class="info-row">
                    <span class="info-label">Session:</span>
                    <span class="info-value">${appointment.session_title}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value">${appointment.appointment_date}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time:</span>
                    <span class="info-value">${appointment.appointment_time}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Type:</span>
                    <span class="info-value">${appointment.appointment_type}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">${appointment.status}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Progress:</span>
                    <span class="info-value">${appointment.progress}</span>
                </div>
            `;
            
            // Update progress dropdown
            const progressDropdown = document.getElementById('appointmentProgress');
            if (progressDropdown && appointment.progress_value) {
                progressDropdown.value = appointment.progress_value;
            }
            
            // Show/hide cancel button based on progress
            const cancelBtn = document.getElementById('cancelAppointmentBtn');
            if (cancelBtn) {
                if (appointment.progress === 'cancelled' || appointment.progress === 'done') {
                    cancelBtn.style.display = 'none';
                } else {
                    cancelBtn.style.display = 'inline-block';
                }
            }
        } else {
            detailsContainer.innerHTML = '<p>Error loading appointment details: ' + data.message + '</p>';
        }
    })
    .catch(error => {
        console.error('Error loading appointment details:', error);
        detailsContainer.innerHTML = '<p>Error loading appointment details</p>';
    });
}

function updateAppointmentProgress() {
    const progress = document.getElementById('appointmentProgress').value;
    
    if (!currentAppointmentId) {
        alert('No appointment selected');
        return;
    }
    
    const formData = new FormData();
    formData.append('request_id', currentAppointmentId);
    formData.append('progress', progress);
    formData.append('appointment_type', currentAppointmentType);
    formData.append('ajax', '1');
    
    fetch('update-appointment-progress.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text);
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data);
        if (data.success) {
            let message = 'Appointment progress updated successfully';
            
            // If appointment is completed or cancelled, show special message
            if (progress === 'done') {
                message = 'Appointment marked as completed and will be moved to cancelled appointments';
            } else if (progress === 'cancelled') {
                message = 'Appointment cancelled and will be moved to cancelled appointments';
            }
            
            alert(message);
            closeAppointmentModal();
            
            // Refresh calendar to remove completed/cancelled appointments
            location.reload();
        } else {
            alert('Error updating appointment progress: ' + (data.error || data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error updating progress:', error);
        alert('Error updating appointment progress: ' + error.message);
    });
}

function cancelAppointment() {
    if (!currentAppointmentId) {
        alert('No appointment selected');
        return;
    }
    
    if (confirm('Are you sure you want to cancel this appointment?')) {
        const formData = new FormData();
        formData.append('cancel_source', currentAppointmentType);
        formData.append('cancel_id', currentAppointmentId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                alert('Appointment cancelled successfully');
                closeAppointmentModal();
                // Refresh calendar
                location.reload();
            } else {
                alert('Error cancelling appointment');
            }
        })
        .catch(error => {
            console.error('Error cancelling appointment:', error);
            alert('Error cancelling appointment');
        });
    }
}

function closeAppointmentModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    currentAppointmentId = null;
    currentAppointmentType = null;
}

// Existing cancel modal functionality
document.addEventListener('click', function(e){
    if(e.target && e.target.classList.contains('cancel-btn')){
        var btn = e.target;
        var source = btn.getAttribute('data-source');
        var id = btn.getAttribute('data-id');
        openCancelModal(source, id);
    }
});
function openCancelModal(source, id){
    document.getElementById('cancel_source').value = source;
    document.getElementById('cancel_id').value = id;
    document.getElementById('cancelModal').style.display = 'block';
}
function closeCancelModal(){
    document.getElementById('cancelModal').style.display = 'none';
}

// Tab switching functionality
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = '#f8f9fa';
        btn.style.color = '#333';
    });
    
    // Show selected tab content
    if (tabName === 'calendar') {
        document.getElementById('calendarTab').classList.add('active');
        document.getElementById('calendarTab').style.display = 'block';
    } else if (tabName === 'cancelled') {
        document.getElementById('cancelledTab').classList.add('active');
        document.getElementById('cancelledTab').style.display = 'block';
        loadCancelledAppointments();
    }
    
    // Add active class to clicked button
    event.target.classList.add('active');
    event.target.style.background = '#007bff';
    event.target.style.color = 'white';
}

// Load cancelled appointments
function loadCancelledAppointments() {
    const container = document.getElementById('cancelledAppointmentsList');
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading cancelled appointments...</div>';
    
    fetch('get-cancelled-appointments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCancelledAppointments(data.appointments);
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Error loading cancelled appointments: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading cancelled appointments:', error);
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Error loading cancelled appointments</div>';
        });
}

// Display cancelled appointments
function displayCancelledAppointments(appointments) {
    const container = document.getElementById('cancelledAppointmentsList');
    
    if (appointments.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i><br>No cancelled appointments found</div>';
        return;
    }
    
    let html = '';
    appointments.forEach(appointment => {
        html += `
            <div class="cancelled-appointment-item">
                <div class="appointment-header">
                    <div class="appointment-patient">${appointment.patient_name}</div>
                    <div class="appointment-status status-cancelled">Cancelled</div>
                </div>
                <div class="appointment-details">
                    <div class="detail-item">
                        <div class="detail-label">Appointment Type</div>
                        <div class="detail-value">${appointment.appointment_type}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date</div>
                        <div class="detail-value">${appointment.appointment_date}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Time</div>
                        <div class="detail-value">${appointment.appointment_time}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Session</div>
                        <div class="detail-value">${appointment.session_title}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Cancelled On</div>
                        <div class="detail-value">${appointment.cancelled_date}</div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Show date appointments modal
function showDateAppointmentsModal(date, appointments, count) {
    const modal = document.getElementById('dateAppointmentsModal');
    const title = document.getElementById('dateAppointmentsTitle');
    const list = document.getElementById('dateAppointmentsList');
    
    // Format date for display
    const formattedDate = new Date(date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    title.textContent = `${count} Appointment${count > 1 ? 's' : ''} for ${formattedDate}`;
    
    // Generate appointments list
    let html = '';
    appointments.forEach((appointment, index) => {
        const statusColor = getStatusColor(appointment.progress);
        const statusText = appointment.progress_text || 'Scheduled';
        
        html += `
            <div class="appointment-item" style="
                background: #fff;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 10px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                cursor: pointer;
                transition: all 0.3s ease;
            " onclick="viewAppointmentDetails('${appointment.id}', '${appointment.type}')">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin: 0 0 5px 0; color: #333; font-size: 16px;">
                            ${appointment.patient_name}
                        </h4>
                        <p style="margin: 0 0 5px 0; color: #666; font-size: 14px;">
                            <strong>Time:</strong> ${appointment.appointment_time}
                        </p>
                        <p style="margin: 0 0 5px 0; color: #666; font-size: 14px;">
                            <strong>Service:</strong> ${appointment.appointment_type}
                        </p>
                        <p style="margin: 0; color: #666; font-size: 14px;">
                            <strong>Session:</strong> ${appointment.session_title}
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <span style="
                            background: ${statusColor};
                            color: white;
                            padding: 4px 8px;
                            border-radius: 12px;
                            font-size: 12px;
                            font-weight: 500;
                        ">${statusText}</span>
                    </div>
                </div>
            </div>
        `;
    });
    
    list.innerHTML = html;
    modal.style.display = 'block';
}

// Close date appointments modal
function closeDateAppointmentsModal() {
    document.getElementById('dateAppointmentsModal').style.display = 'none';
}

// Get status color based on progress
function getStatusColor(progress) {
    switch(progress) {
        case 'ongoing': return '#ffc107';
        case 'done': return '#28a745';
        case 'cancelled': return '#dc3545';
        case 'preappointment': return '#6c757d';
        default: return '#007bff';
    }
}

// View individual appointment details
function viewAppointmentDetails(appointmentId, type) {
    closeDateAppointmentsModal();
    currentAppointmentId = appointmentId;
    currentAppointmentType = type;
    
    // Load appointment details
    loadAppointmentDetails(appointmentId, type);
    
    // Set current progress in dropdown
    const progress = 'preappointment'; // Default, will be updated by loadAppointmentDetails
    document.getElementById('appointmentProgress').value = progress;
    
    // Show/hide cancel button based on progress
    const cancelBtn = document.getElementById('cancelAppointmentBtn');
    if (progress === 'cancelled' || progress === 'done') {
        cancelBtn.style.display = 'none';
    } else {
        cancelBtn.style.display = 'inline-block';
    }
    
    // Show modal
    document.getElementById('appointmentModal').style.display = 'block';
}
</script>

<!-- Date Appointments Modal -->
<div id="dateAppointmentsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px; width: 90%;">
        <div class="modal-header">
            <h3 id="dateAppointmentsTitle">Appointments for Date</h3>
            <span class="close" onclick="closeDateAppointmentsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="dateAppointmentsList">
                <!-- Appointments will be loaded here -->
            </div>
        </div>
    </div>
</div>