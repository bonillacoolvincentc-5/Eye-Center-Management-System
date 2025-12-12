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

// Import database
include("../connection.php");

// Use correct table and column names for patients
$stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();
$userid = $userfetch["Patient_id"];
$username = $userfetch["Fname"];
if (!empty($userfetch["Mname"])) {
    $username .= ' ' . $userfetch["Mname"];
}
$username .= ' ' . $userfetch["Lname"];
if (!empty($userfetch["Suffix"])) {
    $username .= ' ' . $userfetch["Suffix"];
}

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
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
    <title>Book Appointment</title>
    <style>
        .popup{ animation: transitionIn-Y-bottom 0.5s; }
        .sub-table{ animation: transitionIn-Y-bottom 0.5s; }
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
                                <img src="../Images/user.png" alt="" width="100%" style="border-radius:50%">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title"><?php echo htmlspecialchars(substr($username,0,13)); ?>..</p>
                                <p class="profile-subtitle"><?php echo htmlspecialchars(substr($useremail,0,22)); ?></p>
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
                    <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Home</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-session menu-active menu-icon-session-active">
                    <a href="schedule.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Scheduled Sessions</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Bookings</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
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
                    <h2>Book Appointment</h2>
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
                    <center>
                        <div class="abc scroll">
                            <table width="100%" class="sub-table scrolldown" border="0" style="padding: 50px;border:none">
                                <tbody>
                                <?php
                                if (isset($_GET["id"])) {
                                    $id = intval($_GET["id"]);
                                    $sqlmain = "SELECT tbl_schedule.*, 
                                    CONCAT(tbl_doctor.first_name,
                                           CASE WHEN tbl_doctor.middle_name IS NOT NULL AND tbl_doctor.middle_name != '' THEN CONCAT(' ', tbl_doctor.middle_name) ELSE '' END,
                                           ' ', tbl_doctor.last_name,
                                           CASE WHEN tbl_doctor.suffix IS NOT NULL AND tbl_doctor.suffix != '' THEN CONCAT(' ', tbl_doctor.suffix) ELSE '' END
                                    ) AS docname,
                                    tbl_doctor.docemail
                                    FROM tbl_schedule 
                                    INNER JOIN tbl_doctor ON tbl_schedule.docid = tbl_doctor.docid 
                                    WHERE tbl_schedule.scheduleid = ? 
                                    ORDER BY tbl_schedule.scheduledate DESC";
                                    $stmt = $database->prepare($sqlmain);
                                    $stmt->bind_param("i", $id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $row = $result->fetch_assoc();
                                    if ($row) {
                                        $scheduleid = $row["scheduleid"];
                                        $title = $row["title"];
                                        $docname = $row["docname"];
                                        $docemail = $row["docemail"];
                                        $scheduledate = $row["scheduledate"];
                                        $scheduletime = $row["start_time"];
                                        $result12 = $database->query("SELECT * FROM tbl_appointment WHERE scheduleid=$id");
                                        $apponum = ($result12->num_rows) + 1;
                                        echo '
                                        <form action="booking-complete.php" method="post">
                                            <input type="hidden" name="scheduleid" value="' . $scheduleid . '" >
                                            <input type="hidden" name="apponum" value="' . $apponum . '" >
                                            <input type="hidden" name="date" value="' . $today . '" >
                                            <td style="width: 50%;" rowspan="2">
                                                <div class="dashboard-items search-items">
                                                    <div style="width:100%">
                                                        <div class="h1-search" style="font-size:25px;">
                                                            Session Details
                                                        </div><br><br>
                                                        <div class="h3-search" style="font-size:18px;line-height:30px">
                                                            Doctors name:  &nbsp;&nbsp;<b>' . htmlspecialchars($docname) . '</b><br>
                                                            Doctor Email:  &nbsp;&nbsp;<b>' . htmlspecialchars($docemail) . '</b>
                                                        </div>
                                                        <div class="h3-search" style="font-size:18px;">
                                                        </div><br>
                                                        <div class="h3-search" style="font-size:18px;">
                                                            Session Title: ' . htmlspecialchars($title) . '<br>
                                                            Session Scheduled Date: ' . htmlspecialchars($scheduledate) . '<br>
                                                            Session Starts : ' . htmlspecialchars($scheduletime) . '<br>
                                                            Channeling fee : <b>LKR.2 000.00</b>
                                                        </div>
                                                        <br>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="width: 25%;">
                                                <div class="dashboard-items search-items">
                                                    <div style="width:100%;padding-top: 15px;padding-bottom: 15px;">
                                                        <div class="h1-search" style="font-size:20px;line-height: 35px;margin-left:8px;text-align:center;">
                                                            Your Appointment Number
                                                        </div>
                                                        <center>
                                                            <div class="dashboard-icons" style="margin-left: 0px;width:90%;font-size:70px;font-weight:800;text-align:center;color:var(--btnnicetext);background-color: var(--btnice)">' . $apponum . '</div>
                                                        </center>
                                                    </div><br><br><br>
                                                </div>
                                            </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input type="submit" class="login-btn btn-primary btn btn-book" style="margin-left:10px;padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;width:95%;text-align: center;" value="Book now" name="booknow">
                                                </td>
                                            </tr>
                                        </form>
                                        ';
                                    } else {
                                        echo '<tr><td colspan="2">Session not found.</td></tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="2">No session selected.</td></tr>';
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
</body>
</html>