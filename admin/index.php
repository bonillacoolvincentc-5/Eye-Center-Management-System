<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

// Import database
include("../connection.php");

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

// Function to get inventory status
function getInventoryStatus($database) {
    $lowStockCount = 0;
    $mediumStockCount = 0;
    
    // Check surgical equipment
    $sql = "SELECT COUNT(*) as count FROM tbl_surgical_equipment WHERE quantity <= 10";
    $result = $database->query($sql);
    if ($result) {
        $lowStockCount += $result->fetch_assoc()['count'];
    }
    
    $sql = "SELECT COUNT(*) as count FROM tbl_surgical_equipment WHERE quantity > 10 AND quantity <= 25";
    $result = $database->query($sql);
    if ($result) {
        $mediumStockCount += $result->fetch_assoc()['count'];
    }
    
    // Check medicines
    $sql = "SELECT COUNT(*) as count FROM tbl_medicines WHERE quantity <= 10";
    $result = $database->query($sql);
    if ($result) {
        $lowStockCount += $result->fetch_assoc()['count'];
    }
    
    $sql = "SELECT COUNT(*) as count FROM tbl_medicines WHERE quantity > 10 AND quantity <= 25";
    $result = $database->query($sql);
    if ($result) {
        $mediumStockCount += $result->fetch_assoc()['count'];
    }
    
    // Check glass frames
    $sql = "SELECT COUNT(*) as count FROM tbl_glass_frames WHERE quantity <= 5";
    $result = $database->query($sql);
    if ($result) {
        $lowStockCount += $result->fetch_assoc()['count'];
    }
    
    $sql = "SELECT COUNT(*) as count FROM tbl_glass_frames WHERE quantity > 5 AND quantity <= 15";
    $result = $database->query($sql);
    if ($result) {
        $mediumStockCount += $result->fetch_assoc()['count'];
    }
    
    // Determine status
    if ($lowStockCount > 0) {
        return [
            'status' => 'low',
            'count' => $lowStockCount,
            'text' => 'Low Stock',
            'color' => '#dc3545'
        ];
    } elseif ($mediumStockCount > 0) {
        return [
            'status' => 'medium',
            'count' => $mediumStockCount,
            'text' => 'Med Stock',
            'color' => '#ffc107'
        ];
    } else {
        return [
            'status' => 'good',
            'count' => 0,
            'text' => 'Good Stock',
            'color' => '#28a745'
        ];
    }
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <title>Dashboard</title>
    <style>
        .dashbord-tables{
            animation: transitionIn-Y-over 0.5s;
        }
        .filter-container{
            animation: transitionIn-Y-bottom  0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
        
        .chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 20px 0;
    height: 500px; /* Increased height to accommodate chart and info */
    display: flex;
    flex-direction: column;
    overflow: visible; /* Changed from hidden to visible */
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.chart-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.chart-canvas-container {
    flex: 1; /* Take remaining space */
    min-height: 300px; /* Minimum height for chart */
    position: relative;
}

.chart-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
    font-size: 14px;
    color: #666;
    flex-shrink: 0; /* Prevent info from shrinking */
}
        
        .time-filter {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }
        
        .export-btn {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .export-btn:hover {
            background: #0056b3;
        }
        
        .chart-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .pending-badge {
            background: #ffc107;
            color: #212529;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 35px;
            right: -150px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 300px;
            max-width: 400px;
            max-height: 500px;
            z-index: 1000;
            display: none;
            opacity: 1 !important;
            pointer-events: auto;
            overflow: hidden;
            flex-direction: column;
        }
        
        .notification-dropdown[style*="display: block"] {
            display: flex !important;
        }
        
        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #333;
            opacity: 1 !important;
            background: white;
            flex-shrink: 0;
        }
        
        .notification-header:hover {
            opacity: 1 !important;
        }
        
        #notificationList {
            overflow-y: auto;
            overflow-x: hidden;
            max-height: 400px;
            flex: 1;
        }
        
        #notificationList::-webkit-scrollbar {
            width: 6px;
        }
        
        #notificationList::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #notificationList::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        #notificationList::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            opacity: 1 !important;
            transition: background-color 0.2s ease;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
            opacity: 1 !important;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-time {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .notification-bell {
            transition: transform 0.2s ease;
        }
        
        .notification-bell:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .notification-bell:hover svg {
            fill: #007bff;
        }
        
        
        .notification-item.read {
            opacity: 1;
            background: #f8f9fa;
            color: #666;
        }
        
        .notification-item.unread {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
            opacity: 1;
        }
        
        .notification-item.unread:hover {
            background: #ffe69c;
            opacity: 1 !important;
        }
        
        .mark-read-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            cursor: pointer;
            margin-left: 5px;
        }
        
        .mark-read-btn:hover {
            background: #218838;
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
                                <td width="30%" style="padding-left:20px" >
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
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
                    <td class="menu-btn menu-icon-dashbord menu-active menu-icon-dashbord-active" >
                        <a href="index.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Dashboard</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor ">
                        <a href="doctors.php" class="non-style-link-menu "><div><p class="menu-text">Doctors</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Schedule</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointment</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
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
        <div class="dash-body" style="margin-top: 15px">
            <table border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;" >
                <tr>
                    <td colspan="2" class="nav-bar" >
                        <form action="doctors.php" method="post" class="header-search">
                            <input type="search" name="search" class="input-text header-searchbar" placeholder="Search Doctor's name or Email" list="doctors">&nbsp;&nbsp;
                            <?php
                                echo '<datalist id="doctors">';
                                $list11 = $database->query("SELECT first_name, last_name, docemail FROM tbl_doctor;");
                                while ($row00 = $list11->fetch_assoc()) {
                                    // Compose full name from parts
                                    $fullname = htmlspecialchars($row00["first_name"] . ' ' . $row00["last_name"]);
                                    $c = $row00["docemail"];
                                    echo "<option value='$fullname'><br/>";
                                    echo "<option value='$c'><br/>";
                                }
                                echo ' </datalist>';
                            ?>
                            <input type="Submit" value="Search" class="login-btn btn-primary-soft btn" style="padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;">
                        </form>
                    </td>
                    <td width="15%">
                        <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                            Today's Date
                        </p>
                        <p class="heading-sub12" style="padding: 0;margin: 0;">
                            <?php 
                                date_default_timezone_set('Asia/Manila');
                                $today = date('Y-m-d');
                                echo $today;

                                $patientrow = $database->query("SELECT * FROM tbl_patients;");
                                $doctorrow = $database->query("SELECT * FROM tbl_doctor;");
                                
                                // FIXED: Count pending appointment requests instead of confirmed appointments
                                $pendingRequests = $database->query("SELECT * FROM tbl_appointment_requests WHERE status = 'pending' AND DATE(booking_date) >= '$today';");
                                $pendingCount = $pendingRequests->num_rows;
                                
                                $schedulerow = $database->query("SELECT * FROM tbl_schedule WHERE scheduledate='$today';");
                                
                                // Get inventory status
                                $inventoryStatus = getInventoryStatus($database);
                            ?>
                        </p>
                    </td>
                    <td width="10%">
                        <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <center>
                        <table class="filter-container" style="border: none;" border="0">
                            <tr>
                                <td colspan="4">
                                    <div style="display: flex; align-items: center; padding-left: 12px;">
                                        <p style="font-size: 20px;font-weight:600;margin: 0;">Status</p>
                                        <div class="notification-bell" onclick="showNotifications()" style="margin-left: 15px; position: relative; cursor: pointer;">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 2C13.1 2 14 2.9 14 4C14 4.74 13.6 5.39 13 5.73V7H14C17.87 7 21 10.13 21 14V16H3V14C3 10.13 6.13 7 10 7H11V5.73C10.4 5.39 10 4.74 10 4C10 2.9 10.9 2 12 2ZM12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22Z" fill="#666"/>
                                            </svg>
                                            <span class="notification-badge" id="notificationBadge" style="display: none; position: absolute; top: -8px; right: -8px; background: #ff4444; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 12px; text-align: center; line-height: 18px; font-weight: bold;">0</span>
                                            <div class="notification-dropdown" id="notificationDropdown">
                                                <div class="notification-header">
                                                    <span>Cancelled Appointments</span>
                                                    <button id="markAllReadBtn" onclick="markAllAsRead()" style="float: right; background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer;">Mark All Read</button>
                                                </div>
                                                <div id="notificationList">
                                                    <!-- Notifications will be loaded here -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 25%;">
                                    <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display: flex">
                                        <div>
                                            <div class="h1-dashboard">
                                                <?php echo $doctorrow->num_rows; ?>
                                            </div><br>
                                            <div class="h3-dashboard">
                                                Doctors &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                            </div>
                                        </div>
                                        <div class="btn-icon-back dashboard-icons" style="background-image: url('../Images/icons/doctors-hover.svg');"></div>
                                    </div>
                                </td>
                                <td style="width: 25%;">
                                    <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display: flex;">
                                        <div>
                                            <div class="h1-dashboard" style="color: <?php echo $inventoryStatus['color']; ?>;">
                                                <?php echo $inventoryStatus['count']; ?>
                                            </div><br>
                                            <div class="h3-dashboard">
                                                <?php echo $inventoryStatus['text']; ?>
                                            </div>
                                        </div>
                                        <div class="btn-icon-back dashboard-icons" style="background-image: url('../Images/icons/settings-iceblue.svg');"></div>
                                    </div>
                                </td>
                                <td style="width: 25%;">
                                    <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display: flex; ">
                                        <div>
                                            <div class="h1-dashboard">
                                                <?php echo $pendingCount; ?>
                                                <?php if ($pendingCount > 0): ?>
                                                    <span class="pending-badge">Pending</span>
                                                <?php endif; ?>
                                            </div><br>
                                            <div class="h3-dashboard">
                                                New Booking &nbsp;&nbsp;
                                            </div>
                                        </div>
                                        <div class="btn-icon-back dashboard-icons" style="margin-left: 0px;background-image: url('../Images/icons/book-hover.svg');"></div>
                                    </div>
                                </td>
                                <td style="width: 25%;">
                                    <div class="dashboard-items" style="padding:20px;margin:auto;width:95%;display: flex;padding-top:26px;padding-bottom:26px;">
                                        <div>
                                            <div class="h1-dashboard">
                                                <?php echo $schedulerow->num_rows; ?>
                                            </div><br>
                                            <div class="h3-dashboard" style="font-size: 15px">
                                                Today Sessions
                                            </div>
                                        </div>
                                        <div class="btn-icon-back dashboard-icons" style="background-image: url('../Images/icons/session-iceblue.svg');"></div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <div class="chart-container">
    <div class="chart-header">
        <h3 class="chart-title">Appointment Booking Statistics</h3>
        <div class="chart-controls">
            <select id="timeFilter" class="time-filter" onchange="updateChart()">
                <option value="day">Daily (Last 7 days)</option>
                <option value="week">Weekly (Last 4 weeks)</option>
                <option value="month" selected>Monthly (Last 6 months)</option>
                <option value="year">Yearly (Last 3 years)</option>
            </select>
            <button class="export-btn" onclick="exportReport()">
                📊 Export Report
            </button>
        </div>
    </div>
    <div class="chart-canvas-container">
        <canvas id="appointmentChart"></canvas>
    </div>
    <div class="chart-info" id="chartInfo">
        <strong>Chart Information:</strong> This chart shows the number of patients who have booked appointments over the selected time period. Use the dropdown to switch between daily, weekly, monthly, and yearly views. Click "Export Report" to download a PDF report with the current data.
    </div>
</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <table width="100%" border="0" class="dashbord-tables">
                            <tr>
                                <td>
                                    <p style="padding:10px;padding-left:48px;padding-bottom:0;font-size:23px;font-weight:700;color:var(--primarycolor);">
                                        Upcoming Appointments until Next <?php echo date("l",strtotime("+1 week")); ?>
                                    </p>
                                    <p style="padding-bottom:19px;padding-left:50px;font-size:15px;font-weight:500;color:#212529e3;line-height: 20px;">
                                        Here's Quick access to Upcoming Appointments until 7 days<br>
                                        More details available in @Appointment section.
                                    </p>
                                </td>
                                <td>
                                    <p style="text-align:right;padding:10px;padding-right:48px;padding-bottom:0;font-size:23px;font-weight:700;color:var(--primarycolor);">
                                        Upcoming Sessions  until Next <?php echo date("l",strtotime("+1 week")); ?>
                                    </p>
                                    <p style="padding-bottom:19px;text-align:right;padding-right:50px;font-size:15px;font-weight:500;color:#212529e3;line-height: 20px;">
                                        Here's Quick access to Upcoming Sessions that Scheduled until 7 days<br>
                                        Add,Remove and Many features available in @Schedule section.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td width="50%">
                                    <center>
                                        <div class="abc scroll" style="height: 200px;">
                                        <table width="85%" class="sub-table scrolldown" border="0">
                                        <thead>
                                        <tr>    
                                            <th class="table-headin" style="font-size: 12px;">
                                                Appointment number
                                            </th>
                                            <th class="table-headin">
                                                Patient name
                                            </th>
                                            <th class="table-headin">
                                                Doctor
                                            </th>
                                            <th class="table-headin">
                                                Session
                                            </th>
                                            <th class="table-headin">
                                                Status
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $nextweek = date("Y-m-d", strtotime("+1 week"));
                                        
                                        // FIXED: Query to get appointment requests instead of confirmed appointments
                                        $sqlmain = "SELECT 
                                            r.request_id,
                                            r.booking_date,
                                            r.appointment_time,
                                            r.appointment_type,
                                            r.status,
                                            r.appointment_progress,
                                            CONCAT(p.Fname, 
                                                   CASE WHEN p.Mname IS NOT NULL AND p.Mname != '' THEN CONCAT(' ', p.Mname) ELSE '' END,
                                                   ' ', p.Lname,
                                                   CASE WHEN p.Suffix IS NOT NULL AND p.Suffix != '' THEN CONCAT(' ', p.Suffix) ELSE '' END
                                            ) AS patient_name,
                                            CONCAT(d.first_name,
                                                   CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                                                   ' ', d.last_name,
                                                   CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                                            ) AS doctor_name,
                                            r.appointment_type as session_type
                                        FROM tbl_appointment_requests r
                                        LEFT JOIN tbl_patients p ON r.patient_id = p.Patient_id
                                        LEFT JOIN tbl_doctor d ON r.docid = d.docid
                                        WHERE r.booking_date >= '$today' AND r.booking_date <= '$nextweek'
                                        ORDER BY r.booking_date ASC, r.appointment_time ASC";
                                        
                                        $result = $database->query($sqlmain);

                                        if ($result->num_rows == 0) {
                                            echo '<tr>
                                            <td colspan="5">
                                            <br><br><br><br>
                                            <center>
                                            <img src="../Images/notfound.svg" width="25%">
                                            <br>
                                            <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">No upcoming appointment requests found!</p>
                                            <a class="non-style-link" href="appointment.php"><button  class="login-btn btn-primary-soft btn"  style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; Show all Appointment Requests &nbsp;</button>
                                            </a>
                                            </center>
                                            <br><br><br><br>
                                            </td>
                                            </tr>';
                                        } else {
                                            while ($row = $result->fetch_assoc()) {
                                                $request_id = $row["request_id"];
                                                $booking_date = $row["booking_date"];
                                                $appointment_time = $row["appointment_time"];
                                                $appointment_type = $row["appointment_type"];
                                                $status = $row["status"];
                                                $patient_name = $row["patient_name"];
                                                $doctor_name = $row["doctor_name"];
                                                $session_type = $row["session_type"];
                                                
                                                // Format time for display
                                                $display_time = '';
                                                if ($appointment_time) {
                                                    $display_time = date('h:i A', strtotime($appointment_time));
                                                }
                                                
                                                // Format appointment type
                                                $formatted_type = ucwords(str_replace('_', ' ', $appointment_type));
                                                
                                                // Status badge
                                                $status_badge = '';
                                                $status_color = '';
                                                switch($status) {
                                                    case 'pending':
                                                        $status_badge = 'Pending';
                                                        $status_color = 'color: #856404; background: #fff3cd; padding: 2px 8px; border-radius: 4px;';
                                                        break;
                                                    case 'approved':
                                                        $status_badge = 'Approved';
                                                        $status_color = 'color: #155724; background: #d4edda; padding: 2px 8px; border-radius: 4px;';
                                                        break;
                                                    case 'rejected':
                                                        $status_badge = 'Rejected';
                                                        $status_color = 'color: #721c24; background: #f8d7da; padding: 2px 8px; border-radius: 4px;';
                                                        break;
                                                    default:
                                                        $status_badge = ucfirst($status);
                                                        $status_color = 'color: #383d41; background: #e2e3e5; padding: 2px 8px; border-radius: 4px;';
                                                }
                                                
                                                echo '<tr>
                                                    <td style="text-align:center;font-size:18px;font-weight:500; color: var(--btnnicetext);padding:15px;">
                                                        #'.str_pad($request_id, 4, '0', STR_PAD_LEFT).'
                                                    </td>
                                                    <td style="font-weight:600; padding:15px;"> &nbsp;'.htmlspecialchars(substr($patient_name,0,20)).'</td>
                                                    <td style="font-weight:600; padding:15px;"> &nbsp;'.htmlspecialchars(substr($doctor_name,0,20)).'</td>
                                                    <td style="padding:15px;">'.htmlspecialchars(substr($formatted_type,0,15)).'</td>
                                                    <td style="text-align:center; padding:15px;"><span style="'.$status_color.'; font-size: 12px;">'.$status_badge.'</span></td>
                                                </tr>';
                                            }
                                        }
                                        ?>
                                        </tbody>
                                        </table>
                                        </div>
                                    </center>
                                </td>
                                <td width="50%" style="padding: 0;">
                                    <center>
                                        <div class="abc scroll" style="height: 200px;padding: 0;margin: 0;">
                                        <table width="85%" class="sub-table scrolldown" border="0" >
                                        <thead>
                                        <tr>
                                            <th class="table-headin">
                                                Session Title
                                            </th>
                                            <th class="table-headin">
                                                Doctor
                                            </th>
                                            <th class="table-headin">
                                                Scheduled Date & Time
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sqlmain = "SELECT 
                                            tbl_schedule.scheduleid,
                                            tbl_schedule.title,
                                            tbl_doctor.first_name,
                                            tbl_doctor.middle_name,
                                            tbl_doctor.last_name,
                                            tbl_doctor.suffix,
                                            tbl_schedule.scheduledate,
                                            tbl_schedule.start_time,
                                            tbl_schedule.end_time,
                                            tbl_schedule.nop
                                        FROM tbl_schedule
                                        INNER JOIN tbl_doctor ON tbl_schedule.docid = tbl_doctor.docid
                                        WHERE tbl_schedule.scheduledate >= '$today' AND tbl_schedule.scheduledate <= '$nextweek'
                                        ORDER BY tbl_schedule.scheduledate DESC";
                                        $result = $database->query($sqlmain);

                                        if ($result->num_rows == 0) {
                                            echo '<tr>
                                            <td colspan="3">
                                            <br><br><br><br>
                                            <center>
                                            <img src="../Images/notfound.svg" width="25%">
                                            <br>
                                            <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">We couldn\'t find anything related to your keywords!</p>
                                            <a class="non-style-link" href="schedule.php"><button  class="login-btn btn-primary-soft btn"  style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; Show all Sessions &nbsp;</button>
                                            </a>
                                            </center>
                                            <br><br><br><br>
                                            </td>
                                            </tr>';
                                        } else {
                                            while ($row = $result->fetch_assoc()) {
                                                $scheduleid = $row["scheduleid"];
                                                $title = $row["title"];
                                                // Compose full name from parts
                                                $first = $row["first_name"];
                                                $middle = $row["middle_name"];
                                                $last = $row["last_name"];
                                                $suffix = $row["suffix"];
                                                $docname = $first;
                                                if ($middle) $docname .= ' ' . $middle;
                                                $docname .= ' ' . $last;
                                                if ($suffix) $docname .= ' ' . $suffix;
                                                $scheduledate = $row["scheduledate"];
                                                $start_time = $row["start_time"];
                                                $end_time = $row["end_time"];
                                                $nop = $row["nop"];
                                                
                                                // Format time for display
                                                $display_time = '';
                                                if ($start_time && $end_time) {
                                                    $display_time = date('h:i A', strtotime($start_time)) . ' - ' . date('h:i A', strtotime($end_time));
                                                } elseif ($start_time) {
                                                    $display_time = date('h:i A', strtotime($start_time));
                                                }
                                                
                                                echo '<tr>
                                                    <td style="padding:20px;"> &nbsp;'.htmlspecialchars(substr($title,0,30)).'</td>
                                                    <td>'.htmlspecialchars(substr($docname,0,20)).'</td>
                                                    <td style="text-align:center;">'.htmlspecialchars(substr($scheduledate,0,10)).'<br>'.htmlspecialchars($display_time).'</td>
                                                </tr>';
                                            }
                                        }
                                        ?>
                                        </tbody>
                                        </table>
                                        </div>
                                    </center>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <center>
                                        <a href="appointment.php" class="non-style-link"><button class="btn-primary btn" style="width:85%">Show all Appointment Requests</button></a>
                                    </center>
                                </td>
                                <td>
                                    <center>
                                        <a href="schedule.php" class="non-style-link"><button class="btn-primary btn" style="width:85%">Show all Sessions</button></a>
                                    </center>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>

<script>
let appointmentChart;
let currentChartData = {};

// Initialize chart when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    updateChart();
});

