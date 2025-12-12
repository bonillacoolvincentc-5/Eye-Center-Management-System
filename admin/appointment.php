<?php
session_start();

// Check authentication first
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");
require_once('send-email-notification.php');
require_once('service-inventory-mapping.php');

// Fetch admin profile picture
$useremail = $_SESSION["user"];
$profileImage = "../Images/user.png";

// Check if ProfileImage column exists, if not, add it
$checkColumn = $database->query("SHOW COLUMNS FROM tbl_admin LIKE 'ProfileImage'");
if ($checkColumn->num_rows == 0) {
    // Add ProfileImage column if it doesn't exist
    $database->query("ALTER TABLE tbl_admin ADD COLUMN ProfileImage VARCHAR(255) NULL AFTER password");
}

// Now query with ProfileImage column
$stmt = $database->prepare("SELECT admin_id, ProfileImage FROM tbl_admin WHERE email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$admin_result = $stmt->get_result();
if ($admin_result->num_rows > 0) {
    $admin_row = $admin_result->fetch_assoc();
    $admin_id = $admin_row["admin_id"];
    if (isset($admin_row['ProfileImage']) && !empty($admin_row['ProfileImage'])) {
        $profilePath = '../Images/profiles/' . $admin_row['ProfileImage'];
        if (file_exists($profilePath)) {
            $profileImage = $profilePath;
        }
    } else {
        // Try file-based approach
        $fileBasedPath = "../Images/profiles/admin_{$admin_id}.jpg";
        if (file_exists($fileBasedPath)) {
            $profileImage = $fileBasedPath;
        }
    }
}

// Handle approve/reject/cancel actions first, before any HTML output
if(isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    
    // Get patient email and details before updating status
    $sql = "SELECT r.*, p.email, p.Fname, p.Mname, p.Lname, p.Suffix 
            FROM tbl_appointment_requests r 
            LEFT JOIN tbl_patients p ON r.patient_id = p.Patient_id 
            WHERE r.request_id = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    // Format patient name
    $patientName = $appointment['Fname'];
    if (!empty($appointment['Mname'])) $patientName .= ' ' . $appointment['Mname'];
    $patientName .= ' ' . $appointment['Lname'];
    if (!empty($appointment['Suffix'])) $patientName .= ' ' . $appointment['Suffix'];

    if($_POST['action'] == 'approve' || $_POST['action'] == 'reject') {
        $status = $_POST['action'] == 'approve' ? 'approved' : 'rejected';
        
        // Set approved_at timestamp only when approving
        if ($_POST['action'] == 'approve') {
            $sql = "UPDATE tbl_appointment_requests SET status = ?, approved_at = NOW() WHERE request_id = ?";
        } else {
            $sql = "UPDATE tbl_appointment_requests SET status = ? WHERE request_id = ?";
        }
        
        $stmt = $database->prepare($sql);
        $stmt->bind_param("si", $status, $request_id);
        
        if($stmt->execute()) {
            // Required inventory deduction if approved
            if ($status === 'approved') {
                if (empty($_POST['selected_products'])) {
                    // Rollback the approval if no products selected
                    $database->query("UPDATE tbl_appointment_requests SET status = 'pending' WHERE request_id = " . intval($request_id));
                    $_SESSION['approval_error'] = 'Please select at least one product before approving the appointment.';
                    header("Location: appointment.php?error=no_products");
                    exit();
                }
                $selected = json_decode($_POST['selected_products'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($selected) || empty($selected)) {
                    // Rollback the approval if invalid products data
                    $database->query("UPDATE tbl_appointment_requests SET status = 'pending' WHERE request_id = " . intval($request_id));
                    $_SESSION['approval_error'] = 'Invalid product selection. Please try again.';
                    header("Location: appointment.php?error=invalid_products");
                    exit();
                }
                $deduct = deductSelectedInventoryItems($selected, $database, $request_id, $appointment['patient_id'], $patientName, $appointment['appointment_type'], $appointment['docid'], $appointment['doctor_name'] ?? 'Unknown Doctor');
                if ($deduct['success']) {
                    logInventoryDeduction($request_id, $appointment['appointment_type'], $deduct['deducted_items'] ?? [], $database);
                } else {
                    // Rollback approval if deduction failed
                    $database->query("UPDATE tbl_appointment_requests SET status = 'pending' WHERE request_id = " . intval($request_id));
                    $_SESSION['approval_error'] = 'Failed to deduct inventory: ' . $deduct['message'];
                    header("Location: appointment.php?error=deduction_failed");
                    exit();
                }
            }
            // Prepare appointment details for email
            $appointmentDetails = [
                'service' => $appointment['appointment_type'],
                'date' => date('F d, Y', strtotime($appointment['booking_date'])),
                'time' => $appointment['appointment_time'] ? date('h:i A', strtotime($appointment['appointment_time'])) : 'To be determined',
                'duration' => $appointment['duration'] ?? 'To be determined'
            ];

            // Send email notification
            sendEmailNotification(
                $appointment['email'],
                $patientName,
                $status,
                $appointmentDetails
            );
        }
    } 
    // Handle cancel action
    elseif($_POST['action'] == 'cancel') {
        $sql = "UPDATE tbl_appointment_requests SET appointment_progress = 'cancelled' WHERE request_id = ?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $request_id);
        
        if($stmt->execute()) {
            // Prepare appointment details for cancellation email
            $appointmentDetails = [
                'date' => date('F d, Y', strtotime($appointment['booking_date'])),
                'time' => $appointment['appointment_time'] ? date('h:i A', strtotime($appointment['appointment_time'])) : 'To be determined'
            ];

            // Send cancellation email
            sendEmailNotification(
                $appointment['email'],
                $patientName,
                'cancelled',
                $appointmentDetails
            );
        }
    }
    
    header("Location: appointment.php");
    exit();
}

// AJAX: fetch available inventory options for a given request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'inventory_options' && isset($_GET['request_id'])) {
    header('Content-Type: application/json');
    $rid = (int)$_GET['request_id'];
    $stmt = $database->prepare("SELECT appointment_type, patient_id, docid FROM tbl_appointment_requests WHERE request_id = ? LIMIT 1");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $rs = $stmt->get_result();
    if ($row = $rs->fetch_assoc()) {
        $options = getAvailableInventoryForService($row['appointment_type'], $database);
        echo json_encode(['success' => true, 'service' => $row['appointment_type'], 'items' => $options]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
    }
    exit();
}

// Handle progress updates via AJAX - NEW: Separate handling for AJAX requests
if(isset($_POST['progress']) && isset($_POST['request_id']) && isset($_POST['ajax'])) {
    $progress = $_POST['progress'];
    $request_id = $_POST['request_id'];
    
    $sql = "UPDATE tbl_appointment_requests SET appointment_progress = ? WHERE request_id = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("si", $progress, $request_id);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true, 'progress' => $progress]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    exit(); // Stop execution for AJAX requests
}

// Get appointment requests with patient information - FIXED to get patient data from tbl_patients
$sql = "SELECT 
        r.*,
        CONCAT(p.Fname, 
               CASE WHEN p.Mname IS NOT NULL AND p.Mname != '' THEN CONCAT(' ', p.Mname) ELSE '' END,
               ' ', p.Lname,
               CASE WHEN p.Suffix IS NOT NULL AND p.Suffix != '' THEN CONCAT(' ', p.Suffix) ELSE '' END
        ) as fullname,
        p.PhoneNo as mobile,
        TRIM(
            BOTH ', ' FROM CONCAT_WS(', ',
                NULLIF(p.Street, ''),
                NULLIF(p.Barangay, ''),
                NULLIF(p.`City/Municipality`, ''),
                NULLIF(p.Province, '')
            )
        ) as address,
        CASE
            WHEN r.appointment_progress = 'preappointment' THEN 'Pre-appointment'
            WHEN r.appointment_progress = 'ongoing' THEN 'Ongoing'
            WHEN r.appointment_progress = 'done' THEN 'Completed'
            WHEN r.appointment_progress = 'cancelled' THEN 'Cancelled'
            ELSE 'Unavailable'
        END as progress_text,
        CASE 
            WHEN r.status = 'pending' THEN 1
            WHEN r.status = 'approved' THEN 2
            ELSE 3
        END as status_priority,
        DATE_FORMAT(r.approved_at, '%M %d, %Y %h:%i %p') as formatted_approved_at,
        DATE_FORMAT(r.appointment_time, '%h:%i %p') as formatted_appointment_time
        FROM tbl_appointment_requests r
        LEFT JOIN tbl_patients p ON r.patient_id = p.Patient_id
        ORDER BY status_priority ASC, r.booking_date DESC";
$requests_result = $database->query($sql);

// Build categorized appointment arrays BEFORE rendering HTML so all tabs can use them
$pending_appointments = array();
$active_appointments = array();
$history_appointments = array();
$today = date('Y-m-d');
if ($requests_result && $requests_result->num_rows > 0) {
    while ($row = $requests_result->fetch_assoc()) {
        $appointment_date = $row['booking_date'];
        // Normalize empty progress to preappointment
        $normalized_progress = !empty($row['appointment_progress']) ? $row['appointment_progress'] : 'preappointment';
        $row['appointment_progress'] = $normalized_progress;
        if ($row['status'] === 'pending') {
            $pending_appointments[] = $row;
        } elseif ($row['status'] === 'rejected' || in_array($normalized_progress, ['done', 'cancelled']) || $appointment_date < $today) {
            $history_appointments[] = $row;
        } elseif ($row['status'] === 'approved' && $appointment_date >= $today && in_array($normalized_progress, ['preappointment', 'ongoing'])) {
            $active_appointments[] = $row;
        } else {
            $history_appointments[] = $row;
        }
    }
}

// Now we can start HTML output
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Appointments</title>
    <style>
        .popup { animation: transitionIn-Y-bottom 0.5s; }
        .sub-table { animation: transitionIn-Y-bottom 0.5s; }
        .request-card {
            background: #fff;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .id-image {
            max-width: 200px;
            margin: 10px 0;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
        }

        .popup {
            position: relative;
            width: 70%;
            max-width: 800px;
            margin: 70px auto;
            padding: 20px 20px 30px 20px;  /* Adjusted padding */
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }

        .popup h2 {
            margin: 0 0 10px 0;  /* Reduced bottom margin */
            padding: 0 0 10px 0;  /* Added padding bottom for spacing */
            border-bottom: 1px solid #eee;  /* Added subtle border */
        }

        .popup .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            color: #666;
        }

        .popup .close:hover {
            color: #333;
        }

        .id-image {
            max-width: 100%;
            height: auto;
            margin: 15px 0;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-pending {
            background: #ffd700;
            color: #000;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }

        .status-approved {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }

        .status-rejected {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }

        .progress-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .progress-unavailable {
            background-color: #6c757d;
            color: white;
        }

        .progress-ongoing {
            background-color: #17a2b8;
            color: white;
        }

        .progress-done {
            background-color: #28a745;
            color: white;
        }

        .progress-cancelled {
            background-color: #dc3545;
            color: white;
        }

        .detail-row {
            margin: 8px 0;  /* Reduced margin */
            padding: 10px;  /* Reduced padding */
            background: #f8f9fa;
            border-radius: 4px;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            display: inline-block;
            width: 120px;
        }

        .search-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }

        .search-input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 250px;
            margin-right: 10px;
        }

        .search-btn {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .search-btn:hover {
            background: #0056b3;
        }

        .btn-primary-soft[value="reject"] {
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .btn-primary-soft[value="reject"]:hover {
            background-color: #dc3545;
            color: white;
        }

        .btn-primary-soft[value="approve"] {
            border: 1px solid #28a745;
            color: #28a745;
        }

        .btn-primary-soft[value="approve"]:hover {
            background-color: #28a745;
            color: white;
        }

        .btn-cancel {
            border: 1px solid #dc3545;
            color: #dc3545;
            padding: 6px 12px;
            background: transparent;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background-color: #dc3545;
            color: white;
        }

        /* UPDATED: Progress dropdown styling to match theme */
        .progress-select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #007bff;
            background-color: white;
            color: #007bff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23007bff' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 35px;
        }

        .progress-select:hover {
            background-color: #007bff;
            color: white;
            border-color: #0056b3;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        }

        .progress-select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            border-color: #007bff;
        }

        .progress-select:disabled {
            background-color: #e9ecef;
            border-color: #6c757d;
            color: #6c757d;
            cursor: not-allowed;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        }

        .progress-form {
            display: inline;
        }
        
        .table-headin {
            padding: 15px;
            text-align: left;
            background-color: #f8f9fa;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            display: none;
        }

        /* Style for dropdown options */
        .progress-select option {
            background-color: white;
            color: #333;
            padding: 8px;
        }

        .progress-select option:hover {
            background-color: #007bff;
            color: white;
        }

        /* Cancel confirmation modal styles - UPDATED FOR CENTER POSITIONING */
        .cancel-confirm-modal {
            text-align: center;
            padding: 30px;
        }

        .cancel-confirm-modal h3 {
            margin-bottom: 15px;
            color: #dc3545;
            font-size: 24px;
        }

        .cancel-confirm-modal p {
            margin-bottom: 25px;
            color: #666;
            font-size: 16px;
            line-height: 1.5;
        }

        .cancel-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 25px;
        }

        .btn-keep {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-keep:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-confirm-cancel {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-confirm-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Center the cancel confirmation modal properly */
        #cancelConfirmationModal .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            margin: 0;
            padding: 0;
            animation: transitionIn-Y-center 0.5s;
        }

        /* Add animation for center appearance */
        @keyframes transitionIn-Y-center {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        /* Ensure the modal content is properly centered */
        #cancelConfirmationModal .popup .cancel-confirm-modal {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 250px;
        }
        
        /* Tab styles */
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
        }
        
        .tab-button {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab-button:hover {
            color: #007bff;
            background-color: #f8f9fa;
        }
        
        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        .dash-body {
            width: 100%;
        }

        .container {
            width: 100%;
        }

        /* FIXED: Icon button styles for approve/reject */
        .btn-icon-approve, .btn-icon-reject {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 16px;
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-icon-approve {
            border: 1px solid #28a745;
            color: #28a745;
            background: transparent;
        }

        .btn-icon-approve:hover {
            background-color: #28a745;
            color: white;
            transform: scale(1.1);
        }

        .btn-icon-reject {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }

        .btn-icon-reject:hover {
            background-color: #dc3545;
            color: white;
            transform: scale(1.1);
        }

        /* FIXED: Custom tooltip styles - removed title attribute conflict */
        .btn-icon-approve::before, .btn-icon-reject::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            pointer-events: none;
        }

        .btn-icon-approve:hover::before, .btn-icon-reject:hover::before {
            opacity: 1;
            visibility: visible;
            bottom: -35px;
        }

        /* Disable hover tooltips to prevent black bar from showing due to overflow clipping */
        .btn-icon-approve::before, .btn-icon-reject::before {
            content: none !important;
            display: none !important;
        }

        /* FIXED: Horizontal button layout */
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
        }

        /* Ensure proper spacing for view details button */
        .action-buttons .btn-primary-soft {
            white-space: nowrap;
            padding: 8px 16px;
            margin: 0;
        }

        /* Make the form display inline to keep buttons horizontal */
        .action-buttons form {
            display: flex;
            gap: 8px;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="menu">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px">
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title">Administrator</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-dashbord">
                    <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-schedule">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Schedule</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment menu-active menu-icon-appoinment-active">
                    <a href="appointment.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Appointment</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-patient">
                    <a href="patient.php" class="non-style-link-menu"><div><p class="menu-text">Patients</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-inventory">
                    <a href="inventory.php" class="non-style-link-menu"><div><p class="menu-text">Inventory</p></div></a>
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
        <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;margin-top:25px;">
            <tr>
                <td width="13%">
                    <a href="appointment.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Appointment Manager</p>
                </td>
                <td width="15%">
                    <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                        Today's Date
                    </p>
                    <p class="heading-sub12" style="padding: 0;margin: 0;">
                        <?php echo date('Y-m-d'); ?>
                    </p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:10px;width: 100%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 45px;">
                        <p class="heading-main12" style="font-size:18px;color:rgb(49, 49, 49); margin: 0;">
                            Appointment Management
                        </p>
                        <div class="search-container" style="margin: 0;">
                            <input type="text" 
                                   id="searchInput" 
                                   class="search-input" 
                                   placeholder="Search appointments...">
                            <button id="searchBtn" class="search-btn">
                                <i class="fa fa-search"></i>
                                Search
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div class="tab-container" style="padding: 0 45px; margin: 20px 0;">
                        <div class="tabs">
                            <button class="tab-button active" onclick="showAppointmentTab('active')">Active Appointments</button>
                            <button class="tab-button" onclick="showAppointmentTab('pending')">Pending Requests</button>
                            <button class="tab-button" onclick="showAppointmentTab('history')">History & Logs</button>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <center>
                        <!-- Active Appointments Tab -->
                        <div id="activeTab" class="tab-content active">
                        <div class="abc scroll">
                            <table width="93%" class="sub-table scrolldown" border="0">
                                <thead>
                                    <tr>
                                        <th class="table-headin">Full Name</th>
                                        <th class="table-headin">Booking Date</th>
                                        <th class="table-headin">Mobile</th>
                                        <th class="table-headin">Status</th>
                                        <th class="table-headin">Progress</th>
                                        <th class="table-headin">Cancel</th>
                                        <th class="table-headin">Actions</th>
                                    </tr>
                                </thead>
                                    <tbody id="activeAppointments">
                                <?php
                                        if (empty($active_appointments)) {
                                    echo '<tr>
                                        <td colspan="7">
                                            <center>
                                                <img src="../Images/notfound.svg" width="25%">
                                                <br>
                                                <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                                            No active appointments found!
                                                </p>
                                            </center>
                                        </td>
                                    </tr>';
                                } else {
                                            foreach($active_appointments as $row) {
                                        $statusClass = match($row["status"]) {
                                            'pending' => 'status-pending',
                                            'approved' => 'status-approved',
                                            'rejected' => 'status-rejected',
                                            default => ''
                                        };
                                        
                                        $progressClass = match($row["appointment_progress"]) {
                                            'preappointment' => 'progress-unavailable',
                                            'ongoing' => 'progress-ongoing',
                                            'done' => 'progress-done',
                                            'cancelled' => 'progress-cancelled',
                                            default => 'progress-unavailable'
                                        };
                                        
                                                echo '<tr data-status="'.$row["status"].'" data-progress="'.$row["appointment_progress"].'">
                                            <td>'.htmlspecialchars($row["fullname"]).'</td>
                                            <td>'.date('M d, Y', strtotime($row["booking_date"])).'</td>
                                            <td>'.htmlspecialchars($row["mobile"]).'</td>
                                            <td><span class="'.$statusClass.'">'.ucfirst($row["status"]).'</span></td>
                                                    <td>
                                                        <form class="progress-form" onchange="updateProgress(this, '.$row["request_id"].')">
                                                            <select class="progress-select" name="progress">
                                                                <option value="preappointment" '.($row["appointment_progress"] == 'preappointment' ? 'selected' : '').'>Pre-appointment</option>
                                                                <option value="ongoing" '.($row["appointment_progress"] == 'ongoing' ? 'selected' : '').'>Ongoing</option>
                                                                <option value="done" '.($row["appointment_progress"] == 'done' ? 'selected' : '').'>Completed</option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <button onclick="showCancelConfirmation('.$row["request_id"].')" class="btn-cancel">
                                                    Cancel
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <button onclick="viewDetails('.$row["request_id"].')" class="btn-primary-soft btn">
                                                            View Details
                                                        </button>
                                                    </td>
                                                </tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Pending Requests Tab -->
                        <div id="pendingTab" class="tab-content">
                            <div class="abc scroll">
                                <table width="93%" class="sub-table scrolldown" border="0">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Full Name</th>
                                            <th class="table-headin">Booking Date</th>
                                            <th class="table-headin">Mobile</th>
                                            <th class="table-headin">Service</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingAppointments">
                                <?php
                                // Display pending appointments
                                if (empty($pending_appointments)) {
                                    echo '<tr>
                                        <td colspan="6">
                                            <center>
                                                <img src="../Images/notfound.svg" width="25%">
                                                <br>
                                                <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                                    No pending appointment requests found!
                                                </p>
                                            </center>
                                        </td>
                                    </tr>';
                                } else {
                                    foreach($pending_appointments as $row) {
                                        $statusClass = match($row["status"]) {
                                            'pending' => 'status-pending',
                                            'approved' => 'status-approved',
                                            'rejected' => 'status-rejected',
                                            default => ''
                                        };
                                        
                                        echo '<tr data-status="'.$row["status"].'" data-progress="'.$row["appointment_progress"].'">
                                            <td>'.htmlspecialchars($row["fullname"]).'</td>
                                            <td>'.date('M d, Y', strtotime($row["booking_date"])).'</td>
                                            <td>'.htmlspecialchars($row["mobile"]).'</td>
                                            <td>'.htmlspecialchars(ucwords(str_replace('_', ' ', $row["appointment_type"]))).'</td>
                                            <td><span class="'.$statusClass.'">'.ucfirst($row["status"]).'</span></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="viewDetails('.$row["request_id"].')" class="btn-primary-soft btn">
                                                        View Details
                                                    </button>
                                                    <form method="POST" style="display: flex; gap: 8px; margin: 0; padding: 0;">
                                                            <input type="hidden" name="request_id" value="'.$row["request_id"].'">
                                                        <button type="button" onclick="openInventoryModal('.$row["request_id"].', `'.htmlspecialchars($row["appointment_type"], ENT_QUOTES).'` )" class="btn-icon-approve btn-primary-soft btn" data-tooltip="Approve Appointment" title="Approve Appointment">
                                                            <i class="fas fa-check"></i>
                                                            </button>
                                                        <button type="submit" name="action" value="reject" class="btn-icon-reject btn-primary-soft btn" data-tooltip="Reject Appointment" title="Reject Appointment">
                                                            <i class="fas fa-times"></i>
                                                            </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>';
                                    }
                                }
                                ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- History & Logs Tab -->
                        <div id="historyTab" class="tab-content">
                            <div class="abc scroll">
                                <table width="93%" class="sub-table scrolldown" border="0">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Full Name</th>
                                            <th class="table-headin">Booking Date</th>
                                            <th class="table-headin">Mobile</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Progress</th>
                                            <th class="table-headin">Approved At</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyAppointments">
                                        <?php
                                        if (empty($history_appointments)) {
                                            echo '<tr>
                                                <td colspan="7">
                                                    <center>
                                                        <img src="../Images/notfound.svg" width="25%">
                                                        <br>
                                                        <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                                            No appointment history found!
                                                        </p>
                                                    </center>
                                                </td>
                                            </tr>';
                                        } else {
                                            foreach($history_appointments as $row) {
                                                $statusClass = match($row["status"]) {
                                                    'pending' => 'status-pending',
                                                    'approved' => 'status-approved',
                                                    'rejected' => 'status-rejected',
                                                    default => ''
                                                };
                                                
                                                $progressClass = match($row["appointment_progress"]) {
                                                    'preappointment' => 'progress-unavailable',
                                                    'ongoing' => 'progress-ongoing',
                                                    'done' => 'progress-done',
                                                    'cancelled' => 'progress-cancelled',
                                                    default => 'progress-unavailable'
                                                };
                                                
                                                echo '<tr data-status="'.$row["status"].'" data-progress="'.$row["appointment_progress"].'">
                                                    <td>'.htmlspecialchars($row["fullname"]).'</td>
                                                    <td>'.date('M d, Y', strtotime($row["booking_date"])).'</td>
                                                    <td>'.htmlspecialchars($row["mobile"]).'</td>
                                                    <td><span class="'.$statusClass.'">'.ucfirst($row["status"]).'</span></td>
                                                    <td><span class="progress-badge '.$progressClass.'">'.$row["progress_text"].'</span></td>
                                                    <td>'.($row["formatted_approved_at"] ?: 'N/A').'</td>
                                                    <td>
                                                        <button onclick="viewDetails('.$row["request_id"].')" class="btn-primary-soft btn">
                                                            View Details
                                                        </button>
                                            </td>
                                        </tr>';
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </center>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- View Details Modal -->
<div id="detailsModal" class="overlay">
    <div class="popup">
        <a class="close" href="#" onclick="closeDetailsModal()">&times;</a>
        <h2>Appointment Details</h2>
        <div style="display:flex; gap:8px; margin-bottom:10px;">
            <button id="btnShowAppointment" class="btn-primary-soft btn" onclick="showDetailsSection('appointment')">Appointment Details</button>
            <button id="btnShowPatient" class="btn-primary-soft btn" onclick="showDetailsSection('patient')">Patient Details</button>
        </div>
        <div id="modalContent">
            <!-- Content will be loaded here via JavaScript -->
        </div>
    </div>
</div>

<!-- Inventory Selection Modal -->
<div id="inventoryModalOverlay" class="overlay" style="display:none;">
    <div class="popup">
        <a class="close" href="#" onclick="closeInventoryModal(); return false;">&times;</a>
        <h2>Approve Appointment - Select Products</h2>
        <div style="color: #666; margin: 5px 0 15px 0; font-weight: 600;">Please select at least one product to proceed with the approval.</div>
        <div id="inventoryModalBody" style="margin-top:10px;">
            <div style="padding:10px;">Loading inventory options...</div>
        </div>
        <div style="margin-top:16px; display:flex; gap:10px; justify-content:flex-end;">
            <button id="approveWithProductsBtn" class="btn-primary btn" onclick="submitApproval()">Approve Appointment</button>
        </div>
    </div>
    <form id="approvalHiddenForm" method="POST" style="display:none;">
        <input type="hidden" name="request_id" id="approval_request_id" value="">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="selected_products" id="selected_products_input" value="">
    </form>
    <script>
        let currentRequestId = null;
        let currentService = '';
        function openInventoryModal(requestId, serviceType){
            currentRequestId = requestId;
            currentService = serviceType || '';
            document.getElementById('inventoryModalOverlay').style.display = 'block';
            document.getElementById('inventoryModalBody').innerHTML = '<div style="padding:10px;">Loading inventory options...</div>';
            fetch('appointment.php?ajax=inventory_options&request_id=' + encodeURIComponent(requestId))
                .then(r => r.json())
                .then(data => {
                    if (!data.success){
                        document.getElementById('inventoryModalBody').innerHTML = '<div style="color:#b00;">'+ (data.error || 'Failed to load inventory.') +'</div>';
                        return;
                    }
                    const items = data.items || [];
                    if (items.length === 0){
                        document.getElementById('inventoryModalBody').innerHTML = '<div style="color:#dc3545; padding:20px; text-align:center;"><i class="fas fa-exclamation-triangle"></i> No mapped inventory found for this service. Cannot approve appointment without products.</div>';
                        return;
                    }
                    let html = '<div style="margin-bottom:10px; text-align:right;">';
                    html += '<button type="button" onclick="toggleSelectAll()" class="btn-primary-soft btn" style="padding: 8px 16px; font-size: 14px;">Select All</button>';
                    html += '</div>';
                    html += '<div style="max-height:320px; overflow:auto;">';
                    html += '<table class="sub-table" style="width:100%">';
                    html += '<tr><th style="text-align:left;">Product</th><th>Category</th><th>Available</th><th>Use Qty</th><th>Select</th></tr>';
                    items.forEach((it, idx) => {
                        const defQty = Math.max(0, parseInt(it.default_quantity || 0, 10));
                        const metaData = JSON.stringify({product_id: it.product_id, table_name: it.table_name, id_field: it.id_field, product_name: it.product_name, product_category: it.category});
                        html += '<tr>'+
                            '<td>'+ (it.product_name || '') +'</td>'+
                            '<td style="text-align:center;">'+ (it.category || '') +'</td>'+
                            '<td style="text-align:center;">'+ (it.quantity || 0) +'</td>'+
                            '<td style="text-align:center;"><input type="number" min="0" max="'+ (it.quantity || 0) +'" value="'+ defQty +'" id="qty_'+idx+'" style="width:80px;"></td>'+
                            '<td style="text-align:center;"><input type="checkbox" id="sel_'+idx+'" class="product-checkbox"></td>'+
                            '<td style="display:none;"><input type="hidden" id="meta_'+idx+'" value="'+metaData.replace(/"/g,'&quot;')+'"></td>'+
                            '</tr>';
                    });
                    html += '</table>';
                    html += '</div>';
                    document.getElementById('inventoryModalBody').innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('inventoryModalBody').innerHTML = '<div style="color:#b00;">Error loading inventory.</div>';
                });
        }
        function closeInventoryModal(){
            document.getElementById('inventoryModalOverlay').style.display = 'none';
        }
        function toggleSelectAll(){
            const container = document.getElementById('inventoryModalBody');
            const checkboxes = container.querySelectorAll('input[type="checkbox"].product-checkbox');
            const selectAllBtn = event.target;
            
            if (!checkboxes || checkboxes.length === 0) return;
            
            // Check if all are selected
            let allSelected = true;
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    allSelected = false;
                }
            });
            
            // Toggle all checkboxes
            const newState = !allSelected;
            checkboxes.forEach(cb => {
                cb.checked = newState;
            });
            
            // Update button text
            selectAllBtn.textContent = newState ? 'Deselect All' : 'Select All';
        }
        function submitApproval(){
            const container = document.getElementById('inventoryModalBody');
            const table = container.querySelector('table');
            if (!table) {
                alert('No products available for this appointment.');
                return;
            }
            
            const checkboxes = container.querySelectorAll('input[type="checkbox"].product-checkbox');
            const items = [];
            let hasSelectedProducts = false;
            
            // Check all checkboxes using their IDs
            checkboxes.forEach((checkbox) => {
                if (checkbox && checkbox.checked) {
                    try {
                        // Extract index from checkbox ID (sel_0, sel_1, etc.)
                        const idx = checkbox.id.replace('sel_', '');
                        const qtyInput = document.getElementById('qty_'+idx);
                        const metaInput = document.getElementById('meta_'+idx);
                        
                        if (!qtyInput || !metaInput) {
                            console.error('Missing inputs for product index:', idx);
                            return;
                        }
                        
                        const meta = JSON.parse(metaInput.value);
                        const qty = parseInt(qtyInput.value || '0', 10);
                        
                        if (qty <= 0) {
                            alert('Please specify a quantity greater than 0 for selected products.');
                            return;
                        }
                        
                        hasSelectedProducts = true;
                        items.push({ ...meta, quantity: qty });
                    } catch(e) {
                        console.error('Error processing product:', e);
                    }
                }
            });
            
            if (!hasSelectedProducts) {
                alert('Please select at least one product and specify its quantity before approving the appointment.');
                return;
            }

            document.getElementById('approval_request_id').value = currentRequestId;
            document.getElementById('selected_products_input').value = JSON.stringify(items);
            closeInventoryModal();
            document.getElementById('approvalHiddenForm').submit();
        }
    </script>
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancelConfirmationModal" class="overlay">
    <div class="popup">
        <div class="cancel-confirm-modal">
            <h3>Cancel Appointment</h3>
            <p>Are you sure you want to cancel this appointment? This action cannot be undone.</p>
            <div class="cancel-buttons">
                <button class="btn-keep" onclick="closeCancelModal()">Keep Appointment</button>
                <form id="cancelForm" method="POST" style="display: inline;">
                    <input type="hidden" name="request_id" id="cancelRequestId">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn-confirm-cancel">Yes, Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Message -->
<div id="successMessage" class="success-message" style="display: none;">
    <i class="fas fa-check-circle"></i> Progress updated successfully!
</div>

<script>
// Tab navigation
function showAppointmentTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content and activate button
    document.getElementById(tabName + 'Tab').classList.add('active');
    event.currentTarget.classList.add('active');
}

// View appointment details
let _detailsData = null;
function viewDetails(requestId) {
    fetch('get-request-details.php?id=' + requestId)
        .then(response => response.json())
        .then(data => {
            _detailsData = data;
            renderAppointmentDetails();
            document.getElementById('detailsModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalContent').innerHTML = '<p>Error loading appointment details.</p>';
            document.getElementById('detailsModal').style.display = 'block';
        });
}

function renderAppointmentDetails(){
    if(!_detailsData) return;
    const d = _detailsData;
    const idType = d.id_type_formatted || (d.id_type ? (d.id_type.charAt(0).toUpperCase()+d.id_type.slice(1)) : '---');
    const html = `
        <div style="padding: 0 20px;">
            <div class="detail-row"><span class="detail-label">Service:</span><span>${d.appointment_type_formatted}</span></div>
            <div class="detail-row"><span class="detail-label">Selected Doctor:</span><span>${d.selected_doctor_name || 'Not specified'}</span></div>
            <div class="detail-row"><span class="detail-label">Duration:</span><span>${d.duration_formatted}</span></div>
            <div class="detail-row"><span class="detail-label">Schedule:</span><span>${d.appointment_time_formatted || '---'}</span></div>
            <div class="detail-row"><span class="detail-label">ID Type:</span><span>${idType}</span></div>
            <div class="detail-row"><span class="detail-label">Booking Date:</span><span>${d.booking_date}</span></div>
            <div class="detail-row"><span class="detail-label">Status:</span><span class="status-${d.status.toLowerCase()}">${d.status.toUpperCase()}</span></div>
            ${d.approved_at ? (`<div class="detail-row"><span class="detail-label">Approved At:</span><span>${d.approved_at}</span></div>`) : ''}
            <div class="detail-row"><span class="detail-label">Progress:</span><span class="progress-badge progress-${d.appointment_progress}">${d.progress_text}</span></div>
            ${d.id_image_holding && d.id_image_holding !== '' ? (`<div class="detail-row"><span class="detail-label">Image of Holding ID:</span><br><img src="../uploads/id_images/${d.id_image_holding}" class="id-image" alt="Holding ID"></div>`) : ''}
            ${d.id_image_only && d.id_image_only !== '' ? (`<div class="detail-row"><span class="detail-label">Image of ID Only:</span><br><img src="../uploads/id_images/${d.id_image_only}" class="id-image" alt="ID Only"></div>`) : ''}
        </div>`;
    document.getElementById('modalContent').innerHTML = html;
}

function renderPatientDetails(){
    if(!_detailsData) return;
    const d = _detailsData;
    const html = `
        <div style="padding: 0 20px;">
            <div class="detail-row"><span class="detail-label">Full Name:</span><span>${d.fullname}</span></div>
            <div class="detail-row"><span class="detail-label">Mobile:</span><span>${d.mobile || '---'}</span></div>
            <div class="detail-row"><span class="detail-label">Address:</span><span>${d.address || '---'}</span></div>
        </div>`;
    document.getElementById('modalContent').innerHTML = html;
}

function showDetailsSection(which){
    if(which === 'patient'){ renderPatientDetails(); } else { renderAppointmentDetails(); }
}

// Close details modal
function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Show cancel confirmation modal
function showCancelConfirmation(requestId) {
    document.getElementById('cancelRequestId').value = requestId;
    document.getElementById('cancelConfirmationModal').style.display = 'block';
}

// Close cancel confirmation modal
function closeCancelModal() {
    document.getElementById('cancelConfirmationModal').style.display = 'none';
}

// Update appointment progress via AJAX
function updateProgress(form, requestId) {
    const progress = form.progress.value;
    
    fetch('appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'progress=' + encodeURIComponent(progress) + '&request_id=' + encodeURIComponent(requestId) + '&ajax=true'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const successMsg = document.getElementById('successMessage');
            successMsg.style.display = 'block';
            
            // Hide success message after 3 seconds
            setTimeout(() => {
                successMsg.style.display = 'none';
            }, 3000);
            
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            alert('Error updating progress: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating progress');
    });
}

// Search functionality
// Search functionality - ADD NULL CHECK
const searchBtn = document.getElementById('searchBtn');
if (searchBtn) {
    searchBtn.addEventListener('click', function() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const activeTab = document.querySelector('.tab-content.active');
        if (!activeTab) return;
        const tableBody = activeTab.querySelector('tbody');
        if (!tableBody) return;
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const detailsModal = document.getElementById('detailsModal');
    const cancelModal = document.getElementById('cancelConfirmationModal');
    
    if (event.target === detailsModal) {
        closeDetailsModal();
    }
    if (event.target === cancelModal) {
        closeCancelModal();
    }
}

// Handle escape key to close modals
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDetailsModal();
        closeCancelModal();
    }
});
</script>
</body>
</html>