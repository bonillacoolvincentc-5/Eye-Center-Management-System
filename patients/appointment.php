<?php
session_start();

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

include("../connection.php");

// Fetch patient info
$stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$result = $stmt->get_result();
$userinfo = $result->fetch_assoc();

// NEW: profile image path
$profileImage = !empty($userinfo['ProfileImage']) ? '../Images/profiles/' . $userinfo['ProfileImage'] : '../Images/user.png';

// Update the query to include appointment_progress and order by status priority
$sql = "SELECT 
    ar.request_id, ar.patient_id, ar.booking_date, ar.appointment_time, ar.appointment_type, ar.duration, ar.id_type, ar.status, ar.approved_at, ar.created_at, ar.id_image_holding, ar.id_image_only, ar.appointment_progress,
    CASE 
        WHEN ar.status = 'pending' THEN 'Pending Review'
        WHEN ar.status = 'approved' THEN 'Approved'
        WHEN ar.status = 'rejected' THEN 'Rejected'
        ELSE ar.status 
    END as status_text,
    CASE
        WHEN ar.appointment_progress = 'preappointment' THEN 'Pre-appointment'
        WHEN ar.appointment_progress = 'ongoing' THEN 'Ongoing'
        WHEN ar.appointment_progress = 'done' THEN 'Completed'
        WHEN ar.appointment_progress = 'cancelled' THEN 'Cancelled'
        ELSE 'Unavailable'
    END as progress_text,
    CASE 
        WHEN ar.status = 'pending' THEN 1
        WHEN ar.status = 'approved' THEN 2
        WHEN ar.status = 'rejected' THEN 3
        ELSE 4
    END as status_order
FROM tbl_appointment_requests ar
INNER JOIN tbl_patients p ON ar.patient_id = p.Patient_id
WHERE p.Email = ?
ORDER BY status_order ASC, ar.booking_date DESC";

$stmt = $database->prepare($sql);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments</title>
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pappointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
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

        /* Mobile menu animations */
        @keyframes slideInLeft {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutLeft {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(-100%);
                opacity: 0;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
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

/* Modal styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: transparent;
    display: none;
    justify-content: center;
    align-items: center;
    pointer-events: none; /* Allow clicks to pass through when hidden */
}

.modal-overlay[style*="display: flex"] {
    pointer-events: auto; /* Enable clicks when modal is shown */
}

.modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    display: none;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

/* Add smooth animation for modal appearance */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, -48%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

/* Apply animation when modal is shown */
.modal.show {
    animation: modalFadeIn 0.3s ease forwards;
}

/* Update the detail rows styling */
.detail-row {
    display: flex;
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    align-items: center;
}

.detail-label {
    font-weight: 600;
    width: 150px;
    color: #2d3436;
    flex-shrink: 0;
}

/* Update modal content container */
#modalContent {
    margin: 20px 0;
}

/* For the cancel modal */
.cancel-modal {
    text-align: center;
    padding: 20px;
}

