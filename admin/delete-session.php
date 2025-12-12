<?php
// filepath: c:\xampp\htdocs\Appointment\admin\delete-session.php

session_start();

// Only allow access for logged-in admins
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

if (isset($_GET["id"])) {
    include("../connection.php");
    $id = $_GET["id"];

    // Use prepared statement for security
    $stmt = $database->prepare("DELETE FROM tbl_schedule WHERE scheduleid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("location: schedule.php");
    exit();
}
?>