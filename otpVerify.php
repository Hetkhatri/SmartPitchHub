<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Ensure pending registration exists
if (!isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Registration session missing.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email or OTP missing.']);
    exit;
}

// Fetch latest pending OTP
$stmt = $conn->prepare("SELECT otp, status FROM otp_verifications WHERE email=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No OTP found for this email.']);
    exit;
}

$row = $result->fetch_assoc();

// Check OTP status
if ($row['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'OTP already used or expired.']);
    exit;
}

// Validate OTP
if ($row['otp'] !== $otp) {
    echo json_encode(['success' => false, 'message' => 'Incorrect OTP.']);
    exit;
}

// OTP correct → mark verified
$stmt = $conn->prepare("UPDATE otp_verifications SET status='verified' WHERE email=? AND otp=?");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();

// Insert user into correct table
$reg = $_SESSION['pending_registration'];

if ($reg['role'] === 'entrepreneur') {
    $stmt = $conn->prepare("INSERT INTO entrepreneurs (name,email,contact,password,startup_name) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $reg['name'],$reg['email'],$reg['contact'],$reg['password'],$reg['startup_name']);
} else {
    $stmt = $conn->prepare("INSERT INTO investors (name,email,contact,password) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $reg['name'],$reg['email'],$reg['contact'],$reg['password']);
}

if ($stmt->execute()) {
    unset($_SESSION['pending_registration']);
    echo json_encode(['success'=>true,'message'=>'Email verified successfully! Please login.']);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$stmt->error]);
}

exit;
?>
