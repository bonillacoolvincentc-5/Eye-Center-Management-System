<?php

    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
            header("location: ../login.php");
        }

    }else{
        header("location: ../login.php");
    }
    
    
    if($_GET){
        //import database
        include("../connection.php");
        $id=$_GET["id"];
        $sql = "DELETE FROM appointment WHERE appoid=?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("location: appointment.php");
    }


?>