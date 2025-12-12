<?php
session_start();
include('connection.php');

// PHPMailer includes (reuse setup from forgot-password.php)
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['pending_signup'])) {
    header('Location: signup.php');
    exit();
}

$message = '';
$email = $_SESSION['pending_signup']['Email'];

if (!isset($_SESSION['signup_code_sent'])) {
    $_SESSION['signup_code_sent'] = 0;
}
if (!isset($_SESSION['signup_code_expiry'])) {
    $_SESSION['signup_code_expiry'] = 0;
}

function send_signup_code($email) {
    $verification_code = rand(100000, 999999);
    $_SESSION['signup_verification_code'] = $verification_code;
    $_SESSION['signup_code_sent'] = time();
    $_SESSION['signup_code_expiry'] = time() + (10 * 60);

    $subject = 'Your Email Verification Code';
    $body = "Use this verification code to complete your sign up at Pangasinan Eye Center.\n\n";
    $body .= "Your verification code is: $verification_code\n\n";
    $body .= "This code will expire in 10 minutes.";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'PangasinanEyeCenterPH@gmail.com';
        $mail->Password = 'bffqjhfojnujksxh';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('PangasinanEyeCenterPH@gmail.com', 'Pangasinan Eye Center');
        $mail->addAddress($email);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// If first load or resend requested, send code
if (!isset($_SESSION['signup_verification_code']) || isset($_POST['resendcode'])) {
    // Check email is not already used before sending
    $stmt = $database->prepare('SELECT 1 FROM tbl_patients WHERE Email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $message = 'Email already exists. Please use another email.';
    } else {
        if (send_signup_code($email)) {
            $message = 'Verification code sent to your email.';
        } else {
            $message = 'Failed to send verification code. Please try again.';
        }
    }
}

if (isset($_POST['verify'])) {
    $code = isset($_POST['verificationcode']) ? trim($_POST['verificationcode']) : '';
    if (!isset($_SESSION['signup_verification_code'])) {
        $message = 'No verification code found. Please resend the code.';
    } elseif (time() > $_SESSION['signup_code_expiry']) {
        $message = 'Verification code has expired. Please resend the code.';
    } elseif ($code !== strval($_SESSION['signup_verification_code'])) {
        $message = 'Invalid verification code.';
    } else {
        // Create account only after successful verification
        $p = $_SESSION['pending_signup'];
        $stmt = $database->prepare("INSERT INTO tbl_patients (Fname, Mname, Lname, Suffix, Region, Province, `City/Municipality`, Barangay, Street, Birthdate, Email, PhoneNo, Password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'sssssssssssss',
            $p['Fname'],
            $p['Mname'],
            $p['Lname'],
            $p['Suffix'],
            $p['Region'],
            $p['Province'],
            $p['City'],
            $p['Barangay'],
            $p['Street'],
            $p['Birthdate'],
            $p['Email'],
            $p['PhoneNo'],
            $p['Password']
        );
        if ($stmt->execute()) {
            // Log the user in
            $_SESSION['user'] = $p['Email'];
            $_SESSION['usertype'] = 'p';
            $_SESSION['username'] = $p['Fname'];
            // Cleanup
            unset($_SESSION['pending_signup'], $_SESSION['signup_verification_code'], $_SESSION['signup_code_sent'], $_SESSION['signup_code_expiry']);
            header('Location: patients/index.php');
            exit();
        } else {
            $message = 'Failed to create account. Please try again.';
        }
    }
}

$resend_wait = 0;
if (isset($_SESSION['signup_code_sent']) && $_SESSION['signup_code_sent'] > 0) {
    $elapsed = time() - $_SESSION['signup_code_sent'];
    if ($elapsed < 60) { $resend_wait = 60 - $elapsed; }
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
    <title>Verify Email</title>
    <script>
    let resendWait = <?php echo $resend_wait; ?>;
    function startCountdown(){
        const btn=document.getElementById('resend-btn');
        const cd=document.getElementById('countdown');
        if(resendWait>0){
            btn.disabled=true; cd.style.display='inline'; cd.textContent='('+resendWait+'s)';
            let iv=setInterval(()=>{resendWait--; cd.textContent='('+resendWait+'s)'; if(resendWait<=0){clearInterval(iv); btn.disabled=false; cd.style.display='none';}},1000);
        } else { btn.disabled=false; cd.style.display='none'; }
    }
    let codeExpiry = <?php echo isset($_SESSION['signup_code_expiry']) ? max(0, $_SESSION['signup_code_expiry'] - time()) : 0; ?>;
    function updateExpiry(){
        const e=document.getElementById('code-expiry');
        if(codeExpiry>0){ const m=Math.floor(codeExpiry/60); const s=codeExpiry%60; e.textContent='Code expires in: '+m+':'+s.toString().padStart(2,'0'); codeExpiry--; setTimeout(updateExpiry,1000);} else { e.textContent='Code has expired'; }
    }
    window.onload=function(){startCountdown(); updateExpiry();};
    </script>
    <style>
        .container{ animation: transitionIn-Y-bottom 0.5s; }
        
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
            span[style*="color:red"] {
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
            <table border="0" style="margin:0;padding:0;width:60%;">
                <tr><td><p class="header-text">Verify Your Email</p></td></tr>
                <tr><td><p class="sub-text">We sent a 6-digit code to <?php echo htmlspecialchars($email); ?></p></td></tr>
                <tr>
                    <td class="label-td">
                        <label for="verificationcode" class="form-label">Verification Code: </label>
                        <input type="text" name="verificationcode" class="input-text" placeholder="Enter the 6-digit code" required>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="verify" value="Verify & Create Account" class="login-btn btn-primary btn" style="margin-top:10px;">
                    </td>
                </tr>
                <tr>
                    <td>
                        <button type="submit" id="resend-btn" name="resendcode" class="login-btn btn-primary-soft btn" style="margin-top:10px;" formnovalidate>Resend Code <span id="countdown" style="margin-left:5px;"></span></button>
                    </td>
                </tr>
                <tr>
                    <td><span id="code-expiry" style="color:#666;font-size:0.9em;"></span></td>
                </tr>
                <tr>
                    <td><span style="color:red;"><?php echo $message; ?></span></td>
                </tr>
                <tr>
                    <td><br><a href="create-account.php" class="hover-link1 non-style-link">Back</a><br><br><br></td>
                </tr>
            </table>
        </form>
    </div>
</center>
</body>
</html>


