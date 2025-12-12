<?php

    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
            header("location: ../login.php");
        }

    }else{
        header("location: ../login.php");
    }
    
    
    if($_GET){
        //import database
        include("../connection.php");
        $id=$_GET["id"];
        $useremail=$_SESSION["user"];
        
        // Get doctor ID
        $userrow = $database->query("select * from tbl_doctor where docemail='$useremail'");
        $userfetch=$userrow->fetch_assoc();
        $userid= $userfetch["docid"];
        
        // Verify the session belongs to this doctor before deleting
        $check_sql = $database->query("select * from tbl_schedule where scheduleid='$id' and docid='$userid'");
        if($check_sql->num_rows > 0){
            $sql= $database->query("delete from tbl_schedule where scheduleid='$id' and docid='$userid';");
            if($sql){
                header("location: schedule.php?action=session-deleted");
            } else {
                header("location: schedule.php?action=error&message=Failed to delete session");
            }
        } else {
            header("location: schedule.php?action=error&message=Session not found or access denied");
        }
    }


?>