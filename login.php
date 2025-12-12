<?php 
session_start();

date_default_timezone_set('Asia/Manila');
$date = date('Y-m-d');
$_SESSION["date"] = $date;

include("connection.php");

if($_POST){
    $email = $_POST['useremail'];
    $password = $_POST['userpassword'];
    
    $error = '<label for="promter" class="form-label"></label>';

    // Check if account exists in tbl_admin
    $stmt = $database->prepare("SELECT * FROM tbl_admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin_result = $stmt->get_result();

    if ($admin_result->num_rows == 1) {
        $row = $admin_result->fetch_assoc();
        // Check if password is hashed (starts with $2y$) or plain text
        if (strpos($row['password'], '$2y$') === 0) {
            // Password is hashed, use password_verify
            if (password_verify($password, $row['password'])) {
                $_SESSION['user'] = $row['email'];
                $_SESSION['usertype'] = 'a';
                $_SESSION['username'] = $row['fname'];
                header('location: /AppoinmentBackupNonOnline/admin/index.php');
                exit();
            } else {
                $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
            }
        } else {
            // Password is plain text, use direct comparison for backward compatibility
            if ($password === $row['password']) {
                $_SESSION['user'] = $row['email'];
                $_SESSION['usertype'] = 'a';
                $_SESSION['username'] = $row['fname'];
                header('location: /AppoinmentBackupNonOnline/admin/index.php');
                exit();
            } else {
                $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
            }
        }
    } else {
        // Check if account exists in tbl_doctor (doctors)
        $stmt = $database->prepare("SELECT * FROM tbl_doctor WHERE docemail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $doctor_result = $stmt->get_result();

        if ($doctor_result->num_rows == 1) {
            $row = $doctor_result->fetch_assoc();
            // Check if password is hashed (starts with $2y$) or plain text
            if (strpos($row['docpassword'], '$2y$') === 0) {
                // Password is hashed, use password_verify
                if (password_verify($password, $row['docpassword'])) {
                    $_SESSION['user'] = $row['docemail'];
                    $_SESSION['usertype'] = 'd';
                    // Compose full name from parts
                    $first = $row['first_name'] ?? '';
                    $middle = $row['middle_name'] ?? '';
                    $last = $row['last_name'] ?? '';
                    $suffix = $row['suffix'] ?? '';
                    $docname = $first;
                    if ($middle) $docname .= ' ' . $middle;
                    $docname .= ' ' . $last;
                    if ($suffix) $docname .= ' ' . $suffix;
                    $_SESSION['username'] = $docname;
                    header('location: /AppoinmentBackupNonOnline/doctor/index.php');
                    exit();
                } else {
                    $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                }
            } else {
                // Password is plain text, use direct comparison for backward compatibility
                if ($password === $row['docpassword']) {
                    $_SESSION['user'] = $row['docemail'];
                    $_SESSION['usertype'] = 'd';
                    // Compose full name from parts
                    $first = $row['first_name'] ?? '';
                    $middle = $row['middle_name'] ?? '';
                    $last = $row['last_name'] ?? '';
                    $suffix = $row['suffix'] ?? '';
                    $docname = $first;
                    if ($middle) $docname .= ' ' . $middle;
                    $docname .= ' ' . $last;
                    if ($suffix) $docname .= ' ' . $suffix;
                    $_SESSION['username'] = $docname;
                    header('location: /AppoinmentBackupNonOnline/doctor/index.php');
                    exit();
                } else {
                    $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                }
            }
        } else {
            // Check if account exists in tbl_patients (patients)
            $stmt = $database->prepare("SELECT Email, Fname, Password, status FROM tbl_patients WHERE Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // First check if account is blocked
                if ($row['status'] == 'blocked') {
                    $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">This account has been blocked. Please contact the administrator.</label>';
                }
                // If not blocked, verify password
                else if (password_verify($password, $row['Password'])) {
                    $_SESSION['user'] = $row['Email'];
                    $_SESSION['usertype'] = 'p';
                    $_SESSION['username'] = $row['Fname'];
                    header('location: /AppoinmentBackupNonOnline/patients/index.php');
                    exit();
                } else {
                    $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                }
            } else {
                $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
            }
        }
    }
} else {
    $error = '<label for="promter" class="form-label">&nbsp;</label>';
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	 <link rel="stylesheet" href="css/animations.css">  
    <link rel="stylesheet" href="css/main.css">  
    <link rel="stylesheet" href="css/login.css">
        
    <title>Login</title>

    <!-- Mobile responsive styles -->
    <style>
        .logo-container { 
            text-align: center; 
            margin: 40px 0 18px; /* reduced top margin to compress space above the logo */
        }
        .logo-img { 
            max-width: 120px; 
            height: auto; 
            display: inline-block; 
            border-radius: 8px; 
        }
        
        /* Mobile responsive fixes */
        @media (max-width: 768px) {
            body {
                margin: 0;
                padding: 10px;
                background-color: #F6F7FA;
            }
            
            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 20px 15px !important;
                box-sizing: border-box;
            }
            
            table {
                width: 100% !important;
                margin: 0 !important;
            }
            
            .header-text {
                font-size: 24px !important;
                margin-bottom: 8px !important;
            }
            
            .sub-text {
                font-size: 14px !important;
                margin-bottom: 20px !important;
            }
            
            .form-label {
                font-size: 16px !important;
                font-weight: 600 !important;
                margin-bottom: 8px !important;
                display: block;
            }
            
            .input-text {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important; /* Prevents zoom on iOS */
                border: 2px solid #e1e5e9 !important;
                border-radius: 8px !important;
                box-sizing: border-box !important;
                margin-bottom: 10px !important;
            }
            
            .input-text:focus {
                border-color: #007bff !important;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
            }
            
            .login-btn {
                width: 100% !important;
                padding: 15px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                border-radius: 8px !important;
                margin: 5px 0 !important;
                box-sizing: border-box !important;
            }
            
            /* Login link styling */
            .hover-link1 {
                color: #007bff !important;
                text-decoration: none !important;
                font-weight: 600 !important;
            }
            
            .hover-link1:hover {
                text-decoration: underline !important;
            }
            
            /* Error message styling */
            .form-label[style*="color:rgb(255, 62, 62)"] {
                display: block !important;
                text-align: center !important;
                padding: 10px !important;
                margin: 10px 0 !important;
                background: #ffe6e6 !important;
                border: 1px solid #ff9999 !important;
                border-radius: 6px !important;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img { max-width: 90px; }
            
            .container {
                padding: 15px 10px !important;
            }
            
            .header-text {
                font-size: 22px !important;
            }
            
            .sub-text {
                font-size: 13px !important;
            }
            
            .input-text {
                padding: 10px !important;
                font-size: 16px !important;
            }
            
            .login-btn {
                padding: 12px !important;
                font-size: 15px !important;
            }
        }
    </style>
</head>
<body>
<center>
    <div class="container">
        <form action="" method="POST">
            <!-- NEW: logo -->
            <div class="logo-container">
                <img src="logo/Logo.jpg" alt="Clinic Logo" class="logo-img">
            </div>
            <table border="0" style="margin: 0;padding: 0;width: 60%;">
                <tr>
                    <td>
                        <p class="header-text">Welcome Back!</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="sub-text">Login with your details to continue</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="useremail" class="form-label">Email: </label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="email" name="useremail" class="input-text" placeholder="Email Account" required>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="userpassword" class="form-label">Password: </label>
                    </td>
                </tr>
                <tr>
                    <td style="position:relative;">
                        <input type="Password" id="userpassword" name="userpassword" class="input-text" placeholder="Password" required>
                        <button type="button" onclick="togglePassword('userpassword', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                            <span id="eye-userpassword">&#128065;</span>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td style="text-align:right;">
                        <a href="forgot-password.php" class="hover-link1 non-style-link">Forgot Password?</a>
                    </td>
                </tr>
                <tr>
                    <td><br>
                        <?php echo $error ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" value="Login" class="login-btn btn-primary btn">
                    </td>
                </tr>
                <tr>
                    <td>
                        <a href="index.html" class="login-btn btn-secondary btn" style="display:inline-block; text-align:center; width:100%; margin-top:10px;">Back to Home</a>
                    </td>
                </tr>
                <tr>
                    <td>
                        <br>
                        <label for="" class="sub-text" style="font-weight: 280;">Don't have an account&#63; </label>
                        <a href="signup.php" class="hover-link1 non-style-link">Sign Up</a>
                        <br><br><br>
                    </td>
                </tr>
            </table>
        </form>
    </div>
</center>
<script>
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    var eye = btn.querySelector('span');
    if (input.type === "password" || input.type === "Password") {
        input.type = "text";
        eye.textContent = "\u2716"; // simple cross icon when visible
    } else {
        input.type = "password";
        eye.textContent = "\u{1F441}"; // simple eye icon
    }
}
</script>
</body>
</html>