function initializeChart() {
    const ctx = document.getElementById('appointmentChart').getContext('2d');
    appointmentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Appointments Booked',
                data: [],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // This is important for flexible sizing
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
}

function updateChart() {
    const timeFilter = document.getElementById('timeFilter').value;
    
    // Show loading state
    appointmentChart.data.labels = ['Loading...'];
    appointmentChart.data.datasets[0].data = [0];
    appointmentChart.update();
    
    // Fetch data from server
    fetch('get-chart-data.php?filter=' + timeFilter)
        .then(response => response.json())
        .then(data => {
            currentChartData = data;
            appointmentChart.data.labels = data.labels;
            appointmentChart.data.datasets[0].data = data.values;
            appointmentChart.update();
            
            // Update chart info
            updateChartInfo(timeFilter, data);
        })
        .catch(error => {
            console.error('Error fetching chart data:', error);
            appointmentChart.data.labels = ['Error loading data'];
            appointmentChart.data.datasets[0].data = [0];
            appointmentChart.update();
        });
}

function updateChartInfo(filter, data) {
    const total = data.values.reduce((sum, val) => sum + val, 0);
    const average = total > 0 ? (total / data.values.length).toFixed(1) : 0;
    
    let period = '';
    switch(filter) {
        case 'day': period = 'daily'; break;
        case 'week': period = 'weekly'; break;
        case 'month': period = 'monthly'; break;
        case 'year': period = 'yearly'; break;
    }
    
    document.getElementById('chartInfo').innerHTML = `
        <strong>Chart Information:</strong> This ${period} view shows appointment booking trends. 
        <strong>Total appointments:</strong> ${total} | 
        <strong>Average per period:</strong> ${average} | 
        <strong>Peak period:</strong> ${data.labels[data.values.indexOf(Math.max(...data.values))]} (${Math.max(...data.values)} appointments)
    `;
}

