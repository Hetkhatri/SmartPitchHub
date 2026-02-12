<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'entrepreneur') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Get Wallet ID first
$wallet_sql = "SELECT id, balance FROM wallets WHERE user_id = ? AND user_role = 'entrepreneur'";
$wallet = null;
if ($stmt = $conn->prepare($wallet_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $wallet = $res->fetch_assoc();
    } else {
        // Create wallet if not exists (Auto-provision)
        $ins = $conn->prepare("INSERT INTO wallets (user_id, user_role, balance) VALUES (?, 'entrepreneur', 0.00)");
        $ins->bind_param("i", $user_id);
        $ins->execute();
        $wallet = ['id' => $conn->insert_id, 'balance' => 0.00];
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

if ($action === 'add_money') {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $source = $_POST['source'] ?? 'Bank Transfer';
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }

    // Begin Transaction
    $conn->begin_transaction();

    try {
        // 1. Insert Transaction
        $txn_id = 'TXN' . time() . rand(1000, 9999);
        $txn_sql = "INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'entrepreneur', 'credit', ?, ?, ?, 'success')";
        $stmt = $conn->prepare($txn_sql);
        $stmt->bind_param("iidss", $wallet['id'], $user_id, $amount, $source, $txn_id);
        $stmt->execute();

        // 2. Update Wallet Balance
        $upd_sql = "UPDATE wallets SET balance = balance + ? WHERE id = ?";
        $stmt = $conn->prepare($upd_sql);
        $stmt->bind_param("di", $amount, $wallet['id']);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Funds added successfully', 'new_balance' => $wallet['balance'] + $amount]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }

} elseif ($action === 'withdraw_money') {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $method = $_POST['method'] ?? 'Bank Account';

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }

    if ($amount > $wallet['balance']) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
        exit;
    }

    // Begin Transaction
    $conn->begin_transaction();

    try {
        // 1. Insert Transaction
        $txn_id = 'WDR' . time() . rand(1000, 9999);
        $txn_sql = "INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'entrepreneur', 'debit', ?, ?, ?, 'success')";
        $stmt = $conn->prepare($txn_sql);
        $stmt->bind_param("iidss", $wallet['id'], $user_id, $amount, $method, $txn_id);
        $stmt->execute();

        // 2. Update Wallet Balance
        $upd_sql = "UPDATE wallets SET balance = balance - ? WHERE id = ?";
        $stmt = $conn->prepare($upd_sql);
        $stmt->bind_param("di", $amount, $wallet['id']);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Withdrawal successful', 'new_balance' => $wallet['balance'] - $amount]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>