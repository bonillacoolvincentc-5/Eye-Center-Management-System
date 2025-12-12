<?php
session_start();
include("connection.php");

// PHPMailer includes
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$codeSent = false;
$codeVerified = false;
$message = "";

// Handle resend timer
if (!isset($_SESSION['resend_time'])) {
    $_SESSION['resend_time'] = 0;
}

// Add this after the session_start():
if (!isset($_SESSION['code_expiry'])) {
    $_SESSION['code_expiry'] = 0;
}

if (isset($_POST['sendcode']) || isset($_POST['resendcode'])) {
    $useremail = isset($_POST['useremail']) ? $_POST['useremail'] : $_SESSION['reset_email'];
    $verification_code = rand(100000, 999999);
    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['reset_email'] = $useremail;
    $_SESSION['resend_time'] = time();
    $_SESSION['code_expiry'] = time() + (10 * 60); // Code expires in 10 minutes

    $subject = "Your Password Reset Verification Code";
    $body = "This is your verification code for resetting your password at Pangasinan Eye Center.\n\n";
    $body .= "Your verification code is: $verification_code\n\n";
    $body .= "This code will expire in 10 minutes.";

    // Check if the email exists in any user table (patients, doctors, or admin)
    $userFound = false;
    $userType = '';
    $userTable = '';
    
    // Check tbl_patients
    $stmt = $database->prepare("SELECT 'patient' as user_type FROM tbl_patients WHERE Email = ?");
    $stmt->bind_param("s", $useremail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $userFound = true;
        $userType = 'patient';
        $userTable = 'tbl_patients';
    }
    
    // Check tbl_doctor if not found in patients
    if (!$userFound) {
        $stmt = $database->prepare("SELECT 'doctor' as user_type FROM tbl_doctor WHERE docemail = ?");
        $stmt->bind_param("s", $useremail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $userFound = true;
            $userType = 'doctor';
            $userTable = 'tbl_doctor';
        }
    }
    
    // Check tbl_admin if not found in patients or doctors
    if (!$userFound) {
        $stmt = $database->prepare("SELECT 'admin' as user_type FROM tbl_admin WHERE email = ?");
        $stmt->bind_param("s", $useremail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $userFound = true;
            $userType = 'admin';
            $userTable = 'tbl_admin';
        }
    }
    
    if (!$userFound) {
        $message = "Email Account not found in our system.";
        $codeSent = false;
    } else {
        // Store user type and table for later use
        $_SESSION['user_type'] = $userType;
        $_SESSION['user_table'] = $userTable;
        $mail = new PHPMailer(true);
        try {
            // SMTP settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'PangasinanEyeCenterPH@gmail.com';
            $mail->Password   = 'bffqjhfojnujksxh';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('PangasinanEyeCenterPH@gmail.com', 'Pangasinan Eye Center');
            $mail->addAddress($useremail);

            // Content
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            $message = "Verification code sent to your email.";
            $codeSent = true;
        } catch (Exception $e) {
            $message = "Failed to send verification code. Mailer Error: {$mail->ErrorInfo}";
            $codeSent = true;
        }
    }
} else {
    $codeSent = false;
}

if (isset($_POST['resetpassword'])) {
    $entered_code = $_POST['verificationcode'];
    if (!isset($_SESSION['code_expiry']) || time() > $_SESSION['code_expiry']) {
        $message = "Verification code has expired. Please request a new code.";
        $codeSent = false;
        unset($_SESSION['verification_code']);
    } elseif (isset($_SESSION['verification_code']) && $entered_code == $_SESSION['verification_code']) {
        // Redirect to Reset-password.php
        header("Location: Reset-password.php");
        exit();
    } else {
        $message = "Invalid verification code. Please try again.";
        $codeSent = true; // Keep this true so user stays on verification code input
    }
}

// Calculate remaining time for resend
$resend_wait = 0;
if (isset($_SESSION['resend_time']) && $_SESSION['resend_time'] > 0) {
    $elapsed = time() - $_SESSION['resend_time'];
    if ($elapsed < 60) {
        $resend_wait = 60 - $elapsed;
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
    <title>Forgot Password</title>
    <script>
    let resendWait = <?php echo $resend_wait; ?>;
    function startCountdown() {
        const btn = document.getElementById('resend-btn');
        const countdown = document.getElementById('countdown');
        if (resendWait > 0) {
            btn.disabled = true;
            countdown.style.display = 'inline';
            countdown.textContent = '(' + resendWait + 's)';
            let interval = setInterval(function() {
                resendWait--;
                countdown.textContent = '(' + resendWait + 's)';
                if (resendWait <= 0) {
                    clearInterval(interval);
                    btn.disabled = false;
                    countdown.style.display = 'none';
                }
            }, 1000);
        } else {
            btn.disabled = false;
            countdown.style.display = 'none';
        }
    }
    let codeExpiry = <?php echo isset($_SESSION['code_expiry']) ? max(0, $_SESSION['code_expiry'] - time()) : 0; ?>;
    function updateExpiryTime() {
        const expirySpan = document.getElementById('code-expiry');
        if (codeExpiry > 0 && expirySpan) {
            const minutes = Math.floor(codeExpiry / 60);
            const seconds = codeExpiry % 60;
            expirySpan.textContent = `Code expires in: ${minutes}:${seconds.toString().padStart(2, '0')}`;
            codeExpiry--;
            setTimeout(updateExpiryTime, 1000);
        } else if (expirySpan) {
            expirySpan.textContent = 'Code has expired';
        }
    }
    window.onload = function() {
        startCountdown();
        updateExpiryTime();
    };
    </script>
    <style>
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
            
            /* Countdown and expiry styling */
            #countdown, #code-expiry {
                font-size: 14px !important;
                margin: 5px 0 !important;
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
            
            #countdown, #code-expiry {
                font-size: 12px !important;
            }
        }
    </style>
</head>
<body>
<center>
    <div class="container">
        <form action="" method="POST">
            <table border="0" style="margin: 0;padding: 0;width: 60%;">
                <tr>
                    <td>
                        <p class="header-text">Forgot Password</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="sub-text">Enter your email to reset your password</p>
                    </td>
                </tr>
                <?php if (!$codeSent): ?>
                <tr>
                    <td class="label-td">
                        <label for="useremail" class="form-label">Email: </label>
                        <input type="email" name="useremail" class="input-text" placeholder="Email Account" required>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="sendcode" value="Send Code" class="login-btn btn-primary btn">
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td class="label-td">
                        <label for="verificationcode" class="form-label">Verification Code: </label>
                        <input type="text" name="verificationcode" class="input-text" placeholder="Verification Code" required>
                        <input type="hidden" name="useremail" value="<?php echo htmlspecialchars($_SESSION['reset_email']); ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="resetpassword" value="Reset Password" class="login-btn btn-primary btn" style="margin-top: 10px;">
                    </td>
                </tr>
                <tr>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="useremail" value="<?php echo htmlspecialchars($_SESSION['reset_email']); ?>">
                            <button type="submit" id="resend-btn" name="resendcode" class="login-btn btn-primary-soft btn" style="margin-top:10px;">
                                Resend Code <span id="countdown" style="margin-left:5px;"></span>
                            </button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span id="code-expiry" style="color: #666; font-size: 0.9em;"></span>
                    </td>
                </tr>
                <?php endif; ?>
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
