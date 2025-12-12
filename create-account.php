<?php 
session_start();
date_default_timezone_set('Asia/Manila');
include("connection.php");
$error = '';

// Sticky form variables
$email = isset($_POST['newemail']) ? htmlspecialchars($_POST['newemail']) : '';
$tele = isset($_POST['tele']) ? htmlspecialchars($_POST['tele']) : '';
$newpassword = isset($_POST['newpassword']) ? $_POST['newpassword'] : '';
$cpassword = isset($_POST['cpassword']) ? $_POST['cpassword'] : '';

// Allowed email domains (add more as needed)
$allowed_domains = [
    'gmail.com',
    'yahoo.com',
    'hotmail.com',
    'outlook.com',
    'icloud.com',
    'aol.com',
    'zoho.com',
    'protonmail.com',
    'mail.com',
    'gmx.com'
];

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

function is_allowed_email($email, $allowed_domains) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    return in_array($domain, $allowed_domains);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = $_SESSION['personal']['fname'];
    $mname = isset($_SESSION['personal']['mname']) ? $_SESSION['personal']['mname'] : '';
    $lname = $_SESSION['personal']['lname'];
    $suffix = isset($_SESSION['personal']['suffix']) ? $_SESSION['personal']['suffix'] : '';
    $region = isset($_SESSION['personal']['region']) ? $_SESSION['personal']['region'] : '';
    $province = $_SESSION['personal']['province'];
    $city = $_SESSION['personal']['city'];
    $barangay = $_SESSION['personal']['barangay'];
    $street = $_SESSION['personal']['street'];
    $dob = $_SESSION['personal']['dob'];
    $email = $_POST['newemail'];
    $tele = $_POST['tele'];
    $newpassword = $_POST['newpassword'];
    $cpassword = $_POST['cpassword'];

  
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Invalid email format. Please enter a valid email account.</label>';
    } else if (!is_allowed_email($email, $allowed_domains)) {
        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Only popular email domains are allowed (e.g. Gmail, Yahoo, Hotmail, Outlook, iCloud, AOL, Zoho, ProtonMail, Mail.com, GMX).</label>';
    } else if (!preg_match('/^0[0-9]{10}$/', $tele)) {
        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Invalid mobile number format. Please enter a valid number (ex: 09123456789).</label>';
    } else if ($newpassword === $cpassword) {
        $validation = validate_password($newpassword);
        if ($validation !== true) {
            $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">'.$validation.'</label>';
        } else {
            $stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Already have an account for this Email account.</label>';
            } else {
                // Save pending signup to session and redirect to email verification
                $_SESSION['pending_signup'] = [
                    'Fname' => $fname,
                    'Mname' => $mname,
                    'Lname' => $lname,
                    'Suffix' => $suffix,
                    'Region' => $region,
                    'Province' => $province,
                    'City' => $city,
                    'Barangay' => $barangay,
                    'Street' => $street,
                    'Birthdate' => $dob,
                    'Email' => $email,
                    'PhoneNo' => $tele,
                    'Password' => password_hash($newpassword, PASSWORD_DEFAULT)
                ];
                header('Location: verify-email.php');
                exit();
            }
        }
    } else {
        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Password Confirmation Error! Please reconfirm your password.</label>';
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
    <link rel="stylesheet" href="css/signup.css">
    <title>Create Account</title>
    <style>
        .container {
            animation: transitionIn-X 0.5s;
        }
        .pw-check {
            font-size: 13px;
            margin-top: 5px;
            margin-bottom: 0;
            text-align: left;
        }
        .pw-check.valid { color: #2ecc40; }
        .pw-check.invalid { color: #ff3e3e; }

        /* Mobile responsive styles */
        .logo-container { text-align: center; margin: 24px 0 8px; }
        .logo-img { max-width: 110px; height: auto; display: inline-block; border-radius: 8px; }
        
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
            
            .label-td {
                padding: 5px 0 !important;
            }
            
            /* Button row - stack vertically */
            tr:has(.login-btn) {
                display: block !important;
            }
            
            tr:has(.login-btn) td {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
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
            .logo-img { max-width: 80px; }
            
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
</head>
<body>
<center>
    <div class="container">
        <form action="" method="POST" autocomplete="off" id="signupForm">
            <!-- NEW: logo -->
            <div class="logo-container">
                <img src="logo/Logo.jpg" alt="Clinic Logo" class="logo-img">
            </div>

            <table border="0" style="width: 69%;">
                <tr>
                    <td colspan="2">
                        <p class="header-text">Let's Get Started</p>
                        <p class="sub-text">Create User Account.</p>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="newemail" class="form-label">Email: </label>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="email" name="newemail" class="input-text" placeholder="Email Account (Gmail, Yahoo, Hotmail, Outlook, etc.)" required value="<?php echo $email; ?>">
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="tele" class="form-label">Mobile Number: </label>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="tel" name="tele" class="input-text" placeholder="ex: 09123456789" pattern="0[0-9]{10}" minlength="11" maxlength="11" required value="<?php echo $tele; ?>">
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="newpassword" class="form-label">Create New Password: </label>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <div style="position:relative;">
                            <input type="password" name="newpassword" id="newpassword" class="input-text" placeholder="New Password" required style="padding-right:40px;" oninput="checkPassword()" value="<?php echo htmlspecialchars($newpassword); ?>">
                            <button type="button" onclick="togglePassword('newpassword', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                <span id="eye-newpassword">&#128065;</span>
                            </button>
                        </div>
                        <div id="pw-checker"></div>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="cpassword" class="form-label">Confirm Password: </label>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <div style="position:relative;">
                            <input type="password" name="cpassword" id="cpassword" class="input-text" placeholder="Confirm Password" required style="padding-right:40px;" oninput="checkPassword()" value="<?php echo htmlspecialchars($cpassword); ?>">
                            <button type="button" onclick="togglePassword('cpassword', this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;">
                                <span id="eye-cpassword">&#128065;</span>
                            </button>
                        </div>
                        <div id="pw-match"></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <?php echo $error ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">
                    </td>
                    <td>
                        <input type="submit" value="Sign Up" class="login-btn btn-primary btn">
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <br>
                        <label for="" class="sub-text" style="font-weight: 280;">Already have an account&#63; </label>
                        <a href="login.php" class="hover-link1 non-style-link">Login</a>
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
        eye.textContent = "\u2716";
    } else {
        input.type = "password";
        eye.textContent = "\u{1F441}";
    }
}

function checkPassword() {
    var pw = document.getElementById('newpassword').value;
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

    if (pw.length >= 8) {
        checks.push('<span class="pw-check valid">&#10003; Minimum 8 characters</span>');
    } else {
        checks.push('<span class="pw-check invalid">&#10007; Minimum 8 characters</span>');
    }
    var numCount = (pw.match(/\d/g) || []).length;
    if (numCount >= 1) {
        checks.push('<span class="pw-check valid">&#10003; At least 1 number</span>');
    } else {
        checks.push('<span class="pw-check invalid">&#10007; At least 1 number</span>');
    }
    if (/[A-Z]/.test(pw)) {
        checks.push('<span class="pw-check valid">&#10003; At least 1 uppercase letter</span>');
    } else {
        checks.push('<span class="pw-check invalid">&#10007; At least 1 uppercase letter</span>');
    }
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

// Full reset functionality
document.addEventListener('DOMContentLoaded', function() {
    var resetBtn = document.querySelector('input[type="reset"]');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('signupForm').reset();
            document.getElementById('pw-checker').innerHTML = '';
            document.getElementById('pw-match').innerHTML = '';
            document.getElementById('pw-checker').style.display = 'none';
            document.getElementById('pw-match').style.display = 'none';
        });
    }
});
</script>
</body>
</html>