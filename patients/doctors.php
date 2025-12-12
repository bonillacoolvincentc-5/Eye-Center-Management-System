<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");
$useremail = $_SESSION["user"];

// Secure patient data fetch using prepared statement
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
$username = $userfetch["Fname"];
if (!empty($userfetch["Mname"])) {
    $username .= ' ' . $userfetch["Mname"];
}
$username .= ' ' . $userfetch["Lname"];
if (!empty($userfetch["Suffix"])) {
    $username .= ' ' . $userfetch["Suffix"];
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
    <title>Doctors</title>
    <style>
        .popup { animation: transitionIn-Y-bottom 0.5s; }
        .sub-table { animation: transitionIn-Y-bottom 0.5s; }
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
                                <p class="profile-title"><?php echo htmlspecialchars(substr($username,0,13)) ?>..</p>
                                <p class="profile-subtitle"><?php echo htmlspecialchars(substr($useremail,0,22)) ?></p>
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
                <td class="menu-btn menu-icon-doctor menu-active menu-icon-doctor-active">
                    <a href="doctors.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">All Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-session">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Scheduled Sessions</p></div></a>
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
                    <a href="doctors.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <form action="" method="post" class="header-search">
                        <input type="search" name="search" class="input-text header-searchbar" placeholder="Search Doctor's name or Email" list="doctors">&nbsp;&nbsp;
                        <?php
                        echo '<datalist id="doctors">';
                        $list11 = $database->query("SELECT first_name, middle_name, last_name, suffix, docemail FROM tbl_doctor;");
                        while ($row00 = $list11->fetch_assoc()) {
                            // Compose full name from parts
                            $first = $row00["first_name"] ?? '';
                            $middle = $row00["middle_name"] ?? '';
                            $last = $row00["last_name"] ?? '';
                            $suffix = $row00["suffix"] ?? '';
                            $d = $first;
                            if ($middle) $d .= ' ' . $middle;
                            $d .= ' ' . $last;
                            if ($suffix) $d .= ' ' . $suffix;
                            $d = htmlspecialchars($d);
                            $c = htmlspecialchars($row00["docemail"]);
                            echo "<option value=\"$d\">";
                            echo "<option value=\"$c\">";
                        }
                        echo '</datalist>';
                        ?>
                        <input type="submit" value="Search" class="login-btn btn-primary btn" style="padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;">
                    </form>
                </td>
                <td width="15%">
                    <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                        Today's Date
                    </p>
                    <p class="heading-sub12" style="padding: 0;margin: 0;">
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        echo date('Y-m-d');
                        ?>
                    </p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:10px;">
                    <p class="heading-main12" style="margin-left: 45px;font-size:18px;color:rgb(49, 49, 49)">All Doctors</p>
                </td>
            </tr>
            <?php
            // Initialize doctor count
            $doctor_count = 0;
            
            // Handle search or show all doctors
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
                $keyword = $_POST["search"];
                $sqlmain = "SELECT * FROM tbl_doctor WHERE docemail=? OR first_name LIKE ? OR last_name LIKE ? OR (CONCAT(first_name, ' ', last_name) LIKE ?) OR (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?)";
                $stmt = $database->prepare($sqlmain);
                $like1 = "%$keyword%";
                $like2 = "%$keyword%";
                $like3 = "%$keyword%";
                $like4 = "%$keyword%";
                $stmt->bind_param("sssss", $keyword, $like1, $like2, $like3, $like4);
                $stmt->execute();
                $result = $stmt->get_result();
                $doctor_count = $result->num_rows;
            } else {
                $sqlmain = "SELECT * FROM tbl_doctor ORDER BY docid DESC";
                $result = $database->query($sqlmain);
                $doctor_count = $result->num_rows;
            }
            ?>
            <tr>
                <td colspan="4">
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
                                if ($doctor_count == 0) {
                                    echo '<tr>
                                    <td colspan="4">
                                    <br><br><br><br>
                                    <center>
                                    <img src="../Images/notfound.svg" width="25%">
                                    <br>
                                    <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">No doctors found!</p>
                                    <a class="non-style-link" href="doctors.php"><button class="login-btn btn-primary-soft btn" style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; Show all Doctors &nbsp;</button></a>
                                    </center>
                                    <br><br><br><br>
                                    </td>
                                    </tr>';
                                } else {
                                    while ($row = $result->fetch_assoc()) {
                                        $docid = $row["docid"];
                                        // Compose full name from parts
                                        $first = $row["first_name"] ?? '';
                                        $middle = $row["middle_name"] ?? '';
                                        $last = $row["last_name"] ?? '';
                                        $suffix = $row["suffix"] ?? '';
                                        $name = $first;
                                        if ($middle) $name .= ' ' . $middle;
                                        $name .= ' ' . $last;
                                        if ($suffix) $name .= ' ' . $suffix;
                                        $name = htmlspecialchars($name);
                                        $email = htmlspecialchars($row["docemail"]);
                                        $spe = $row["specialties"];
                                        
                                        // FIX: Use tbl_specialties instead of specialties
                                        $stmt_spec = $database->prepare("SELECT sname FROM tbl_specialties WHERE id=?");
                                        $stmt_spec->bind_param("i", $spe);
                                        $stmt_spec->execute();
                                        $spec_result = $stmt_spec->get_result();
                                        $spcil_array = $spec_result->fetch_assoc();
                                        $spcil_name = htmlspecialchars($spcil_array["sname"] ?? 'General');
                                        
                                        echo '<tr>
                                            <td>&nbsp;'.substr($name,0,30).'</td>
                                            <td>'.substr($email,0,20).'</td>
                                            <td>'.substr($spcil_name,0,20).'</td>
                                            <td>
                                                <div style="display:flex;justify-content: center;">
                                                    <a href="?action=view&id='.$docid.'" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-view" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">View</font></button></a>
                                                    &nbsp;&nbsp;&nbsp;
                                                    <a href="?action=session&id='.$docid.'&name='.urlencode($name).'" class="non-style-link"><button class="btn-primary-soft btn button-icon menu-icon-session-active" style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">Sessions</font></button></a>
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
// Handle view and session actions
$popup_html = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $docid = intval($_GET['id']);

    if ($action === 'view') {
        // Fetch doctor details
        $stmt = $database->prepare("SELECT * FROM tbl_doctor WHERE docid=?");
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();

        // Fetch specialty name
        $spcil_name = 'General';
        if ($doc && !empty($doc['specialties'])) {
            $stmt_spec = $database->prepare("SELECT sname FROM tbl_specialties WHERE id=?");
            $stmt_spec->bind_param("i", $doc['specialties']);
            $stmt_spec->execute();
            $spec_result = $stmt_spec->get_result();
            $spcil_array = $spec_result->fetch_assoc();
            if ($spcil_array) {
                $spcil_name = htmlspecialchars($spcil_array["sname"]);
            }
        }

        if ($doc) {
            // Compose full name from parts
            $first = $doc['first_name'] ?? '';
            $middle = $doc['middle_name'] ?? '';
            $last = $doc['last_name'] ?? '';
            $suffix = $doc['suffix'] ?? '';
            $docname = $first;
            if ($middle) $docname .= ' ' . $middle;
            $docname .= ' ' . $last;
            if ($suffix) $docname .= ' ' . $suffix;
            
            $popup_html = '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <center>
                        <h2>Doctor Details</h2>
                        <a class="close" href="doctors.php">&times;</a>
                        <div class="content">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($docname) . '</td></tr>
                                <tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($doc["docemail"]) . '</td></tr>
                                <tr><td><strong>NIC:</strong></td><td>' . htmlspecialchars($doc["docnic"]) . '</td></tr>
                                <tr><td><strong>Contact No:</strong></td><td>' . htmlspecialchars($doc["doctel"]) . '</td></tr>
                                <tr><td><strong>Specialty:</strong></td><td>' . $spcil_name . '</td></tr>
                            </table>
                        </div>
                        <div style="display: flex;justify-content: center;">
                            <a href="doctors.php"><button class="btn-primary btn" style="margin:10px;padding:10px;">OK</button></a>
                        </div>
                    </center>
                </div>
            </div>
            ';
        }
    } elseif ($action === 'session') {
        // Fetch sessions for this doctor
        $stmt = $database->prepare("SELECT * FROM tbl_schedule WHERE docid=? ORDER BY scheduledate ASC, scheduletime ASC");
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        $sessions = $stmt->get_result();

        $popup_html = '
        <div id="popup1" class="overlay">
            <div class="popup">
                <center>
                    <h2>Doctor Sessions</h2>
                    <a class="close" href="doctors.php">&times;</a>
                    <div class="content">
                        <table width="90%" class="sub-table scrolldown" border="0">
                            <thead>
                                <tr>
                                    <th>Session Title</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Max Patients</th>
                                </tr>
                            </thead>
                            <tbody>';
        if ($sessions->num_rows == 0) {
            $popup_html .= '<tr><td colspan="4">No sessions found for this doctor.</td></tr>';
        } else {
            while ($row = $sessions->fetch_assoc()) {
                $popup_html .= '<tr>
                    <td>' . htmlspecialchars($row["title"]) . '</td>
                    <td>' . htmlspecialchars($row["scheduledate"]) . '</td>
                    <td>' . htmlspecialchars($row["scheduletime"]) . '</td>
                    <td>' . htmlspecialchars($row["nop"]) . '</td>
                </tr>';
            }
        }
        $popup_html .= '
                            </tbody>
                        </table>
                    </div>
                    <div style="display: flex;justify-content: center;">
                        <a href="doctors.php"><button class="btn-primary btn" style="margin:10px;padding:10px;">OK</button></a>
                    </div>
                </center>
            </div>
        </div>
        ';
    }
}

// Output the popup HTML
echo $popup_html;
?>

</body>
</html>