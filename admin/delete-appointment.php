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

if ($_GET && isset($_GET["id"])) {
    include("../connection.php");
    $id = intval($_GET["id"]);
    // Use correct table name and parameterized query for safety
    $stmt = $database->prepare("DELETE FROM tbl_appointment WHERE appoid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("location: appointment.php");
    exit();
}
?>