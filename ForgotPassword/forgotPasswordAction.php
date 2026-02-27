<?php
session_start();

// 1. Load Database (Go up one folder)
require_once '../db.php';

// 2. Load Composer's Autoloader (Go up one folder to find 'vendor')
require '../vendor/autoload.php';

// 3. Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['submit_email'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $emailFound = false;

    // --- Check All Tables ---
    // 1. Check Entrepreneurs
    $stmt = $conn->prepare("SELECT id FROM entrepreneurs WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $emailFound = true;
    $stmt->close();

    // 2. Check Investors
    if (!$emailFound) {
        $stmt = $conn->prepare("SELECT id FROM investors WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $emailFound = true;
        $stmt->close();
    }

    // 3. Check Admins
    if (!$emailFound) {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $emailFound = true;
        $stmt->close();
    }

    if ($emailFound) {
        $token = bin2hex(random_bytes(50));
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Insert Token
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expiry);
        
        if ($stmt->execute()) {
            // Generate Link
            // Note: We use dirname twice to go up from 'forgoraPassword' to root if needed, 
            // but usually just pointing to the current folder's reset file is correct:
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
            
            // --- Send Email ---
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $config = require '../config.php';
                $mail->isSMTP();                                            
                $mail->Host       = $config['smtp_host'];                     
                $mail->SMTPAuth   = true;                                   
                $mail->Username   = $config['smtp_user'];
                $mail->Password   = $config['smtp_pass'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
                $mail->Port       = $config['smtp_port'];                                    

                // Recipients
                $mail->setFrom($config['from_email'], $config['from_name']); 
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);                                  
                $mail->Subject = 'Reset Your Password - SmartPitchHub';
                $mail->Body    = "
                    <h3>Password Reset Request</h3>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='$resetLink'>$resetLink</a></p>
                ";
                $mail->AltBody = "Reset link: $resetLink";

                $mail->send();
                
                $_SESSION['msg'] = "Reset link sent! Check your inbox.";
                $_SESSION['msg_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['msg'] = "Mailer Error: {$mail->ErrorInfo}";
                $_SESSION['msg_type'] = "error";
            }
        } else {
            $_SESSION['msg'] = "Database error.";
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "We can't find a user with that email address.";
        $_SESSION['msg_type'] = "error";
    }
}

header("Location: forgot-password.php");
exit();
?>