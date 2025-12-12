<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
        

    <title>Settings</title>
    <style>
        .dashbord-tables{
            animation: transitionIn-Y-over 0.5s;
        }
        .filter-container{
            animation: transitionIn-X  0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .pw-check {
            font-size: 13px;
            margin-top: 5px;
            margin-bottom: 0;
            text-align: left;
        }
        .pw-check.valid { color: #2ecc40; }
        .pw-check.invalid { color: #ff3e3e; }
    </style>
    
    
</head>
<body>
    <?php
    session_start();

    // Password validation function
    function validate_password($password) {
        if (strlen($password) < 8) {
            return "Password must be at least 8 characters.";
        }
        if (preg_match_all('/\d/', $password) < 1) {
            return "Password must contain at least 1 number.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return "Password must contain at least 1 uppercase letter.";
        }
        if (strtoupper($password) === $password && !preg_match('/[a-z]/', $password)) {
            return "If all letters are uppercase, password must contain at least 1 lowercase letter.";
        }
        return true;
    }

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
            header("location: ../login.php");
        }else{
            $useremail=$_SESSION["user"];
        }

    }else{
        header("location: ../login.php");
    }
    

    //import database
    include("../connection.php");
    $userrow = $database->query("select * from tbl_doctor where docemail='$useremail'");
    $userfetch=$userrow->fetch_assoc();
    $userid= $userfetch["docid"];
    // Compose full name from parts
    $first = $userfetch["first_name"] ?? '';
    $middle = $userfetch["middle_name"] ?? '';
    $last = $userfetch["last_name"] ?? '';
    $suffix = $userfetch["suffix"] ?? '';
    $username = $first;
    if ($middle) $username .= ' ' . $middle;
    $username .= ' ' . $last;
    if ($suffix) $username .= ' ' . $suffix;
    $profilePath = "../Images/profiles/doctor_{$userid}.jpg";
    $profileImage = file_exists($profilePath) ? $profilePath : "../Images/user.png";


    //echo $userid;
    //echo $username;
    
    ?>
    <div class="container">
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px" >
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Dr. <?php echo htmlspecialchars(explode(' ', trim($username))[0]); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php" ><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                                </td>
                            </tr>
                    </table>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-dashbord" >
                        <a href="index.php" class="non-style-link-menu "><div><p class="menu-text">Dashboard</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Appointments</p></div></a>
                    </td>
                </tr>
                
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">My Sessions</p></div></a>
                    </td>
                </tr>
                
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-settings  menu-active menu-icon-settings-active">
                        <a href="settings.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Settings</p></div></a>
                    </td>
                </tr>
                
            </table>
        </div>
        <div class="dash-body" style="margin-top: 15px">
            <table border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;" >
                        
                        <tr >
                            
                        <td width="13%" >
                    <a href="settings.php" ><button  class="login-btn btn-primary-soft btn btn-icon-back"  style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                    </td>
                    <td>
                        <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Settings</p>
                                           
                    </td>
                    
                            <td width="15%">
                                <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                                    Today's Date
                                </p>
                                <p class="heading-sub12" style="padding: 0;margin: 0;">
                                    <?php 
                                date_default_timezone_set('Asia/Kolkata');
        
                                $today = date('Y-m-d');
                                echo $today;


                                $patientrow = $database->query("select  * from  tbl_patients;");
                                $doctorrow = $database->query("select  * from  tbl_doctor;");
                                $appointmentrow = $database->query("select  * from  tbl_appointment where appodate>='$today';");
                                $schedulerow = $database->query("select  * from  tbl_schedule where scheduledate='$today';");


                                ?>
                                </p>
                            </td>
                            <td width="10%">
                                <button  class="btn-label"  style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                            </td>
        
        
                        </tr>
                <tr>
                    <td colspan="4">
                        
                        <center>
                        <table class="filter-container" style="border: none;" border="0">
                            <tr>
                                <td colspan="4">
                                    <p style="font-size: 20px">&nbsp;</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 25%;">
                                    <a href="?action=edit&id=<?php echo $userid ?>&error=0" class="non-style-link">
                                    <div  class="dashboard-items setting-tabs"  style="padding:20px;margin:auto;width:95%;display: flex">
                                        <div class="btn-icon-back dashboard-icons-setting" style="background-image: url('../Images/icons/doctors-hover.svg');"></div>
                                        <div>
                                                <div class="h1-dashboard">
                                                    Account Settings  &nbsp;

                                                </div><br>
                                                <div class="h3-dashboard" style="font-size: 15px;">
                                                    Edit your Account Details & Change Password
                                                </div>
                                        </div>
                                                
                                    </div>
                                    </a>
                                </td>
                                
                                
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <p style="font-size: 5px">&nbsp;</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <p style="font-size: 5px">&nbsp;</p>
                                </td>
                            </tr>
                            <tr>
                            <td style="width: 25%;">
                                    <a href="?action=drop&id=<?php echo $userid.'&name='.$username ?>" class="non-style-link">
                                    <div  class="dashboard-items setting-tabs"  style="padding:20px;margin:auto;width:95%;display: flex;">
                                        <div class="btn-icon-back dashboard-icons-setting" style="background-image: url('../Images/icons/patients-hover.svg');"></div>
                                        <div>
                                                <div class="h1-dashboard" style="color: #ff5050;">
                                                    Deactivate Account
                                                    
                                                </div><br>
                                                <div class="h3-dashboard"  style="font-size: 15px;">
                                                    Will Deactivate your Account
                                                </div>
                                        </div>
                                                
                                    </div>
                                    </a>
                                </td>
                                
                            </tr>
                        </table>
                    </center>
                    </td>
                </tr>
            
            </table>
        </div>
    </div>
    <?php 
    if($_GET){
        
        $id=$_GET["id"];
        $action=$_GET["action"];
        if($action=='drop'){
            $nameget=$_GET["name"];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2>Are you sure?</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content">
                            You want to delete this record<br>('.substr($nameget,0,40).').
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <a href="delete-doctor.php?id='.$id.'" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Yes&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;
                        <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;No&nbsp;&nbsp;</font></button></a>

                        </div>
                    </center>
            </div>
            </div>
            ';
        }elseif($action=='view'){
            $sqlmain= "select * from tbl_doctor where docid='$id'";
            $result= $database->query($sqlmain);
            $row=$result->fetch_assoc();
            // Compose full name from parts
            $first = $row["first_name"] ?? '';
            $middle = $row["middle_name"] ?? '';
            $last = $row["last_name"] ?? '';
            $suffix = $row["suffix"] ?? '';
            $name = $first;
            if ($middle) $name .= ' ' . $middle;
            $name .= ' ' . $last;
            if ($suffix) $name .= ' ' . $suffix;
            $email=$row["docemail"];
            $spe=$row["specialties"];
            
            $spcil_res= $database->query("select sname from tbl_specialties where id='$spe'");
            $spcil_array= $spcil_res->fetch_assoc();
            $spcil_name=$spcil_array["sname"];
            $nic=$row['docnic'];
            $tele=$row['doctel'];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2></h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content">
                            Account Details<br>
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details.</p><br><br>
                                </td>
                            </tr>
                            
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Name: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    '.$name.'<br><br>
                                </td>
                                
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Email" class="form-label">Email: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$email.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="nic" class="form-label">NIC: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$nic.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Tele" class="form-label">Contact No: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$tele.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="spec" class="form-label">Specialties: </label>
                                    
                                </td>
                            </tr>
                            <tr>
                            <td class="label-td" colspan="2">
                            '.$spcil_name.'<br><br>
                            </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="settings.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn" ></a>
                                
                                    
                                </td>
                
                            </tr>
                           

                        </table>
                        </div>
                    </center>
                    <br><br>
            </div>
            </div>
            ';
        }elseif($action=='edit'){
            $sqlmain= "select * from tbl_doctor where docid='$id'";
            $result= $database->query($sqlmain);
            $row=$result->fetch_assoc();
            // Get name parts
            $first = $row["first_name"] ?? '';
            $middle = $row["middle_name"] ?? '';
            $last = $row["last_name"] ?? '';
            $suffix = $row["suffix"] ?? '';
            $email=$row["docemail"];
            $spe=$row["specialties"];
            
            $spcil_res= $database->query("select sname from tbl_specialties where id='$spe'");
            $spcil_array= $spcil_res->fetch_assoc();
            $spcil_name=$spcil_array["sname"];
            $nic=$row['docnic'];
            $tele=$row['doctel'];

            $error_1=$_GET["error"];
                $errorlist= array(
                    '1'=>'<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Already have an account for this Email Account.</label>',
                    '2'=>'<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Password Conformation Error! Reconform Password</label>',
                    '3'=>'<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;"></label>',
                    '4'=>"",
                    '0'=>'',

                );

            if($error_1!='4'){
                    echo '
                    <div id="popup1" class="overlay">
                            <div class="popup">
                            <center>
                            
                                <a class="close" href="settings.php">&times;</a> 
                                <div style="display: flex;justify-content: center;">
                                <div class="abc">
                                <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                        <td class="label-td" colspan="2">'.
                                            $errorlist[$error_1]
                                        .'</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Edit Doctor Details.</p>
                                        Doctor ID : '.$id.' (Auto Generated)<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <form action="edit-doc.php" method="POST" class="add-new-form" enctype="multipart/form-data">
                                            <label for="Email" class="form-label">Email: </label>
                                            <input type="hidden" value="'.$id.'" name="id00">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                        <input type="hidden" name="oldemail" value="'.$email.'" >
                                        <input type="email" name="email" class="input-text" placeholder="Email Account" value="'.$email.'" required><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="first_name" class="form-label">First Name: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="text" name="first_name" class="input-text" placeholder="First Name" value="'.$first.'" required><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="middle_name" class="form-label">Middle Name (Optional): </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="text" name="middle_name" class="input-text" placeholder="Middle Name" value="'.$middle.'"><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="last_name" class="form-label">Last Name: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="text" name="last_name" class="input-text" placeholder="Last Name" value="'.$last.'" required><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="suffix" class="form-label">Suffix (Optional): </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="text" name="suffix" class="input-text" placeholder="e.g., Jr., Sr., MD" value="'.$suffix.'"><br>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="nic" class="form-label">NIC: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="text" name="nic" class="input-text" placeholder="NIC Number" value="'.$nic.'" required><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="Tele" class="form-label">Contact No: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="tel" name="Tele" class="input-text" placeholder="Contact Number" value="'.$tele.'" required><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="spec" class="form-label">Choose specialties: (Current'.$spcil_name.')</label>
                                            
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <select name="spec" id="" class="box">';
                                                
                
                                                $list11 = $database->query("select  * from  tbl_specialties;");
                
                                                for ($y=0;$y<$list11->num_rows;$y++){
                                                    $row00=$list11->fetch_assoc();
                                                    $sn=$row00["sname"];
                                                    $id00=$row00["id"];
                                                    echo "<option value=".$id00.">$sn</option><br/>";
                                                };
                
                
                
                                                
                                echo     '       </select><br><br>
                                        </td>
                                    </tr>
                                    ' . (isset($error_1) && $error_1 == '2' ? '<tr><td colspan="2"><label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Password Confirmation Error! Please reconfirm your password.</label></td></tr>' : '') . '
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="password" class="form-label">Change Password (leave blank to keep current): </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <div style="position:relative;">
                                                <input type="password" id="password" name="password" class="input-text" placeholder="New Password (optional)" oninput="checkPassword()" style="padding-right:40px;">
                                                <button type="button" onclick="togglePassword(\'password\', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                                    <span id="eye-password">&#128065;</span>
                                                </button>
                                            </div>
                                            <div id="pw-checker"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="cpassword" class="form-label">Confirm New Password: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <div style="position:relative;">
                                                <input type="password" id="cpassword" name="cpassword" class="input-text" placeholder="Confirm New Password (if changing)" oninput="checkPassword()" style="padding-right:40px;">
                                                <button type="button" onclick="togglePassword(\'cpassword\', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                                    <span id="eye-cpassword">&#128065;</span>
                                                </button>
                                            </div>
                                            <div id="pw-match"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="profile" class="form-label">Profile Picture: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="file" name="profile_image" accept="image/*" class="input-text"><br>
                                            <small>JPEG/PNG, max 2MB. Saved as circle avatar.</small>
                                        </td>
                                    </tr>
                                    
                        
                                    <tr>
                                        <td colspan="2">
                                            <input type="reset" value="Reset" class="login-btn btn-primary-soft btn" >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        
                                            <input type="submit" value="Save" class="login-btn btn-primary btn">
                                        </td>
                        
                                    </tr>
                                
                                    </form>
                                </table>
                                </div>
                                </div>
                            </center>
                            <br><br>
                    </div>
                    </div>
                    ';
        }else{
            echo '
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                        <br><br><br><br>
                            <h2>Edit Successfully!</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content">
                                If You change your email also Please logout and login again with your new email
                                
                            </div>
                            <div style="display: flex;justify-content: center;">
                            
                            <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                            <a href="../logout.php" class="non-style-link"><button  class="btn-primary-soft btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;Log out&nbsp;&nbsp;</font></button></a>

                            </div>
                            <br><br>
                        </center>
                </div>
                </div>
    ';



        }; }

    }
        ?>
<script>
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    var eye = btn.querySelector('span');
    if (input.type === "password") {
        input.type = "text";
        eye.textContent = "\u2716"; // cross icon when visible
    } else {
        input.type = "password";
        eye.textContent = "\u{1F441}"; // eye icon
    }
}

