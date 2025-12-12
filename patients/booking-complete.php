<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'p') {
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

// Use correct table and columns
$sqlmain = "SELECT * FROM tbl_patients WHERE Email=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userrow = $stmt->get_result();
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["Patient_id"];
$username = $userfetch["Fname"];
if (!empty($userfetch["Mname"])) {
    $username .= ' ' . $userfetch["Mname"];
}
$username .= ' ' . $userfetch["Lname"];
if (!empty($userfetch["Suffix"])) {
    $username .= ' ' . $userfetch["Suffix"];
}

if ($_POST) {
    if (isset($_POST["booknow"])) {
        $apponum = $_POST["apponum"];
        $scheduleid = $_POST["scheduleid"];
        $date = $_POST["date"];
        
        // Changed table name from 'appointment' to 'tbl_appointment'
        $sql2 = "INSERT INTO tbl_appointment (pid, apponum, scheduleid, appodate, status) 
                 VALUES (?, ?, ?, ?, 'Active')";
        $stmt2 = $database->prepare($sql2);
        $stmt2->bind_param("iiis", $userid, $apponum, $scheduleid, $date);
        
        // Add error handling
        if (!$stmt2->execute()) {
            error_log("Booking failed: " . $stmt2->error);
            header("location: appointment.php?action=booking-failed");
            exit();
        }

        header("location: appointment.php?action=booking-added&id=" . $apponum . "&titleget=none");
        exit();
    }
}
?>