<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Use the same PHPMailer includes as in forgot-password.php
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

function sendEmailNotification($patientEmail, $patientName, $status, $appointmentDetails = []) {
    $mail = new PHPMailer(true);

    try {
        // Use the same SMTP settings that work in your forgot-password.php
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'PangasinanEyeCenterPH@gmail.com';
        $mail->Password = 'bffqjhfojnujksxh';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('PangasinanEyeCenterPH@gmail.com', 'Pangasinan Eye Center');
        $mail->addAddress($patientEmail, $patientName);

        // Content
        $mail->isHTML(true);
        
        // Set email content based on status
        switch($status) {
            case 'approved':
                $mail->Subject = 'Your Appointment Request has been Approved';
                $body = "
                    <h2 style='color: #28a745;'>Appointment Request Approved</h2>
                    <p>Dear {$patientName},</p>
                    <p>Your appointment request has been approved. Here are the details:</p>
                    <ul style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>
                        <li><strong>Service:</strong> {$appointmentDetails['service']}</li>
                        <li><strong>Date:</strong> {$appointmentDetails['date']}</li>
                        <li><strong>Time:</strong> {$appointmentDetails['time']}</li>
                        <li><strong>Duration:</strong> {$appointmentDetails['duration']}</li>
                    </ul>
                    <p><strong>Important:</strong> Please arrive 15 minutes before your scheduled appointment time.</p>
                    <br>
                    <p>Best regards,<br>Pangasinan Eye Center</p>
                ";
                break;

            case 'rejected':
                $mail->Subject = 'Your Appointment Request has been Declined';
                $body = "
                    <h2 style='color: #dc3545;'>Appointment Request Declined</h2>
                    <p>Dear {$patientName},</p>
                    <p>We regret to inform you that your appointment request has been declined.</p>
                    <p>If you would like to schedule a different appointment, please feel free to submit a new request.</p>
                    <br>
                    <p>Best regards,<br>Pangasinan Eye Center</p>
                ";
                break;

            case 'cancelled':
                $mail->Subject = 'Your Appointment has been Cancelled';
                $body = "
                    <h2 style='color: #dc3545;'>Appointment Cancelled</h2>
                    <p>Dear {$patientName},</p>
                    <p>Your appointment scheduled for {$appointmentDetails['date']} at {$appointmentDetails['time']} has been cancelled.</p>
                    <p>If you would like to schedule a new appointment, please submit a new request.</p>
                    <br>
                    <p>Best regards,<br>Pangasinan Eye Center</p>
                ";
                break;
        }

        $mail->Body = $body;
        $mail->send();
        
        // Log successful email sending
        error_log("Appointment notification email sent successfully to: $patientEmail");
        return true;
    } catch (Exception $e) {
        // Log error details
        error_log("Failed to send appointment notification email to: $patientEmail. Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>