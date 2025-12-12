<?php
session_start();

if(!isset($_SESSION["user"]) || $_SESSION["user"]=="" || $_SESSION['usertype']!='d'){
    header("location: ../login.php");
    exit();
}

if($_POST){
    //import database
    include("../connection.php");
    
    // Get the current doctor's ID
    $useremail = $_SESSION["user"];
    $userrow = $database->query("select * from tbl_doctor where docemail='$useremail'");
    $userfetch = $userrow->fetch_assoc();
    $userid = $userfetch["docid"];
    
    // Sanitize and validate inputs
    $title = mysqli_real_escape_string($database, $_POST["title"]);
    $nop = intval($_POST["nop"]);
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    
    // Validate inputs
    if(empty($title) || empty($date) || empty($start_time) || empty($end_time) || $nop <= 0) {
        header("location: schedule.php?action=error&message=Please fill in all required fields");
        exit();
    }
    // Enforce maximum number of patients (max 10)
    if ($nop > 10) {
        header("location: schedule.php?action=error&message=Maximum number of patients per session is 10");
        exit();
    }
    
    // Validate that end time is after start time
    if(strtotime($end_time) <= strtotime($start_time)) {
        header("location: schedule.php?action=error&message=End time must be after start time");
        exit();
    }
    
    // Check for time conflicts with existing sessions for this doctor
    $conflictSql = "SELECT title, start_time, end_time FROM tbl_schedule 
                    WHERE docid = ? AND scheduledate = ? 
                    AND (
                        (start_time <= ? AND end_time > ?) OR 
                        (start_time < ? AND end_time >= ?) OR
                        (start_time >= ? AND end_time <= ?)
                    )";
    
    $stmt = $database->prepare($conflictSql);
    $stmt->bind_param("ssssssss", $userid, $date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $conflict = $result->fetch_assoc();
        $conflictTime = date('h:i A', strtotime($conflict['start_time'])) . ' - ' . date('h:i A', strtotime($conflict['end_time']));
        header("location: schedule.php?action=error&message=Time conflict detected! You already have a session '".$conflict['title']."' scheduled from ".$conflictTime." on this date. Please choose a different time.");
        exit();
    }
    
    // Insert the new session
    $sql = "INSERT INTO tbl_schedule (title, scheduledate, start_time, end_time, nop, docid) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("ssssis", $title, $date, $start_time, $end_time, $nop, $userid);
    
    if($stmt->execute()){
        header("location: schedule.php?action=session-added&title=".urlencode($title));
    } else {
        header("location: schedule.php?action=error&message=Failed to create session");
    }
    exit();
}
?>

