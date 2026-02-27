<?php
// dashboards/api_finalize_round.php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// 1. AUTHENTICATION
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'entrepreneur') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$entrepreneur_id = $_SESSION['user_id'];
$pitch_id = isset($_POST['pitch_id']) ? intval($_POST['pitch_id']) : 0;

if ($pitch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pitch ID.']);
    exit;
}

try {
    $conn->begin_transaction();

    // 2. VERIFY OWNERSHIP & PITCH STATUS
    $pitch_sql = "SELECT * FROM pitches WHERE id = ? AND entrepreneur_id = ? FOR UPDATE";
    $stmt = $conn->prepare($pitch_sql);
    $stmt->bind_param("ii", $pitch_id, $entrepreneur_id);
    $stmt->execute();
    $pitch = $stmt->get_result()->fetch_assoc();

    if (!$pitch) throw new Exception("Pitch not found or access denied.");
    if (($pitch['round_status'] ?? '') === 'completed') throw new Exception("This round is already finalized.");

    // 3. CALCULATE FUNDS IN ESCROW
    $escrow_sql = "SELECT SUM(amount) as total_escrow FROM investments 
                   WHERE pitch_id = ? AND payout_status = 'escrow' AND status = 'completed'";
    $e_stmt = $conn->prepare($escrow_sql);
    $e_stmt->bind_param("i", $pitch_id);
    $e_stmt->execute();
    $escrow_res = $e_stmt->get_result()->fetch_assoc();
    $total_to_release = floatval($escrow_res['total_escrow'] ?? 0);

    if ($total_to_release <= 0) {
        throw new Exception("No funds available in escrow to release.");
    }

    // 4. GET/CREATE ENTREPRENEUR WALLET
    $wallet_sql = "SELECT id, balance FROM wallets WHERE user_id = ? AND user_role = 'entrepreneur' FOR UPDATE";
    $w_stmt = $conn->prepare($wallet_sql);
    $w_stmt->bind_param("i", $entrepreneur_id);
    $w_stmt->execute();
    $wallet = $w_stmt->get_result()->fetch_assoc();

    if (!$wallet) {
        $conn->query("INSERT INTO wallets (user_id, user_role, balance, status) VALUES ($entrepreneur_id, 'entrepreneur', 0.00, 'active')");
        $wallet_id = $conn->insert_id;
        $current_balance = 0;
    } else {
        $wallet_id = $wallet['id'];
        $current_balance = $wallet['balance'];
    }

    // 5. UPDATE TABLES
    // A. Add funds to entrepreneur wallet
    $new_balance = $current_balance + $total_to_release;
    $up_w = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $up_w->bind_param("di", $new_balance, $wallet_id);
    $up_w->execute();

    // B. Mark investments as released
    $up_inv = $conn->prepare("UPDATE investments SET payout_status = 'released' WHERE pitch_id = ? AND payout_status = 'escrow'");
    $up_inv->bind_param("i", $pitch_id);
    $up_inv->execute();

    // C. Mark pitch as funding closed
    $up_pitch = $conn->prepare("UPDATE pitches SET round_status = 'completed' WHERE id = ?");
    $up_pitch->bind_param("i", $pitch_id);
    $up_pitch->execute();

    // D. Log the transaction
    $txn_id = "REL-" . strtoupper(uniqid());
    $stmt_txn = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'entrepreneur', 'credit', ?, ?, ?, 'success')");
    $source_txt = "Round Finalized: " . $pitch['startup_name'];
    $stmt_txn->bind_param("iidss", $wallet_id, $entrepreneur_id, $total_to_release, $source_txt, $txn_id);
    $stmt_txn->execute();

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Success! ₹' . number_format($total_to_release, 2) . ' has been released to your wallet. Use this to grow your business!',
        'released_amount' => $total_to_release
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
