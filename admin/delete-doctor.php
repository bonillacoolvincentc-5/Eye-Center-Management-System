<?php

    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
            header("location: ../login.php");
        }

    }else{
        header("location: ../login.php");
    }
    
    
    if($_GET){
        //import database
        include("../connection.php");
        $id = intval($_GET["id"]); // Sanitize the input
        $result001 = $database->query("SELECT * FROM tbl_doctor WHERE docid = $id");
        if($result001 && $result001->num_rows > 0) {
            $email = ($result001->fetch_assoc())["docemail"];
            
            // Delete from tbl_webuser first (foreign key constraint)
            $sql = $database->query("DELETE FROM tbl_webuser WHERE email = '$email'");
            if($sql) {
                // Then delete from tbl_doctor
                $sql = $database->query("DELETE FROM tbl_doctor WHERE docemail = '$email'");
                if(!$sql) {
                    echo "Error deleting from tbl_doctor: " . $database->error;
                }
            } else {
                echo "Error deleting from tbl_webuser: " . $database->error;
            }
        } else {
            echo "Doctor not found with ID: " . $id;
        }
        //print_r($email);
        header("location: doctors.php");
    }


?>