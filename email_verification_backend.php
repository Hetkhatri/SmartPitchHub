<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database connection
$conn = new mysqli('localhost', 'root', '', 'smartpitchhub-1');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get email from POST
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Check if email already has pending OTP
$stmt = $conn->prepare("SELECT * FROM otp_verifications WHERE email=? AND status='pending'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'An OTP verification is already pending for this email.']);
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);

// Insert OTP into table
$stmt = $conn->prepare("INSERT INTO otp_verifications (email, otp, status, created_at) VALUES (?, ?, 'pending', NOW())");
$stmt->bind_param("si", $email, $otp);
$stmt->execute();
$stmt->close();
$conn->close();

// Send OTP email
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.example.com'; // Replace with your SMTP host
    $mail->SMTPAuth = true;
    $mail->Username = 'hetkhatri22@gmail.com'; // Replace with your SMTP email
    $mail->Password = 'ojba jgvq vfuy idyr';    // Replace with your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('hetkhatri22@gmail.com', 'SmartPitchHub'); // Replace
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Code';
    $mail->Body = "Hello,<br><br>Your OTP verification code is: <b>$otp</b><br><br>Thanks,<br>SmartPitchHub";

    $mail->send();

    echo json_encode(['success' => true, 'message' => "We've sent a 6-digit code to $email"]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Failed to send OTP: " . $mail->ErrorInfo]);
}
exit;
