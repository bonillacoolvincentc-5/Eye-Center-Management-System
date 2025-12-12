<?php

    session_start();

    if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
        header("location: ../login.php");
        exit();
    }

    $useremail = $_SESSION["user"];

    include("../connection.php");

    // Get current patient info
    $sql = "SELECT * FROM tbl_patients WHERE Email = ?";
    $stmt = $database->prepare($sql);
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

    if (isset($_GET["id"])) {
        $id = $_GET["id"];

        // Get email of patient to delete
        $sql = "SELECT * FROM tbl_patients WHERE Patient_id = ?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $email = ($result->fetch_assoc())["Email"];

        // Delete from webuser (if you have this table, otherwise you can skip this part)
        $sql = "DELETE FROM webuser WHERE email = ?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        // Delete from tbl_patients
        $sql = "DELETE FROM tbl_patients WHERE Email = ?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        header("location: ../logout.php");
        exit();
    }
?>