.cancel-btn-group {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

/* Scrollbar styling for modal */
.modal::-webkit-scrollbar {
    width: 8px;
}

.modal::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modal::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.modal::-webkit-scrollbar-thumb:hover {
    background: #555;
}
        /* Mobile hamburger + centered layout */
        @media (max-width: 992px){
            .mobile-header{ display:flex !important; align-items:center; justify-content:space-between; padding:12px 16px; background:#fff; border-bottom:1px solid #eaeaea; position:sticky; top:0; z-index:3000; width:100%; box-sizing:border-box; max-width:560px; margin:0 auto; }
            .hamburger{ 
                width:28px; 
                height:22px; 
                position:relative; 
                cursor:pointer; 
                z-index:3001; 
                pointer-events:auto; 
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .hamburger span{ 
                position:absolute; 
                left:0; 
                right:0; 
                height:3px; 
                background:#333; 
                border-radius:2px; 
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                transform-origin: center;
            }
            .hamburger span:nth-child(1){ top:0; }
            .hamburger span:nth-child(2){ top:9px; }
            .hamburger span:nth-child(3){ bottom:0; }
            
            /* Hamburger animation when menu is open */
            .menu.open ~ .mobile-header .hamburger span:nth-child(1) {
                transform: rotate(45deg) translate(6px, 6px);
            }
            .menu.open ~ .mobile-header .hamburger span:nth-child(2) {
                opacity: 0;
                transform: scaleX(0);
            }
            .menu.open ~ .mobile-header .hamburger span:nth-child(3) {
                transform: rotate(-45deg) translate(6px, -6px);
            }
            .mobile-title{ font-weight:600; color:#161c2d; }
            .container{ height:auto; flex-direction:column; }
            .menu{ 
                width:260px; 
                height:100vh; 
                position:fixed; 
                top:0; 
                left:-280px; 
                background:#fff; 
                z-index:1002; 
                overflow-y:auto; 
                transition:left 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
                box-shadow:2px 0 12px rgba(0,0,0,.06);
                transform: translateZ(0); /* Hardware acceleration */
            }
            .menu.open{ 
                left:0; 
                animation: slideInLeft 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            }
            
            /* Menu items animation */
            .menu .menu-row {
                opacity: 0;
                transform: translateX(-20px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .menu.open .menu-row {
                opacity: 1;
                transform: translateX(0);
            }
            
            .menu.open .menu-row:nth-child(1) { transition-delay: 0.1s; }
            .menu.open .menu-row:nth-child(2) { transition-delay: 0.15s; }
            .menu.open .menu-row:nth-child(3) { transition-delay: 0.2s; }
            .menu.open .menu-row:nth-child(4) { transition-delay: 0.25s; }
            .menu.open .menu-row:nth-child(5) { transition-delay: 0.3s; }
            .dash-body{ width:100% !important; padding:15px; max-width:560px; margin:0 auto; box-sizing:border-box; }
            .overlay{ 
                display:none; 
                position:fixed; 
                top: 0;
                left: 260px;
                right: 0;
                bottom: 0;
                background:rgba(0,0,0,.35); 
                z-index:1000; 
                cursor: pointer;
                opacity: 0;
                transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                transform: translateZ(0); /* Hardware acceleration */
            }
            .overlay.show{ 
                display:block; 
                opacity: 1;
                animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            }
        }
        @media (max-width: 768px){ 
            html, body{ overflow-x:hidden; } 
            
            /* Mobile Header and Layout */
            .dash-body {
                padding: 10px !important;
            }
            
            /* Mobile Page Title and Search */
            .dash-body table tr:first-child td {
                padding: 0 !important;
            }
            
            .dash-body table tr:first-child td > div {
                flex-direction: column !important;
                gap: 15px;
                align-items: stretch !important;
            }
            
            .dash-body table tr:first-child td > div > p {
                font-size: 20px !important;
                text-align: center;
                margin: 0;
            }
            
            .dash-body table tr:first-child td > div > div {
                flex-direction: column !important;
                gap: 10px;
                width: 100%;
            }
            
            #searchInput {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important;
                border-radius: 8px !important;
                border: 2px solid #e5e7eb !important;
            }
            
            #searchBtn {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important;
                border-radius: 8px !important;
            }
            
            /* Hide desktop table on mobile */
            .sub-table {
                display: none !important;
            }
            
            /* Mobile Card Layout */
            .mobile-appointments-container {
                display: block !important;
            }
            
            .appointment-card {
                background: white;
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 16px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                border: 1px solid #e5e7eb;
            }
            
            .appointment-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .appointment-date {
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
            }
            
            .appointment-type {
                font-size: 14px;
                color: #6b7280;
                background: #f3f4f6;
                padding: 4px 8px;
                border-radius: 6px;
            }
            
            .appointment-status-row {
                display: flex;
                gap: 8px;
                margin-bottom: 12px;
                flex-wrap: wrap;
            }
            
            .status-badge,
            .progress-badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .status-pending {
                background: #fef3c7;
                color: #d97706;
            }
            
            .status-approved {
                background: #d1fae5;
                color: #059669;
            }
            
            .status-rejected {
                background: #fee2e2;
                color: #dc2626;
            }
            
            .progress-unavailable {
                background: #f3f4f6;
                color: #6b7280;
            }
            
            .progress-ongoing {
                background: #dbeafe;
                color: #2563eb;
            }
            
            .progress-done {
                background: #d1fae5;
                color: #059669;
            }
            
            .progress-cancelled {
                background: #fee2e2;
                color: #dc2626;
            }
            
            .appointment-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .btn-view,
            .btn-cancel {
                padding: 8px 16px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                flex: 1;
                min-width: 120px;
            }
            
            .btn-view {
                background: #3b82f6;
                color: white;
            }
            
            .btn-view:hover {
                background: #2563eb;
                transform: translateY(-1px);
            }
            
            .btn-cancel {
                background: #ef4444;
                color: white;
            }
            
            .btn-cancel:hover {
                background: #dc2626;
                transform: translateY(-1px);
            }
            
            /* Mobile Modal Fixes */
            .modal {
                width: 95% !important;
                max-width: none !important;
                margin: 10px auto !important;
                padding: 20px !important;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .modal h2 {
                font-size: 1.3rem;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .detail-row {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px;
                padding: 12px !important;
            }
            
            .detail-label {
                width: auto !important;
                font-size: 14px;
                color: #6b7280;
            }
            
            .detail-row span:not(.detail-label) {
                font-size: 16px;
                color: #1f2937;
                word-break: break-word;
            }
            
            .btn-group {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }
            
            .btn-close {
                flex: 1;
                padding: 12px;
                background: #6b7280;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }
            
            .btn-close:hover {
                background: #4b5563;
            }
            
            /* Cancel Modal Mobile */
            .cancel-modal {
                padding: 15px !important;
            }
            
            .cancel-modal h3 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }
            
            .cancel-modal p {
                font-size: 14px;
                line-height: 1.5;
                margin-bottom: 20px;
            }
            
            .cancel-btn-group {
                flex-direction: column !important;
                gap: 10px !important;
            }
            
            .cancel-btn-group button {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important;
                border-radius: 8px !important;
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
        <div class="mobile-title">My Bookings</div>
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
                                <td width="30%" style="padding-left:20px">
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo substr($userinfo['Fname'],0,13) ?></p>
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
                    <td class="menu-btn menu-icon-home">
                        <a href="index.php" class="non-style-link-menu">
                            <div><p class="menu-text">Home</p></div>
                        </a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu">
                            <div><p class="menu-text">Book Appointment</p></div>
                        </a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment menu-active menu-icon-appoinment-active">
                        <a href="appointment.php" class="non-style-link-menu non-style-link-menu-active">
                            <div><p class="menu-text">My Bookings</p></div>
                        </a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu">
                            <div><p class="menu-text">Settings</p></div>
                        </a>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="dash-body" id="content">
            <table border="0" width="100%" style="padding: 0;margin: 0;">
                <tr>
                    <td>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 12px;">
                            <p style="font-size: 23px;font-weight: 600;">My Appointment History</p>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="searchInput" placeholder="Search appointments..." 
                                    style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 250px;">
                                <button id="searchBtn" 
                                    style="padding: 8px 15px; 
                                    background-color: #2196F3; 
                                    color: white; 
                                    border: none; 
                                    border-radius: 4px; 
                                    cursor: pointer;
                                    transition: background-color 0.3s;">
                                    <i class="fa fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div style="padding: 10px 12px">
                            <!-- Desktop Table -->
                            <table width="100%" class="sub-table" border="0" id="appointmentsTable">
                                <thead>
                                    <tr>
                                        <th class="table-headin">Booking Date</th>
                                        <th class="table-headin">Status</th>
                                        <th class="table-headin">Progress</th>
                                        <th class="table-headin">Appointment Type</th>
                                        <th class="table-headin">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($appointments->num_rows > 0):
                                        while($row = $appointments->fetch_assoc()): 
                                            $statusClass = match($row['status']) {
                                                'pending' => 'status-pending',
                                                'approved' => 'status-approved',
                                                'rejected' => 'status-rejected',
                                                default => ''
                                            };
                                            
                                            $progressClass = match($row['appointment_progress']) {
                                                'preappointment' => 'progress-unavailable',
                                                'ongoing' => 'progress-ongoing',
                                                'done' => 'progress-done',
                                                'cancelled' => 'progress-cancelled',
                                                default => 'progress-unavailable'
                                            };
                                            
                                            // Determine if cancel button should be enabled
                                            $canCancel = ($row['status'] == 'approved' && 
                                                         $row['appointment_progress'] != 'cancelled' &&
                                                         $row['appointment_progress'] != 'done');
                                    ?>
                                    <tr class="fade-in" style="animation-delay: calc(0.1s * <?php echo $index; ?>)">
                                        <td><?php echo date('M d, Y', strtotime($row['booking_date'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $row['status_text']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="progress-badge <?php echo $progressClass; ?>">
                                                <?php echo $row['progress_text']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $row['appointment_type'])); ?></td>
                                        <td>
                                            <button class="btn-view btn-hover-effect pulse-on-hover" onclick="viewDetails(<?php echo $row['request_id']; ?>)">
                                                View Details
                                            </button>
                                            <?php if ($canCancel): ?>
                                            <button class="btn-cancel" onclick="showCancelModal(<?php echo $row['request_id']; ?>)">
                                                Cancel
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; 
                                    else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:20px;">
                                            <img src="../Images/notfound.svg" width="25%">
                                            <br>
                                            <p style="padding-top:10px;">No appointment history found</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Mobile Card Layout -->
                            <div class="mobile-appointments-container" style="display: none;">
                                <?php 
                                // Reset the result pointer to loop through appointments again for mobile cards
                                $appointments->data_seek(0);
                                if ($appointments->num_rows > 0):
                                    $index = 0;
                                    while($row = $appointments->fetch_assoc()): 
                                        $statusClass = match($row['status']) {
                                            'pending' => 'status-pending',
                                            'approved' => 'status-approved',
                                            'rejected' => 'status-rejected',
                                            default => ''
                                        };
                                        
                                        $progressClass = match($row['appointment_progress']) {
                                            'preappointment' => 'progress-unavailable',
                                            'ongoing' => 'progress-ongoing',
                                            'done' => 'progress-done',
                                            'cancelled' => 'progress-cancelled',
                                            default => 'progress-unavailable'
                                        };
                                        
                                        // Determine if cancel button should be enabled
                                        $canCancel = ($row['status'] == 'approved' && 
                                                     $row['appointment_progress'] != 'cancelled' &&
                                                     $row['appointment_progress'] != 'done');
                                ?>
                                <div class="appointment-card fade-in" style="animation-delay: calc(0.1s * <?php echo $index; ?>)">
                                    <div class="appointment-card-header">
                                        <div class="appointment-date">
                                            <?php echo date('M d, Y', strtotime($row['booking_date'])); ?>
                                        </div>
                                        <div class="appointment-type">
                                            <?php echo ucwords(str_replace('_', ' ', $row['appointment_type'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="appointment-status-row">
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $row['status_text']; ?>
                                        </span>
                                        <span class="progress-badge <?php echo $progressClass; ?>">
                                            <?php echo $row['progress_text']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="appointment-actions">
                                        <button class="btn-view" onclick="viewDetails(<?php echo $row['request_id']; ?>)">
                                            View Details
                                        </button>
                                        <?php if ($canCancel): ?>
                                        <button class="btn-cancel" onclick="showCancelModal(<?php echo $row['request_id']; ?>)">
                                            Cancel
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php 
                                        $index++;
                                    endwhile; 
                                else: ?>
                                <div class="appointment-card" style="text-align: center; padding: 40px 20px;">
                                    <img src="../Images/notfound.svg" width="25%" style="margin-bottom: 20px;">
                                    <p style="color: #6b7280; font-size: 16px;">No appointment history found</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal-overlay" id="modalOverlay"></div>
    <div class="modal pop-in" id="detailsModal">
        <h2 style="margin-bottom: 20px;">Appointment Details</h2>
        <div id="modalContent"></div>
        <div class="btn-group">
            <button class="btn-close" onclick="closeModal()">Close</button>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal" id="cancelModal">
        <div class="cancel-modal">
            <h3>Cancel Appointment</h3>
            <p>Are you sure you want to cancel this appointment? This action cannot be undone.</p>
            <div class="cancel-btn-group">
                <button class="btn-close" onclick="closeCancelModal()">No, Keep It</button>
                <button class="btn-cancel" id="confirmCancelBtn">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <script>
    let currentAppointmentId = null;

    function viewDetails(id) {
    fetch('get-appointment-details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            const modalOverlay = document.getElementById('modalOverlay');
            const detailsModal = document.getElementById('detailsModal');
            const modalContent = document.getElementById('modalContent');
            
            let htmlContent = `
                <div class="detail-row">
                    <span class="detail-label">Booking Date:</span>
                    <span>${data.booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Schedule:</span>
                    <span>${data.appointment_time || 'Not specified'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Full Name:</span>
                    <span>${data.fullname}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span>${data.address || 'Not provided'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Mobile:</span>
                    <span>${data.mobile}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span>${data.appointment_type}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Doctor:</span>
                    <span>${data.doctor_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Specialty:</span>
                    <span>${data.doctor_specialty}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ID Type:</span>
                    <span>${data.id_type ? (data.id_type.charAt(0).toUpperCase() + data.id_type.slice(1)) : 'Not provided'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span>${data.duration || 'Not specified'} minutes</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge ${getStatusClass(data.status)}">${data.status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Progress:</span>
                    <span class="progress-badge ${getProgressClass(data.progress)}">${data.progress}</span>
                </div>
            `;
            
            // Add helper functions for status and progress classes
            function getStatusClass(status) {
                switch(status.toLowerCase()) {
                    case 'pending review': return 'status-pending';
                    case 'approved': return 'status-approved';
                    case 'rejected': return 'status-rejected';
                    default: return '';
                }
            }
            
            function getProgressClass(progress) {
                switch(progress.toLowerCase()) {
                    case 'pre-appointment': return 'progress-unavailable';
                    case 'ongoing': return 'progress-ongoing';
                    case 'completed': return 'progress-done';
                    case 'cancelled': return 'progress-cancelled';
                    default: return 'progress-unavailable';
                }
            }
            
            if (data.id_image_holding || data.id_image_only) {
                htmlContent += `
                    <div class="detail-row" style="flex-direction: column; align-items: flex-start;">
                        <span class="detail-label">ID Verification Images:</span>
                        <div style="display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap;">
                `;
                
                if (data.id_image_holding) {
                    htmlContent += `
                        <div style="text-align: center;">
                            <div style="font-weight: 500; margin-bottom: 5px;">You Holding ID</div>
                            <img src="${data.id_image_holding}" 
                                 style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;"
                                 onerror="this.style.display='none'">
                        </div>
                    `;
                }
                
                if (data.id_image_only) {
                    htmlContent += `
                        <div style="text-align: center;">
                            <div style="font-weight: 500; margin-bottom: 5px;">ID Only</div>
                            <img src="${data.id_image_only}" 
                                 style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;"
                                 onerror="this.style.display='none'">
                        </div>
                    `;
                }
                
                htmlContent += `
                        </div>
                    </div>
                `;
            } else {
                htmlContent += `
                    <div class="detail-row">
                        <span class="detail-label">ID Images:</span>
                        <span>No images uploaded</span>
                    </div>
                `;
            }
            
            modalContent.innerHTML = htmlContent;
            
            // Close mobile menu if open to prevent conflicts
            var sidebar = document.getElementById('sidebar');
            var mobileOverlay = document.getElementById('overlay');
            if (sidebar && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                if (mobileOverlay) {
                    mobileOverlay.classList.remove('show');
                    mobileOverlay.style.display = 'none';
                }
            }
            
            // Show the modal with animation
            modalOverlay.style.display = 'flex';
            detailsModal.style.display = 'block';
            detailsModal.classList.add('show');
            
            // Prevent body scrolling when modal is open
            document.body.style.overflow = 'hidden';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load appointment details');
        });
}

    function showCancelModal(id) {
        currentAppointmentId = id;
        
        // Close mobile menu if open to prevent conflicts
        var sidebar = document.getElementById('sidebar');
        var mobileOverlay = document.getElementById('overlay');
        if (sidebar && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            if (mobileOverlay) {
                mobileOverlay.classList.remove('show');
                mobileOverlay.style.display = 'none';
            }
        }
        
        document.getElementById('modalOverlay').style.display = 'block';
        document.getElementById('cancelModal').style.display = 'block';
    }

    function closeCancelModal() {
        document.getElementById('modalOverlay').style.display = 'none';
        document.getElementById('cancelModal').style.display = 'none';
        currentAppointmentId = null;
    }

    function closeModal() {
        const modalOverlay = document.getElementById('modalOverlay');
        const detailsModal = document.getElementById('detailsModal');
        const cancelModal = document.getElementById('cancelModal');
        
        modalOverlay.style.display = 'none';
        detailsModal.style.display = 'none';
        cancelModal.style.display = 'none';
        detailsModal.classList.remove('show');
        
        // Restore body scrolling
        document.body.style.overflow = 'auto';
        
        currentAppointmentId = null;
    }

    // Handle cancel confirmation
    document.getElementById('confirmCancelBtn').addEventListener('click', function() {
        if (!currentAppointmentId) return;
        
        fetch('cancel-appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + currentAppointmentId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Appointment cancelled successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to cancel appointment');
        });
    });

    // Search functionality
    function performSearch() {
        var filter = document.getElementById('searchInput').value.toLowerCase();
        
        // Search in desktop table
        var rows = document.querySelectorAll('#appointmentsTable tbody tr');
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
        });
        
        // Search in mobile cards
        var cards = document.querySelectorAll('.appointment-card');
        cards.forEach(function(card) {
            var text = card.textContent.toLowerCase();
            card.style.display = text.indexOf(filter) > -1 ? '' : 'none';
        });
    }

    document.getElementById('searchBtn').addEventListener('click', performSearch);
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    document.getElementById('searchInput').addEventListener('keyup', performSearch);
    </script>
<script>
// Ensure content visible after nav on mobile
(function(){
  function scrollToContent(){ var el=document.getElementById('content'); if(!el) return; if (window.matchMedia('(max-width: 768px)').matches){ el.scrollIntoView({behavior:'smooth',block:'start'});} }
  window.addEventListener('load',scrollToContent);
  document.querySelectorAll('.menu a').forEach(function(a){ a.addEventListener('click', function(){ setTimeout(scrollToContent,150); }); });
})();
// Mobile hamburger toggle - Simple working version
(function(){
  var header = document.querySelector('.mobile-header');
  var hamburger = document.getElementById('hamburger');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('overlay');
  function syncHeader(){
    if (window.matchMedia('(max-width: 992px)').matches) {
      if (header) header.style.display = 'flex';
      // Ensure sidebar is closed initially on mobile
      if (sidebar) sidebar.classList.remove('open');
      if (overlay){ overlay.classList.remove('show'); overlay.style.display = 'none'; }
      document.body.style.overflow = '';
      
      // Ensure modal overlay is hidden on mobile
      var modalOverlay = document.getElementById('modalOverlay');
      if (modalOverlay) {
        modalOverlay.style.display = 'none';
      }
    } else {
      if (header) header.style.display = 'none';
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('show');
      if (overlay) overlay.style.display = 'none';
      
      // Ensure modal overlay is hidden on desktop too
      var modalOverlay = document.getElementById('modalOverlay');
      if (modalOverlay) {
        modalOverlay.style.display = 'none';
      }
    }
  }
  function openMenu(){
    if (!sidebar) return;
    
    // Hide modal overlay to prevent conflicts
    var modalOverlay = document.getElementById('modalOverlay');
    if (modalOverlay) {
      modalOverlay.style.display = 'none';
      modalOverlay.style.visibility = 'hidden';
      modalOverlay.style.opacity = '0';
      modalOverlay.style.background = 'transparent';
    }
    
    sidebar.classList.add('open');
    if (overlay){ overlay.classList.add('show'); overlay.style.display = 'block'; }
    if (hamburger) hamburger.setAttribute('aria-expanded','true');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu(){
    if (!sidebar) return;
    sidebar.classList.remove('open');
    if (overlay){ overlay.classList.remove('show'); overlay.style.display = 'none'; }
    if (hamburger) hamburger.setAttribute('aria-expanded','false');
    document.body.style.overflow = '';
    
    // Aggressively ensure modal overlay stays hidden
    var modalOverlay = document.getElementById('modalOverlay');
    if (modalOverlay) {
      modalOverlay.style.display = 'none';
      modalOverlay.style.visibility = 'hidden';
      modalOverlay.style.opacity = '0';
      modalOverlay.style.background = 'transparent';
    }
  }
  if (hamburger){
    hamburger.addEventListener('click', function(){
      if (sidebar.classList.contains('open')) closeMenu(); else openMenu();
    });
  }
  if (overlay){ overlay.addEventListener('click', closeMenu); }
  window.addEventListener('resize', syncHeader);
  window.addEventListener('load', syncHeader);
  // Close on menu link click (mobile)
  document.querySelectorAll('.menu a').forEach(function(a){
    a.addEventListener('click', function(){
      if (window.matchMedia('(max-width: 992px)').matches) closeMenu();
    });
  });
})();
</script>
    <script src="../js/animations.js"></script>
</body>
</html>