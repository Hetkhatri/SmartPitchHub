<?php
// Investment/process-investment.php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// 1. AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required to invest.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

if ($user_role !== 'investor') {
    echo json_encode(['success' => false, 'message' => 'Only investors can participate in this round.']);
    exit;
}

// --- NEW: BACKEND KYC SECURITY CHECK ---
$kyc_check_sql = "SELECT status FROM investor_kyc_details WHERE investor_id = ?";
$kyc_status = 'not_submitted';
if ($stmtK = $conn->prepare($kyc_check_sql)) {
    $stmtK->bind_param("i", $user_id);
    $stmtK->execute();
    $resK = $stmtK->get_result();
    if ($rowK = $resK->fetch_assoc()) {
        $kyc_status = $rowK['status'];
    }
    $stmtK->close();
}

if ($kyc_status !== 'approved') {
    echo json_encode(['success' => false, 'message' => 'Identity Verification Required: Please complete your KYC and get it approved to invest.']);
    exit;
}

// 2. INPUT VALIDATION
$pitch_id = isset($_POST['pitch_id']) ? intval($_POST['pitch_id']) : 0;
$shares_to_buy = isset($_POST['shares']) ? intval($_POST['shares']) : 0;

if ($pitch_id <= 0 || $shares_to_buy <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid investment parameters.']);
    exit;
}

try {
    // 3. FETCH PITCH & SHARES DATA
    $conn->begin_transaction();

    $pitch_sql = "SELECT p.*, (SELECT SUM(shares_bought) FROM investments WHERE pitch_id = p.id AND status = 'completed') as sold_count 
                  FROM pitches p WHERE p.id = ? FOR UPDATE";
    $stmt = $conn->prepare($pitch_sql);
    $stmt->bind_param("i", $pitch_id);
    $stmt->execute();
    $pitch = $stmt->get_result()->fetch_assoc();

    if (!$pitch) throw new Exception("Pitch not found.");

    // --- SMART PRICE LOGIC (Matches Console) ---
    $db_shares_issued = isset($pitch['shares_issued']) && $pitch['shares_issued'] > 0 ? intval($pitch['shares_issued']) : 100000;
    $db_share_price = isset($pitch['share_price']) && $pitch['share_price'] > 0 ? floatval($pitch['share_price']) : 0;
    $db_valuation = isset($pitch['valuation']) && $pitch['valuation'] > 0 ? floatval($pitch['valuation']) : 0;

    if ($db_share_price > 0) {
        $share_price = $db_share_price;
    } elseif ($db_valuation > 0) {
        $share_price = $db_valuation / $db_shares_issued;
    } else {
        $share_price = ($pitch['funding_goal'] * 7.5) / $db_shares_issued;
    }

    $total_cost = $shares_to_buy * $share_price;
    $remaining_in_round = $db_shares_issued - ($pitch['sold_count'] ?? 0);

    if ($shares_to_buy > $remaining_in_round) {
        throw new Exception("Not enough shares available in this round. Remaining: " . $remaining_in_round);
    }

    // --- NEW: BID DEDUCTION LOGIC ---
    // If it's the user's first investment in this pitch, deduct the required bids
    $req_bids = intval($pitch['required_bids'] ?? 0);
    if ($req_bids > 0) {
        $check_inv = $conn->prepare("SELECT id FROM investments WHERE investor_id = ? AND pitch_id = ? AND status = 'completed' LIMIT 1");
        $check_inv->bind_param("ii", $user_id, $pitch_id);
        $check_inv->execute();
        $is_first_investment = ($check_inv->get_result()->num_rows === 0);
        $check_inv->close();

        if ($is_first_investment) {
            $bid_stmt = $conn->prepare("SELECT total_bids FROM investor_bids WHERE investor_id = ? FOR UPDATE");
            $bid_stmt->bind_param("i", $user_id);
            $bid_stmt->execute();
            $bid_res = $bid_stmt->get_result()->fetch_assoc();
            $available_bids = $bid_res['total_bids'] ?? 0;

            if ($available_bids < $req_bids) {
                throw new Exception("Insufficient Bids. This startup requires $req_bids bids to unlock, but you only have $available_bids.");
            }

            // Deduct Bids
            $update_bids = $conn->prepare("UPDATE investor_bids SET total_bids = total_bids - ?, used_bids = used_bids + ? WHERE investor_id = ?");
            $update_bids->bind_param("iii", $req_bids, $req_bids, $user_id);
            $update_bids->execute();
        }
    }

    // 4. CHECK WALLET
    $wallet_sql = "SELECT id, balance FROM wallets WHERE user_id = ? AND user_role = 'investor' FOR UPDATE";
    $w_stmt = $conn->prepare($wallet_sql);
    $w_stmt->bind_param("i", $user_id);
    $w_stmt->execute();
    $wallet = $w_stmt->get_result()->fetch_assoc();

    if (!$wallet) {
        // Create wallet if doesn't exist (safety)
        $conn->query("INSERT INTO wallets (user_id, user_role, balance, status) VALUES ($user_id, 'investor', 0.00, 'active')");
        $wallet_id = $conn->insert_id;
        $wallet_balance = 0;
        throw new Exception("Insufficient balance. Your wallet balance is ₹0.00");
    }

    $wallet_id = $wallet['id'];
    $wallet_balance = $wallet['balance'];

    if ($wallet_balance < $total_cost) {
        throw new Exception("Insufficient balance. Total required: ₹" . number_format($total_cost, 2));
    }

    // 5. EXECUTE TRANSACTION
    // A. Deduct from wallet
    $new_balance = $wallet_balance - $total_cost;
    $update_w = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
    $update_w->bind_param("di", $new_balance, $wallet_id);
    $update_w->execute();

    // B. Create Investment Record
    $inv_sql = "INSERT INTO investments (investor_id, pitch_id, amount, shares_bought, purchase_price, investment_type, payout_status, status, transaction_id, created_at) 
                VALUES (?, ?, ?, ?, ?, 'primary', 'escrow', 'completed', ?, NOW())";
    $transaction_id = "INV-" . strtoupper(uniqid());
    $inv_stmt = $conn->prepare($inv_sql);
    $inv_stmt->bind_param("iiddss", $user_id, $pitch_id, $total_cost, $shares_to_buy, $share_price, $transaction_id);
    $inv_stmt->execute();

    // C. Update Pitch Aggregates (Performance Optimization)
    $update_pitch_sql = "UPDATE pitches SET amount_raised = amount_raised + ?, shares_sold = shares_sold + ? WHERE id = ?";
    $up_stmt = $conn->prepare($update_pitch_sql);
    $up_stmt->bind_param("dii", $total_cost, $shares_to_buy, $pitch_id);
    $up_stmt->execute();

    // Log the transaction in wallet_transactions
    $stmt_txn = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'investor', 'debit', ?, ?, ?, 'success')");
    $source_txt = substr("Invested in " . $pitch['startup_name'], 0, 50);
    $stmt_txn->bind_param("iidss", $wallet_id, $user_id, $total_cost, $source_txt, $transaction_id);
    $stmt_txn->execute();

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Congratulations! You have successfully purchased ' . number_format($shares_to_buy) . ' shares.',
        'transaction_id' => $transaction_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>