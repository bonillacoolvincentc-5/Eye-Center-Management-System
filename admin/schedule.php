<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
    <title>Schedule</title>
    <style>
        .popup { animation: transitionIn-Y-bottom 0.5s; }
        .sub-table { animation: transitionIn-Y-bottom 0.5s; }
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
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

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
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-schedule menu-active menu-icon-schedule-active">
                    <a href="schedule.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Schedule</p></div></a>
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
                    <a href="schedule.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Schedule Manager</p>
                </td>
                <td width="15%">
                    <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                        Today's Date
                    </p>
                    <p class="heading-sub12" style="padding: 0;margin: 0;">
                        <?php echo $today; ?>
                    </p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div style="display: flex;margin-top: 40px;">
                        <div class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49);margin-top: 5px;">Schedule a Session</div>
                        <a href="?action=add-session&id=none&error=0" class="non-style-link"><button class="login-btn btn-primary btn button-icon" style="margin-left:25px;background-image: url('../Images/icons/add.svg');">Add a Session</button></a>
                    </div>
                </td>
            </tr>
            <?php
            // Get all sessions count
            $list110 = $database->query("SELECT * FROM tbl_schedule;");
            ?>
            <tr>
                <td colspan="4" style="padding-top:10px;width: 100%;">
                    <p class="heading-main12" style="margin-left: 45px;font-size:18px;color:rgb(49, 49, 49)">All Sessions (<?php echo $list110->num_rows; ?>)</p>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:0px;width: 100%;">
                    <center>
                        <table class="filter-container" border="0">
                            <tr>
                                <td width="10%"></td>
                                <td width="5%" style="text-align: center;">Date:</td>
                                <td width="30%">
                                    <form action="" method="post">
                                        <input type="date" name="sheduledate" id="date" class="input-text filter-container-items" style="margin: 0;width: 95%;">
                                </td>
                                <td width="12%">
                                    <input type="submit" name="filter" value="Filter" class="btn-primary-soft btn button-icon btn-filter" style="padding: 15px; margin:0;width:100%">
                                    </form>
                                </td>
                                <td width="12%">
                                    <a href="schedule.php" class="non-style-link">
                                        <button class="btn-primary-soft btn button-icon btn-filter" style="padding: 15px; margin:0;width:100%">
                                            Reset
                                        </button>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </center>
                </td>
            </tr>
            <?php
            // Filtering logic - initial query
            $sqlmain = "SELECT scheduleid, title, scheduledate, start_time, end_time, nop FROM tbl_schedule";

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter']) && !empty($_POST["sheduledate"])) {
                $sheduledate = $_POST["sheduledate"];
                $sqlmain .= " WHERE scheduledate = ?";
                $stmt = $database->prepare($sqlmain . " ORDER BY scheduledate DESC");
                $stmt->bind_param("s", $sheduledate);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                // Default query
                $result = $database->query($sqlmain . " ORDER BY scheduledate DESC");
            }
            ?>
            <tr>
                <td colspan="4">
                    <center>
                        <div class="abc scroll">
                            <table width="93%" class="sub-table scrolldown" border="0">
                                <thead>
                                    <tr>
                                        <th class="table-headin">Session Title</th>
                                        <th class="table-headin">Scheduled Date & Time</th>
                                        <th class="table-headin">Max Bookings</th>
                                        <th class="table-headin">Events</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result->num_rows == 0) {
                                        echo '<tr>
                                            <td colspan="5">
                                                <br><br><br><br>
                                                <center>
                                                    <img src="../Images/notfound.svg" width="25%">
                                                    <br>
                                                    <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">We couldn\'t find anything related to your keywords!</p>
                                                    <a class="non-style-link" href="schedule.php"><button class="login-btn btn-primary-soft btn" style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; Show all Sessions &nbsp;</button></a>
                                                </center>
                                                <br><br><br><br>
                                            </td>
                                        </tr>';
                                    } else {
                                        while ($row = $result->fetch_assoc()) {
                                            $scheduleid = $row["scheduleid"];
                                            $title = $row["title"];
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
                                                <td>' . htmlspecialchars(substr($title, 0, 30)) . '</td>
                                                <td style="text-align:center;">' . htmlspecialchars(substr($scheduledate, 0, 10)) . ' ' . htmlspecialchars($display_time) . '</td>
                                                <td style="text-align:center;">' . htmlspecialchars($nop) . '</td>
                                                <td>
                                                    <div style="display:flex;justify-content: center;">
                                                        <a href="schedule.php?action=edit&id=' . $scheduleid . '" class="non-style-link">
                                                            <button class="btn-primary-soft btn button-icon btn-edit" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;margin-right: 10px;">
                                                                <font class="tn-in-text">Edit</font>
                                                            </button>
                                                        </a>
                                                        <a href="schedule.php?action=drop&id=' . $scheduleid . '&name=' . urlencode($title) . '" class="non-style-link">
                                                            <button class="btn-primary-soft btn button-icon btn-delete" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;">
                                                                <font class="tn-in-text">Remove</font>
                                                            </button>
                                                        </a>
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
                </td>
            </tr>
        </table>
    </div>
</div>
<?php
if ($_GET) {
    $id = $_GET["id"] ?? '';
    $action = $_GET["action"] ?? '';
    if ($action == 'add-session') {
        // Build doctor options for select
        $doctorOptions = '';
        $dres = $database->query("SELECT docid, first_name, middle_name, last_name, suffix FROM tbl_doctor ORDER BY last_name, first_name");
        while ($drow = $dres->fetch_assoc()) {
            // Compose full name from parts
            $docname = $drow['first_name'];
            if (!empty($drow['middle_name'])) $docname .= ' ' . $drow['middle_name'];
            $docname .= ' ' . $drow['last_name'];
            if (!empty($drow['suffix'])) $docname .= ' ' . $drow['suffix'];
            $doctorOptions .= '<option value="'.htmlspecialchars($drow['docid']).'">'.htmlspecialchars($docname).'</option>';
        }

        echo '<div id="popup1" class="overlay">
    <div class="popup">
        <center>
            <a class="close" href="schedule.php">&times;</a>
            <div style="display: flex;justify-content: center;">
                <div class="abc">
                    <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        <tr>
                            <td>
                                <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Add New Session.</p><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">
                                <form action="add-session.php" method="POST" class="add-new-form">
                                    <label for="title" class="form-label">Session Title : </label>
                                    <input type="text" name="title" class="input-text" placeholder="Name of this Session" required><br>

                                    <label for="docid" class="form-label">Select Doctor(s) : </label>
                                    <select name="docid[]" class="box" multiple required style="height: 120px;">
                                        '.$doctorOptions.'
                                    </select>
                                    <small style="color: #666; font-size: 12px;">Hold Ctrl/Cmd to select multiple doctors</small><br>

                                    <label for="nop" class="form-label">Number of Patients/Appointment Numbers : </label>
                                    <input type="number" name="nop" class="input-text" min="1" max="10" placeholder="Maximum number of appointments for this session (max: 10)" required><br>

                                    <label for="date" class="form-label">Session Date: </label>
                                    <input type="date" name="date" class="input-text" min="' . date('Y-m-d') . '" required><br>

                                    <div style="display: flex; gap: 20px;">
                                        <div style="flex: 1;">
                                            <label for="start_time" class="form-label">🕐 Start Time: </label>
                                            <input type="time" name="start_time" class="input-text" step="1800" required>
                                        </div>
                                        <div style="flex: 1;">
                                            <label for="end_time" class="form-label">🕐 End Time: </label>
                                            <input type="time" name="end_time" class="input-text" step="1800" required>
                                        </div>
                                    </div><br>

                                    <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <input type="submit" value="Place this Session" class="login-btn btn-primary btn" name="shedulesubmit">
                                </form>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </center>
        <br><br>
    </div>
</div>';
    } elseif ($action == 'session-added') {
        $titleget = $_GET["title"] ?? '';
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <br><br>
                    <h2>Session Placed.</h2>
                    <a class="close" href="schedule.php">&times;</a>
                    <div class="content">
                        ' . htmlspecialchars(substr($titleget, 0, 40)) . ' was scheduled.<br><br>
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="schedule.php" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;&nbsp;OK&nbsp;&nbsp;</button></a>
                        <br><br><br><br>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'error') {
        $message = $_GET["message"] ?? 'An error occurred';
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <br><br>
                    <h2 style="color: #e74c3c;">Error</h2>
                    <a class="close" href="schedule.php">&times;</a>
                    <div class="content" style="white-space: pre-line;">
                        ' . htmlspecialchars($message) . '<br><br>
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="schedule.php" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;&nbsp;OK&nbsp;&nbsp;</button></a>
                        <br><br><br><br>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'session-updated') {
        $titleget = $_GET["title"] ?? '';
        echo '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <br><br>
                    <h2>Session Updated.</h2>
                    <a class="close" href="schedule.php">&times;</a>
                    <div class="content">
                        ' . htmlspecialchars(substr($titleget, 0, 40)) . ' was updated successfully.<br><br>
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="schedule.php" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;&nbsp;OK&nbsp;&nbsp;</button></a>
                        <br><br><br><br>
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
                    <a class="close" href="schedule.php">&times;</a>
                    <div class="content">
                        You want to delete this record<br>(' . htmlspecialchars(substr($nameget, 0, 40)) . ').
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="delete-session.php?id=' . htmlspecialchars($id) . '" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;Yes&nbsp;</button></a>&nbsp;&nbsp;&nbsp;
                        <a href="schedule.php" class="non-style-link"><button class="btn-primary btn" style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">&nbsp;&nbsp;No&nbsp;&nbsp;</button></a>
                    </div>
                </center>
            </div>
        </div>
        ';
    } elseif ($action == 'edit') {
        if (!$id) {
            echo "<script>window.location.href = 'schedule.php';</script>";
            exit();
        }

        // Get the session details
        $sqlmain = "SELECT * FROM tbl_schedule WHERE scheduleid = ?";
        $stmt = $database->prepare($sqlmain);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo "<script>window.location.href = 'schedule.php';</script>";
            exit();
        }
        
        $row = $result->fetch_assoc();
        $title = $row["title"];
        $scheduledate = $row["scheduledate"];
        $start_time = $row["start_time"];
        $end_time = $row["end_time"];
        $nop = $row["nop"];
        
        echo '<div id="popup1" class="overlay">
        <div class="popup">
            <center>
                <a class="close" href="schedule.php">&times;</a>
                <div style="display: flex;justify-content: center;">
                    <div class="abc">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Edit Session.</p><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <form action="edit-session.php" method="POST" class="add-new-form">
                                        <input type="hidden" name="scheduleid" value="' . $id . '">
                                        <label for="title" class="form-label">Session Title : </label>
                                        <input type="text" name="title" class="input-text" placeholder="Name of this Session" value="' . htmlspecialchars($title) . '" required><br>

                                        <label for="nop" class="form-label">Number of Patients/Appointment Numbers : </label>
                                        <input type="number" name="nop" class="input-text" min="1" max="10" placeholder="Maximum number of appointments for this session (max: 10)" value="' . htmlspecialchars($nop) . '" required><br>

                                        <label for="date" class="form-label">Session Date: </label>
                                        <input type="date" name="date" class="input-text" min="' . date('Y-m-d') . '" value="' . htmlspecialchars($scheduledate) . '" required><br>

                                        <div style="display: flex; gap: 20px;">
                                            <div style="flex: 1;">
                                                <label for="start_time" class="form-label">Start Time: </label>
                                                <input type="time" name="start_time" class="input-text" value="' . htmlspecialchars($start_time) . '" required>
                                            </div>
                                            <div style="flex: 1;">
                                                <label for="end_time" class="form-label">End Time: </label>
                                                <input type="time" name="end_time" class="input-text" value="' . htmlspecialchars($end_time) . '" required>
                                            </div>
                                        </div><br>

                                        <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <input type="submit" value="Update Session" class="login-btn btn-primary btn" name="schedulesubmit">
                                    </form>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </center>
            <br><br>
        </div>
    </div>';
    } elseif ($action == 'view') {
        if (!$id) {
            echo "<script>window.location.href = 'schedule.php';</script>";
            exit();
        }

        $sqlmain = "SELECT 
            s.scheduleid,
            s.title,
            s.scheduledate,
            s.start_time,
            s.end_time,
            s.nop 
        FROM tbl_schedule s
        WHERE s.scheduleid = ?";

        $stmt = $database->prepare($sqlmain);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            echo "<script>window.location.href = 'schedule.php';</script>";
            exit();
        }

        $row = $result->fetch_assoc();
        $scheduleid = $row["scheduleid"];
        $title = $row["title"];
        $scheduledate = $row["scheduledate"];
        $start_time = $row["start_time"];
        $end_time = $row["end_time"];
        $nop = $row['nop'];

        // Format time for display
        $display_time = '';
        if ($start_time && $end_time) {
            $display_time = date('h:i A', strtotime($start_time)) . ' - ' . date('h:i A', strtotime($end_time));
        } elseif ($start_time) {
            $display_time = date('h:i A', strtotime($start_time));
        }

        // Get appointments for this schedule
        $sqlmain12 = "SELECT 
            a.apponum,
            p.Patient_id,
            CONCAT(
                p.Fname,
                IF(p.Mname != '', CONCAT(' ', p.Mname), ''),
                ' ',
                p.Lname,
                IF(p.Suffix != '', CONCAT(' ', p.Suffix), '')
            ) AS pname,
            p.ptel 
        FROM tbl_appointment a
        INNER JOIN tbl_patients p ON p.Patient_id = a.pid 
        WHERE a.scheduleid = ?";

        $stmt12 = $database->prepare($sqlmain12);
        $stmt12->bind_param("i", $id);
        $stmt12->execute();
        $result12 = $stmt12->get_result();

        echo '
        <div id="popup1" class="overlay">
            <div class="popup" style="width: 70%;">
                <center>
                    <a class="close" href="schedule.php">&times;</a>
                    <div class="abc scroll" style="display: flex;justify-content: center;">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details.</p><br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label class="form-label">Session Title: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . htmlspecialchars($title) . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label class="form-label">Scheduled Date: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . htmlspecialchars($scheduledate) . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label class="form-label">Scheduled Time: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . htmlspecialchars($display_time) . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label class="form-label"><b>Patients that Already registered for this session:</b> (' . $result12->num_rows . '/' . $nop . ')</label><br><br></td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <center>
                                        <div class="abc scroll">
                                            <table width="100%" class="sub-table scrolldown" border="0">
                                                <thead>
                                                    <tr>
                                                        <th class="table-headin">Patient ID</th>
                                                        <th class="table-headin">Patient name</th>
                                                        <th class="table-headin">Appointment number</th>
                                                        <th class="table-headin">Patient Contact No.</th>
                                                    </tr>
                                                </thead>
                                                <tbody>';
        if ($result12->num_rows == 0) {
            echo '<tr>
                <td colspan="4">
                    <br><br><br><br>
                    <center>
                        <img src="../Images/notfound.svg" width="25%">
                        <br>
                        <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">No patients registered for this session.</p>
                    </center>
                    <br><br><br><br>
                </td>
            </tr>';
        } else {
            while ($row12 = $result12->fetch_assoc()) {
                echo '<tr style="text-align:center;">
                    <td>' . htmlspecialchars($row12["Patient_id"]) . '</td>
                    <td style="font-weight:600;padding:25px">' . htmlspecialchars(substr($row12["pname"], 0, 25)) . '</td>
                    <td style="text-align:center;font-size:23px;font-weight:500; color: var(--btnnicetext);">' . htmlspecialchars($row12["apponum"]) . '</td>
                    <td>' . htmlspecialchars($row12["ptel"]) . '</td>
                </tr>';
            }
        }
        echo '                                  </tbody>
                                            </table>
                                        </div>
                                    </center>
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
</body>
</html>