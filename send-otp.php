<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'db.php';

$response = ['success' => false, 'message' => 'Something went wrong. Try again.'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);

        // Validate email
        if (empty($email)) {
            $response['message'] = "Email address is required.";
            echo json_encode($response);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = "Invalid email address.";
            echo json_encode($response);
            exit;
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Insert OTP into DB (allow multiple OTPs for same email, previous ones pending)
        $stmt = $conn->prepare("INSERT INTO otp_verifications (email, otp, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param("si", $email, $otp);
        if (!$stmt->execute()) {
            $response['message'] = "Database error: " . $stmt->error;
            echo json_encode($response);
            exit;
        }

        // Send OTP via PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'smartpitchhub@gmail.com'; // your Gmail
        $mail->Password = 'rnon yugk xhtb vbxu';  // Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('smartpitchhub@gmail.com', 'SmartPitchHub');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your OTP Code";
        $mail->Body    = "Your 6-digit OTP code is: <b>$otp</b>";
        $mail->send();

        // Set session for verification
        $_SESSION['verificationEmail'] = $email; // match JS localStorage key
        $_SESSION['otp'] = $otp;

        $response['success'] = true;
        $response['message'] = "We've sent a 6-digit OTP to $email";

    } else {
        $response['message'] = "Invalid request method.";
    }
} catch (Exception $e) {
    $response['message'] = "Exception: " . $e->getMessage();
}

echo json_encode($response);
exit;
?>
