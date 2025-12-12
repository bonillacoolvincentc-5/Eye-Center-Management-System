<?php
session_start();

if(!isset($_SESSION["user"]) || $_SESSION["user"]=="" || $_SESSION['usertype']!='a'){
    header("location: ../login.php");
    exit();
}

if($_POST){
    //import database
    include("../connection.php");
    
    // Sanitize and validate inputs
    $scheduleid = intval($_POST["scheduleid"]);
    $title = mysqli_real_escape_string($database, $_POST["title"]);
    $nop = intval($_POST["nop"]);
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    
    // Enforce maximum number of patients (max 10)
    if ($nop <= 0 || $nop > 10) {
        header("location: schedule.php?action=error&message=".urlencode("Number of patients must be between 1 and 10"));
        exit();
    }
    
    // Validate that end time is after start time
    if(strtotime($end_time) <= strtotime($start_time)) {
        header("location: schedule.php?action=error&message=".urlencode("End time must be after start time"));
        exit();
    }

    // Check if end_time column exists
    $checkColumnSql = "SHOW COLUMNS FROM tbl_schedule LIKE 'end_time'";
    $columnResult = $database->query($checkColumnSql);
    $hasEndTime = $columnResult->num_rows > 0;

    if ($hasEndTime) {
    // Update with start_time and end_time
    $sql = "UPDATE tbl_schedule 
            SET title = ?, scheduledate = ?, start_time = ?, nop = ?, end_time = ? 
            WHERE scheduleid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("sssisi", $title, $date, $start_time, $nop, $end_time, $scheduleid);
} else {
    // Update only start_time field
    $sql = "UPDATE tbl_schedule 
            SET title = ?, scheduledate = ?, start_time = ?, nop = ? 
            WHERE scheduleid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("sssii", $title, $date, $start_time, $nop, $scheduleid);
}
    
    if($stmt->execute()){
        header("location: schedule.php?action=session-updated&title=".urlencode($title));
    } else {
        header("location: schedule.php?action=error");
    }
    exit();
}
?>