function exportReport() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add title
    doc.setFontSize(20);
    doc.text('Appointment Booking Report', 20, 20);
    
    // Add date
    doc.setFontSize(12);
    doc.text('Generated on: ' + new Date().toLocaleDateString(), 20, 35);
    
    // Add filter info
    const filter = document.getElementById('timeFilter').value;
    doc.text('Time Period: ' + document.getElementById('timeFilter').selectedOptions[0].text, 20, 45);
    
    // Add summary statistics
    const total = currentChartData.values ? currentChartData.values.reduce((sum, val) => sum + val, 0) : 0;
    const average = total > 0 ? (total / currentChartData.values.length).toFixed(1) : 0;
    
    doc.text('Summary Statistics:', 20, 60);
    doc.text('• Total Appointments: ' + total, 25, 70);
    doc.text('• Average per Period: ' + average, 25, 80);
    
    if (currentChartData.labels && currentChartData.values) {
        const maxIndex = currentChartData.values.indexOf(Math.max(...currentChartData.values));
        doc.text('• Peak Period: ' + currentChartData.labels[maxIndex] + ' (' + Math.max(...currentChartData.values) + ' appointments)', 25, 90);
    }
    
    // Add detailed data
    doc.text('Detailed Data:', 20, 110);
    let yPos = 120;
    
    if (currentChartData.labels && currentChartData.values) {
        for (let i = 0; i < currentChartData.labels.length; i++) {
            if (yPos > 270) {
                doc.addPage();
                yPos = 20;
            }
            doc.text(currentChartData.labels[i] + ': ' + currentChartData.values[i] + ' appointments', 25, yPos);
            yPos += 10;
        }
    }
    
    // Add chart image (canvas to image)
    const canvas = document.getElementById('appointmentChart');
    const imgData = canvas.toDataURL('image/png');
    
    doc.addPage();
    doc.text('Appointment Booking Chart', 20, 20);
    doc.addImage(imgData, 'PNG', 20, 30, 170, 100);
    
    // Save the PDF
    doc.save('appointment-booking-report-' + new Date().toISOString().split('T')[0] + '.pdf');
}

