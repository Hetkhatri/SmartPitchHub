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

    // 2. Fetch All REAL Shareholders and Proportionality
    // Dividend is based on current share holdings, considering transfers.
    $sql_investors = "SELECT i.investor_id, SUM(i.shares_bought) as current_shares, w.id as investor_wallet_id 
                      FROM investments i 
                      JOIN pitches p ON i.pitch_id = p.id 
                      JOIN wallets w ON (w.user_id = i.investor_id AND w.user_role = 'investor')
                      WHERE p.entrepreneur_id = ? AND i.status = 'completed'
                      GROUP BY i.investor_id
                      HAVING current_shares > 0";
                      
    $stmt_inv = $conn->prepare($sql_investors);
    $stmt_inv->bind_param("i", $entrepreneur_id);
    $stmt_inv->execute();
    $result_inv = $stmt_inv->get_result();
    
    $shareholders = [];
    $total_platform_shares = 0;
    
    while ($row = $result_inv->fetch_assoc()) {
        $shareholders[] = [
            'investor_id' => $row['investor_id'],
            'wallet_id' => $row['investor_wallet_id'],
            'shares' => intval($row['current_shares'])
        ];
        $total_platform_shares += intval($row['current_shares']);
    }
    
    if ($total_platform_shares <= 0) {
        throw new Exception("No active shareholders found to distribute dividends to.");
    }

    // 3. Process Transactions
    // Deduct from Entrepreneur
    $new_ent_balance = $wallet['balance'] - $payout_amount;
    $upd_ent = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $upd_ent->bind_param("di", $new_ent_balance, $ent_wallet_id);
    $upd_ent->execute();

    // Log Debit
    $txn_ref = "DIV-" . time() . "-" . strtoupper(uniqid());
    $log_ent = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'entrepreneur', 'debit', ?, 'Dividend Payout', ?, 'success')");
    $log_ent->bind_param("iids", $ent_wallet_id, $entrepreneur_id, $payout_amount, $txn_ref);
    $log_ent->execute();

    // Credit Shareholders proportionally
    foreach ($shareholders as $sh) {
        // Calculate ratio based on shares owned vs total shares sold by this founder
        $share_ratio = $sh['shares'] / $total_platform_shares;
        $dividend_share = floor($payout_amount * $share_ratio * 100) / 100;
        
        if ($dividend_share > 0) {
            // Update Investor Wallet
            $upd_sh = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
            $upd_sh->bind_param("di", $dividend_share, $sh['wallet_id']);
            $upd_sh->execute();
            
            // Log Credit
            $log_sh = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'investor', 'credit', ?, 'Dividend Received', ?, 'success')");
            $log_sh->bind_param("iids", $sh['wallet_id'], $sh['investor_id'], $dividend_share, $txn_ref);
            $log_sh->execute();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Dividends distributed successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
