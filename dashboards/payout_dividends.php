<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'entrepreneur') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$entrepreneur_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$payout_amount = floatval($input['amount'] ?? 0);

if ($payout_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid amount greater than 0.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Check Entrepreneur's Wallet Balance
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ? AND user_role = 'entrepreneur' FOR UPDATE");
    $stmt->bind_param("i", $entrepreneur_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $wallet = $res->fetch_assoc();

    if (!$wallet || $wallet['balance'] < $payout_amount) {
        throw new Exception("Insufficient wallet balance for this payout.");
    }
    
    $ent_wallet_id = $wallet['id'];

    // 2. Fetch All Investors and Calculation Basis
    // We base it on proportional investment in THIS entrepreneur's start-up(s).
    // Note: A more complex system would do it per-share, but based on current schema:
    // Total Raised for this entrepreneur? Or Total Shares Issued?
    
    // Let's get total shares issued by this entrepreneur (across all pitches or active ones?)
    // Simpler approach: Get all 'completed' investments for this entrepreneur's pitches.
    $sql_investors = "SELECT i.investor_id, i.amount, w.id as investor_wallet_id 
                      FROM investments i 
                      JOIN pitches p ON i.pitch_id = p.id 
                      JOIN wallets w ON (w.user_id = i.investor_id AND w.user_role = 'investor')
                      WHERE p.entrepreneur_id = ? AND i.status = 'completed'";
                      
    $stmt_inv = $conn->prepare($sql_investors);
    $stmt_inv->bind_param("i", $entrepreneur_id);
    $stmt_inv->execute();
    $result_inv = $stmt_inv->get_result();
    
    $investments = [];
    $total_invested_capital = 0;
    
    while ($row = $result_inv->fetch_assoc()) {
        $inv_id = $row['investor_id'];
        $amount = $row['amount'];
        
        if (!isset($investments[$inv_id])) {
            $investments[$inv_id] = [
                'wallet_id' => $row['investor_wallet_id'],
                'total_invested' => 0
            ];
        }
        $investments[$inv_id]['total_invested'] += $amount;
        $total_invested_capital += $amount;
    }
    
    if ($total_invested_capital <= 0) {
        throw new Exception("No active investors found to distribute dividends to.");
    }

    // 3. Process Transactions
    // Deduct from Entrepreneur
    $new_ent_balance = $wallet['balance'] - $payout_amount;
    $upd_ent = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $upd_ent->bind_param("di", $new_ent_balance, $ent_wallet_id);
    $upd_ent->execute();

    // Log Debit
    $txn_ref = "DIV-" . time() . "-" . uniqid();
    $log_ent = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'entrepreneur', 'debit', ?, 'Dividend Payout', ?, 'success')");
    $log_ent->bind_param("iids", $ent_wallet_id, $entrepreneur_id, $payout_amount, $txn_ref);
    $log_ent->execute();

    // Credit Investors
    foreach ($investments as $inv_id => $data) {
        // Calculate share of the dividend pool
        $share_ratio = $data['total_invested'] / $total_invested_capital;
        $dividend_share = floor($payout_amount * $share_ratio * 100) / 100; // Floor to 2 decimals to be safe
        
        if ($dividend_share > 0) {
            // Update Investor Wallet
            $upd_inv = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
            $upd_inv->bind_param("di", $dividend_share, $data['wallet_id']);
            $upd_inv->execute();
            
            // Log Credit
            $log_inv = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'investor', 'credit', ?, 'Dividend Received', ?, 'success')");
            $log_inv->bind_param("iids", $data['wallet_id'], $inv_id, $dividend_share, $txn_ref);
            $log_inv->execute();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Dividends distributed successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
