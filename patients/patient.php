<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Patients</title>
    <style>
        .popup, .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
</head>
<body>
<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'd') {
    header("location: ../login.php");
    exit();
}

$useremail = $_SESSION["user"];
include("../connection.php");

// Get doctor info
$sqlmain = "SELECT * FROM tbl_doctor WHERE docemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userrow = $stmt->get_result();
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["docid"];
// Compose full name from parts
$first = $userfetch["first_name"] ?? '';
$middle = $userfetch["middle_name"] ?? '';
$last = $userfetch["last_name"] ?? '';
$suffix = $userfetch["suffix"] ?? '';
$username = $first;
if ($middle) $username .= ' ' . $middle;
$username .= ' ' . $last;
if ($suffix) $username .= ' ' . $suffix;
?>
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
                                <p class="profile-title"><?php echo substr($username, 0, 13); ?>..</p>
                                <p class="profile-subtitle"><?php echo substr($useremail, 0, 22); ?></p>
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
                <td class="menu-btn menu-icon-dashbord">
                    <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointments.php" class="non-style-link-menu"><div><p class="menu-text">My Appointments</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-session">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">My Sessions</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-patient menu-active menu-icon-patient-active">
                    <a href="patient.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">My Patients</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-settings">
                    <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a>
                </td>
            </tr>
        </table>
    </div>
    <?php
    $selecttype = "My";
    $current = "My patients Only";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST["search"])) {
            $keyword = $_POST["search12"];
            $sqlmain = "SELECT * FROM tbl_patients WHERE pemail=? OR pname=? OR pname LIKE ? OR pname LIKE ? OR pname LIKE ?";
            $like1 = "$keyword%";
            $like2 = "%$keyword";
            $like3 = "%$keyword%";
            $stmt = $database->prepare($sqlmain);
            $stmt->bind_param("sssss", $keyword, $keyword, $like1, $like2, $like3);
            $stmt->execute();
            $list11 = $stmt->get_result();
            $selecttype = "my";
        } elseif (isset($_POST["filter"])) {
            if ($_POST["showonly"] == 'all') {
                $sqlmain = "SELECT * FROM tbl_patients";
                $list11 = $database->query($sqlmain);
                $selecttype = "All";
                $current = "All patients";
            } else {
                $sqlmain = "SELECT * FROM tbl_appointment INNER JOIN tbl_patients ON tbl_patients.pid=tbl_appointment.pid INNER JOIN tbl_schedule ON tbl_schedule.scheduleid=tbl_appointment.scheduleid WHERE tbl_schedule.docid=?";
                $stmt = $database->prepare($sqlmain);
                $stmt->bind_param("i", $userid);
                $stmt->execute();
                $list11 = $stmt->get_result();
                $selecttype = "My";
                $current = "My patients Only";
            }
        }
    } else {
        $sqlmain = "SELECT * FROM tbl_appointment INNER JOIN tbl_patients ON tbl_patients.pid=tbl_appointment.pid INNER JOIN tbl_schedule ON tbl_schedule.scheduleid=tbl_appointment.scheduleid WHERE tbl_schedule.docid=?";
        $stmt = $database->prepare($sqlmain);
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $list11 = $stmt->get_result();
        $selecttype = "My";
    }
    ?>
    <div class="dash-body">
        <table border="0" width="100%" style="border-spacing: 0; margin:0; padding:0; margin-top:25px;">
            <tr>
                <td width="13%">
                    <a href="patient.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <form action="" method="post" class="header-search">
                        <input type="search" name="search12" class="input-text header-searchbar" placeholder="Search Patient name or Email" list="patient">&nbsp;&nbsp;
                        <?php
                        echo '<datalist id="patient">';
                        if (isset($list11)) {
                            foreach ($list11 as $row00) {
                                echo "<option value='{$row00["pname"]}'><br/>";
                                echo "<option value='{$row00["pemail"]}'><br/>";
                            }
                        }
                        echo '</datalist>';
                        ?>
                        <input type="submit" value="Search" name="search" class="login-btn btn-primary btn" style="padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;">
                    </form>
                </td>
                <td width="15%">
                    <p style="font-size: 14px; color: rgb(119, 119, 119); padding: 0; margin: 0; text-align: right;">Today's Date</p>
                    <p class="heading-sub12" style="padding: 0; margin: 0;">
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        $date = date('Y-m-d');
                        echo $date;
                        ?>
                    </p>
                </td>
                <td width="10%">
                    <button class="btn-label" style="display: flex; justify-content: center; align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:10px;">
                    <p class="heading-main12" style="margin-left: 45px; font-size:18px; color:rgb(49, 49, 49)">
                        <?php echo $selecttype . " Patients (" . (isset($list11) ? $list11->num_rows : 0) . ")"; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top:0px; width: 100%;">
                    <center>
                        <table class="filter-container" border="0">
                            <form action="" method="post">
                                <td style="text-align: right;">Show Details About : &nbsp;</td>
                                <td width="30%">
                                    <select name="showonly" class="box filter-container-items" style="width:90%; height: 37px; margin: 0;">
                                        <option value="" disabled selected hidden><?php echo $current; ?></option>
                                        <option value="my">My Patients Only</option>
                                        <option value="all">All Patients</option>
                                    </select>
                                </td>
                                <td width="12%">
                                    <input type="submit" name="filter" value=" Filter" class="btn-primary-soft btn button-icon btn-filter" style="padding: 15px; margin:0; width:100%">
                                </td>
                            </form>
                        </table>
                    </center>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <center>
                        <div class="abc scroll">
                            <table width="93%" class="sub-table scrolldown" style="border-spacing:0;">
                                <thead>
                                    <tr>
                                        <th class="table-headin">Name</th>
                                        <th class="table-headin">NIC</th>
                                        <th class="table-headin">Contact No</th>
                                        <th class="table-headin">Email</th>
                                        <th class="table-headin">Date of Birth</th>
                                        <th class="table-headin">Events</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (isset($list11)) {
                                        if ($list11->num_rows == 0) {
                                            echo '<tr>
                                                <td colspan="6">
                                                    <br><br><br><br>
                                                    <center>
                                                        <img src="../Images/notfound.svg" width="25%">
                                                        <br>
                                                        <p class="heading-main12" style="margin-left: 45px; font-size:20px; color:rgb(49, 49, 49)">We couldn\'t find anything related to your keywords!</p>
                                                        <a class="non-style-link" href="patient.php"><button class="login-btn btn-primary-soft btn" style="display: flex; justify-content: center; align-items: center; margin-left:20px;">&nbsp; Show all Patients &nbsp;</button></a>
                                                    </center>
                                                    <br><br><br><br>
                                                </td>
                                            </tr>';
                                        } else {
                                            foreach ($list11 as $row) {
                                                $pid = $row["pid"];
                                                $name = $row["pname"];
                                                $email = $row["pemail"];
                                                $nic = $row["pnic"];
                                                $dob = $row["pdob"];
                                                $tel = $row["ptel"];
                                                echo '<tr>
                                                    <td>&nbsp;' . substr($name, 0, 35) . '</td>
                                                    <td>' . substr($nic, 0, 12) . '</td>
                                                    <td>' . substr($tel, 0, 10) . '</td>
                                                    <td>' . substr($email, 0, 20) . '</td>
                                                    <td>' . substr($dob, 0, 10) . '</td>
                                                    <td>
                                                        <div style="display:flex;justify-content: center;">
                                                            <a href="?action=view&id=' . $pid . '" class="non-style-link"><button class="btn-primary-soft btn button-icon btn-view" style="padding-left: 40px; padding-top: 12px; padding-bottom: 12px; margin-top: 10px;"><font class="tn-in-text">View</font></button></a>
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
                </td>
            </tr>
        </table>
    </div>
</div>
<?php
if (isset($_GET["action"]) && $_GET["action"] == "view" && isset($_GET["id"])) {
    $id = $_GET["id"];
    $sqlmain = "SELECT * FROM tbl_patients WHERE pid=?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $name = $row["pname"];
    $email = $row["pemail"];
    $nic = $row["pnic"];
    $dob = $row["pdob"];
    $tele = $row["ptel"];
    $address = $row["paddress"];
    echo '
    <div id="popup1" class="overlay">
        <div class="popup">
            <center>
                <a class="close" href="patient.php">&times;</a>
                <div class="content"></div>
                <div style="display: flex;justify-content: center;">
                    <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        <tr>
                            <td>
                                <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details.</p><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="name" class="form-label">Patient ID: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">P-' . $id . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="name" class="form-label">Name: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $name . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="Email" class="form-label">Email: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $email . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="nic" class="form-label">NIC: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $nic . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="Tele" class="form-label">Contact No: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $tele . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="spec" class="form-label">Address: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $address . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="name" class="form-label">Date of Birth: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $dob . '<br><br></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="patient.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a>
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
?>
</body>
</html>