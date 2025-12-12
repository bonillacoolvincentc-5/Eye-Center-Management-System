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
    $title = mysqli_real_escape_string($database, $_POST["title"]);
    $nop = intval($_POST["nop"]);
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $docids = isset($_POST['docid']) ? $_POST['docid'] : array();
    
    // Validate that at least one doctor is selected
    if(empty($docids) || !is_array($docids)) {
        header("location: schedule.php?action=error&message=Please select at least one doctor");
        exit();
    }
    
    // Enforce maximum number of patients (max 10)
    if ($nop <= 0 || $nop > 10) {
        header("location: schedule.php?action=error&message=Number of patients must be between 1 and 10");
        exit();
    }

    // Validate that end time is after start time
    if(strtotime($end_time) <= strtotime($start_time)) {
        header("location: schedule.php?action=error&message=End time must be after start time");
        exit();
    }

    // Check if end_time column exists
    $checkColumnSql = "SHOW COLUMNS FROM tbl_schedule LIKE 'end_time'";
    $columnResult = $database->query($checkColumnSql);
    $hasEndTime = $columnResult->num_rows > 0;

    $successCount = 0;
    $errorCount = 0;
    $conflictMessages = array();
    
    // Check for time conflicts for each doctor before inserting
    foreach($docids as $docid) {
        $docid = intval($docid);
        if($docid <= 0) continue;
        
        // Check for time conflicts with existing sessions for this doctor
        $conflictSql = "SELECT title, start_time, end_time FROM tbl_schedule 
                        WHERE docid = ? AND scheduledate = ? 
                        AND (
                            (start_time <= ? AND end_time > ?) OR 
                            (start_time < ? AND end_time >= ?) OR
                            (start_time >= ? AND end_time <= ?)
                        )";
        
        $stmt = $database->prepare($conflictSql);
        $stmt->bind_param("ssssssss", $docid, $date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $conflict = $result->fetch_assoc();
            $conflictTime = date('h:i A', strtotime($conflict['start_time'])) . ' - ' . date('h:i A', strtotime($conflict['end_time']));
            $conflictMessages[] = "Doctor ID $docid: Time conflict with session '".$conflict['title']."' scheduled from ".$conflictTime." on this date.";
        }
    }
    
    // If there are conflicts, show error message
    if(!empty($conflictMessages)) {
        $message = "Time conflicts detected:\n" . implode("\n", $conflictMessages);
        header("location: schedule.php?action=error&message=".urlencode($message));
        exit();
    }
    
    // Insert session for each selected doctor
    foreach($docids as $docid) {
        $docid = intval($docid);
        if($docid <= 0) continue;
        
        if ($hasEndTime) {
            $sql = "INSERT INTO tbl_schedule (title, scheduledate, start_time, nop, end_time, docid) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $database->prepare($sql);
            $stmt->bind_param("sssiss", $title, $date, $start_time, $nop, $end_time, $docid);
        } else {
            // Fallback: store only in start_time field
            $sql = "INSERT INTO tbl_schedule (title, scheduledate, start_time, nop, docid) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $database->prepare($sql);
            $stmt->bind_param("sssis", $title, $date, $start_time, $nop, $docid);
        }
        
        if($stmt->execute()){
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    if($successCount > 0) {
        $message = $successCount . " session(s) created successfully";
        if($errorCount > 0) {
            $message .= " (" . $errorCount . " failed)";
        }
        header("location: schedule.php?action=session-added&title=".urlencode($title)."&message=".urlencode($message));
    } else {
        header("location: schedule.php?action=error&message=Failed to create sessions");
    }
    exit();
}
?>