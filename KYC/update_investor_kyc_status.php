<?php
// update_investor_kyc_status.php
header('Content-Type: application/json');
session_start();
require_once '../db.php'; 

// 1. Admin Check
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// 2. Get Input Data
$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;
$reason = $_POST['reason'] ?? '';

if (!$id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or Status']);
    exit;
}

// 3. Update Database
$sql = "UPDATE investor_kyc_details SET status = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $reason, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'KYC status updated']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
}
$stmt->close();
?>