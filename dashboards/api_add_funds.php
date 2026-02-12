<?php
// dashboards/api_add_funds.php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : 'test_txn';

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount.']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Get or Create Wallet
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ? AND user_role = 'investor' FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();

    if (!$wallet) {
        $stmt_create = $conn->prepare("INSERT INTO wallets (user_id, user_role, balance, status) VALUES (?, 'investor', 0, 'active')");
        $stmt_create->bind_param("i", $user_id);
        $stmt_create->execute();
        $wallet_id = $conn->insert_id;
        $current_balance = 0;
    } else {
        $wallet_id = $wallet['id'];
        $current_balance = floatval($wallet['balance']);
    }

    // 2. Update Balance
    $new_balance = $current_balance + $amount;
    $stmt_update = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $stmt_update->bind_param("di", $new_balance, $wallet_id);
    $stmt_update->execute();

    // 3. Log Transaction
    $stmt_log = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'investor', 'credit', ?, 'Razorpay Topup', ?, 'success')");
    $stmt_log->bind_param("iids", $wallet_id, $user_id, $amount, $payment_id);
    $stmt_log->execute();

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => '₹' . number_format($amount) . ' added to your Smart Wallet successfully!',
        'new_balance' => $new_balance
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