function checkPassword() {
    var pw = document.getElementById('password').value;
    var cpw = document.getElementById('cpassword').value;
    var pwChecker = document.getElementById('pw-checker');
    var pwMatch = document.getElementById('pw-match');
    var checks = [];

    if (!pw) {
        pwChecker.style.display = 'none';
        pwMatch.style.display = 'none';
        pwChecker.innerHTML = '';
        pwMatch.innerHTML = '';
        return;
    } else {
        pwChecker.style.display = 'block';
    }

    // Minimum 8 characters
    if (pw.length >= 8) {
        checks.push('<span class="pw-check valid">&#10003; Minimum 8 characters</span>');
    } else {
        checks.push('<span class="pw-check invalid">&#10007; Minimum 8 characters</span>');
    }

    // At least 1 number
    var numCount = (pw.match(/\d/g) || []).length;
    if (numCount >= 1) {
        checks.push('<span class="pw-check valid">&#10003; At least 1 number</span>');
    } else {
        checks.push('<span class="pw-check invalid">&#10007; At least 1 number</span>');
    }

    // At least 1 uppercase
    if (/[A-Z]/.test(pw)) {
        checks.push('<span class="pw-check valid">&#10003; At least 1 uppercase letter</span>');
    } else {
        checks.push('<span class="pw-check invalid">&#10007; At least 1 uppercase letter</span>');
    }

    // Lowercase rule (if all letters uppercase then require a lowercase)
    if (pw && pw.toUpperCase() === pw && !/[a-z]/.test(pw)) {
        checks.push('<span class="pw-check invalid">&#10007; At least 1 lowercase letter (if all uppercase)</span>');
    } else {
        checks.push('<span class="pw-check valid">&#10003; Lowercase letter present or not all uppercase</span>');
    }

    pwChecker.innerHTML = checks.join('<br>');

    if (!cpw) {
        pwMatch.style.display = 'none';
        pwMatch.innerHTML = '';
    } else {
        pwMatch.style.display = 'block';
        if (pw === cpw) {
            pwMatch.innerHTML = '<span class="pw-check valid">&#10003; Password Matched</span>';
        } else {
            pwMatch.innerHTML = '<span class="pw-check invalid">&#10007; Passwords do not match</span>';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var pwChecker = document.getElementById('pw-checker');
    if (pwChecker) pwChecker.style.display = 'none';
    var pwMatch = document.getElementById('pw-match');
    if (pwMatch) pwMatch.style.display = 'none';
});
</script>
</body>
</html>