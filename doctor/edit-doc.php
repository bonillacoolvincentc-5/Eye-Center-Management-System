<?php
//import database
include("../connection.php");

if($_POST){
    //print_r($_POST);
    $result= $database->query("select * from tbl_webuser");
    // Accept name parts
    $first = trim($_POST['first_name'] ?? '');
    $middle = trim($_POST['middle_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    
    // Validate required name parts
    if ($first === '' || $last === '') {
        $error = '2';
        $id = $_POST['id00'];
        header("location: settings.php?action=edit&error=".$error."&id=".$id);
        exit();
    }
    
    $oldemail=$_POST["oldemail"];
    $nic=$_POST['nic'];
    $spec=$_POST['spec'];
    $email=$_POST['email'];
    $tele=$_POST['Tele'];
    $password=$_POST['password'];
    $cpassword=$_POST['cpassword'];
    $id=$_POST['id00'];
    
    // Check if password fields are filled
    $passwordChanged = false;
    $error = '3';
    
    if (!empty($password) || !empty($cpassword)) {
        // If either password field is filled, validate both
        if ($password == $cpassword) {
            if (!empty($password)) {
                $passwordChanged = true;
            }
        } else {
            $error = '2'; // Password confirmation error
            header("location: settings.php?action=edit&error=".$error."&id=".$id);
            exit;
        }
    }
    
    // Only proceed with email check if no password error
    if ($error != '2') {
        $result= $database->query("select tbl_doctor.docid from tbl_doctor inner join tbl_webuser on tbl_doctor.docemail=tbl_webuser.email where tbl_webuser.email='$email';");
        
        if($result->num_rows==1){
            $id2=$result->fetch_assoc()["docid"];
        }else{
            $id2=$id;
        }
        
        echo $id2."jdfjdfdh";
        
        if($id2!=$id){
            $error='1'; // Email already exists
        }else{
            // Ensure NIC uniqueness (cannot match another doctor's NIC)
            $nicStmt = $database->prepare("SELECT docid FROM tbl_doctor WHERE docnic = ? AND docid <> ? LIMIT 1");
            $nicStmt->bind_param("si", $nic, $id);
            $nicStmt->execute();
            $nicRes = $nicStmt->get_result();
            if ($nicRes && $nicRes->num_rows > 0) {
                $error = '2'; // Reuse error code for NIC conflict
            } else {
                // Build the SQL query based on whether password is being changed
                if ($passwordChanged) {
                    // Hash the password before updating
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    // Update with new password and name parts
                    $sql1="update tbl_doctor set docemail='$email',docpassword='$hashed_password',docnic='$nic',doctel='$tele',specialties=$spec,first_name='$first',middle_name='$middle',last_name='$last',suffix='$suffix' where docid=$id ;";
                } else {
                    // Update without changing password, with name parts
                    $sql1="update tbl_doctor set docemail='$email',docnic='$nic',doctel='$tele',specialties=$spec,first_name='$first',middle_name='$middle',last_name='$last',suffix='$suffix' where docid=$id ;";
                }
                
                $database->query($sql1);

            // Update webuser email if changed
            if($oldemail != $email) {
                $sql2="update tbl_webuser set email='$email' where email='$oldemail' ;";
                $database->query($sql2);
            }

            echo $sql1;
            
            // Handle profile image upload if provided
            if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK){
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $ext = strtolower($ext);
                if(in_array($ext, ['jpg','jpeg','png'])){
                    // Check file size (max 2MB)
                    if($_FILES['profile_image']['size'] <= 2 * 1024 * 1024){
                        $destDir = "../Images/profiles";
                        if(!is_dir($destDir)){
                            @mkdir($destDir, 0755, true);
                        }
                        $dest = $destDir."/doctor_".$id.".jpg";
                        
                        // Convert to jpg if png or move jpeg
                        if($ext === 'png'){
                            $img = imagecreatefrompng($_FILES['profile_image']['tmp_name']);
                            if($img !== false){
                                imagejpeg($img, $dest, 90);
                                imagedestroy($img);
                            }
                        } else {
                            move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest);
                        }
                    }
                }
            }

            $error= '4'; // Success
        }
    }
    
}else{
    //header('location: signup.php');
    $error='3';
}}
header("location: settings.php?action=edit&error=".$error."&id=".$id);
exit;
?>