// Notification system functions
let notificationDropdownVisible = false;

function showNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const badge = document.getElementById('notificationBadge');
    
    if (notificationDropdownVisible) {
        dropdown.style.display = 'none';
        notificationDropdownVisible = false;
    } else {
        dropdown.style.display = 'flex';
        notificationDropdownVisible = true;
        loadNotifications();
    }
}

function loadNotifications() {
    fetch('get-notifications.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Notification data received:', data); // Debug log
            if (data.success === false) {
                throw new Error(data.message || 'Server returned error');
            }
            updateNotificationBadge(data.count);
            displayNotifications(data.notifications);
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            // Show error in notification dropdown
            const notificationList = document.getElementById('notificationList');
            notificationList.innerHTML = '<div class="notification-item" style="text-align: center; color: #dc3545; font-style: italic;">Error loading notifications. Check console for details.</div>';
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

function displayNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('markAllReadBtn');
    
    if (notifications.length === 0) {
        notificationList.innerHTML = '<div class="notification-item" style="text-align: center; color: #666; font-style: italic;">No cancelled appointments</div>';
        markAllBtn.style.display = 'none';
        return;
    }
    
    // Show/hide mark all button based on unread notifications
    const hasUnread = notifications.some(n => !n.is_read);
    markAllBtn.style.display = hasUnread ? 'block' : 'none';
    
    notificationList.innerHTML = notifications.map(notification => {
        const readClass = notification.is_read ? 'read' : 'unread';
        const markReadBtn = notification.is_read ? '' : `<button class="mark-read-btn" onclick="markAsRead(${notification.request_id}, event)">Mark Read</button>`;
        
        return `
            <div class="notification-item ${readClass}" onclick="viewAppointment(${notification.request_id})">
                <div style="font-weight: 500;">${notification.patient_name} ${markReadBtn}</div>
                <div style="font-size: 13px; color: #666;">${notification.appointment_type} - ${notification.booking_date}</div>
                <div class="notification-time">${notification.cancelled_time}</div>
            </div>
        `;
    }).join('');
}

