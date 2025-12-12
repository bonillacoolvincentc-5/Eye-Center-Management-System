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
    <title>Doctors</title>
    <style>
        .popup { animation: transitionIn-Y-bottom 0.5s; }
        .sub-table { animation: transitionIn-Y-bottom 0.5s; }
        
        /* Tab styles */
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
            margin: 20px 0;
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
        
        .doctor-appointment-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 10px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .doctor-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        
        .appointment-count {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .status-active {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-inactive {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>
<?php
session_start();

if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Handle error messages
$error = $_GET['error'] ?? '0';
$error_message = '';
switch ($error) {
    case '1':
        $error_message = "Email already exists!";
        break;
    case '2':
        $error_message = "Passwords don't match!";
        break;
    case '4':
        $error_message = "Doctor added successfully!";
        break;
    case '5':
        $error_message = "Doctor updated successfully!";
        break;
    case 'nic_exists':
        $error_message = "NIC already exists! Please enter a unique NIC.";
        break;
    case 'service_added':
        $error_message = "Service added successfully!";
        break;
    case 'service_updated':
        $error_message = "Service updated successfully!";
        break;
    case 'service_deleted':
        $error_message = "Service deleted successfully!";
        break;
    case 'service_code_exists':
        $error_message = "Service code already exists!";
        break;
    case 'service_in_use':
        $error_message = "Cannot delete service - it is being used in appointments!";
        break;
    case 'service_add_failed':
        $error_message = "Failed to add service!";
        break;
    case 'service_update_failed':
        $error_message = "Failed to update service!";
        break;
    case 'service_delete_failed':
        $error_message = "Failed to delete service!";
        break;
}

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
?>
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
                <td class="menu-btn menu-icon-doctor menu-active menu-icon-doctor-active">
                    <a href="doctors.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-schedule">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Schedule</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointment</p></div></a>
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
                    <a href="doctors.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <form action="" method="post" class="header-search">
                        <input type="search" name="search" class="input-text header-searchbar" placeholder="Search Doctor's name or Email" list="doctors">&nbsp;&nbsp;
                        <?php
                        echo '<datalist id="doctors">';
                        $list11 = $database->query("SELECT first_name, last_name, docemail FROM tbl_doctor;");
                        while ($row00 = $list11->fetch_assoc()) {
                            // Compose full name from parts
                            $fullname = htmlspecialchars($row00["first_name"] . ' ' . $row00["last_name"]);
                            $c = htmlspecialchars($row00["docemail"]);
                            echo "<option value='$fullname'>";
                            echo "<option value='$c'>";
                        }
                        echo '</datalist>';
                        ?>
                        <input type="Submit" value="Search" class="login-btn btn-primary btn" style="padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;">
                    </form>
                </td>
                <td width="15%">
                    <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                        Today's Date
                    </p>
                    <p class="heading-sub12" style="padding: 0;margin: 0;">
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        $date = date('Y-m-d');
                        echo $date;
                        ?>
                    </p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top:30px;">
                    <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">Add New Doctor</p>
                </td>
                <td colspan="2">
                    <a href="?action=add&id=none&error=0" class="non-style-link"><button class="login-btn btn-primary btn button-icon" style="display: flex;justify-content: center;align-items: center;margin-left:75px;background-image: url('../Images/icons/add.svg');">Add New</button></a>
                </td>
            </tr>
            <?php if (!empty($error_message)): ?>
            <tr>
                <td colspan="4" style="padding: 10px 45px;">
                    <div style="background: <?php echo (strpos($error_message, 'successfully') !== false) ? '#d4edda' : '#f8d7da'; ?>; 
                                border: 1px solid <?php echo (strpos($error_message, 'successfully') !== false) ? '#c3e6cb' : '#f5c6cb'; ?>; 
                                color: <?php echo (strpos($error_message, 'successfully') !== false) ? '#155724' : '#721c24'; ?>; 
                                padding: 10px; border-radius: 4px; margin: 10px 0;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="4">
                    <div class="tabs" style="margin-left: 45px;">
                        <button class="tab-button active" onclick="showTab('doctors', this)">Manage Doctors</button>
                        <button class="tab-button" onclick="showTab('appointments', this)">Doctor Appointments</button>
                        <button class="tab-button" onclick="showTab('services', this)">Services Management</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:10px;">
                    <!-- Tab content containers removed - content is in the divs below -->
                </td>
            </tr>
            <?php
            if ($_POST) {
                $keyword = $_POST["search"];
                // Search in name parts and email
                $sqlmain = "SELECT * FROM tbl_doctor WHERE docemail='$keyword' OR first_name LIKE '%$keyword%' OR last_name LIKE '%$keyword%' OR (CONCAT(first_name, ' ', last_name) LIKE '%$keyword%')";
            } else {
                $sqlmain = "SELECT * FROM tbl_doctor ORDER BY docid DESC";
            }
            ?>
            <tr>
                <td colspan="4">
                    <!-- Doctors Management Tab -->
                    <div id="doctorsTabContent" class="tab-content active">
                        <center>
                            <div class="abc scroll">
                                <table width="93%" class="sub-table scrolldown" border="0">
                                <thead>
                                    <tr>
                                        <th class="table-headin">Doctor's Name</th>
                                        <th class="table-headin">Email</th>
                                        <th class="table-headin">Specialties</th>
                                        <th class="table-headin">Events</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = $database->query($sqlmain);
                                    if ($result->num_rows == 0) {
                                        echo '<tr>
                                            <td colspan="4">
                                            <br><br><br><br>
                                            <center>
                                            <img src="../Images/notfound.svg" width="25%">
                                            <br>
                                            <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">We couldn\'t find anything related to your keywords!</p>
                                            <a class="non-style-link" href="doctors.php"><button class="login-btn btn-primary-soft btn" style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; Show all Doctors &nbsp;</button></a>
                                            </center>
                                            <br><br><br><br>
                                            </td>
                                            </tr>';
                                    } else {
                                        while ($row = $result->fetch_assoc()) {
                                            $docid = $row["docid"];
                                            // Compose full name from parts
                                            $first = htmlspecialchars($row["first_name"] ?? '');
                                            $middle = htmlspecialchars($row["middle_name"] ?? '');
                                            $last = htmlspecialchars($row["last_name"] ?? '');
                                            $suffix = htmlspecialchars($row["suffix"] ?? '');
                                            $name = $first;
                                            if ($middle !== '') $name .= ' ' . $middle;
                                            $name .= ' ' . $last;
                                            if ($suffix !== '') $name .= ' ' . $suffix;
                                            $email = htmlspecialchars($row["docemail"]);
                                            $spe = $row["specialties"];
                                            $spcil_res = $database->query("SELECT sname FROM tbl_specialties WHERE id='$spe'");
                                            $spcil_array = $spcil_res->fetch_assoc();
                                            $spcil_name = htmlspecialchars($spcil_array["sname"] ?? 'General');
                                            echo '<tr>
                                                <td>&nbsp;' . substr($name, 0, 30) . '</td>
                                                <td>' . substr($email, 0, 20) . '</td>
                                                <td>' . substr($spcil_name, 0, 20) . '</td>
                                                <td>
                                                    <div style="display:flex;justify-content: center;">
                                                        <a href="?action=edit&id=' . $docid . '&error=0" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-edit" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">Edit</font></button></a>
                                                        &nbsp;&nbsp;&nbsp;
                                                        <a href="?action=view&id=' . $docid . '" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-view" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">View</font></button></a>
                                                        &nbsp;&nbsp;&nbsp;
                                                        <a href="?action=drop&id=' . $docid . '&name=' . urlencode($name) . '" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-delete" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">Remove</font></button></a>
                                                    </div>
                                                </td>
                                            </tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                                </table>
                            </div>
                        </center>
                    </div>
                    
                    <!-- Doctor Appointments Tab -->
                    <div id="appointmentsTabContent" class="tab-content">
                        <center>
                            <div class="abc scroll">
                                <?php
                                // Get all doctors with their appointment counts
                                $doctors_sql = "SELECT d.docid, 
                                               CONCAT(d.first_name,
                                                      CASE WHEN d.middle_name IS NOT NULL AND d.middle_name != '' THEN CONCAT(' ', d.middle_name) ELSE '' END,
                                                      ' ', d.last_name,
                                                      CASE WHEN d.suffix IS NOT NULL AND d.suffix != '' THEN CONCAT(' ', d.suffix) ELSE '' END
                                               ) AS docname,
                                               d.docemail, s.sname as specialty_name,
                                               COUNT(CASE WHEN ar.status = 'pending' THEN 1 END) as pending_count,
                                               COUNT(CASE WHEN ar.status = 'approved' THEN 1 END) as approved_count,
                                               COUNT(CASE WHEN ar.status = 'rejected' THEN 1 END) as rejected_count
                                               FROM tbl_doctor d 
                                               LEFT JOIN tbl_specialties s ON d.specialties = s.id
                                               LEFT JOIN tbl_schedule sc ON d.docid = sc.docid
                                               LEFT JOIN tbl_appointment_requests ar ON sc.scheduledate = DATE(ar.booking_date)
                                                   AND ar.appointment_time BETWEEN sc.start_time AND sc.end_time
                                               GROUP BY d.docid, d.first_name, d.middle_name, d.last_name, d.suffix, d.docemail, s.sname
                                               ORDER BY d.last_name, d.first_name";
                                $doctors_result = $database->query($doctors_sql);
                                
                                if ($doctors_result->num_rows == 0) {
                                    echo '<div style="text-align: center; padding: 50px;">
                                        <img src="../Images/notfound.svg" width="25%">
                                        <br>
                                        <p class="heading-main12" style="font-size:20px;color:rgb(49, 49, 49)">No doctors found!</p>
                                    </div>';
                                } else {
                                    while($doctor = $doctors_result->fetch_assoc()) {
                                        $total_appointments = $doctor['pending_count'] + $doctor['approved_count'] + $doctor['rejected_count'];
                                        echo '<div class="doctor-appointment-card">
                                            <div class="doctor-name">
                                                Dr. ' . htmlspecialchars($doctor['docname']) . 
                                                '<span class="appointment-count">' . $total_appointments . ' Total</span>
                                            </div>
                                            <div style="margin-bottom: 15px;">
                                                <strong>Specialty:</strong> ' . htmlspecialchars($doctor['specialty_name'] ?: 'General Practice') . '<br>
                                                <strong>Email:</strong> ' . htmlspecialchars($doctor['docemail']) . '
                                            </div>
                                            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                                                <div style="background: #ffd700; color: #000; padding: 8px 12px; border-radius: 4px; font-size: 14px;">
                                                    <strong>Pending:</strong> ' . $doctor['pending_count'] . '
                                                </div>
                                                <div style="background: #28a745; color: white; padding: 8px 12px; border-radius: 4px; font-size: 14px;">
                                                    <strong>Approved:</strong> ' . $doctor['approved_count'] . '
                                                </div>
                                                <div style="background: #dc3545; color: white; padding: 8px 12px; border-radius: 4px; font-size: 14px;">
                                                    <strong>Rejected:</strong> ' . $doctor['rejected_count'] . '
                                                </div>
                                            </div>
                                            <button onclick="viewDoctorAppointments(' . $doctor['docid'] . ', \'' . htmlspecialchars($doctor['docname']) . '\')" 
                                                    class="btn-primary-soft btn" style="padding: 8px 16px;">
                                                View Detailed Appointments
                                            </button>
                                        </div>';
                                    }
                                }
                                ?>
                            </div>
                        </center>
                    </div>
                    
                    <!-- Services Management Tab -->
                    <div id="servicesTabContent" class="tab-content">
                        <center>
                            <div style="margin-bottom: 20px;">
                                <a href="?action=add-service" class="non-style-link">
                                    <button class="login-btn btn-primary btn button-icon" style="background-image: url('../Images/icons/add.svg');">
                                        Add New Service
                                    </button>
                                </a>
                            </div>
                            <div class="abc scroll">
                                <table width="93%" class="sub-table scrolldown" border="0">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Service Name</th>
                                            <th class="table-headin">Service Code</th>
                                            <th class="table-headin">Duration</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Check if services table exists, if not show setup message
                                        $services_check = $database->query("SHOW TABLES LIKE 'tbl_services'");
                                        if ($services_check->num_rows == 0) {
                                            echo '<tr>
                                                <td colspan="5" style="text-align: center; padding: 40px;">
                                                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px;">
                                                        <h3 style="color: #856404; margin-bottom: 15px;">Services Table Not Found</h3>
                                                        <p style="color: #856404; margin-bottom: 20px;">The services table needs to be set up first.</p>
                                                        <a href="setup-services.php" class="non-style-link">
                                                            <button class="btn-primary btn" style="padding: 10px 20px;">
                                                                Setup Services Table
                                                            </button>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>';
                                        } else {
                                            // Get all services
                                            $services_sql = "SELECT * FROM tbl_services ORDER BY service_name";
                                            $services_result = $database->query($services_sql);
                                            
                                            if ($services_result->num_rows == 0) {
                                                echo '<tr>
                                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                                        <img src="../Images/notfound.svg" width="25%">
                                                        <br>
                                                        <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">
                                                            No services found! Add your first service.
                                                        </p>
                                                    </td>
                                                </tr>';
                                            } else {
                                                while ($service = $services_result->fetch_assoc()) {
                                                    $status_class = $service['is_active'] ? 'status-active' : 'status-inactive';
                                                    $status_text = $service['is_active'] ? 'Active' : 'Inactive';
                                                    
                                                    echo '<tr>
                                                        <td>' . htmlspecialchars($service['service_name']) . '</td>
                                                        <td><code>' . htmlspecialchars($service['service_code']) . '</code></td>
                                                        <td>' . $service['duration_minutes'] . ' minutes</td>
                                                        <td><span class="' . $status_class . '">' . $status_text . '</span></td>
                                                        <td>
                                                            <div style="display:flex;justify-content: center;gap:5px;">
                                                                <a href="?action=edit-service&id=' . $service['service_id'] . '" class="non-style-link">
                                                                    <button class="btn-primary-soft btn" style="padding: 8px 12px; font-size: 14px;">
                                                                        Edit
                                                                    </button>
                                                                </a>
                                                                <a href="?action=delete-service&id=' . $service['service_id'] . '&name=' . urlencode($service['service_name']) . '" class="non-style-link">
                                                                    <button class="btn-primary-soft btn" style="padding: 8px 12px; font-size: 14px;">
                                                                        Delete
                                                                    </button>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>';
                                                }
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </center>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
<?php
if ($_GET) {
    $id = $_GET["id"] ?? '';
    $action = $_GET["action"] ?? '';
    
    if ($action == 'add') {
        // Get specialties for dropdown
        $spec_res = $database->query("SELECT * FROM tbl_specialties");
        
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Add New Doctor</h2>
                    <a class="close" href="doctors.php">&times;</a>';
                    
                    if (!empty($error_message)) {
                        echo '<div style="color: ' . ($error == '4' ? 'green' : 'red') . '; margin: 10px 0; font-weight: bold;">' . $error_message . '</div>';
                    }
                    
                    echo '
                    <div class="content">
                        <form action="add-new.php" method="POST" class="add-doc-form-container" id="doctorForm" onsubmit="return validateForm()">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="first_name" class="form-label">First Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="first_name" class="input-text" placeholder="First Name" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="middle_name" class="form-label">Middle Name (Optional): </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="middle_name" class="input-text" placeholder="Middle Name"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="last_name" class="form-label">Last Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="last_name" class="input-text" placeholder="Last Name" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="suffix" class="form-label">Suffix (Optional): </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="suffix" class="input-text" placeholder="e.g., Jr., Sr., MD"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="nic" class="form-label">NIC: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="nic" class="input-text" placeholder="Enter NIC (e.g., ABC1234567)" pattern="[A-Za-z0-9\-]{6,20}" title="6-20 characters, letters/numbers/dashes" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="spec" class="form-label">Specialties: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <select name="spec" class="box" id="spec" required>
                                            <option value="" disabled selected>Choose Speciality</option>';
                                            
                                            while ($row = $spec_res->fetch_assoc()) {
                                                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['sname']) . '</option>';
                                            }
                                            
                                            echo '
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="email" class="form-label">Email: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="email" name="email" class="input-text" placeholder="Email Account" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="Tele" class="form-label">Contact No: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="tel" name="Tele" class="input-text" placeholder="Contact Number (11 digits)" 
                                               pattern="[0-9]{11}" maxlength="11" minlength="11"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, \'\')"
                                               title="Please enter exactly 11 digits" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="password" class="form-label">Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <div style="position: relative;">
                                            <input type="password" name="password" id="password" class="input-text" 
                                                   placeholder="Password" onkeyup="validatePassword(this.value)" required>
                                            <i class="password-toggle-icon fas fa-eye-slash" 
                                               onclick="togglePasswordVisibility(\'password\')" 
                                               style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                                        </div>
                                        <div id="password-requirements" style="font-size: 12px; margin-top: 5px; display: none;">
                                            <div id="length" class="requirement">At least 8 characters</div>
                                            <div id="uppercase" class="requirement">At least one uppercase letter</div>
                                            <div id="lowercase" class="requirement">At least one lowercase letter</div>
                                            <div id="number" class="requirement">At least one number</div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="cpassword" class="form-label">Confirm Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <div style="position: relative;">
                                            <input type="password" name="cpassword" id="cpassword" class="input-text" 
                                                   placeholder="Confirm Password" onkeyup="validateConfirmPassword()" required>
                                            <i class="password-toggle-icon fas fa-eye-slash" 
                                               onclick="togglePasswordVisibility(\'cpassword\')" 
                                               style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                                        </div>
                                        <div id="password-match" style="font-size: 12px; margin-top: 5px;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="submit" value="Add Doctor" class="login-btn btn-primary btn" style="width: 100%; margin-top: 20px;">
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'drop') {
        $nameget = $_GET["name"] ?? '';
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Are you sure?</h2>
                    <a class="close" href="doctors.php">&times;</a>
                    <div class="content">
                        You want to delete this record<br>(' . htmlspecialchars(substr($nameget, 0, 40)) . ').
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="delete-doctor.php?id=' . htmlspecialchars($id) . '" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;Yes&nbsp;</button></a>&nbsp;&nbsp;&nbsp;
                        <a href="doctors.php" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;&nbsp;No&nbsp;&nbsp;</button></a>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'view') {
        $sqlmain = "SELECT * FROM tbl_doctor WHERE docid='$id'";
        $result = $database->query($sqlmain);
        $row = $result->fetch_assoc();
        // Compose full name from parts
        $first = htmlspecialchars($row["first_name"] ?? '');
        $middle = htmlspecialchars($row["middle_name"] ?? '');
        $last = htmlspecialchars($row["last_name"] ?? '');
        $suffix = htmlspecialchars($row["suffix"] ?? '');
        $name = $first;
        if ($middle !== '') $name .= ' ' . $middle;
        $name .= ' ' . $last;
        if ($suffix !== '') $name .= ' ' . $suffix;
        $email = htmlspecialchars($row["docemail"]);
        $spe = $row["specialties"];
        $spcil_res = $database->query("SELECT sname FROM tbl_specialties WHERE id='$spe'");
        $spcil_array = $spcil_res->fetch_assoc();
        $spcil_name = htmlspecialchars($spcil_array["sname"] ?? 'General');
        $nic = htmlspecialchars($row['docnic']);
        $tele = htmlspecialchars($row['doctel']);
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Doctor Details</h2>
                    <a class="close" href="doctors.php">&times;</a>
                    <div class="content">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr><td><strong>Name:</strong></td><td>' . $name . '</td></tr>
                            <tr><td><strong>Email:</strong></td><td>' . $email . '</td></tr>
                            <tr><td><strong>NIC:</strong></td><td>' . $nic . '</td></tr>
                            <tr><td><strong>Contact No:</strong></td><td>' . $tele . '</td></tr>
                            <tr><td><strong>Specialties:</strong></td><td>' . $spcil_name . '</td></tr>
                        </table>
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="doctors.php"><button class="btn-primary btn" style="margin:10px;padding:10px;">OK</button></a>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'edit') {
        // Get doctor data for editing
        $sqlmain = "SELECT * FROM tbl_doctor WHERE docid='$id'";
        $result = $database->query($sqlmain);
        $row = $result->fetch_assoc();
        
        // Read name parts directly from new columns (no need to parse)
        $first = htmlspecialchars($row["first_name"] ?? '');
        $middle = htmlspecialchars($row["middle_name"] ?? '');
        $last = htmlspecialchars($row["last_name"] ?? '');
        $suffix = htmlspecialchars($row["suffix"] ?? '');
        
        // Compose full name from parts
        $name = $first;
        if ($middle !== '') $name .= ' ' . $middle;
        $name .= ' ' . $last;
        if ($suffix !== '') $name .= ' ' . $suffix;
        $email = htmlspecialchars($row["docemail"]);
        $spe = $row["specialties"];
        $nic = htmlspecialchars($row['docnic']);
        $tele = htmlspecialchars($row['doctel']);
        
        // Get specialties for dropdown
        $spec_res = $database->query("SELECT * FROM tbl_specialties");
        
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Edit Doctor</h2>
                    <a class="close" href="doctors.php">&times;</a>';
                    
                    if (!empty($error_message)) {
                        echo '<div style="color: ' . ($error == '5' ? 'green' : 'red') . '; margin: 10px 0; font-weight: bold;">' . $error_message . '</div>';
                    }
                    
                    echo '
                    <div class="content">
                        <form action="edit-doc.php" method="POST" class="add-doc-form-container">
                            <input type="hidden" name="id00" value="' . $id . '">
                            <input type="hidden" name="oldemail" value="' . $email . '">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label class="form-label">Name:</label>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:50%; padding-right:8px;">
                                        <input type="text" name="first_name" class="input-text" placeholder="First name" value="' . $first . '" required><br>
                                    </td>
                                    <td style="width:50%; padding-left:8px;">
                                        <input type="text" name="middle_name" class="input-text" placeholder="Middle name (optional)" value="' . $middle . '"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:50%; padding-right:8px;">
                                        <input type="text" name="last_name" class="input-text" placeholder="Last name" value="' . $last . '" required><br>
                                    </td>
                                    <td style="width:50%; padding-left:8px;">
                                        <input type="text" name="suffix" class="input-text" placeholder="Suffix (optional)" value="' . $suffix . '"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="nic" class="form-label">NIC: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="nic" class="input-text" placeholder="Enter NIC (e.g., ABC1234567)" pattern="[A-Za-z0-9\-]{6,20}" title="6-20 characters, letters/numbers/dashes" value="' . $nic . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="spec" class="form-label">Specialties: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <select name="spec" class="box" id="spec" required>';
                                            
                                            $spec_res->data_seek(0); // Reset pointer
                                            while ($spec_row = $spec_res->fetch_assoc()) {
                                                $selected = ($spec_row['id'] == $spe) ? 'selected' : '';
                                                echo '<option value="' . $spec_row['id'] . '" ' . $selected . '>' . htmlspecialchars($spec_row['sname']) . '</option>';
                                            }
                                            
                                            echo '
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="email" class="form-label">Email: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="email" name="email" class="input-text" placeholder="Email Account" value="' . $email . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="Tele" class="form-label">Contact No: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="tel" name="Tele" class="input-text" placeholder="Contact Number" value="' . $tele . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="password" class="form-label">Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="password" name="password" class="input-text" placeholder="Leave blank to keep current password"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="cpassword" class="form-label">Confirm Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="password" name="cpassword" class="input-text" placeholder="Confirm Password"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="submit" value="Update Doctor" class="login-btn btn-primary btn" style="width: 100%; margin-top: 20px;">
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'add-service') {
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Add New Service</h2>
                    <a class="close" href="doctors.php?tab=services">&times;</a>
                    <div class="content">
                        <form action="manage-services.php" method="POST" class="add-doc-form-container">
                            <input type="hidden" name="action" value="add">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="service_name" class="form-label">Service Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="service_name" class="input-text" placeholder="Service Name" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="service_code" class="form-label">Service Code: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="service_code" class="input-text" placeholder="service_code (e.g., examination)" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="description" class="form-label">Description: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <textarea name="description" class="input-text" placeholder="Service description" rows="3"></textarea><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="duration_minutes" class="form-label">Duration (minutes): </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="number" name="duration_minutes" class="input-text" placeholder="Duration in minutes" min="1" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="price" class="form-label">Price (₱): </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="number" name="price" class="input-text" placeholder="Service price" min="0" step="0.01" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="is_active" class="form-label">Status: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <select name="is_active" class="box" required>
                                            <option value="1" selected>Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="submit" value="Add Service" class="login-btn btn-primary btn" style="width: 100%; margin-top: 20px;">
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'edit-service') {
        // Get service data for editing
        $sqlmain = "SELECT * FROM tbl_services WHERE service_id='$id'";
        $result = $database->query($sqlmain);
        $row = $result->fetch_assoc();
        $service_name = htmlspecialchars($row["service_name"]);
        $service_code = htmlspecialchars($row["service_code"]);
        $description = htmlspecialchars($row["description"]);
        $duration_minutes = $row["duration_minutes"];
        $price = $row["price"];
        $is_active = $row["is_active"];
        
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Edit Service</h2>
                    <a class="close" href="doctors.php?tab=services">&times;</a>
                    <div class="content">
                        <form action="manage-services.php" method="POST" class="add-doc-form-container">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="service_id" value="' . $id . '">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="service_name" class="form-label">Service Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="service_name" class="input-text" placeholder="Service Name" value="' . $service_name . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="service_code" class="form-label">Service Code: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="service_code" class="input-text" placeholder="service_code" value="' . $service_code . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="description" class="form-label">Description: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <textarea name="description" class="input-text" placeholder="Service description" rows="3">' . $description . '</textarea><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="duration_minutes" class="form-label">Duration (minutes): </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="number" name="duration_minutes" class="input-text" placeholder="Duration in minutes" min="1" value="' . $duration_minutes . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="price" class="form-label">Price (₱): </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="number" name="price" class="input-text" placeholder="Service price" min="0" step="0.01" value="' . $price . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="is_active" class="form-label">Status: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <select name="is_active" class="box" required>
                                            <option value="1"' . ($is_active ? ' selected' : '') . '>Active</option>
                                            <option value="0"' . (!$is_active ? ' selected' : '') . '>Inactive</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="submit" value="Update Service" class="login-btn btn-primary btn" style="width: 100%; margin-top: 20px;">
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'delete-service') {
        $nameget = $_GET["name"] ?? '';
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Are you sure?</h2>
                    <a class="close" href="doctors.php">&times;</a>
                    <div class="content">
                        You want to delete this service<br>(' . htmlspecialchars(substr($nameget, 0, 40)) . ').
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="manage-services.php?action=delete&id=' . htmlspecialchars($id) . '" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;Yes&nbsp;</button></a>&nbsp;&nbsp;&nbsp;
                        <a href="doctors.php" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;&nbsp;No&nbsp;&nbsp;</button></a>
                    </div>
                </center>
            </div>
        </div>
        ';
    }
}
?>

<!-- Doctor Appointments Detail Modal -->
<div id="doctorAppointmentsModal" class="overlay" style="display: none;">
    <div class="popup">
        <h2 id="doctorModalTitle">Doctor Appointments</h2>
        <a class="close" href="javascript:void(0)" onclick="closeDoctorModal()">&times;</a>
        <div class="content" id="doctorModalContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
function showTab(tabName, clickedButton) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    if (tabName === 'doctors') {
        const tabContent = document.getElementById('doctorsTabContent');
        if (tabContent) tabContent.classList.add('active');
    } else if (tabName === 'appointments') {
        const tabContent = document.getElementById('appointmentsTabContent');
        if (tabContent) tabContent.classList.add('active');
    } else if (tabName === 'services') {
        const tabContent = document.getElementById('servicesTabContent');
        if (tabContent) tabContent.classList.add('active');
    }
    
    // Add active class to clicked button
    if (clickedButton) {
        clickedButton.classList.add('active');
    } else {
        // If no button provided, find the button by tab name
        const buttons = document.querySelectorAll('.tab-button');
        buttons.forEach(btn => {
            if (btn.textContent.includes('Manage Doctors') && tabName === 'doctors') {
                btn.classList.add('active');
            } else if (btn.textContent.includes('Doctor Appointments') && tabName === 'appointments') {
                btn.classList.add('active');
            } else if (btn.textContent.includes('Services Management') && tabName === 'services') {
                btn.classList.add('active');
            }
        });
    }
}

// Check URL parameters and set active tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    
    if (tab === 'services') {
        const btn = document.querySelector('.tab-button:contains("Services Management")') || 
                   Array.from(document.querySelectorAll('.tab-button')).find(b => b.textContent.includes('Services Management'));
        showTab('services', btn);
    } else if (tab === 'appointments') {
        const btn = Array.from(document.querySelectorAll('.tab-button')).find(b => b.textContent.includes('Doctor Appointments'));
        showTab('appointments', btn);
    } else {
        const btn = Array.from(document.querySelectorAll('.tab-button')).find(b => b.textContent.includes('Manage Doctors'));
        showTab('doctors', btn); // Default tab
    }
});

function viewDoctorAppointments(doctorId, doctorName) {
    document.getElementById('doctorModalTitle').textContent = 'Appointments for Dr. ' + doctorName;
    document.getElementById('doctorModalContent').innerHTML = '<div style="text-align: center; padding: 20px;">Loading appointments...</div>';
    document.getElementById('doctorAppointmentsModal').style.display = 'block';
    
    // Fetch doctor appointments
    fetch('get-doctor-appointments.php?doctor_id=' + doctorId)
        .then(response => response.json())
        .then(data => {
            let content = "";
            if (data.length === 0) {
                content = "<div style=\"text-align: center; padding: 20px;\">No appointments found for this doctor.</div>";
            } else {
                content = "<table width=\"100%\" class=\"sub-table\" border=\"0\">";
                content += "<thead><tr>";
                content += "<th class=\"table-headin\">Patient Name</th>";
                content += "<th class=\"table-headin\">Date</th>";
                content += "<th class=\"table-headin\">Time</th>";
                content += "<th class=\"table-headin\">Service</th>";
                content += "<th class=\"table-headin\">Status</th>";
                content += "<th class=\"table-headin\">Progress</th>";
                content += "</tr></thead><tbody>";
                
                data.forEach(appointment => {
                    const statusClass = appointment.status === 'pending' ? 'status-pending' : 
                                      appointment.status === 'approved' ? 'status-approved' : 'status-rejected';
                    const progressClass = 'progress-' + appointment.appointment_progress;
                    
                    content += '<tr>';
                    content += "<td>" + appointment.patient_name + "</td>";
                    content += "<td>" + appointment.booking_date + "</td>";
                    content += "<td>" + (appointment.appointment_time || "---") + "</td>";
                    content += "<td>" + appointment.appointment_type + "</td>";
                    content += "<td><span class=\"" + statusClass + "\">" + appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) + "</span></td>";
                    content += "<td><span class=\"progress-badge " + progressClass + "\">" + appointment.progress_text + "</span></td>";
                    content += '</tr>';
                });
                
                content += '</tbody></table>';
            }
            document.getElementById('doctorModalContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById("doctorModalContent").innerHTML = "<div style=\"text-align: center; padding: 20px; color: red;\">Error loading appointments.</div>";
        });
}

function closeDoctorModal() {
    document.getElementById("doctorAppointmentsModal").style.display = "none";
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('doctorAppointmentsModal');
    if (event.target == modal) {
        closeDoctorModal();
    }
}

// Function to toggle password visibility
function togglePasswordVisibility(inputId) {
    const passwordInput = document.getElementById(inputId);
    const icon = event.target;
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    }
}

// Password and form validation
let passwordValid = false;
let passwordsMatch = false;

function validatePassword(password) {
    const requirementsDiv = document.getElementById("password-requirements");
    
    // Show requirements only when user starts typing
    if(password.length > 0) {
        requirementsDiv.style.display = "block";
    } else {
        requirementsDiv.style.display = "none";
        return;
    }
    
    // Reset password valid flag
    passwordValid = true;
    
    // Length check
    if(password.length >= 8) {
        document.getElementById("length").classList.add("valid");
    } else {
        document.getElementById("length").classList.remove("valid");
        passwordValid = false;
    }
    
    // Uppercase check
    if(/[A-Z]/.test(password)) {
        document.getElementById("uppercase").classList.add("valid");
    } else {
        document.getElementById("uppercase").classList.remove("valid");
        passwordValid = false;
    }
    
    // Lowercase check
    if(/[a-z]/.test(password)) {
        document.getElementById("lowercase").classList.add("valid");
    } else {
        document.getElementById("lowercase").classList.remove("valid");
        passwordValid = false;
    }
    
    // Number check
    if(/[0-9]/.test(password)) {
        document.getElementById("number").classList.add("valid");
    } else {
        document.getElementById("number").classList.remove("valid");
        passwordValid = false;
    }
    
    validateConfirmPassword();
}

function validateConfirmPassword() {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("cpassword").value;
    const matchDiv = document.getElementById("password-match");
    
    if(confirmPassword === "") {
        matchDiv.style.color = "#dc3545";
        matchDiv.innerHTML = "Please confirm your password";
        passwordsMatch = false;
    } else if(password === confirmPassword) {
        matchDiv.style.color = "#28a745";
        matchDiv.innerHTML = "✓ Password Matched";
        passwordsMatch = true;
    } else {
        matchDiv.style.color = "#dc3545";
        matchDiv.innerHTML = "✕ Passwords do not match";
        passwordsMatch = false;
    }
}

function validateForm() {
    if(!passwordValid) {
        alert("Please ensure your password meets all requirements.");
        return false;
    }
    if(!passwordsMatch) {
        alert("Please ensure your password matches.");
        return false;
    }
    return true;
}
</script>

<style>
.requirement {
    color: #dc3545;
    margin: 2px 0;
}
.requirement.valid {
    color: #28a745;
}
.requirement::before {
    content: "✕";
    margin-right: 5px;
}
.requirement.valid::before {
    content: "✓";
}

.password-toggle-icon {
    color: #666;
    font-size: 16px;
    transition: color 0.3s ease;
}

.password-toggle-icon:hover {
    color: #333;
}
</style>
</body>
</html>