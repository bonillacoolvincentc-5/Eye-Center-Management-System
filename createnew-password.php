<?php
session_start();
include("connection.php");

$error = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_email'])) {
        // Coming from settings page
        $_SESSION['reset_email'] = $_POST['reset_email'];
        $_SESSION['from_settings'] = true;
        // Store user type if provided
        if (isset($_POST['user_type'])) {
            $_SESSION['user_type'] = $_POST['user_type'];
        }
    } elseif (isset($_POST['newpassword']) && isset($_POST['confirmpassword'])) {
        // Process password change
        $newpassword = $_POST['newpassword'];
        $confirmpassword = $_POST['confirmpassword'];
        
        if (!isset($_SESSION['reset_email']) || empty($_SESSION['reset_email'])) {
            $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Session expired. Please try again.</label>';
        } else {
            $email = $_SESSION['reset_email'];
            $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'patient';
            
            // Password validation
            $validation = validate_password($newpassword);
            if ($validation !== true) {
                $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">'.$validation.'</label>';
            } else {
                // Hash the new password for security
                $hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);

                // Update password based on user type
                $success = false;
                $redirect_url = "login.php";
                
                if ($user_type === 'admin') {
                    // Update password in tbl_admin
                    $stmt = $database->prepare("UPDATE tbl_admin SET password=? WHERE email=?");
                    $stmt->bind_param("ss", $hashed_password, $email);
                    if ($stmt->execute()) {
                        $success = true;
                        if (isset($_SESSION['from_settings'])) {
                            $redirect_url = "admin/settings.php";
                        }
                    }
                } else {
                    // Update password in tbl_patients (default)
                    $stmt = $database->prepare("UPDATE tbl_patients SET Password=? WHERE Email=?");
                    $stmt->bind_param("ss", $hashed_password, $email);
                    if ($stmt->execute()) {
                        $success = true;
                        if (isset($_SESSION['from_settings'])) {
                            $redirect_url = "patients/settings.php";
                        }
                    }
                }
                
                if ($success) {
                    if (isset($_SESSION['from_settings'])) {
                        unset($_SESSION['from_settings']);
                        unset($_SESSION['user_type']);
                    }
                    echo '<script>
                        alert("Password updated successfully!");
                        window.location.href = "'.$redirect_url.'";
                    </script>';
                    exit();
                } else {
                    $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Failed to update password. Please try again.</label>';
                }
            }
        }
    }
} elseif (!isset($_SESSION['reset_email']) || empty($_SESSION['reset_email'])) {
    // No valid session, redirect to login
    header("Location: login.php");
    exit();
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
    <title>Create New Password</title>
    <style>
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
<center>
    <div class="container">
        <form action="" method="POST">
        <table border="0" style="margin: 0;padding: 0;width: 60%;">
            <tr>
                <td>
                    <p class="header-text">Create New Password</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="sub-text">Enter your new password below</p>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <label for="newpassword" class="form-label">New Password: </label>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                        <div style="position:relative;">
                            <input type="password" id="newpassword" name="newpassword" class="input-text" placeholder="New Password" required oninput="checkPassword()" style="padding-right:40px;">
                            <button type="button" onclick="togglePassword('newpassword', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                <span id="eye-newpassword">&#128065;</span>
                            </button>
                        </div>
                        <div id="pw-checker"></div>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <label for="confirmpassword" class="form-label">Confirm Password: </label>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <div style="position:relative;">
                        <input type="password" id="confirmpassword" name="confirmpassword" class="input-text" placeholder="Confirm Password" required oninput="checkPassword()" style="padding-right:40px;">
                        <button type="button" onclick="togglePassword('confirmpassword', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                            <span id="eye-confirmpassword">&#128065;</span>
                        </button>
                    </div>
                    <div id="pw-match"></div>
                </td>
            </tr>
            <tr>
                <td><br>
                    <?php echo $error; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" name="change_password" value="Update Password" class="login-btn btn-primary btn">
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
    var pw = document.getElementById('newpassword').value;
    var cpw = document.getElementById('confirmpassword').value;
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