function viewAppointment(requestId) {
    // Redirect to appointment page with filter for this specific appointment
    window.location.href = 'appointment.php?view=' + requestId;
}

function markAsRead(requestId, event) {
    // Prevent the click from bubbling up to the notification item
    event.stopPropagation();
    
    const formData = new FormData();
    formData.append('action', 'mark_read');
    formData.append('request_id', requestId);
    
    fetch('get-notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Notification marked as read');
            // Reload notifications to update the display
            loadNotifications();
        } else {
            console.error('Failed to mark notification as read:', data.message);
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllAsRead() {
    const formData = new FormData();
    formData.append('action', 'mark_read');
    
    fetch('get-notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('All notifications marked as read');
            // Reload notifications to update the display
            loadNotifications();
        } else {
            console.error('Failed to mark all notifications as read:', data.message);
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing notification system...');
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
});

// Close notification dropdown when clicking outside
document.addEventListener('click', function(event) {
    const bell = document.querySelector('.notification-bell');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (dropdown && !bell.contains(event.target) && !dropdown.contains(event.target) && notificationDropdownVisible) {
        dropdown.style.display = 'none';
        notificationDropdownVisible = false;
    }
});

// Prevent page scroll when scrolling inside notification dropdown
document.addEventListener('DOMContentLoaded', function() {
    const notificationList = document.getElementById('notificationList');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (notificationList && dropdown) {
        // Prevent scroll propagation to parent when scrolling inside notification list
        notificationList.addEventListener('wheel', function(e) {
            const isScrollingDown = e.deltaY > 0;
            const isScrollingUp = e.deltaY < 0;
            const isAtTop = notificationList.scrollTop <= 0;
            const isAtBottom = notificationList.scrollTop + notificationList.clientHeight >= notificationList.scrollHeight - 1;
            
            // If scrolling within bounds, prevent page scroll
            if (!((isAtTop && isScrollingUp) || (isAtBottom && isScrollingDown))) {
                e.stopPropagation();
            }
        }, { passive: false });
        
        // Prevent scroll on the dropdown container itself
        dropdown.addEventListener('wheel', function(e) {
            // Only prevent if the scroll is happening on the dropdown or its children
            if (dropdown.contains(e.target)) {
                // Check if we're scrolling the notification list
                if (e.target === notificationList || notificationList.contains(e.target)) {
                    // Let the notificationList handler deal with it
                    return;
                }
                // For other elements in dropdown, prevent page scroll
                e.stopPropagation();
            }
        }, { passive: false });
        
        // Also prevent touchmove events for mobile
        notificationList.addEventListener('touchmove', function(e) {
            e.stopPropagation();
        }, { passive: false });
    }
});
</script>
</body>
</html>