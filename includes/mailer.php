<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';   // Composer autoloader

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    
    // Enable debugging - shows detailed error messages (only log to error log, not browser)
    $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
    $mail->Debugoutput = function($str, $level) {
        // Log debug messages to PHP error log only (prevents header issues)
        error_log("PHPMailer DEBUG [$level]: $str");
    };

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';  
        $mail->SMTPAuth = true;
        $mail->Username = 'hetkhatri22@gmail.com'; 
        $mail->Password = 'svpn kgnx gxbe moaz'; // Gmail App Password - NEEDS TO BE UPDATED
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Additional settings for better reliability
        $mail->Timeout = 30; // 30 second timeout
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        //Recipients
        $mail->setFrom('hetkhatri22@gmail.com', 'SmartPitchHub');
        $mail->addAddress($email);

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code - SmartPitchHub';
        $mail->Body    = "
            <h3>Your OTP Code</h3>
            <p>Hello,</p>
            <p>Your OTP code for SmartPitchHub registration is: <strong style='font-size: 18px; color: #667eea;'>$otp</strong></p>
            <p>This code is valid for 5 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
            <br>
            <p>Best regards,<br>SmartPitchHub Team</p>
        ";
        
        $mail->AltBody = "Your OTP code is: $otp. It is valid for 5 minutes.";

        $mail->send();
        
        // Log successful email sending
        error_log("OTP email sent successfully to: $email");
        return true;
        
    } catch (Exception $e) {
        // Log the detailed error
        $errorMessage = "Email sending failed: " . $e->getMessage();
        error_log($errorMessage);
        
        // Store error in session for user feedback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['email_error'] = $errorMessage;
        
        return false;
    }
}
