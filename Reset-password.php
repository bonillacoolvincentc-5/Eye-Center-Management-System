<?php
session_start();
include("connection.php");

$message = "";

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

// Check if user came from verification and has reset_email set
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

if (isset($_POST['reset'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];

    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match!";
    } else {
        // Custom password validation
        $validation = validate_password($new_password);
        if ($validation !== true) {
            $message = $validation;
        } else {
            // Hash the new password for security
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password based on user type
            $userType = $_SESSION['user_type'] ?? 'patient';
            $userTable = $_SESSION['user_table'] ?? 'tbl_patients';
            
            if ($userType === 'patient') {
                $stmt = $database->prepare("UPDATE tbl_patients SET Password=? WHERE Email=?");
                $stmt->bind_param("ss", $hashed_password, $email);
            } elseif ($userType === 'doctor') {
                $stmt = $database->prepare("UPDATE tbl_doctor SET docpassword=? WHERE docemail=?");
                $stmt->bind_param("ss", $hashed_password, $email);
            } elseif ($userType === 'admin') {
                $stmt = $database->prepare("UPDATE tbl_admin SET Password=? WHERE email=?");
                $stmt->bind_param("ss", $hashed_password, $email);
            }
            
            $stmt->execute();
            session_destroy();
            header("Location: login.php?reset=success");
            exit();
        }
    }
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
    <title>Reset Password</title>
    <style>
        .pw-check {
            font-size: 13px;
            margin-top: 5px;
            margin-bottom: 0;
            text-align: left;
        }
        .pw-check.valid { color: #2ecc40; }
        .pw-check.invalid { color: #ff3e3e; }
        
        /* Mobile responsive styles */
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
                line-height: 1.4 !important;
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
            
            .label-td {
                padding: 5px 0 !important;
            }
            
            /* Password visibility button */
            button[onclick*="togglePassword"] {
                right: 12px !important;
                width: 30px !important;
                height: 30px !important;
                font-size: 16px !important;
            }
            
            /* Password checker styling */
            .pw-check {
                font-size: 12px !important;
                margin: 2px 0 !important;
                display: block !important;
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
            span[style*="color: red"] {
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
            
            .pw-check {
                font-size: 11px !important;
            }
        }
    </style>
    <script>
    function togglePassword(id, btn) {
        var input = document.getElementById(id);
        var eye = document.getElementById('eye-' + id);
        if (input.type === "password") {
            input.type = "text";
            eye.innerHTML = "&#128065;";
        } else {
            input.type = "password";
            eye.innerHTML = "&#128065;";
        }
    }

    function checkPassword() {
        var pw = document.getElementById('new_password').value;
        var cpw = document.getElementById('confirm_password').value;
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

        // Check minimum length
        if (pw.length >= 8) {
            checks.push('<span class="pw-check valid">&#10003; Minimum 8 characters</span>');
        } else {
            checks.push('<span class="pw-check invalid">&#10007; Minimum 8 characters</span>');
        }
        // Check numbers
        var numCount = (pw.match(/\d/g) || []).length;
        if (numCount >= 1) {
            checks.push('<span class="pw-check valid">&#10003; At least 1 number</span>');
        } else {
            checks.push('<span class="pw-check invalid">&#10007; At least 1 number</span>');
        }
        // Check uppercase
        if (/[A-Z]/.test(pw)) {
            checks.push('<span class="pw-check valid">&#10003; At least 1 uppercase letter</span>');
        } else {
            checks.push('<span class="pw-check invalid">&#10007; At least 1 uppercase letter</span>');
        }
        // Check lowercase if all uppercase
        if (pw && pw.toUpperCase() === pw && !/[a-z]/.test(pw)) {
            checks.push('<span class="pw-check invalid">&#10007; At least 1 lowercase letter (if all uppercase)</span>');
        } else {
            checks.push('<span class="pw-check valid">&#10003; Lowercase letter present or not all uppercase</span>');
        }

        pwChecker.innerHTML = checks.join('<br>');

        // Confirm Password Matched
        if (!cpw) {
            pwMatch.style.display = 'none';
            pwMatch.innerHTML = '';
        } else {
            pwMatch.style.display = 'block';
            if (pw === cpw) {
                pwMatch.innerHTML = '<span class="pw-check valid">&#10003; Password matched</span>';
            } else {
                pwMatch.innerHTML = '<span class="pw-check invalid">&#10007; Passwords do not match</span>';
            }
        }
    }
    </script>
</head>
<body>
<center>
    <div class="container">
        <form action="" method="POST" autocomplete="off">
            <table border="0" style="margin: 0;padding: 0;width: 60%;">
                <tr>
                    <td>
                        <p class="header-text">Reset Password</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="sub-text">Enter your new password below</p>
                    </td>
                </tr>
                <tr>
                    <td class="label-td">
                        <label for="new_password" class="form-label">New Password: </label>
                        <div style="position:relative;">
                            <input type="password" id="new_password" name="new_password" class="input-text" placeholder="New Password (min 8 chars)" required oninput="checkPassword()">
                            <button type="button" onclick="togglePassword('new_password', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                <span id="eye-new_password">&#128065;</span>
                            </button>
                        </div>
                        <div id="pw-checker"></div>
                    </td>
                </tr>
                <tr>
                    <td class="label-td">
                        <label for="confirm_password" class="form-label">Confirm New Password: </label>
                        <div style="position:relative;">
                            <input type="password" id="confirm_password" name="confirm_password" class="input-text" placeholder="Confirm New Password" required oninput="checkPassword()">
                            <button type="button" onclick="togglePassword('confirm_password', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                <span id="eye-confirm_password">&#128065;</span>
                            </button>
                        </div>
                        <div id="pw-match"></div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="reset" value="Confirm" class="login-btn btn-primary btn">
                    </td>
                </tr>
                <tr>
                    <td>
                        <span style="color: red;"><?php echo $message; ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <br>
                        <a href="login.php" class="hover-link1 non-style-link">Back to Login</a>
                        <br><br><br>
                    </td>
                </tr>
            </table>
        </form>
    </div>
</center>
</body>
</html>