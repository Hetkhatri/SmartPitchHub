<?php
// =================================================================
// 1. CONFIGURATION & ERROR HANDLING
// =================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if database file exists
if (!file_exists('../db.php')) {
    die("<div style='padding:20px;color:red;font-family:sans-serif;'><strong>Error:</strong> db.php is missing. Please ensure it is in the parent folder.</div>");
}
require_once '../db.php';
$config = require '../config.php';

// =================================================================
// 2. AUTHENTICATION & KYC CHECK
// =================================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = "Investor"; 
$user_initials = "IN";

// DEFAULT KYC STATUS
$kyc_status = 'not_submitted'; 
$kyc_submitted = false;

try {
    // A. Check Real KYC Status from Database
    // This is needed for the "Restricted Features" popup
    $stmt_kyc = $conn->prepare("SELECT status FROM investor_kyc_details WHERE investor_id = ?");
    $stmt_kyc->bind_param("i", $user_id);
    $stmt_kyc->execute();
    $res_kyc = $stmt_kyc->get_result();
    if ($row_kyc = $res_kyc->fetch_assoc()) {
        $kyc_status = $row_kyc['status']; // 'under_review', 'approved', 'rejected'
        $kyc_submitted = true;
    }
    $stmt_kyc->close();

} catch(Exception $e) {
    // Fail silently for KYC check to avoid breaking the whole page
    error_log("KYC Check Error: " . $e->getMessage());
}

// Barrier Logic: Show popup if No Record OR Rejected
$show_kyc_popup = (!$kyc_submitted || $kyc_status === 'rejected');

// =================================================================
// 3. HANDLE INVESTMENT SUBMISSION
// =================================================================
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'invest') {
    try {
        // A. Security: Block investment if KYC is not approved
        if ($kyc_status !== 'approved') {
            throw new Exception("You must complete KYC verification before investing.");
        }

        $inv_amount = floatval($_POST['amount']);
        $inv_pitch_id = intval($_POST['pitch_id']);

        if ($inv_amount <= 0 || $inv_pitch_id <= 0) {
            throw new Exception("Invalid investment parameters.");
        }

        $conn->begin_transaction();

        // B. Fetch Pitch & Share Data
        $pitch_sql = "SELECT p.*, (SELECT SUM(shares_bought) FROM investments WHERE pitch_id = p.id AND status = 'completed') as sold_count 
                      FROM pitches p WHERE p.id = ? FOR UPDATE";
        $stmt_p = $conn->prepare($pitch_sql);
        $stmt_p->bind_param("i", $inv_pitch_id);
        $stmt_p->execute();
        $pitch_data = $stmt_p->get_result()->fetch_assoc();

        if (!$pitch_data) throw new Exception("Pitch not found.");

        $share_price = $pitch_data['share_price'] ?? 10; // Default to 10 if not set
        $shares_to_buy = floor($inv_amount / $share_price);

        if ($shares_to_buy <= 0) throw new Exception("Investment amount is too low for a single share (Price: ₹" . number_format($share_price) . ")");

        // === NEW: BID DEDUCTION LOGIC ===
        $req_bids = intval($pitch_data['required_bids'] ?? 0);
        if ($req_bids > 0) {
            $check_inv = $conn->prepare("SELECT id FROM investments WHERE investor_id = ? AND pitch_id = ? AND status = 'completed' LIMIT 1");
            $check_inv->bind_param("ii", $user_id, $inv_pitch_id);
            $check_inv->execute();
            $is_first_inv = ($check_inv->get_result()->num_rows === 0);
            $check_inv->close();

            if ($is_first_inv) {
                $bid_stmt = $conn->prepare("SELECT total_bids FROM investor_bids WHERE investor_id = ? FOR UPDATE");
                $bid_stmt->bind_param("i", $user_id);
                $bid_stmt->execute();
                $bid_res = $bid_stmt->get_result()->fetch_assoc();
                $available_bids = $bid_res['total_bids'] ?? 0;

                if ($available_bids < $req_bids) {
                    throw new Exception("Startup Lock: $req_bids Bids required to unlock this investment.");
                }

                $update_bids = $conn->prepare("UPDATE investor_bids SET total_bids = total_bids - ?, used_bids = used_bids + ? WHERE investor_id = ?");
                $update_bids->bind_param("iii", $req_bids, $req_bids, $user_id);
                $update_bids->execute();
            }
        }

        // C. Check Wallet
        $wallet_sql = "SELECT id, balance FROM wallets WHERE user_id = ? AND user_role = 'investor' FOR UPDATE";
        $stmt_w = $conn->prepare($wallet_sql);
        $stmt_w->bind_param("i", $user_id);
        $stmt_w->execute();
        $wallet_data = $stmt_w->get_result()->fetch_assoc();

        if (!$wallet_data || $wallet_data['balance'] < $inv_amount) {
            $bal = $wallet_data ? $wallet_data['balance'] : 0;
            throw new Exception("Insufficient balance. Total required: ₹" . number_format($inv_amount, 2) . " (Available: ₹" . number_format($bal, 2) . ")");
        }

        $wallet_id = $wallet_data['id'];

        // D. Execute Transaction
        // 1. Deduct from wallet
        $new_bal = $wallet_data['balance'] - $inv_amount;
        $stmt_up_w = $conn->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
        $stmt_up_w->bind_param("di", $new_bal, $wallet_id);
        $stmt_up_w->execute();

        // 2. Create Investment Record (Linking with Escrow)
        $inv_sql = "INSERT INTO investments (investor_id, pitch_id, amount, shares_bought, purchase_price, investment_type, payout_status, status, transaction_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'primary', 'escrow', 'completed', ?, NOW())";
        $transaction_id = "INV-" . strtoupper(uniqid());
        $stmt_inv = $conn->prepare($inv_sql);
        $stmt_inv->bind_param("iiddss", $user_id, $inv_pitch_id, $inv_amount, $shares_to_buy, $share_price, $transaction_id);
        $stmt_inv->execute();

        // 3. Log the transaction
        $source_txt = substr("Invested in " . $pitch_data['startup_name'], 0, 50);
        $stmt_txn = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, user_role, txn_type, amount, source, reference_id, status) VALUES (?, ?, 'investor', 'debit', ?, ?, ?, 'success')");
        $stmt_txn->bind_param("iidss", $wallet_id, $user_id, $inv_amount, $source_txt, $transaction_id);
        $stmt_txn->execute();

        $conn->commit();
        $message = "🎉 Congratulations! You have successfully invested ₹" . number_format($inv_amount) . " in " . $pitch_data['startup_name'] . ". (" . number_format($shares_to_buy) . " shares)";

    } catch (Exception $e) {
        if ($conn->in_transaction) $conn->rollback();
        $message = "Error: " . $e->getMessage();
    }
}

// =================================================================
// 4. FETCH DASHBOARD DATA
// =================================================================
try {
    // User Info
    $stmt = $conn->prepare("SELECT name FROM investors WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if ($user) {
        $user_name = $user['name'];
        $words = explode(" ", $user_name);
        $user_initials = "";
        foreach ($words as $w) $user_initials .= isset($w[0]) ? strtoupper($w[0]) : '';
        $user_initials = substr($user_initials, 0, 2);
    }

    // Wallet
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? AND user_role = 'investor'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $wallet = $res->fetch_assoc();
    $stmt->close();
    
    $wallet_balance = $wallet ? floatval($wallet['balance']) : 0.00;

    // Fetch Bid Balance
    $stmt_bids = $conn->prepare("SELECT total_bids FROM investor_bids WHERE investor_id = ?");
    $stmt_bids->bind_param("i", $user_id);
    $stmt_bids->execute();
    $bid_res = $stmt_bids->get_result()->fetch_assoc();
    $bid_balance = intval($bid_res['total_bids'] ?? 0);
    $stmt_bids->close();

    // Escrow / Locked Funds (Pending Payouts or active deal locks)
    // For this implementation, we'll consider investments with payout_status = 'escrow'
    $stmt_escrow = $conn->prepare("SELECT SUM(amount) as escrow_funds FROM investments WHERE investor_id = ? AND payout_status = 'escrow'");
    $stmt_escrow->bind_param("i", $user_id);
    $stmt_escrow->execute();
    $escrow_res = $stmt_escrow->get_result()->fetch_assoc();
    $escrow_balance = floatval($escrow_res['escrow_funds'] ?? 0);
    $stmt_escrow->close();

    // Recent Transactions
    $recent_txns = [];
    $stmt_txns = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
    $stmt_txns->bind_param("i", $user_id);
    $stmt_txns->execute();
    $res_txns = $stmt_txns->get_result();
    while($row = $res_txns->fetch_assoc()) {
        $recent_txns[] = $row;
    }
    $stmt_txns->close();

    // Portfolio Stats
    // UPDATED: Now calculating current portfolio value based on share prices
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT i.pitch_id) as active_holdings,
            SUM(i.shares_bought * p.share_price) as portfolio_value,
            SUM(i.amount) as total_spent
        FROM investments i
        JOIN pitches p ON i.pitch_id = p.id
        WHERE i.investor_id = ? AND i.status = 'completed'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stats = $res->fetch_assoc();
    $stmt->close();

    $portfolio_value = $stats['portfolio_value'] ?? 0;
    $total_invested = $stats['total_spent'] ?? 0;
    $active_investments = $stats['active_holdings'] ?? 0;
    $total_profit = $portfolio_value - $total_invested;
    $profit_percent = ($total_invested > 0) ? ($total_profit / $total_invested) * 100 : 0;

    // My Investments List - Grouped by Pitch & Status for Portfolio View
    $stmt = $conn->prepare("
        SELECT 
            p.id as pitch_id, 
            p.startup_name, 
            p.industry, 
            p.share_price as current_price,
            p.round_status,
            p.expiry_date,
            i.payout_status,
            SUM(i.shares_bought) as total_shares,
            SUM(i.amount) as total_spent,
            CASE WHEN SUM(i.shares_bought) > 0 THEN (SUM(i.amount) / SUM(i.shares_bought)) ELSE 0 END as avg_price
        FROM investments i 
        JOIN pitches p ON i.pitch_id = p.id 
        WHERE i.investor_id = ? AND i.status = 'completed'
        GROUP BY p.id, i.payout_status
        HAVING total_shares > 0
        ORDER BY i.payout_status DESC, total_shares DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $my_investments = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Recent Activity - Individual Transactions
    $stmt_act = $conn->prepare("
        SELECT i.*, p.startup_name, p.share_price
        FROM investments i
        JOIN pitches p ON i.pitch_id = p.id
        WHERE i.investor_id = ?
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $stmt_act->bind_param("i", $user_id);
    $stmt_act->execute();
    $recent_activity = $stmt_act->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_act->close();

    // All Pitches (For Explore)
    $result = $conn->query("
        SELECT p.*, COALESCE(SUM(i.amount), 0) as amount_raised, COUNT(DISTINCT i.investor_id) as investor_count
        FROM pitches p LEFT JOIN investments i ON p.id = i.pitch_id AND i.status = 'completed'
        WHERE p.is_approved = 1 
          AND EXISTS (SELECT 1 FROM warzone_sessions WHERE pitch_id = p.id AND status = 'completed')
        GROUP BY p.id");
    
    $all_pitches = $result->fetch_all(MYSQLI_ASSOC);

    // Prepare JS Data
    $js_pitches = [];
    foreach ($all_pitches as $p) {
        $progress = ($p['funding_goal'] > 0) ? min(100, round(($p['amount_raised'] / $p['funding_goal']) * 100)) : 0;
       $js_pitches[] = [
    'id' => $p['id'],
    'name' => htmlspecialchars($p['startup_name']),
    'tagline' => htmlspecialchars($p['tagline']),
    'industry' => htmlspecialchars($p['industry']),
    'fundingGoal' => "₹" . number_format($p['funding_goal']),
    
    // --- Share Data ---
    'sharePrice' => "₹" . number_format(isset($p['share_price']) ? $p['share_price'] : 0),
    'sharesIssued' => number_format(isset($p['shares_issued']) ? $p['shares_issued'] : 0),

    // --- NEW: Send Bid Cost to JS ---
    'bids' => isset($p['required_bids']) ? $p['required_bids'] : 2, 
    // --------------------------------

    'progress' => $progress,
    'investors' => $p['investor_count'],
    'verified' => true,
    'desc' => !empty($p['description']) ? htmlspecialchars($p['description']) : "No description provided.",
    'location' => !empty($p['location']) ? htmlspecialchars($p['location']) : "Remote",
    'stage' => !empty($p['stage']) ? htmlspecialchars($p['stage']) : "Seed",
    'raisedRaw' => $p['amount_raised'],
    'goalRaw' => $p['funding_goal'],
    'expiryDate' => $p['expiry_date'],
    'warzoneScore' => $p['warzone_score']
];
    }

    // Saved Pitches Check
    $saved_ids = [];
    $stmt_saved = $conn->prepare("SELECT pitch_id FROM saved_pitches WHERE user_id = ?");
    $stmt_saved->bind_param("i", $user_id);
    $stmt_saved->execute();
    $res_saved = $stmt_saved->get_result();
    while ($row = $res_saved->fetch_assoc()) {
        $saved_ids[] = $row['pitch_id'];
    }
    $stmt_saved->close();

    // =================================================================
    // 6. SECONDARY MARKET LISTINGS
    // =================================================================
    $stmt_market = $conn->prepare("
        SELECT smo.*, p.startup_name, p.industry, u.name as seller_name
        FROM secondary_market_orders smo
        JOIN pitches p ON smo.pitch_id = p.id
        JOIN investors u ON smo.seller_id = u.id
        WHERE smo.status = 'listing' AND smo.seller_id != ?
        ORDER BY smo.created_at DESC
    ");
    $stmt_market->bind_param("i", $user_id);
    $stmt_market->execute();
    $market_listings = $stmt_market->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_market->close();
    
    // Check user's own listings
    $stmt_my_market = $conn->prepare("
        SELECT smo.*, p.startup_name
        FROM secondary_market_orders smo
        JOIN pitches p ON smo.pitch_id = p.id
        WHERE smo.seller_id = ? AND smo.status = 'listing'
    ");
    $stmt_my_market->bind_param("i", $user_id);
    $stmt_my_market->execute();
    $my_active_listings = $stmt_my_market->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_my_market->close();

} catch(Exception $e) { die("DB Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartPitchHub - Investor Dashboard</title>
  <style>
    /* ====================== CSS VARIABLES ====================== */
    /* SECONDARY MARKET STYLES */
    .trade-card:hover { transform: translateY(-5px); border-color: var(--primary) !important; box-shadow: 0 10px 25px rgba(167, 139, 250, 0.1); }
    .pulse-green { display: inline-block; width: 8px; height: 8px; background: var(--success); border-radius: 50%; margin-right: 6px; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); animation: pulse-green 2s infinite; }
    @keyframes pulse-green { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
    .btn-buy-secondary:hover { background: var(--primary-hover) !important; transform: scale(1.02); }

    :root {
      /* Light Theme */
      --light-bg: #f8fafc;
      --light-bg-secondary: #ffffff;
      --light-bg-card: #ffffff;
      --light-border: #e2e8f0;
      --light-text: #0f172a;
      --light-text-muted: #64748b;
      --light-sidebar: #ffffff;
      --light-sidebar-border: #e2e8f0;
      
      /* Dark Theme */
      --dark-bg: #0B0E17;
      --dark-bg-secondary: #000000ff;
      --dark-bg-card: #111422;
      --dark-border: #1e2235;
      --dark-text: #f8fafc;
      --dark-text-muted: #94a3b8;
      --dark-sidebar: #0D0F1A;
      --dark-sidebar-border: #1e2235;
      
      /* Shared Colors */
      --primary: #A78BFA;
      --primary-hover: #c4b5fd;
      --primary-glow: rgba(167, 139, 250, 0.15);
      --accent: #818cf8;
      --success: #22c55e;
      --warning: #f59e0b;
      --destructive: #ef4444;
      
      /* Fonts */
      --font-display: 'Inter', system-ui, -apple-system, sans-serif;
      --font-body: 'Inter', system-ui, -apple-system, sans-serif;
    }

    /* Theme Application */
    [data-theme="dark"] {
      --bg: var(--dark-bg);
      --bg-secondary: var(--dark-bg-secondary);
      --bg-card: var(--dark-bg-card);
      --border: var(--dark-border);
      --text: var(--dark-text);
      --text-muted: var(--dark-text-muted);
      --sidebar-bg: var(--dark-sidebar);
      --sidebar-border: var(--dark-sidebar-border);
    }

    [data-theme="light"] {
      --bg: var(--light-bg);
      --bg-secondary: var(--light-bg-secondary);
      --bg-card: var(--light-bg-card);
      --border: var(--light-border);
      --text: var(--light-text);
      --text-muted: var(--light-text-muted);
      --sidebar-bg: var(--light-sidebar);
      --sidebar-border: var(--light-sidebar-border);
    }

    /* ====================== RESET & BASE ====================== */
    *, *::before, *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: var(--font-body);
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.6;
      min-height: 100vh;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    button {
      font-family: inherit;
      cursor: pointer;
      border: none;
      background: none;
    }

    input, select, textarea {
      font-family: inherit;
      font-size: inherit;
    }

    /* ====================== LAYOUT ====================== */
    .dashboard {
      display: flex;
      min-height: 100vh;
      width: 100%;
      overflow-x: hidden;
    }

    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      width: 260px;
      height: 100vh;
      background: var(--sidebar-bg);
      border-right: 1px solid var(--sidebar-border);
      display: flex;
      flex-direction: column;
      z-index: 100;
      transition: width 0.3s ease, background-color 0.3s ease;
    }

    .sidebar.collapsed {
      width: 72px;
    }

    .main-content {
      margin-left: 260px;
      padding: 24px 32px;
      min-height: 100vh;
      transition: margin-left 0.3s ease;
      width: calc(100% - 260px);
      min-width: 0;
    }

    .sidebar.collapsed + .main-content {
      margin-left: 72px;
      width: calc(100% - 72px);
    }

    /* ====================== SIDEBAR ====================== */
    .sidebar-header {
      height: 72px;
      padding: 0 20px;
      border-bottom: 1px solid var(--sidebar-border);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 4px 20px rgba(167, 139, 250, 0.3);
    }

    .logo svg {
      width: 20px;
      height: 20px;
      color: white;
    }

    .logo-text {
      overflow: hidden;
      transition: opacity 0.3s ease;
    }

    .sidebar.collapsed .logo-text {
      opacity: 0;
      width: 0;
    }

    .logo-text h1 {
      font-size: 15px;
      font-weight: 700;
      white-space: nowrap;
    }

    .logo-text p {
      font-size: 11px;
      color: var(--text-muted);
      margin-top: -2px;
    }

    .sidebar-toggle {
      position: absolute;
      right: -12px;
      top: 76px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: all 0.2s ease;
    }

    .sidebar-toggle:hover {
      background: var(--bg-secondary);
    }

    .sidebar-toggle svg {
      width: 14px;
      height: 14px;
      color: var(--text-muted);
    }

    .sidebar-nav {
      flex: 1;
      padding: 16px 12px;
      overflow-y: auto;
    }

    .nav-item {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      color: var(--text-muted);
      font-size: 13px;
      font-weight: 500;
      transition: all 0.2s ease;
      margin-bottom: 2px;
    }

    .nav-item:hover {
      background: var(--primary-glow);
      color: var(--text);
    }

    .nav-item.active {
      background: var(--primary-glow);
      color: var(--primary);
    }

    .locked-item {
      opacity: 0.5;
      cursor: not-allowed !important;
    }
    
    .nav-item.locked-item:hover {
        background: transparent;
        transform: none;
        color: var(--text-muted);
    }

    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 20px;
      background: var(--primary);
      border-radius: 0 4px 4px 0;
    }

    .nav-item svg {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }

    .nav-item span {
      white-space: nowrap;
      transition: opacity 0.3s ease;
    }

    .sidebar.collapsed .nav-item span {
      opacity: 0;
      width: 0;
    }

    .sidebar-footer {
      padding: 12px;
      border-top: 1px solid var(--sidebar-border);
    }

    .user-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px;
      border-radius: 12px;
      transition: background 0.2s ease;
    }

    .user-card:hover {
      background: var(--primary-glow);
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-glow), rgba(129, 140, 248, 0.2));
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      font-weight: 600;
      font-size: 14px;
      flex-shrink: 0;
      border: 2px solid rgba(167, 139, 250, 0.2);
    }

    .user-info {
      overflow: hidden;
      transition: opacity 0.3s ease;
    }

    .sidebar.collapsed .user-info {
      opacity: 0;
      width: 0;
    }

    .user-info p {
      font-size: 14px;
      font-weight: 500;
      white-space: nowrap;
    }

    .user-info span {
      font-size: 11px;
      color: var(--text-muted);
    }

    .logout-btn {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 12px;
      border-radius: 8px;
      color: var(--text-muted);
      font-size: 13px;
      font-weight: 500;
      margin-top: 4px;
      transition: all 0.2s ease;
    }

    .logout-btn:hover {
      color: var(--destructive);
      background: rgba(239, 68, 68, 0.1);
    }

    .logout-btn svg {
      width: 16px;
      height: 16px;
    }

    /* ====================== THEME TOGGLE ====================== */
    .theme-toggle-container {
      padding: 8px 12px;
      margin-bottom: 8px;
    }

    .theme-toggle {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
    }

    .sidebar.collapsed .theme-toggle {
      padding: 10px 8px;
      justify-content: center;
    }

    .theme-toggle-label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--text-muted);
    }

    .sidebar.collapsed .theme-toggle-label span {
      display: none;
    }

    .theme-switch {
      position: relative;
      width: 44px;
      height: 24px;
      background: var(--border);
      border-radius: 12px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    [data-theme="dark"] .theme-switch {
      background: var(--primary);
    }

    .theme-switch::after {
      content: '';
      position: absolute;
      top: 2px;
      left: 2px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: white;
      transition: transform 0.3s ease;
    }

    [data-theme="dark"] .theme-switch::after {
      transform: translateX(20px);
    }

    .sidebar.collapsed .theme-switch {
      display: none;
    }

    /* ====================== CARDS ====================== */
    .card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      transition: all 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 8px 32px rgba(167, 139, 250, 0.1);
    }

    .card-premium {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .card-premium:hover {
      border-color: rgba(167, 139, 250, 0.3);
      box-shadow: 0 8px 32px rgba(167, 139, 250, 0.08);
    }

    /* ====================== STAT CARDS ====================== */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    @media (max-width: 1400px) {
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }

    .stat-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      border-color: rgba(167, 139, 250, 0.3);
      box-shadow: 0 12px 24px rgba(167, 139, 250, 0.1);
    }

    .stat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: var(--primary-glow);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .stat-icon svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
    }

    .stat-trend {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 8px;
    }

    .stat-trend.positive {
      background: rgba(34, 197, 94, 0.15);
      color: var(--success);
    }

    .stat-trend.negative {
      background: rgba(239, 68, 68, 0.15);
      color: var(--destructive);
    }

    .stat-value {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 13px;
      color: var(--text-muted);
    }

    .stat-subtitle {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 4px;
    }

    /* ====================== BUTTONS ====================== */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: white;
      box-shadow: 0 4px 16px rgba(167, 139, 250, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(167, 139, 250, 0.4);
    }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
    }

    .btn-outline:hover {
      background: var(--bg-secondary);
      border-color: var(--primary);
      color: var(--primary);
    }

    .btn-ghost {
      background: transparent;
      color: var(--text-muted);
    }

    .btn-ghost:hover {
      background: var(--bg-secondary);
      color: var(--text);
    }

    .btn-icon {
      width: 40px;
      height: 40px;
      padding: 0;
      border-radius: 10px;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
    }

    .btn-lg {
      padding: 12px 24px;
      font-size: 15px;
    }

    /* ====================== INPUTS ====================== */
    .input {
      width: 100%;
      padding: 12px 16px;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 12px;
      color: var(--text);
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.15);
    }

    .input::placeholder {
      color: var(--text-muted);
    }

    .input-search {
      padding-left: 44px;
    }

    .search-container {
      position: relative;
    }

    .search-container svg {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      width: 18px;
      height: 18px;
      color: var(--text-muted);
    }

    /* ====================== PROGRESS BAR ====================== */
    .progress-bar-container {
      height: 8px;
      background: var(--bg-secondary);
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-bar {
      height: 100%;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      border-radius: 4px;
      transition: width 1s ease-out;
    }

    /* ====================== BADGES ====================== */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 500;
    }

    .badge-primary {
      background: rgba(167, 139, 250, 0.15);
      color: var(--primary);
    }

    .badge-success {
      background: rgba(34, 197, 94, 0.15);
      color: var(--success);
    }

    .badge-warning {
      background: rgba(245, 158, 11, 0.15);
      color: var(--warning);
    }

    .badge-destructive {
      background: rgba(239, 68, 68, 0.15);
      color: var(--destructive);
    }

    .badge-muted {
      background: var(--bg-secondary);
      color: var(--text-muted);
    }

    /* ====================== TABLES ====================== */
    .table-container {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead tr {
      border-bottom: 1px solid var(--border);
      background: var(--bg-secondary);
    }

    th {
      padding: 12px 16px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
    }

    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.2s ease;
    }

    tbody tr:hover {
      background: var(--bg-secondary);
    }

    td {
      padding: 16px;
      font-size: 14px;
    }

    /* ====================== ACTIVITY ITEMS ====================== */
    .activity-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 12px 16px;
      border-radius: 12px;
      transition: background 0.2s ease;
    }

    .activity-item:hover {
      background: var(--bg-secondary);
    }

    .activity-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .activity-icon.investment {
      background: rgba(34, 197, 94, 0.15);
    }

    .activity-icon.investment svg {
      color: var(--success);
    }

    .activity-icon.pitch {
      background: rgba(167, 139, 250, 0.15);
    }

    .activity-icon.pitch svg {
      color: var(--primary);
    }

    .activity-icon.reply {
      background: rgba(129, 140, 248, 0.15);
    }

    .activity-icon.reply svg {
      color: var(--accent);
    }

    .activity-content {
      flex: 1;
      min-width: 0;
    }

    .activity-title {
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 2px;
    }

    .activity-desc {
      font-size: 13px;
      color: var(--text-muted);
    }

    .activity-time {
      font-size: 12px;
      color: var(--text-muted);
      white-space: nowrap;
    }

    /* ====================== PITCH CARDS ====================== */
    .pitch-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
    }

    @media (max-width: 1200px) {
      .pitch-grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      }
    }

    @media (max-width: 768px) {
      .pitch-grid {
        grid-template-columns: 1fr;
      }
    }

    .pitch-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      transition: all 0.3s ease;
    }

    .pitch-card:hover {
      transform: translateY(-4px);
      border-color: rgba(167, 139, 250, 0.3);
      box-shadow: 0 16px 40px rgba(167, 139, 250, 0.1);
    }

    .pitch-header {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 16px;
    }

    .pitch-logo {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--primary-glow), rgba(129, 140, 248, 0.15));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: 700;
      color: var(--primary);
      flex-shrink: 0;
    }

    .pitch-info {
      flex: 1;
      min-width: 0;
    }

    .pitch-title {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 4px;
    }

    .pitch-title h3 {
      font-size: 16px;
      font-weight: 600;
    }

    .pitch-tagline {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .pitch-save-btn {
      padding: 6px;
      border-radius: 8px;
      color: var(--text-muted);
      transition: all 0.2s ease;
    }

    .pitch-save-btn:hover {
      background: var(--primary-glow);
      color: var(--primary);
    }

    .pitch-save-btn.saved {
      color: var(--primary);
    }

    .pitch-tags {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
    }

    .pitch-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      padding: 12px 0;
      border-top: 1px solid var(--border);
      margin-bottom: 16px;
    }

    .pitch-stat {
      text-align: center;
    }

    .pitch-stat-value {
      font-size: 16px;
      font-weight: 600;
    }

    .pitch-stat-label {
      font-size: 11px;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .pitch-progress {
      margin-bottom: 16px;
    }

    .pitch-progress-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 12px;
    }

    .pitch-progress-label {
      color: var(--text-muted);
    }

    .pitch-progress-value {
      color: var(--primary);
      font-weight: 600;
    }

    .pitch-actions {
      display: flex;
      gap: 8px;
    }

    .pitch-actions .btn {
      flex: 1;
    }

    /* ====================== QUICK ACTIONS ====================== */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }

    @media (max-width: 768px) {
      .quick-actions {
        grid-template-columns: 1fr;
      }
    }

    .quick-action {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 12px;
      transition: all 0.2s ease;
      text-align: left;
    }

    .quick-action:hover {
      border-color: rgba(167, 139, 250, 0.3);
      box-shadow: 0 8px 24px rgba(167, 139, 250, 0.08);
    }

    .quick-action-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s ease;
    }

    .quick-action-icon svg {
      width: 20px;
      height: 20px;
    }

    .quick-action-icon.primary {
      background: rgba(167, 139, 250, 0.15);
      color: var(--primary);
    }

    .quick-action-icon.success {
      background: rgba(34, 197, 94, 0.15);
      color: var(--success);
    }

    .quick-action-icon.accent {
      background: rgba(129, 140, 248, 0.15);
      color: var(--accent);
    }

    .quick-action:hover .quick-action-icon.primary {
      background: rgba(167, 139, 250, 0.25);
    }

    .quick-action:hover .quick-action-icon.success {
      background: rgba(34, 197, 94, 0.25);
    }

    .quick-action:hover .quick-action-icon.accent {
      background: rgba(129, 140, 248, 0.25);
    }

    .quick-action-content p {
      font-size: 14px;
      font-weight: 500;
      
    }

    .quick-action-content span {
      font-size: 12px;
      color: var(--text-muted);
    }

    /* ====================== FEATURED BANNER ====================== */
    .featured-banner {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }

    .featured-banner::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--primary-glow), rgba(129, 140, 248, 0.05));
      pointer-events: none;
    }

    .featured-content {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .featured-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .featured-icon {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      background: rgba(167, 139, 250, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .featured-icon svg {
      width: 24px;
      height: 24px;
      color: var(--primary);
    }

    .featured-text h3 {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .featured-text p {
      font-size: 14px;
      color: var(--text-muted);
    }

    /* ====================== FILTERS ====================== */
    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 24px;
    }

    .filter-pills {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .filter-pill {
      padding: 8px 16px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      background: var(--bg-secondary);
      color: var(--text-muted);
      border: 1px solid var(--border);
      transition: all 0.2s ease;
    }

    .filter-pill:hover {
      color: var(--text);
      border-color: var(--primary);
    }

    .filter-pill.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    /* ====================== TOGGLE SWITCH ====================== */
    .toggle-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px;
      background: var(--bg-secondary);
      border-radius: 12px;
      margin-bottom: 16px;
    }

    .toggle-label {
      display: flex;
      flex-direction: column;
    }

    .toggle-label p {
      font-size: 14px;
      font-weight: 500;
    }

    .toggle-label span {
      font-size: 13px;
      color: var(--text-muted);
    }

    .toggle-switch {
      position: relative;
      width: 44px;
      height: 24px;
      background: var(--border);
      border-radius: 12px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .toggle-switch.active {
      background: var(--primary);
    }

    .toggle-switch::after {
      content: '';
      position: absolute;
      top: 2px;
      left: 2px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: white;
      transition: transform 0.3s ease;
    }

    .toggle-switch.active::after {
      transform: translateX(20px);
    }

    /* ====================== PAGE HEADER ====================== */
    .page-header {
      margin-bottom: 24px;
    }

    .page-header h1 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .page-header p {
      font-size: 14px;
      color: var(--text-muted);
    }

    .page-header-flex {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ====================== SECTION TITLE ====================== */
    .section-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }

    .section-title h2 {
      font-size: 18px;
      font-weight: 600;
    }

    /* ====================== WALLET UPDATED ====================== */
    .wallet-grid {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
    }

    @media (max-width: 1024px) {
      .wallet-grid {
        grid-template-columns: 1fr;
      }
    }

    /* ============================================================
       ULTIMATE NEXT-GEN FINTECH WALLET UI
       ============================================================ */
    .wallet-grid {
      display: grid;
      grid-template-columns: 1.4fr 1fr;
      gap: 32px;
      padding: 10px;
    }

    @media (max-width: 1250px) {
      .wallet-grid { grid-template-columns: 1fr; }
    }

    .smart-wallet-card {
      background: #0a0a0c;
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 40px;
      padding: 40px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.8);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 480px;
    }

    /* Ultra-Premium Mesh Background */
    .wallet-mesh {
      position: absolute;
      inset: -50%;
      background: 
        radial-gradient(circle at 30% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
        radial-gradient(circle at 70% 60%, rgba(139, 92, 246, 0.15) 0%, transparent 40%),
        radial-gradient(circle at 40% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 40%);
      filter: blur(80px);
      z-index: 0;
      animation: meshFloat 20s infinite alternate;
    }

    @keyframes meshFloat {
      0% { transform: rotate(0deg) scale(1); }
      100% { transform: rotate(10deg) scale(1.1); }
    }

    .wallet-brand-chip {
      position: absolute;
      top: 40px;
      right: 40px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 10px 20px;
      border-radius: 100px;
      backdrop-filter: blur(20px);
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 2;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .wallet-brand-chip span {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #818cf8;
    }

    .wallet-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: rgba(34, 197, 94, 0.05);
      color: #4ade80;
      border: 1px solid rgba(34, 197, 94, 0.1);
      border-radius: 100px;
      font-size: 12px;
      font-weight: 600;
      width: fit-content;
      z-index: 1;
    }

    .wallet-balance-main {
      position: relative;
      z-index: 1;
      margin-top: 40px;
    }

    .wallet-balance-label {
      color: #64748b;
      font-size: 16px;
      font-weight: 500;
      margin-bottom: 8px;
    }

    .wallet-balance-value {
      font-size: 72px;
      font-weight: 800;
      letter-spacing: -3px;
      background: linear-gradient(180deg, #ffffff 0%, #94a3b8 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 40px;
      line-height: 1;
    }

    /* Modern Breakdown Section */
    .wallet-breakdown {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      z-index: 1;
      position: relative;
    }

    .breakdown-item {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      padding: 24px;
      border-radius: 24px;
      transition: all 0.3s ease;
    }

    .breakdown-item:hover {
      background: rgba(255, 255, 255, 0.04);
      border-color: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }

    .breakdown-label {
      color: #64748b;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      font-weight: 700;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .breakdown-value {
      color: #ffffff;
      font-size: 24px;
      font-weight: 700;
    }

    /* Floating Action Buttons */
    .wallet-actions-dock {
      display: flex;
      gap: 12px;
      margin-top: 32px;
      z-index: 1;
      position: relative;
    }

    .btn-action-premium {
      flex: 1;
      background: #ffffff;
      color: #000000;
      border: none;
      padding: 18px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-action-premium:hover {
      transform: scale(1.02);
      box-shadow: 0 20px 40px -10px rgba(255, 255, 255, 0.1);
    }

    .btn-action-premium.secondary {
      background: rgba(255, 255, 255, 0.05);
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-action-premium.bid-accent {
      background: #a78bfa;
      color: #000;
      border: none;
    }

    .btn-action-premium.bid-accent:hover {
      background: #c4b5fd;
      transform: translateY(-2px);
      box-shadow: 0 10px 20px -5px rgba(167, 139, 250, 0.4);
    }

    /* Advanced Ledger Card */
    .ledger-card {
      background: rgba(15, 15, 20, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 40px;
      padding: 32px;
      backdrop-filter: blur(40px);
      display: flex;
      flex-direction: column;
    }

    .ledger-header {
      padding-bottom: 24px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .txn-item {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px;
      border-radius: 20px;
      transition: all 0.2s ease;
      margin-bottom: 8px;
    }

    .txn-item:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .txn-icon-circle {
      width: 48px;
      height: 48px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .txn-icon-circle.credit { color: #4ade80; background: rgba(34, 197, 94, 0.05); }
    .txn-icon-circle.debit { color: #f87171; background: rgba(248, 113, 113, 0.05); }

    .txn-info { flex: 1; }
    .txn-info h4 { font-size: 15px; font-weight: 600; color: #f1f5f9; margin-bottom: 4px; }
    .txn-info p { font-size: 12px; color: #64748b; }

    .ledger-footer {
      margin-top: auto;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      display: flex;
      justify-content: center;
    }

    .btn-view-all {
      color: #818cf8;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .stat-badge {
      position: absolute;
      top: -2px;
      right: 0;
      font-size: 9px;
      padding: 2px 6px;
      background: rgba(99, 102, 241, 0.1);
      color: #818cf8;
      border-radius: 4px;
      font-weight: 600;
    }

    .wallet-quick-actions {
      display: flex;
      gap: 16px;
      margin-top: 40px;
    }

    .btn-wallet {
      flex: 1;
      height: 54px;
      border-radius: 16px;
      font-weight: 700;
      font-size: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-wallet-primary {
      background: #6366f1;
      color: white;
      border: none;
      box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
    }

    .btn-wallet-primary:hover {
      background: #4f46e5;
      box-shadow: 0 15px 25px -5px rgba(99, 102, 241, 0.5);
      transform: translateY(-3px);
    }

    .btn-wallet-secondary {
      background: rgba(255, 255, 255, 0.03);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(10px);
    }

    .btn-wallet-secondary:hover {
      background: rgba(255, 255, 255, 0.08);
      transform: translateY(-3px);
    }

    /* Ledger Enhancements */
    .ledger-card {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 28px;
      backdrop-filter: blur(20px);
    }

    .ledger-header {
        padding: 28px 32px;
    }

    .txn-item {
        padding: 20px 32px;
        animation: slideUpIn 0.5s ease backwards;
    }

    @keyframes slideUpIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .txn-item:nth-child(1) { animation-delay: 0.1s; }
    .txn-item:nth-child(2) { animation-delay: 0.2s; }
    .txn-item:nth-child(3) { animation-delay: 0.3s; }

    .txn-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
    }

    /* Trust Footer */
    .wallet-trust-footer {
        display: flex;
        justify-content: center;
        gap: 24px;
        margin-top: 32px;
        opacity: 0.5;
        filter: grayscale(1);
        transition: opacity 0.3s;
    }

    .wallet-trust-footer:hover { opacity: 1; filter: none; }

    .trust-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
    }

    /* Transactions Ledger */
    .ledger-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 0;
      overflow: hidden;
    }

    .ledger-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .ledger-header h3 {
      font-size: 16px;
      font-weight: 600;
    }

    .txn-list {
      list-style: none;
      padding: 0;
    }

    .txn-item {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px 24px;
      border-bottom: 1px solid var(--border);
      transition: background 0.2s;
    }

    .txn-item:last-child {
      border-bottom: none;
    }

    .txn-item:hover {
      background: rgba(99, 102, 241, 0.03);
    }

    .txn-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .txn-icon.credit {
      background: rgba(34, 197, 94, 0.1);
      color: #22c55e;
    }

    .txn-icon.debit {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }

    .txn-details {
      flex: 1;
    }

    .txn-title {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 2px;
    }

    .txn-meta {
      font-size: 12px;
      color: var(--text-muted);
    }

    .txn-amount {
      text-align: right;
    }

    .txn-amount.credit { color: #22c55e; font-weight: 700; }
    .txn-amount.debit { color: #f8fafc; font-weight: 600; }

    .txn-amount p:last-child {
      font-size: 10px;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-top: 2px;
    }

    /* ====================== PAGE SECTIONS ====================== */

    .wallet-stat-card {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .wallet-stat-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .wallet-stat-icon.warning {
      background: rgba(245, 158, 11, 0.15);
      color: var(--warning);
    }

    .wallet-stat-icon.success {
      background: rgba(34, 197, 94, 0.15);
      color: var(--success);
    }

    .wallet-stat-label {
      font-size: 11px;
      color: var(--text-muted);
    }

    .wallet-stat-value {
      font-size: 20px;
      font-weight: 700;
    }

    .wallet-stat-note {
      font-size: 12px;
      color: var(--text-muted);
    }

    /* ====================== PAYMENT METHODS ====================== */
    .payment-methods {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    @media (max-width: 768px) {
      .payment-methods {
        grid-template-columns: 1fr;
      }
    }

    .payment-method {
      padding: 16px;
      border: 1px solid var(--border);
      border-radius: 12px;
      text-align: left;
      transition: all 0.2s ease;
    }

    .payment-method:hover {
      border-color: rgba(167, 139, 250, 0.5);
      background: var(--primary-glow);
    }

    .payment-method svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
      margin-bottom: 12px;
    }

    .payment-method p {
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 2px;
    }

    .payment-method span {
      font-size: 12px;
      color: var(--text-muted);
    }

    /* ====================== PAGE SECTIONS ====================== */
    .page-section {
      display: none;
    }

    .page-section.active {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* ====================== BACK BUTTON ====================== */
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 24px;
      transition: color 0.2s ease;
    }

    .back-btn:hover {
      color: var(--text);
    }

    .back-btn svg {
      width: 16px;
      height: 16px;
    }

    /* ====================== VIEW PITCH PAGE ====================== */
    .pitch-detail-header {
      display: flex;
      gap: 20px;
      align-items: flex-start;
    }

    .pitch-detail-logo {
      width: 80px;
      height: 80px;
      border-radius: 20px;
      background: linear-gradient(135deg, var(--primary-glow), rgba(129, 140, 248, 0.15));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      font-weight: 700;
      color: var(--primary);
      border: 1px solid var(--border);
      flex-shrink: 0;
    }

    .pitch-detail-info {
      flex: 1;
    }

    .pitch-detail-title {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
    }

    .pitch-detail-title h1 {
      font-size: 24px;
      font-weight: 700;
    }

    .pitch-detail-tagline {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 12px;
    }

    .pitch-detail-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .pitch-detail-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 16px;
      margin-top: 16px;
    }

    @media (max-width: 1024px) {
      .pitch-detail-grid {
        grid-template-columns: 1fr;
      }
    }

    .pitch-detail-main {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .pitch-detail-sidebar {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .pitch-invest-card {
      position: sticky;
      top: 24px;
    }

    .pitch-invest-header {
      text-align: center;
      margin-bottom: 20px;
    }

    .pitch-invest-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      margin-bottom: 4px;
    }

    .pitch-invest-value {
      font-size: 32px;
      font-weight: 700;
    }

    .pitch-invest-goal {
      font-size: 14px;
      color: var(--text-muted);
    }

    .pitch-invest-progress {
      margin: 20px 0;
    }

    .pitch-invest-progress-value {
      text-align: center;
      font-size: 14px;
      color: var(--primary);
      font-weight: 600;
      margin-top: 8px;
    }

    .pitch-invest-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
      margin-bottom: 20px;
    }

    .pitch-invest-stat {
      text-align: center;
      padding: 12px;
      background: var(--bg-secondary);
      border-radius: 12px;
    }

    .pitch-invest-stat-value {
      font-size: 20px;
      font-weight: 700;
    }

    .pitch-invest-stat-label {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      color: var(--text-muted);
    }

    .pitch-invest-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .pitch-invest-note {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding-top: 16px;
      margin-top: 16px;
      border-top: 1px solid var(--border);
      font-size: 12px;
      color: var(--text-muted);
    }

    .pitch-invest-note svg {
      width: 14px;
      height: 14px;
    }

    .pitch-highlights {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }

    @media (max-width: 768px) {
      .pitch-highlights {
        grid-template-columns: 1fr;
      }
    }

    .pitch-highlight {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 12px;
      background: rgba(34, 197, 94, 0.05);
      border: 1px solid rgba(34, 197, 94, 0.15);
      border-radius: 12px;
    }

    .pitch-highlight-icon {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: rgba(34, 197, 94, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
      color: var(--success);
      font-size: 12px;
    }

    .pitch-highlight span {
      font-size: 14px;
    }

    .pitch-founder {
      display: flex;
      align-items: flex-start;
      gap: 16px;
    }

    .pitch-founder-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-glow), rgba(129, 140, 248, 0.2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      font-weight: 600;
      color: var(--primary);
      flex-shrink: 0;
      border: 2px solid rgba(167, 139, 250, 0.2);
    }

    .pitch-founder-info h4 {
      font-size: 16px;
      font-weight: 600;
    }

    .pitch-founder-role {
      font-size: 14px;
      color: var(--primary);
      margin-bottom: 8px;
    }

    .pitch-founder-bio {
      font-size: 14px;
      color: var(--text-muted);
      line-height: 1.6;
    }

    .pitch-funds-item {
      margin-bottom: 16px;
    }

    .pitch-funds-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .pitch-funds-category {
      font-weight: 500;
    }

    .pitch-funds-percent {
      color: var(--primary);
      font-weight: 600;
    }

    .pitch-quick-stats {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .pitch-quick-stat {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .pitch-quick-stat svg {
      width: 18px;
      height: 18px;
      color: var(--primary);
    }

    .pitch-quick-stat-content {
      flex: 1;
    }

    .pitch-quick-stat-label {
      font-size: 12px;
      color: var(--text-muted);
    }

    .pitch-quick-stat-value {
      font-size: 14px;
      font-weight: 500;
    }

    .pitch-quick-stat-value a {
      color: var(--primary);
    }

    .pitch-quick-stat-value a:hover {
      text-decoration: underline;
    }

    /* Professional Status Banners (Themed) */
    .status-banner {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 0.92rem;
      font-weight: 500;
      border: 1px solid transparent;
      animation: bannerSlideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .status-banner-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* Warning / Not Submitted */
    .banner-warning {
      background: rgba(245, 158, 11, 0.03);
      border-color: rgba(245, 158, 11, 0.15);
      color: #eab308;
    }
    .banner-warning .status-banner-icon {
      background: rgba(245, 158, 11, 0.1);
      color: #f59e0b;
    }

    /* Info / Under Review */
    .banner-info {
      background: rgba(167, 139, 250, 0.03);
      border-color: rgba(167, 139, 250, 0.15);
      color: #a78bfa;
    }
    .banner-info .status-banner-icon {
      background: rgba(167, 139, 250, 0.1);
      color: #8b5cf6;
    }

    /* Danger / Rejected */
    .banner-danger {
      background: rgba(239, 68, 68, 0.03);
      border-color: rgba(239, 68, 68, 0.15);
      color: #f87171;
    }
    .banner-danger .status-banner-icon {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }

    .banner-content {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }

    .banner-text {
      display: flex;
      flex-direction: column;
    }

    .banner-text strong {
      font-weight: 700;
      margin-bottom: 1px;
    }

    .banner-text span {
      opacity: 0.8;
      font-size: 0.85rem;
    }

    .banner-action-link {
      background: rgba(255, 255, 255, 0.05);
      color: white;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      transition: all 0.23s;
      border: 1px solid rgba(255, 255, 255, 0.1);
      text-decoration: none;
      white-space: nowrap;
    }

    .banner-action-link:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.3);
    }

    @keyframes bannerSlideDown {
      from { transform: translateY(-15px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    /* ====================== RESPONSIVE ====================== */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        padding: 16px;
        width: 100%;
      }

      .mobile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        background: var(--sidebar-bg);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 50;
      }

      .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
      }

      .mobile-menu-btn svg {
        width: 20px;
        height: 20px;
        color: var(--text);
      }

      .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 99;
      }

      .sidebar-overlay.open {
        display: block;
      }
    }

    @media (min-width: 769px) {
      .mobile-header {
        display: none;
      }

      .sidebar-overlay {
        display: none !important;
      }
    }


    /* Modal Overlay */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    animation: fadeIn 0.3s ease;
}

/* Modal Content */
.modal-content {
    background: #1e293b; border: 1px solid #334155;
    padding: 32px; border-radius: 16px; width: 90%; max-width: 450px;
    text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    transform: scale(0.95); animation: popIn 0.3s forwards;
}

/* Animations */
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes popIn { to { transform: scale(1); } }

/* Icon */
.modal-icon-box {
    width: 64px; height: 64px; background: rgba(167, 139, 250, 0.1);
    color: #A78BFA; border-radius: 50%; margin: 0 auto 20px;
    display: flex; align-items: center; justify-content: center;
}
.modal-icon-box svg { width: 32px; height: 32px; }

/* Typography */
.modal-content h2 { color: #fff; margin-bottom: 10px; font-size: 1.5rem; }
.modal-content p { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 24px; }

/* Status Badge */
.modal-status-box { 
    background: #0f172a; padding: 12px; border-radius: 8px; 
    margin-bottom: 24px; color: #cbd5e1; font-size: 0.9rem;
}
.badge-pending { color: #f59e0b; font-weight: 600; }
.badge-review { color: #3b82f6; font-weight: 600; }

/* Buttons */
.modal-actions { display: flex; flex-direction: column; gap: 12px; }
.btn-verify {
    background: linear-gradient(135deg, #A78BFA, #8B5CF6);
    color: white; padding: 12px; border-radius: 8px; text-decoration: none;
    font-weight: 600; transition: transform 0.2s; display: block;
}
.btn-verify:hover { transform: translateY(-2px); }
.btn-cancel {
    background: transparent; border: 1px solid #334155; color: #94a3b8;
    padding: 12px; border-radius: 8px; cursor: pointer; width: 100%;
}
.btn-cancel:hover { color: #fff; border-color: #fff; }








/* Unique "Unlock Cost" Badge */
.bid-cost-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(139, 92, 246, 0.15); /* Light Purple Glass */
    border: 1px solid rgba(139, 92, 246, 0.3);
    backdrop-filter: blur(4px);
    padding: 6px 12px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #c4b5fd; /* Soft Purple Text */
    font-size: 12px;
    font-weight: 600;
    z-index: 10;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.bid-cost-badge:hover {
    background: rgba(139, 92, 246, 0.25);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
    cursor: help;
}

.bid-icon {
    width: 14px;
    height: 14px;
    fill: #a78bfa; /* Lightning Color */
}

    /* ====================== SECONDARY MARKET: SELL MODAL ====================== */
    .sell-modal {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 20px;
      width: 100%;
      max-width: 480px;
      padding: 0;
      overflow: hidden;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      position: relative;
    }

    .sell-modal-header {
      padding: 24px;
      background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.1));
      border-bottom: 1px solid var(--border);
      position: relative;
    }

    .sell-modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: transparent;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      padding: 4px;
      border-radius: 6px;
      transition: all 0.2s;
    }

    .sell-modal-close:hover {
      background: rgba(255,255,255,0.05);
      color: var(--text);
    }

    .sell-modal-body {
      padding: 24px;
    }

    .asset-info-card {
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sell-form-group {
      margin-bottom: 20px;
    }

    .sell-form-label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-muted);
      margin-bottom: 8px;
    }

    .sell-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .sell-input {
      width: 100%;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 16px;
      color: var(--text);
      font-size: 16px;
      font-weight: 600;
      outline: none;
      transition: border-color 0.2s;
    }

    .sell-input:focus {
      border-color: var(--primary);
    }

    .ai-price-suggestion {
      background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.1));
      border: 1px dashed rgba(139, 92, 246, 0.3);
      border-radius: 10px;
      padding: 12px;
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .profit-preview {
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    .profit-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .btn-list-sale {
      width: 100%;
      background: linear-gradient(135deg, #8b5cf6, #3b82f6);
      color: white;
      border: none;
      border-radius: 10px;
      padding: 14px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 24px;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-list-sale:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
    }
  </style>
</head>
<body>
  <div class="mobile-header">
    <div style="display: flex; align-items: center; gap: 12px;">
      <div class="logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
        </svg>
      </div>
      <span style="font-weight: 700;">SmartPitchHub</span>
    </div>
    <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" x2="20" y1="12" y2="12"/>
        <line x1="4" x2="20" y1="6" y2="6"/>
        <line x1="4" x2="20" y1="18" y2="18"/>
      </svg>
    </button>
  </div>

  <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>

  <div class="dashboard">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
          </svg>
        </div>
        <div class="logo-text">
          <h1>SmartPitchHub</h1>
          <p>Investor Portal</p>
        </div>
      </div>

      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg id="toggleIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6"/>
        </svg>
      </button>

      <nav class="sidebar-nav">
        <button class="nav-item active" data-page="dashboard" onclick="showPage('dashboard')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="7" height="9" x="3" y="3" rx="1"/>
            <rect width="7" height="5" x="14" y="3" rx="1"/>
            <rect width="7" height="9" x="14" y="12" rx="1"/>
            <rect width="7" height="5" x="3" y="16" rx="1"/>
          </svg>
          <span>Dashboard</span>
        </button>
        <button class="nav-item" data-page="explore" onclick="showPage('explore')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.3-4.3"/>
          </svg>
          <span>Explore Pitches</span>
        </button>

        <a class="nav-item" href="components/Bids/bids.php" target="_blank">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
            <path d="M13 5v2"/>
            <path d="M13 17v2"/>
            <path d="M13 11v2"/>
          </svg>
          <span>Buy Bids</span>
        </a>
        
        <?php $kyc_ok = (isset($kyc_status) && $kyc_status === 'approved'); ?>

        <button class="nav-item <?php echo !$kyc_ok ? 'locked-item' : ''; ?>" 
                <?php echo $kyc_ok ? 'data-page="investments" onclick="showPage(\'investments\')"' : 'onclick="alert(\'Verification Required: Please complete KYC to view your portfolio.\')"'; ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            <rect width="20" height="14" x="2" y="6" rx="2"/>
          </svg>
          <span>Portfolio <?php if(!$kyc_ok) echo '🔒'; ?></span>
        </button>

        <button class="nav-item <?php echo !$kyc_ok ? 'locked-item' : ''; ?>" 
                <?php echo $kyc_ok ? 'data-page="secondary" onclick="showPage(\'secondary\')"' : 'onclick="alert(\'Verification Required: Trade Board is only available for verified investors.\')"'; ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
          <span style="display: flex; align-items: center; gap: 5px;">
            Trade Board <?php if(!$kyc_ok) echo '🔒'; ?>
            <?php if($kyc_ok): ?>
            <span class="pulse-icon" style="width: 6px; height: 6px; background: var(--success); border-radius: 50%; border: 2px solid rgba(34, 197, 94, 0.3); animation: pulse-green 2s infinite;"></span>
            <?php endif; ?>
          </span>
        </button>

        <button class="nav-item" data-page="saved" onclick="showPage('saved')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
          </svg>
          <span>Saved Pitches</span>
        </button>

        <button class="nav-item <?php echo !$kyc_ok ? 'locked-item' : ''; ?>" 
                <?php echo $kyc_ok ? 'data-page="wallet" onclick="showPage(\'wallet\')"' : 'onclick="alert(\'Verification Required: Please verify your ID to manage your wallet.\')"'; ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
            <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
          </svg>
          <span>Wallet <?php if(!$kyc_ok) echo '🔒'; ?></span>
        </button>

        <button class="nav-item <?php echo !$kyc_ok ? 'locked-item' : ''; ?>" 
                <?php echo $kyc_ok ? 'data-page="transactions" onclick="showPage(\'transactions\')"' : 'onclick="alert(\'Verification Required: Verify ID to view transaction history.\')"'; ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
            <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
            <path d="M12 17.5v-11"/>
          </svg>
          <span>Transactions <?php if(!$kyc_ok) echo '🔒'; ?></span>
        </button>

        <button class="nav-item" onclick="window.location.href='../KYC/investor-kyc-status.php'">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="4" width="18" height="16" rx="3" />
    <circle cx="9" cy="10" r="2" />
    <line x1="15" y1="8" x2="17" y2="8" />
    <line x1="15" y1="12" x2="17" y2="12" />
    <line x1="7" y1="16" x2="17" y2="16" />
  </svg>
  <span>KYC Status</span>
</button>
        <button class="nav-item" onclick="window.location.href='../KYC/Investor-kyc.php'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            <path d="m9 12 2 2 4-4"/>
          </svg>
          <span>KYC Verification</span>
        </button>
        <button class="nav-item" data-page="settings" onclick="showPage('settings')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          <span>Settings</span>
        </button>
        <button class="nav-item" data-page="support" onclick="showPage('support')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <path d="M12 17h.01"/>
          </svg>
          <span>Support</span>
        </button>
      </nav>

      <div class="theme-toggle-container">
        <div class="theme-toggle">
          <div class="theme-toggle-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
              <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
            </svg>
            <span>Dark Mode</span>
          </div>
          <div class="theme-switch" id="themeSwitch" onclick="toggleTheme()"></div>
        </div>
      </div>

      <?php
      $k_status = $kyc_status ?? 'not_submitted';
      $k_label = 'KYC Not Submitted';
      $k_desc = 'Please complete your KYC.';
      $k_color_hsl = '230, 20%, 18%'; // Grey-ish
      $k_text_color = 'var(--text-muted)';
      $k_icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />';

      if ($k_status === 'approved') {
          $k_label = 'KYC Verified';
          $k_desc = 'You have full access.';
          $k_color_hsl = '142, 76%, 36%'; // Green
          $k_text_color = 'var(--success)';
          $k_icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />';
      } elseif ($k_status === 'under_review') {
          $k_label = 'KYC Under Review';
          $k_desc = 'Verification in progress.';
          $k_color_hsl = '38, 92%, 50%'; // Yellow
          $k_text_color = 'var(--warning)';
          $k_icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
      } elseif ($k_status === 'rejected') {
          $k_label = 'KYC Rejected';
          $k_desc = 'Please resubmit details.';
          $k_color_hsl = '0, 72%, 51%'; // Red
          $k_text_color = 'var(--destructive)';
          $k_icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />';
      }
      ?>

      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
          <div class="user-info">
            <p><?php echo htmlspecialchars($user_name); ?></p>
            <span>Premium Investor</span>
          </div>
        </div>
        <a href="../logout.php">
          <button class="logout-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" x2="9" y1="12" y2="12"/>
            </svg>
            <span>Sign Out</span>
          </button>
        </a>
      </div>
    </aside>

    <main class="main-content">
      <section class="page-section active" id="page-dashboard">
        <div class="page-header">
          <div class="page-header-flex">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?></h1>
            <span>👋</span>
          </div>
          <p>Here's what's happening with your investments today.</p>
        </div>

        <?php if ($kyc_status === 'not_submitted'): ?>
          <div class="status-banner banner-warning">
            <div class="status-banner-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="banner-content">
              <div class="banner-text">
                <strong>Identity Verification Required</strong>
                <span>Complete your KYC to unlock full investment features and start building your portfolio.</span>
              </div>
              <a href="../KYC/Investor-kyc.php" class="banner-action-link">Verify Now</a>
            </div>
          </div>
        <?php elseif ($kyc_status === 'under_review'): ?>
          <div class="status-banner banner-info">
            <div class="status-banner-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="banner-content">
              <div class="banner-text">
                <strong>KYC Under Review</strong>
                <span>Our team is currently verifying your documents. This usually takes 24-48 business hours.</span>
              </div>
              <a href="../KYC/investor-kyc-status.php" class="banner-action-link">Check Status</a>
            </div>
          </div>
        <?php elseif ($kyc_status === 'rejected'): ?>
          <div class="status-banner banner-danger">
            <div class="status-banner-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div class="banner-content">
              <div class="banner-text">
                <strong>Verification Rejected</strong>
                <span>There was an issue with your submission. Please review the details and resubmit.</span>
              </div>
              <a href="../KYC/Investor-kyc.php" class="banner-action-link">View Issues & Re-verify</a>
            </div>
          </div>
        <?php endif; ?>

        <div class="featured-banner">
          <div class="featured-content">
            <div class="featured-left">
              <div class="featured-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
                </svg>
              </div>
              <div class="featured-text">
                <h3>New Trending Pitches</h3>
                <p><?php echo count($all_pitches); ?> new startups match your investment criteria</p>
              </div>
            </div>
            <button class="btn btn-primary" onclick="showPage('explore')">
              Explore Now
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <path d="M5 12h14"/>
                <path d="m12 5 7 7-7 7"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary);">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
              </div>
              <div class="stat-trend <?php echo ($total_profit >= 0) ? 'positive' : 'negative'; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                  <path d="<?php echo ($total_profit >= 0) ? 'm5 12 7-7 7 7' : 'm19 12-7 7-7-7'; ?>"/>
                  <path d="M12 <?php echo ($total_profit >= 0) ? '19V5' : '5v14'; ?>"/>
                </svg>
                <?php echo number_format(abs($profit_percent), 1); ?>%
              </div>
            </div>
            <div class="stat-value">₹<?php echo number_format($portfolio_value); ?></div>
            <div class="stat-label">Portfolio Value</div>
            <div class="stat-subtitle">Current market worth</div>
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--success);">
                  <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                  <polyline points="16 7 22 7 22 13"></polyline>
                </svg>
              </div>
              <div class="stat-trend <?php echo ($total_profit >= 0) ? 'positive' : 'negative'; ?>">
                ₹<?php echo number_format(abs($total_profit)); ?>
              </div>
            </div>
            <div class="stat-value">₹<?php echo number_format($total_invested); ?></div>
            <div class="stat-label">Total Invested</div>
            <div class="stat-subtitle">Across <?php echo $active_investments; ?> holdings</div>
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #3b82f6;">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </div>
            </div>
            <div class="stat-value"><?php echo $active_investments; ?></div>
            <div class="stat-label">Active Holdings</div>
            <div class="stat-subtitle">Portfolio Assets</div>
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #f59e0b;">
                  <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
                  <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
                </svg>
              </div>
            </div>
            <div class="stat-value">₹<?php echo number_format($wallet_balance); ?></div>
            <div class="stat-label">Wallet Balance</div>
            <div class="stat-subtitle">Available to invest</div>
          </div>
        </div>

        <div class="section-title">
          <h2>Quick Actions</h2>
        </div>
        <div class="quick-actions">
          <button class="quick-action" onclick="showPage('explore')">
            <div class="quick-action-icon primary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.3-4.3"/>
              </svg>
            </div>
            <div class="quick-action-content">
              <p>Browse Pitches</p>
              <span>Find your next investment</span>
            </div>
          </button>
          <button class="quick-action" onclick="showPage('wallet')">
            <div class="quick-action-icon success">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14"/>
                <path d="M5 12h14"/>
              </svg>
            </div>
            <div class="quick-action-content">
              <p>Add Funds</p>
              <span>Top up your wallet</span>
            </div>
          </button>
          <button class="quick-action" onclick="showPage('investments')">
            <div class="quick-action-icon accent">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </div>
            <div class="quick-action-content">
              <p>View Portfolio</p>
              <span>Track your investments</span>
            </div>
          </button>
        </div>

        <div class="section-title">
          <h2>Recent Activity</h2>
          <button class="btn btn-ghost btn-sm">
            View All
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
              <path d="M5 12h14"/>
              <path d="m12 5 7 7-7 7"/>
            </svg>
          </button>
        </div>
        <div class="card-premium">
          <?php if(empty($recent_activity)): ?>
              <p style="text-align:center; color: var(--text-muted); padding: 20px;">No recent activity.</p>
          <?php else: ?>
              <?php foreach($recent_activity as $inv): ?>
              <div class="activity-item">
                <div class="activity-icon investment">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
                    <path d="M12 18V6"/>
                  </svg>
                </div>
                <div class="activity-content">
                  <div class="activity-title">Invested in <?php echo htmlspecialchars($inv['startup_name']); ?></div>
                 <div class="activity-desc">
    ₹<?php echo number_format($inv['amount']); ?> 
</div>
                </div>
                <div class="activity-time"><?php echo date("M d, Y", strtotime($inv['created_at'])); ?></div>
              </div>
              <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="page-section" id="page-explore">
        <div class="page-header">
          <h1>Explore Pitches</h1>
          <p>Discover promising startups and find your next investment opportunity.</p>
        </div>

        <div class="card-premium" style="margin-bottom: 24px;">
          <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <div class="search-container" style="flex: 1; min-width: 200px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.3-4.3"/>
              </svg>
              <input type="text" class="input input-search" placeholder="Search startups, industries..." id="pitchSearch" oninput="filterPitches()">
            </div>
            <div class="filter-pills">
              <button class="filter-pill active" data-industry="All" onclick="setIndustryFilter(this, 'All')">All</button>
              <button class="filter-pill" data-industry="FinTech" onclick="setIndustryFilter(this, 'FinTech')">FinTech</button>
              <button class="filter-pill" data-industry="HealthTech" onclick="setIndustryFilter(this, 'HealthTech')">HealthTech</button>
              <button class="filter-pill" data-industry="AI/ML" onclick="setIndustryFilter(this, 'AI/ML')">AI/ML</button>
              <button class="filter-pill" data-industry="EdTech" onclick="setIndustryFilter(this, 'EdTech')">EdTech</button>
            </div>
          </div>
        </div>

        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 16px;">
          Showing <span style="color: var(--text); font-weight: 600;" id="pitchCount">6</span> pitches
        </p>

        <div class="pitch-grid" id="pitchGrid">
          </div>
      </section>

      <section class="page-section" id="page-viewPitch">
        <button class="back-btn" onclick="showPage('explore')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m12 19-7-7 7-7"/>
            <path d="M19 12H5"/>
          </svg>
          Back to Pitches
        </button>

        <div class="card-premium" style="margin-bottom: 16px;">
          <div class="pitch-detail-header">
            <div class="pitch-detail-logo" id="pitchDetailLogo">T</div>
            <div class="pitch-detail-info">
              <div class="pitch-detail-title">
                <h1 id="pitchDetailName">TechFlow AI</h1>
                <span class="badge badge-primary" id="pitchDetailVerified">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                    <path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/>
                    <path d="m9 12 2 2 4-4"/>
                  </svg>
                  Verified
                </span>
              </div>
              <p class="pitch-detail-tagline" id="pitchDetailTagline">AI-powered workflow automation for enterprise teams</p>
              <div class="pitch-detail-tags">
                <span class="badge badge-primary" id="pitchDetailIndustry">AI/ML</span>
                <span class="badge badge-muted">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                  </svg>
                  Seed
                </span>
                <span class="badge badge-muted">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                    <circle cx="12" cy="10" r="3"/>
                  </svg>
                  San Francisco, CA
                </span>
              </div>
            </div>
            <button class="btn btn-outline btn-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="pitch-detail-grid">
          <div class="pitch-detail-main">
            <div class="card-premium">
              <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">About</h2>
              <p id="pitchDetailDesc" style="color: var(--text-muted); font-size: 14px; line-height: 1.7;">
                Description loading...
              </p>
            </div>

            <div class="card-premium">
              <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Key Highlights</h2>
              <div class="pitch-highlights">
                <div class="pitch-highlight">
                  <div class="pitch-highlight-icon">✓</div>
                  <span>High Growth Potential</span>
                </div>
                <div class="pitch-highlight">
                  <div class="pitch-highlight-icon">✓</div>
                  <span>Experienced Team</span>
                </div>
                <div class="pitch-highlight">
                  <div class="pitch-highlight-icon">✓</div>
                  <span>Scalable Tech</span>
                </div>
                <div class="pitch-highlight">
                  <div class="pitch-highlight-icon">✓</div>
                  <span>Large Market Opportunity</span>
                </div>
              </div>
            </div>
          </div>

          <div class="pitch-detail-sidebar">
            <div class="card-premium pitch-invest-card">
              <div class="pitch-invest-header">
                <p class="pitch-invest-label">Amount Raised</p>
                <p class="pitch-invest-value" id="pitchRaised">₹0</p>
                <p class="pitch-invest-goal">of <span id="pitchGoal">₹0</span> goal</p>
              </div>

              <div class="pitch-invest-progress">
                <div class="progress-bar-container" style="height: 10px;">
                  <div class="progress-bar" id="pitchProgressBar" style="width: 0%;"></div>
                </div>
                <p class="pitch-invest-progress-value" id="pitchProgressText">0% funded</p>
              </div>

              <div class="pitch-invest-stats">
                <div class="pitch-invest-stat">
                  <p class="pitch-invest-stat-value" id="pitchInvestors">0</p>
                  <p class="pitch-invest-stat-label">Investors</p>
                </div>
                <div class="pitch-invest-stat">
                  <p class="pitch-invest-stat-value" id="pitchSharePrice">₹0</p>
                  <p class="pitch-invest-stat-label">Share Price</p>
                </div>
                <div class="pitch-invest-stat">
                  <p class="pitch-invest-stat-value">30</p>
                  <p class="pitch-invest-stat-label">Days Left</p>
                </div>
              </div>

              <div class="pitch-invest-actions">
                <form method="POST" style="width: 100%;">
                    <input type="hidden" name="action" value="invest">
                    <input type="hidden" name="pitch_id" id="form-pitch-id">
                    <div style="margin-bottom: 10px;">
                        <input type="number" name="amount" class="input" placeholder="Enter Amount ($)" required min="100">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/>
                        <path d="M12 18V6"/>
                      </svg>
                      Invest Now
                    </button>
                </form>
                <button class="btn btn-outline" style="width: 100%;">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                    <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
                  </svg>
                  Contact Founder
                </button>
              </div>

              <div class="pitch-invest-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Minimum investment: ₹100
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="page-section" id="page-secondary">
        <div class="page-header">
          <div class="header-left">
            <h1 class="page-title">Secondary Trade Board</h1>
            <p class="page-subtitle">Buy shares directly from other investors at fixed prices.</p>
          </div>
          <div class="market-status-pill">
            <span class="pulse-icon"></span>
            Live Market
          </div>
        </div>

        <?php if(!empty($my_active_listings)): ?>
        <div class="my-listings-banner" style="background: var(--primary-glow); border: 1px solid var(--primary); padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong style="color: var(--primary)">Your Active Listings:</strong>
                <span style="margin-left: 10px; font-size: 0.9rem;">You have <?php echo count($my_active_listings); ?> open sell orders on the market.</span>
            </div>
            <button class="btn-view-my-trades" style="background: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 12px; border-radius: 6px; cursor: pointer;" onclick="showMyListings()">Manage My Orders</button>
        </div>
        <?php endif; ?>

        <div class="secondary-filters" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <div class="search-box" style="flex: 1; position: relative;">
                <input type="text" placeholder="Search startup or industry..." style="width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text);">
            </div>
            <select style="padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text);">
                <option>Price: Low to High</option>
                <option>Price: High to Low</option>
                <option>Newest First</option>
            </select>
        </div>

        <div class="trade-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
          <?php if(empty($market_listings)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: var(--bg-card); border-radius: 16px; border: 1px dashed var(--border);">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin-bottom: 15px;">
                    <circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
                </svg>
                <h3>No Active Listings</h3>
                <p style="color: var(--text-muted)">There are currently no shares available for sale in the secondary market.</p>
            </div>
          <?php else: ?>
            <?php foreach($market_listings as $listing): ?>
            <div class="trade-card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; transition: all 0.3s ease; position: relative; overflow: hidden;">
                <div class="trade-card-header" style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div style="width: 40px; height: 40px; background: var(--primary-glow); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: bold;">
                            <?php echo substr($listing['startup_name'], 0, 1); ?>
                        </div>
                        <div>
                            <h4 style="margin: 0;"><?php echo htmlspecialchars($listing['startup_name']); ?></h4>
                            <span style="font-size: 0.8rem; color: var(--text-muted)"><?php echo htmlspecialchars($listing['industry']); ?></span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: bold; color: var(--success); font-size: 1.1rem;">₹<?php echo number_format($listing['price_per_share'], 2); ?></div>
                        <span style="font-size: 0.75rem; color: var(--text-muted)">per share</span>
                    </div>
                </div>

                <div class="trade-card-body" style="background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; color: var(--text-muted)">Available Quantity:</span>
                        <strong style="font-size: 0.85rem;"><?php echo number_format($listing['shares_quantity']); ?> Shares</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-size: 0.85rem; color: var(--text-muted)">Total Value:</span>
                        <strong style="font-size: 0.85rem;">₹<?php echo number_format($listing['shares_quantity'] * $listing['price_per_share'], 2); ?></strong>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; font-size: 0.8rem;">
                    <div style="width: 20px; height: 20px; border-radius: 50%; background: #ccc; overflow: hidden;">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($listing['seller_name']); ?>&background=random" style="width:100%; height:100%;">
                    </div>
                    <span style="color: var(--text-muted)">Seller: <?php echo htmlspecialchars($listing['seller_name']); ?></span>
                </div>

                <button class="btn-buy-secondary" 
                  style="width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: transform 0.2s;"
                  onclick="openBuySecondaryModal(<?php echo htmlspecialchars(json_encode($listing)); ?>)">
                  Instant Buy
                </button>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="page-section" id="page-investments">
        <div class="page-header">
          <h1>My Investments</h1>
          <p>Track and manage your startup investment portfolio.</p>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 24px;">
          <div class="stat-card">
            <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px;">Net Asset Value</p>
            <p class="stat-value">₹<?php echo number_format($portfolio_value); ?></p>
            <p class="stat-subtitle">Total portfolio worth</p>
          </div>
          <div class="stat-card">
            <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px;">Unrealized P&L</p>
            <p class="stat-value <?php echo ($total_profit >= 0) ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($total_profit >= 0) ? '+' : ''; ?>₹<?php echo number_format(abs($total_profit)); ?>
            </p>
            <p class="stat-subtitle"><?php echo number_format($profit_percent, 1); ?>% Overall ROI</p>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 24px; align-items: start;">
            <div class="card-premium" style="margin-bottom: 0;">
              <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 0;">
                <h2 style="font-size: 16px; font-weight: 600;">Asset Holdings</h2>
              </div>
              <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Startup</th>
                  <th>Shares</th>
                  <th>Avg Price</th>
                  <th>Live Price</th>
                  <th>P&L</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($my_investments)): ?>
                    <tr><td colspan="6" style="text-align:center;">No assets in portfolio.</td></tr>
                <?php else: ?>
                    <?php foreach($my_investments as $inv): 
                        $pl = ($inv['current_price'] - $inv['avg_price']) * $inv['total_shares'];
                        $pl_percent = ($inv['avg_price'] > 0) ? (($inv['current_price'] - $inv['avg_price']) / $inv['avg_price']) * 100 : 0;
                        
                        // Smart Locking: Allow selling if round is finalized (released) OR if the round is over
                        $is_released = (isset($inv['payout_status']) && $inv['payout_status'] === 'released');
                        $is_round_over = (isset($inv['round_status']) && $inv['round_status'] === 'completed') || (isset($inv['expiry_date']) && strtotime($inv['expiry_date']) < time());
                        $can_sell = $is_released || $is_round_over;
                    ?>
                    <tr>
                      <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                          <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--primary-glow), rgba(129, 140, 248, 0.15)); display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 600; font-size: 14px;">
                            <?php echo strtoupper($inv['startup_name'][0]); ?>
                          </div>
                          <div>
                            <span style="font-weight: 500; display: block;"><?php echo htmlspecialchars($inv['startup_name']); ?></span>
                            <?php if ($can_sell && !$is_released): ?>
                                <span style="font-size: 9px; padding: 2px 6px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 4px; text-transform: uppercase;">Round Over</span>
                            <?php elseif (!$is_released): ?>
                                <span style="font-size: 9px; padding: 2px 6px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 4px; text-transform: uppercase;">In Escrow</span>
                            <?php else: ?>
                                <span style="font-size: 9px; padding: 2px 6px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 4px; text-transform: uppercase;">Asset Holding</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td style="font-weight: 500;"><?php echo number_format($inv['total_shares']); ?></td>
                      <td style="color: var(--text-muted);">₹<?php echo number_format($inv['avg_price'], 2); ?></td>
                      <td style="font-weight: 600;">₹<?php echo number_format($inv['current_price'], 2); ?></td>
                      <td>
                        <span class="<?php echo ($pl >= 0) ? 'text-success' : 'text-danger'; ?>" style="font-weight: 600; font-size: 13px;">
                            <?php echo ($pl >= 0) ? '+' : ''; ?>₹<?php echo number_format(abs($pl)); ?>
                            <br>
                            <small style="font-weight: 400;"><?php echo number_format($pl_percent, 1); ?>%</small>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <div style="display: flex; gap: 4px; justify-content: flex-end;">
                            <button class="btn btn-ghost btn-sm" title="View Details">
                              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                              </svg>
                            </button>
                            <?php if ($can_sell): ?>
                                <button class="btn btn-outline btn-sm" 
                                        style="padding: 4px 8px; font-size: 11px; border-color: var(--primary); color: var(--primary);" 
                                        onclick="openSellModal('<?php echo addslashes($inv['startup_name']); ?>', <?php echo $inv['total_shares']; ?>, <?php echo $inv['avg_price']; ?>, <?php echo $inv['current_price']; ?>, <?php echo $inv['pitch_id']; ?>)">
                                        SELL
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline btn-sm" 
                                        style="padding: 4px 8px; font-size: 11px; border-color: var(--border); color: var(--text-muted); cursor: not-allowed; opacity: 0.6;" 
                                        onclick="alert('🚫 Smart Escrow Protection: Your shares are currently locked because the funding round is still LIVE. You can start selling in the secondary market once the round is completed or reaches its expiry date.')">
                                        LOCKED
                                </button>
                            <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- AI Portfolio Advisor -->
        <div class="card-premium" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%); border: 1px solid rgba(139, 92, 246, 0.2);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <div style="padding: 6px; background: var(--primary); border-radius: 8px; color: white;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 2a10 10 0 0 1 10 10h-10V2z"/><path d="M12 12L2.2 9a10 10 0 0 1 19.6 0L12 12z"/>
                    </svg>
                </div>
                <h3 style="font-size: 14px; font-weight: 600;">AI Portfolio Analyst</h3>
            </div>
            <div id="ai-portfolio-insight" style="font-size: 13px; line-height: 1.6; color: var(--text-muted);">
                <?php if(empty($my_investments)): ?>
                    Invest in your first startup to see AI-driven portfolio insights and risk analysis.
                <?php else: ?>
                    Analyzing your <?php echo $active_investments; ?> holdings... <br>
                    <span style="display: inline-block; margin-top: 8px; color: var(--text-main);">
                        "Your portfolio is heavily weighted in <b><?php echo htmlspecialchars($my_investments[0]['industry']); ?></b>. Consider diversifying into Fintech or SaaS for better risk management."
                    </span>
                <?php endif; ?>
            </div>
            <button class="btn btn-ghost btn-sm" style="width: 100%; margin-top: 16px; border: 1px dashed var(--border);">Get Detailed Risk Report</button>
        </div>
      </div>
    </section>

      <section class="page-section" id="page-saved">
        <div class="page-header">
          <h1>Saved Pitches</h1>
          <p>Your bookmarked startups for quick access.</p>
        </div>
        
        <div class="stat-card" style="display: inline-block; margin-bottom: 24px;">
          <p style="font-size: 14px; color: var(--text-muted);">Total Saved</p>
          <p class="stat-value">0</p>
        </div>

        <div class="pitch-grid" id="savedPitchesList">
          <p style="grid-column: 1 / -1; text-align: center; color: var(--text-muted);">Loading saved pitches...</p>
        </div>
      </section>

      <section class="page-section" id="page-wallet">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h1>Ultimate Smart Escrow</h1>
            <p>Institutional-grade capital management and audit trail.</p>
          </div>
          <div style="text-align: right;">
            <div style="background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.2); padding: 8px 16px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2.5">
                <circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>
              </svg>
              <span style="font-size: 13px; font-weight: 600; color: #a78bfa;"><?php echo number_format($bid_balance); ?> Bids Available</span>
            </div>
          </div>
        </div>

        <div class="wallet-grid">
          <!-- SMART ESCROW CARD -->
          <div class="smart-wallet-card">
            <div class="wallet-mesh"></div>
            
            <div class="wallet-brand-chip">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              </svg>
              <span>Verified Escrow</span>
            </div>

            <div class="wallet-status-badge">
              <div class="wallet-status-dot"></div>
              Live Protection Active
            </div>

            <div class="wallet-balance-main">
              <p class="wallet-balance-label">Total Account Value</p>
              <h2 class="wallet-balance-value">₹<?php echo number_format($wallet_balance + $escrow_balance, 2); ?></h2>
              
              <div class="wallet-breakdown">
                <div class="breakdown-item">
                  <div class="breakdown-label">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    In Escrow
                  </div>
                  <div class="breakdown-value">₹<?php echo number_format($escrow_balance, 2); ?></div>
                </div>
                <div class="breakdown-item">
                  <div class="breakdown-label" style="color: #4ade80;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Available
                  </div>
                  <div class="breakdown-value" style="color: #4ade80;">₹<?php echo number_format($wallet_balance, 2); ?></div>
                </div>
                <div class="breakdown-item">
                  <div class="breakdown-label" style="color: #a78bfa;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>
                    </svg>
                    Bid Tokens
                  </div>
                  <div class="breakdown-value" style="color: #a78bfa;"><?php echo number_format($bid_balance); ?> Bids</div>
                </div>
              </div>

              <div class="wallet-actions-dock">
                <button class="btn-action-premium" onclick="showAddFundsModal()">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 5v14M5 12h14"></path>
                  </svg>
                  Deposit
                </button>
                <button class="btn-action-premium secondary" onclick="alert('Withdrawals are processed every Friday.')">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M13 18l6-6-6-6"></path>
                  </svg>
                  Withdraw
                </button>
                <button class="btn-action-premium bid-accent" onclick="window.open('components/Bids/bids.php', '_blank')">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>
                  </svg>
                  Buy Bids
                </button>
              </div>
            </div>
          </div>

          <!-- TRANSACTION LEDGER -->
          <div class="ledger-card">
            <div class="ledger-header">
              <div>
                <h3 style="color: #ffffff; font-size: 20px; font-weight: 700; margin-bottom: 4px;">Financial Ledger</h3>
                <p style="color: #64748b; font-size: 12px;">Authenticated transaction history</p>
              </div>
              <button class="btn-view-all" style="background:none; border:none; cursor:pointer;" onclick="showPage('transactions')">
                View All
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
              </button>
            </div>
            <div class="txn-scroller" style="flex: 1; overflow-y: auto; max-height: 380px;">
              <?php if (empty($recent_txns)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #475569;">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom: 16px; opacity: 0.2;"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                  <p>No transactions recorded yet.</p>
                </div>
              <?php else: foreach ($recent_txns as $txn): 
                $is_credit = ($txn['txn_type'] == 'credit');
              ?>
                <div class="txn-item" style="display: flex; align-items: center; gap: 16px; padding: 12px; border-radius: 16px; margin-bottom: 8px;">
                  <div class="txn-icon <?php echo $is_credit ? 'credit' : 'debit'; ?>">
                    <?php if($is_credit): ?>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12l7-7 7 7"/>
                      </svg>
                    <?php else: ?>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 19V5M5 12l7 7 7-7"/>
                      </svg>
                    <?php endif; ?>
                  </div>
                  <div class="txn-info" style="flex: 1;">
                    <p class="txn-title"><?php echo htmlspecialchars($txn['source'] ?? 'Transaction'); ?></p>
                    <p class="txn-meta"><?php echo date('d M, h:i A', strtotime($txn['created_at'])); ?></p>
                  </div>
                  <div style="text-align: right;">
                    <div style="font-size: 15px; font-weight: 700; color: <?php echo $is_credit ? '#4ade80' : '#f87171'; ?>;">
                        <?php echo ($is_credit ? '+' : '-') . ' ₹' . number_format($txn['amount'], 0); ?>
                    </div>
                    <div style="font-size: 9px; color: #475569; text-transform: uppercase;">Success</div>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <div class="ledger-footer" style="margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 6px; font-size: 10px; color: #475569;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  PCI-DSS
                </div>
                <div style="display: flex; align-items: center; gap: 6px; font-size: 10px; color: #475569;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                  SEBI
                </div>
            </div>
          </div>
        </div>
      </section>
      
      <section class="page-section" id="page-transactions">
          <div class="page-header"><h1>Transactions</h1><p>Transaction history coming soon.</p></div>
      </section>
      <section class="page-section" id="page-messages">
          <div class="page-header"><h1>Messages</h1><p>Messaging feature coming soon.</p></div>
      </section>
      <section class="page-section" id="page-settings">
          <div class="page-header"><h1>Settings</h1><p>Account settings coming soon.</p></div>
      </section>
      <section class="page-section" id="page-support">
          <div class="page-header"><h1>Support</h1><p>Support center coming soon.</p></div>
      </section>
      
    </main>
  </div>

  <script>
    // ====================== DATA & CONFIGURATION ======================
    // Inject PHP data into JavaScript
    const pitches = <?php echo json_encode($js_pitches); ?>;
    
    // Saved Pitch IDs (Converted to Set for fast lookup)
    const savedPitchIds = new Set(<?php echo json_encode($saved_ids); ?>);

    // NEW: Get KYC Status from PHP for the Lock System
    const currentKycStatus = "<?php echo isset($kyc_status) ? $kyc_status : 'not_submitted'; ?>";
    
    let currentIndustryFilter = 'All';

    // ====================== KYC RESTRICTION LOGIC (NEW) ======================
    function checkKycLock(event) {
        // 1. If approved, allow the click to happen normally
        if (currentKycStatus === 'approved') return true;

        // 2. If NOT approved, stop the click and show the popup
        if(event) {
            event.preventDefault(); 
            event.stopPropagation();
        }
        showKycModal();
        return false;
    }

    function showKycModal() {
        const modal = document.getElementById('kyc-lock-modal');
        const statusText = document.getElementById('modal-status-text');
        
        // Dynamic text based on status
        if (currentKycStatus === 'under_review') {
            statusText.innerHTML = 'Status: <span style="color:#3b82f6">Under Review</span> (Please wait for admin approval)';
        } else if (currentKycStatus === 'rejected') {
            statusText.innerHTML = 'Status: <span style="color:#ef4444">Rejected</span> (Please fix issues)';
        } else {
            statusText.innerHTML = 'Status: <span style="color:#f59e0b">Not Submitted</span>';
        }

        modal.style.display = 'flex';
    }

    function closeKycModal() {
        document.getElementById('kyc-lock-modal').style.display = 'none';
    }

    // ====================== THEME ======================
    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    }

    function initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    }

    // ====================== SIDEBAR ======================
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const toggleIcon = document.getElementById('toggleIcon');
        sidebar.classList.toggle('collapsed');
        
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.innerHTML = '<path d="m9 18 6-6-6-6"/>';
        } else {
            toggleIcon.innerHTML = '<path d="m15 18-6-6 6-6"/>';
        }
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    }

    // ====================== STEP 4: SECONDARY MARKET JS ======================
    let activeHolding = null;

    function openSellModal(startupName, totalShares, avgPrice, currentPrice, pitchId) {
        activeHolding = { startupName, totalShares, avgPrice, currentPrice, pitchId };
        
        document.getElementById('sell-startup-name').textContent = startupName;
        document.getElementById('sell-startup-icon').textContent = startupName[0].toUpperCase();
        document.getElementById('sell-holding-info').textContent = `You own ${totalShares.toLocaleString()} shares (Avg ₹${parseFloat(avgPrice).toFixed(2)})`;
        document.getElementById('sell-pitch-id').value = pitchId;
        
        // Suggest current market price
        const suggested = parseFloat(currentPrice);
        document.getElementById('sell-price').value = suggested.toFixed(2);
        document.getElementById('ai-suggested-price').textContent = `₹${suggested.toFixed(2)}`;
        
        document.getElementById('sell-asset-modal').style.display = 'flex';
        
        // Reset calculations
        updateSellCalculations();
    }

    function closeSellModal() {
        document.getElementById('sell-asset-modal').style.display = 'none';
        activeHolding = null;
    }

    function setSellQty(ratio) {
        if(!activeHolding) return;
        const qty = Math.floor(activeHolding.totalShares * ratio);
        document.getElementById('sell-quantity').value = qty;
        updateSellCalculations();
    }

    function updateSellCalculations() {
        if(!activeHolding) return;
        const qty = parseFloat(document.getElementById('sell-quantity').value) || 0;
        const price = parseFloat(document.getElementById('sell-price').value) || 0;
        
        // Validation: Limit quantity
        if (qty > activeHolding.totalShares) {
            document.getElementById('sell-quantity').value = activeHolding.totalShares;
            return updateSellCalculations();
        }

        const revenue = qty * price;
        const cost = qty * activeHolding.avgPrice;
        const profit = revenue - cost;
        const roi = activeHolding.avgPrice > 0 ? (profit / cost) * 100 : 0;
        
        // 1. Update UI Labels
        document.getElementById('est-revenue').textContent = `₹${revenue.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        const profitEl = document.getElementById('est-profit');
        
        if (qty > 0) {
            profitEl.innerHTML = `${profit >= 0 ? 'Profit: +' : 'Loss: '}₹${Math.abs(profit).toLocaleString(undefined, {minimumFractionDigits: 2})} <span style="font-size: 10px; opacity: 0.8;">(${roi.toFixed(1)}% ROI)</span>`;
            profitEl.style.color = profit >= 0 ? '#10b981' : '#ef4444';
        } else {
            profitEl.textContent = '₹0.00';
            profitEl.style.color = 'var(--text-muted)';
        }

        // 2. Smart Price Guard
        const priceWarning = document.getElementById('price-guard-warning') || createPriceWarning();
        if (price > activeHolding.currentPrice * 2) {
            priceWarning.style.display = 'block';
            priceWarning.innerHTML = `<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> High Price! 200% above market. Might not sell.`;
        } else {
            priceWarning.style.display = 'none';
        }
    }

    function createPriceWarning() {
        const warn = document.createElement('div');
        warn.id = 'price-guard-warning';
        warn.style.cssText = 'font-size: 10px; color: #f59e0b; margin-top: 4px; display: flex; align-items: center;';
        document.querySelector('.ai-price-suggestion').after(warn);
        return warn;
    }

    // Event listeners for calculations
    document.addEventListener('DOMContentLoaded', function() {
        const sellQtyInput = document.getElementById('sell-quantity');
        const sellPriceInput = document.getElementById('sell-price');
        const sellForm = document.getElementById('sell-listing-form');

        if(sellQtyInput) sellQtyInput.addEventListener('input', updateSellCalculations);
        if(sellPriceInput) sellPriceInput.addEventListener('input', updateSellCalculations);

        if(sellForm) {
            sellForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.btn-list-sale');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Listing on Market...';

                fetch('api_secondary_market.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Listing Active!',
                            text: 'Your shares are now live on the secondary market. Other investors can now see and buy your offer.',
                            confirmButtonColor: 'var(--primary)'
                        }).then(() => {
                            window.location.reload();
                        });
                        closeSellModal();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Listing Failed',
                            text: data.message,
                            confirmButtonColor: 'var(--primary)'
                        });
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'List Shares for Sale';
                    }
                })
                .catch(err => {
                    console.error('Listing error:', err);
                    Swal.fire('Error', 'Something went wrong with the market connection.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'List Shares for Sale';
                });
            });
        }
    });

    // ====================== STEP 4: BUY SECONDARY ASSET JS ======================
    let activeListing = null;

    function openBuySecondaryModal(listing) {
        activeListing = listing;
        
        document.getElementById('buy-startup-name').textContent = listing.startup_name;
        document.getElementById('buy-startup-icon').textContent = listing.startup_name[0].toUpperCase();
        document.getElementById('buy-seller-name').textContent = listing.seller_name;
        document.getElementById('buy-price-display').textContent = `₹${parseFloat(listing.price_per_share).toFixed(2)}`;
        document.getElementById('buy-max-qty').textContent = `${parseInt(listing.shares_quantity).toLocaleString()} Shares`;
        document.getElementById('buy-order-id').value = listing.id;
        document.getElementById('buy-quantity').max = listing.shares_quantity;
        document.getElementById('buy-quantity').value = '';
        
        document.getElementById('buy-secondary-modal').style.display = 'flex';
        updateBuyCalculations();
    }

    function closeBuySecondaryModal() {
        document.getElementById('buy-secondary-modal').style.display = 'none';
        activeListing = null;
    }

    function setBuyMax() {
        if(!activeListing) return;
        document.getElementById('buy-quantity').value = activeListing.shares_quantity;
        updateBuyCalculations();
    }

    function updateBuyCalculations() {
        if(!activeListing) return;
        const qty = parseInt(document.getElementById('buy-quantity').value) || 0;
        const price = parseFloat(activeListing.price_per_share);
        
        const value = qty * price;
        const fee = value * 0.005; // 0.5% fee
        const total = value + fee;
        
        document.getElementById('buy-total-value').textContent = `₹${value.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        document.getElementById('buy-fee').textContent = `₹${fee.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        document.getElementById('buy-total-payable').textContent = `₹${total.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const buyQtyInput = document.getElementById('buy-quantity');
        const buyForm = document.getElementById('buy-trade-form');

        if(buyQtyInput) buyQtyInput.addEventListener('input', updateBuyCalculations);

        if(buyForm) {
            buyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.btn-confirm-buy');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing Transaction...';

                fetch('api_secondary_buy.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        alert('Success: Transaction completed! Shares have been transferred to your portfolio.');
                        closeBuySecondaryModal();
                        window.location.reload();
                    } else {
                        alert('Trade Error: ' + data.message);
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Confirm & Secure Transfer';
                    }
                })
                .catch(err => {
                    console.error('Trade error:', err);
                    alert('Connection failure.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Confirm & Secure Transfer';
                });
            });
        }
    });

    function showMyListings() {
        // Feature: Show a filtered view or modal with user's own listings
        alert('Showing your active listings. You can cancel them from the Manage Portfolio section (Coming Soon)');
    }

    // ====================== NAVIGATION ======================
    function showPage(pageId) {
        // Hide all pages
        document.querySelectorAll('.page-section').forEach(page => {
            page.classList.remove('active');
        });

        // Show selected page
        const targetPage = document.getElementById('page-' + pageId);
        if (targetPage) {
            targetPage.classList.add('active');
        }

        // Update nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('data-page') === pageId) {
                item.classList.add('active');
            }
        });
        
        // Refresh saved list if opening that tab
        if(pageId === 'saved') {
            refreshSavedList(); 
        }

        // --- ADDED FIX: Ensure pitches render when navigating to Explore ---
        if(pageId === 'explore') {
            renderPitchCards();
        }
    }

    // ====================== RENDER: EXPLORE PITCHES ======================
    function renderPitchCards() {
        console.log("Rendering pitches:", pitches);
        const grid = document.getElementById('pitchGrid');
        if (!grid) {
            console.error("pitchGrid element not found!");
            return;
        }

        const searchQuery = (document.getElementById('pitchSearch')?.value || "").toLowerCase();
        
        const filteredPitches = (pitches || []).filter(pitch => {
            const name = (pitch.name || "").toLowerCase();
            const tagline = (pitch.tagline || "").toLowerCase();
            const industry = pitch.industry || "Other";
            
            const matchesSearch = name.includes(searchQuery) || tagline.includes(searchQuery);
            const matchesIndustry = currentIndustryFilter === 'All' || industry === currentIndustryFilter;
            return matchesSearch && matchesIndustry;
        });

        const countEl = document.getElementById('pitchCount');
        if(countEl) countEl.textContent = filteredPitches.length;

        if (filteredPitches.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">No startups found matching your criteria.</div>';
            return;
        }

        let html = '';
        filteredPitches.forEach(pitch => {
            const fundingGoal = (pitch.fundingGoal || "₹0").toString().replace('$', '₹');
            const sharePrice = (pitch.sharePrice || "₹0").toString();
            const initial = (pitch.name || "?").charAt(0).toUpperCase();
            const bids = pitch.bids || 0;
            const investors = pitch.investors || 0;
            const progress = pitch.progress || 0;
            const score = pitch.warzoneScore || 0;
            const expiry = pitch.expiryDate;

            // Timer Calculation
            let timeLabel = "EXPIRED";
            if(expiry) {
                const diff = new Date(expiry) - new Date();
                if(diff > 0) {
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    timeLabel = days > 0 ? days + "d left" : "Ends today";
                }
            }

            html += `
                <div class="pitch-card" style="position: relative; display: block;">
                    <div class="bid-cost-badge" title="Requires ${bids} Bids to Unlock">
                        <svg class="bid-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" clip-rule="evenodd" />
                        </svg>
                        <span>${bids} Bids</span>
                    </div>

                    <div style="position:absolute; top: 12px; right: 12px; display: flex; flex-direction: column; gap: 4px; align-items: flex-end; z-index: 5;">
                        <div class="badge badge-success" style="font-size: 10px; background: hsla(142, 72%, 29%, 0.1); border: 1px solid var(--success); color: var(--success);">
                            AI Trust: ${score}%
                        </div>
                        <div class="badge" style="font-size: 10px; background: hsla(217, 91%, 60%, 0.1); border: 1px solid var(--primary); color: var(--primary);">
                            ${timeLabel}
                        </div>
                    </div>

                    <div class="pitch-header" onclick="location.href='../Pitches/view-pitch.php?id=${pitch.id}'" style="cursor: pointer;">
                        <div class="pitch-logo">${initial}</div>
                        <div class="pitch-info">
                            <div class="pitch-title">
                                <h3 style="margin:0; font-size: 16px;">${pitch.name}</h3>
                            </div>
                            <p class="pitch-tagline" style="font-size: 12px; color: var(--text-muted);">${pitch.tagline}</p>
                        </div>
                    </div>
                    
                    <div class="pitch-tags" style="margin: 12px 0;">
                        <span class="badge badge-primary">${pitch.industry}</span>
                        <span class="badge badge-muted">${investors} investors</span>
                    </div>
                    
                    <div class="pitch-progress">
                        <div class="pitch-progress-header" style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px;">
                            <span class="pitch-progress-label">Funding Progress</span>
                            <span class="pitch-progress-value">${progress}%</span>
                        </div>
                        <div class="progress-bar-container" style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                            <div class="progress-bar" style="width: ${progress}%; height: 100%; background: var(--primary);"></div>
                        </div>
                    </div>
                    
                    <div class="pitch-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 15px 0; text-align: center;">
                        <div class="pitch-stat">
                            <p class="pitch-stat-value" style="font-weight: 600; font-size: 13px; margin: 0;">${fundingGoal}</p>        
                            <p class="pitch-stat-label" style="font-size: 10px; color: var(--text-muted); margin: 0;">Goal</p>
                        </div>
                        <div class="pitch-stat">
                            <p class="pitch-stat-value" style="font-weight: 600; font-size: 13px; margin: 0;">${sharePrice}</p>
                            <p class="pitch-stat-label" style="font-size: 10px; color: var(--text-muted); margin: 0;">Price/Share</p>
                        </div>
                        <div class="pitch-stat">
                            <p class="pitch-stat-value" style="font-weight: 600; font-size: 13px; margin: 0;">${investors}</p>
                            <p class="pitch-stat-label" style="font-size: 10px; color: var(--text-muted); margin: 0;">Backers</p>
                        </div>
                    </div>
                    
                    <div class="pitch-actions">
                        <a href="../Pitches/view-pitch.php?id=${pitch.id}" class="btn btn-primary" style="text-decoration: none; display: block; width: 100%; padding: 10px; font-weight: 600; text-align: center;">
                            View Details
                        </a>
                    </div>
                </div>
            `;
        });
        grid.innerHTML = html;
    }
    // ====================== API: SAVED PITCHES ======================
    function refreshSavedList() {
        const container = document.getElementById('savedPitchesList');
        const countEl = document.querySelector('#page-saved .stat-value');

        container.innerHTML = '<p style="text-align:center; color: var(--text-muted); grid-column: 1 / -1;">Loading your saved pitches...</p>';

        fetch('../Pitches/get_saved_ids.php')
        .then(response => response.json())
        .then(ids => {
            savedPitchIds.clear();
            ids.forEach(id => savedPitchIds.add(id));

            if(countEl) countEl.textContent = ids.length;

            if (ids.length === 0) {
                container.innerHTML = '<p style="text-align:center; color: var(--text-muted); padding: 40px; grid-column: 1 / -1;">You haven\'t saved any pitches yet.</p>';
                return;
            }

            container.innerHTML = '';
            ids.forEach(id => {
                fetchAndRenderSavedPitch(id);
            });
        })
        .catch(err => {
            console.error('Fetch Error:', err);
            container.innerHTML = '<p style="text-align:center; color: var(--destructive); grid-column: 1 / -1;">Error loading saved list.</p>';
        });
    }

    function fetchAndRenderSavedPitch(id) {
        fetch(`../Pitches/get_pitch_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if(data.error) {
                console.error("Error loading pitch " + id, data.error);
                return;
            }
            renderSingleSavedCard(data);
        })
        .catch(err => console.error("API Error:", err));
    }

    function renderSingleSavedCard(pitch) {
        const container = document.getElementById('savedPitchesList');
        
        const name = pitch.name || "Unknown Startup";
        const tagline = pitch.tagline || (pitch.description ? pitch.description.substring(0, 60) + "..." : "No description available"); 
        const industry = pitch.category || "General"; 
        const goal = pitch.funding || "₹0"; 
        const sharePrice = pitch.share_price || "0";
        const progress = pitch.progress || 0;
        const id = pitch.id;
        const imgUrl = pitch.logo;

        const cardHtml = `
            <div class="pitch-card">
                <div class="pitch-header">
                ${imgUrl && (imgUrl.startsWith('http') || imgUrl.startsWith('data'))
                    ? `<img src="${imgUrl}" class="pitch-logo" style="object-fit:cover;">` 
                    : `<div class="pitch-logo">${name.charAt(0)}</div>`
                }
                <div class="pitch-info">
                    <div class="pitch-title">
                    <h3>${name}</h3>
                    </div>
                    <p class="pitch-tagline">${tagline}</p>
                </div>
                </div>
                <div class="pitch-tags">
                <span class="badge badge-primary">${industry}</span>
                <span class="badge badge-success">Saved</span>
                </div>

                <div class="pitch-progress" style="margin: 12px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; color: var(--text-muted);">
                        <span>Funding Progress</span>
                        <span>${progress}%</span>
                    </div>
                    <div style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                        <div style="width: ${progress}%; height: 100%; background: var(--primary); transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <div class="pitch-stats">
                <div class="pitch-stat">
                    <p class="pitch-stat-value">${goal}</p>
                    <p class="pitch-stat-label">Goal</p>
                </div>
                <div class="pitch-stat">
                    <p class="pitch-stat-value">₹${sharePrice}</p>
                    <p class="pitch-stat-label">Price</p>
                </div>
                </div>
                <div class="pitch-actions" style="margin-top:15px; display:flex; gap:10px;">
                <a href="../Pitches/view-pitch.php?id=${id}" class="btn btn-primary" style="flex:1; text-decoration:none; text-align:center;">View</a>
                
                <button onclick="removeSavedPitch(${id})" class="btn btn-outline" style="padding: 0 12px; border-color: var(--destructive); color: var(--destructive);" title="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M3 6h18"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', cardHtml);
    }

    // ====================== API: REMOVE SAVED PITCH ======================
    function removeSavedPitch(id) {
        if(!confirm('Remove this pitch from your saved list?')) return;

        fetch('../Pitches/toggle_save_pitch.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pitch_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                refreshSavedList();
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(err => console.error("Error unsaving:", err));
    }

    // ====================== FILTER LOGIC ======================
    function setIndustryFilter(btn, industry) {
        currentIndustryFilter = industry;
        document.querySelectorAll('.filter-pill').forEach(pill => pill.classList.remove('active'));
        btn.classList.add('active');
        renderPitchCards();
    }

    function filterPitches() {
        renderPitchCards();
    }

    // ====================== INITIALIZE ======================
    // ====================== INITIALIZE ======================
    document.addEventListener('DOMContentLoaded', function() {
        initTheme();
        renderPitchCards();
        refreshSavedList(); 

        // 1. Handle URL Navigation
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page');
        if (page) { showPage(page); } else { showPage('dashboard'); }

        // ----------------------------------------------------
        // 2. AUTO-POPUP LOGIC (The Fix)
        // ----------------------------------------------------
        // If KYC is NOT submitted, show the popup immediately on load
        if (currentKycStatus === 'not_submitted') {
            showKycModal();
        } 
        // Optional: If you want to remind them if they are rejected
        else if (currentKycStatus === 'rejected') {
            showKycModal();
        }

        // 3. Global Button Lock (For clicks)
        document.body.addEventListener('click', function(e) {
            const restrictedBtn = e.target.closest('.restricted-action');
            if (restrictedBtn) {
                checkKycLock(e);
            }
        });
    });
</script>
  <!-- Step 4: Secondary Market Sell Modal -->
  <!-- ADD FUNDS ESCROW MODAL -->
  <div id="add-funds-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 480px;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div style="width: 40px; height: 40px; background: rgba(99, 102, 241, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
          </div>
          <div>
            <h2 style="margin: 0; font-size: 18px;">Escrow Top-up</h2>
            <p style="margin: 0; font-size: 12px; color: var(--text-muted);">Add funds to your secure wallet</p>
          </div>
        </div>
        <button class="close-btn" onclick="closeAddFundsModal()">×</button>
      </div>
      
      <div style="padding: 24px;">
        <div style="background: rgba(99, 102, 241, 0.05); border: 1px dashed rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 16px; margin-bottom: 24px;">
          <p style="font-size: 12px; margin: 0; color: #818cf8; font-weight: 600;">ESCROW PROTECTION ACTIVE</p>
          <p style="font-size: 11px; margin: 4px 0 0; color: var(--text-muted);">Funds are kept in a SEBI-compliant escrow account until investment is finalized.</p>
        </div>

        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Enter Amount (₹)</label>
        <div style="position: relative; margin-bottom: 20px;">
          <span style="position: absolute; left: 16px; top: 12px; color: var(--text-muted); font-weight: 600;">₹</span>
          <input type="number" id="fund-amount" placeholder="50,000" style="width: 100%; padding: 12px 16px 12px 40px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-hover); color: var(--text); font-size: 20px; font-weight: 700;">
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 24px;">
          <button onclick="setFundAmount(10000)" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: transparent; font-size: 12px; cursor: pointer; color: var(--text);">+ ₹10k</button>
          <button onclick="setFundAmount(50000)" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: transparent; font-size: 12px; cursor: pointer; color: var(--text);">+ ₹50k</button>
          <button onclick="setFundAmount(100000)" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: transparent; font-size: 12px; cursor: pointer; color: var(--text);">+ ₹1L</button>
        </div>

        <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
          Note: Funds will be credited to your escrow wallet immediately upon successful payment.
        </p>

        <button id="rzp-button-wallet" class="btn btn-primary btn-lg" style="width: 100%; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);" onclick="startRazorpayWallet()">
          Secure Pay with Razorpay
        </button>
      </div>
    </div>
  </div>

  <div id="sell-asset-modal" class="modal-overlay" style="display: none;">
    <div class="sell-modal">
      <button class="sell-modal-close" onclick="closeSellModal()">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>

      <div class="sell-modal-header">
        <h2 style="font-size: 18px; font-weight: 700;">List Asset for Sale</h2>
        <p style="font-size: 13px; color: var(--text-muted);">Convert your shares into instant liquidity.</p>
      </div>

      <div class="sell-modal-body">
        <div class="asset-info-card">
          <div id="sell-startup-icon" style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">T</div>
          <div>
            <h3 id="sell-startup-name" style="font-size: 15px; font-weight: 600;">TechNova</h3>
            <p id="sell-holding-info" style="font-size: 12px; color: var(--text-muted);">You own 500 shares (Avg ₹100.00)</p>
          </div>
        </div>

        <form id="sell-listing-form">
          <input type="hidden" id="sell-pitch-id" name="pitch_id">
          <input type="hidden" id="sell-investment-id" name="investment_id">
          
          <div class="sell-form-group">
            <label class="sell-form-label">Quantity to Sell</label>
            <div class="sell-input-wrapper">
              <input type="number" id="sell-quantity" name="quantity" class="sell-input" placeholder="0" min="1">
              <span style="position: absolute; right: 16px; font-size: 12px; color: var(--text-muted);">SHARES</span>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 8px;">
                <button type="button" onclick="setSellQty(0.25)" style="flex: 1; padding: 4px; font-size: 10px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 4px; cursor: pointer;">25%</button>
                <button type="button" onclick="setSellQty(0.5)" style="flex: 1; padding: 4px; font-size: 10px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 4px; cursor: pointer;">50%</button>
                <button type="button" onclick="setSellQty(1)" style="flex: 1; padding: 4px; font-size: 10px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 4px; cursor: pointer;">MAX</button>
            </div>
          </div>

          <div class="sell-form-group">
            <label class="sell-form-label">Asking Price (Per Share)</label>
            <div class="sell-input-wrapper">
              <input type="number" id="sell-price" name="price_per_share" class="sell-input" step="0.01" placeholder="0.00">
              <span style="position: absolute; right: 16px; font-size: 12px; color: var(--text-muted);">INR</span>
            </div>
            
            <!-- AI Price Assistant Badge -->
            <div class="ai-price-suggestion">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 2a10 10 0 0 1 10 10h-10V2z"/><path d="M12 12L2.2 9a10 10 0 0 1 19.6 0L12 12z"/></svg>
                    <span style="font-size: 11px; font-weight: 600; color: #8b5cf6;">AI Suggested Price</span>
                </div>
                <span id="ai-suggested-price" style="font-size: 12px; font-weight: 700; color: var(--text);">₹165.40</span>
            </div>
          </div>

          <div class="profit-preview">
            <div class="profit-row">
              <span style="color: var(--text-muted);">Estimated Revenue</span>
              <span id="est-revenue" style="font-weight: 600;">₹0.00</span>
            </div>
            <div class="profit-row">
              <span style="color: var(--text-muted);">Est. Net Profit</span>
              <span id="est-profit" style="font-weight: 600; color: var(--success);">+₹0.00</span>
            </div>
          </div>

          <button type="submit" class="btn-list-sale">List Shares for Sale</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ====================== STEP 4: BUY SECONDARY ASSET MODAL ====================== -->
  <div id="buy-secondary-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; width: 90%; max-width: 450px; overflow: hidden;">
      <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: rgba(167, 139, 250, 0.05);">
        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--primary)" stroke-width="2">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
          Buy Secondary Shares
        </h3>
        <button onclick="closeBuySecondaryModal()" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 5px;">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      
      <div class="modal-body" style="padding: 25px;">
        <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 25px;">
          <div id="buy-startup-icon" style="width: 50px; height: 50px; background: var(--primary); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold;">?</div>
          <div>
            <h4 id="buy-startup-name" style="margin: 0; font-size: 1.2rem;">Startup Name</h4>
            <p style="color: var(--text-muted); font-size: 0.9rem;">From: <span id="buy-seller-name" style="color: var(--accent)">Seller</span></p>
          </div>
        </div>

        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 25px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span style="color: var(--text-muted);">Offered Price</span>
            <strong style="color: var(--success);" id="buy-price-display">₹0.00</strong>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span style="color: var(--text-muted);">Max Quantity</span>
            <strong id="buy-max-qty">0 Shares</strong>
          </div>
        </div>

        <form id="buy-trade-form">
          <input type="hidden" name="order_id" id="buy-order-id">
          
          <div class="form-group" style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 0.9rem;">Quantity to Buy</label>
            <div style="position: relative;">
              <input type="number" name="quantity" id="buy-quantity" placeholder="Enter share count" required min="1" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1rem;">
              <button type="button" onclick="setBuyMax()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: var(--primary-glow); border: none; color: var(--primary); padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; cursor: pointer;">MAX</button>
            </div>
          </div>

          <div style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 15px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
              <span style="color: var(--text-muted); font-size: 0.9rem;">Transaction Value:</span>
              <span style="font-weight: 600;" id="buy-total-value">₹0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
              <span style="color: var(--text-muted); font-size: 0.9rem;">Exchange Fee (0.5%):</span>
              <span id="buy-fee">₹0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 10px; margin-top: 5px;">
              <strong style="font-size: 1.1rem;">Total Payable:</strong>
              <strong style="font-size: 1.1rem; color: var(--primary)" id="buy-total-payable">₹0.00</strong>
            </div>
          </div>

          <button type="submit" class="btn-confirm-buy" style="width: 100%; padding: 15px; background: var(--success); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);">
            Confirm & Secure Transfer
          </button>
          
          <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem; margin-top: 15px;">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Secured via SmartPitchHub Escrow
          </p>
        </form>
      </div>
    </div>
  </div>

  </div>
</div>

<!-- KYC LOCK MODAL (Restored) -->
<div id="kyc-lock-modal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-icon-box" style="background: rgba(245, 158, 11, 0.1); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
        <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
    </div>
    <h2>Unlock Investing Features</h2>
    <p style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-bottom: 20px;">To comply with financial regulations and ensure security, you must verify your identity before making investments or adding funds.</p>
    
    <div id="modal-status-text" style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 24px;">
       Status: <span style="color: #f59e0b; font-weight: 600;">Not Submitted</span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 10px;">
      <a href="../KYC/investor-kyc.php" class="btn btn-primary" style="text-decoration: none;">Complete KYC Now</a>
      <button onclick="closeKycModal()" class="btn btn-outline">Browse for Now</button>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    // ====================== SMART WALLET LOGIC ======================
    function showAddFundsModal() {
        // We'll allow adding funds for testing even if KYC is pending
        // but normally you would check: if (currentKycStatus !== 'approved') ...
        document.getElementById('add-funds-modal').style.display = 'flex';
    }

    function closeAddFundsModal() {
        document.getElementById('add-funds-modal').style.display = 'none';
    }

    function setFundAmount(amt) {
        document.getElementById('fund-amount').value = amt;
    }

    async function startRazorpayWallet() {
        const amountEl = document.getElementById('fund-amount');
        const realAmount = parseFloat(amountEl.value);

        if (!realAmount || realAmount < 100) {
            alert("Minimum top-up amount is ₹100");
            return;
        }

        const btn = document.getElementById('rzp-button-wallet');
        btn.disabled = true;
        btn.textContent = "Opening Secure Gateway...";

        // Real amount in Paisa (required by Razorpay)
        const amountInPaisa = Math.round(realAmount * 100);
        
        const options = {
            "key": "<?php echo $config['razorpay_key']; ?>",
            "amount": amountInPaisa, 
            "currency": "INR",
            "name": "SmartPitchHub Escrow",
            "description": "Wallet Top-up Request: ₹" + realAmount,
            "image": "../img/logo.png",
            "handler": function (response) {
                processWalletCredit(realAmount, response.razorpay_payment_id);
            },
            "prefill": {
                "name": "<?php echo $user_name; ?>",
                "email": "investor@example.com"
            },
            "theme": {
                "color": "#6366f1"
            },
            "modal": {
                "ondismiss": function() {
                    btn.disabled = false;
                    btn.textContent = "Secure Pay with Razorpay";
                }
            }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    }

    async function processWalletCredit(amount, paymentId) {
        const btn = document.getElementById('rzp-button-wallet');
        btn.textContent = "Synchronizing Escrow...";

        const formData = new FormData();
        formData.append('amount', amount);
        formData.append('payment_id', paymentId);

        try {
            const res = await fetch('api_add_funds.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                alert(data.message);
                window.location.reload(); // Refresh to see balance and history
            } else {
                alert("Error Updating Wallet: " + data.message);
                btn.disabled = false;
                btn.textContent = "Retry Transaction";
            }
        } catch (error) {
            console.error("Credit Error:", error);
            alert("Connection error. If money was deducted, contact support with Payment ID: " + paymentId);
            btn.disabled = false;
            btn.textContent = "Retry Transaction";
        }
    }
</script>

<?php if (isset($show_kyc_popup) && $show_kyc_popup): ?>
<style>
    .investor-kyc-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(10, 11, 20, 0.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: kycFadeIn 0.5s ease;
    }

    .investor-kyc-card {
        background: radial-gradient(circle at top left, rgba(99, 102, 241, 0.15) 0%, transparent 40%), 
                    #161726;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        width: 100%;
        max-width: 500px;
        padding: 40px;
        position: relative;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4), 
                    inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        text-align: center;
        animation: kycSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .kyc-glow {
        position: absolute;
        top: -50px; left: 50%;
        transform: translateX(-50%);
        width: 200px; height: 100px;
        background: #6366f1;
        filter: blur(80px);
        opacity: 0.3;
        z-index: -1;
    }

    .kyc-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.2);
        color: #818cf8;
        padding: 6px 16px;
        border-radius: 100px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 24px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .kyc-title {
        color: white;
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 12px;
        line-height: 1.2;
    }

    .kyc-desc {
        color: #94a3b8;
        font-size: 1.05rem;
        line-height: 1.6;
        margin-bottom: 32px;
    }

    .kyc-perks {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        margin-bottom: 32px;
        text-align: left;
    }

    .perk-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .perk-icon {
        width: 32px; height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(99, 102, 241, 0.1);
        border-radius: 8px;
        color: #818cf8;
    }

    .perk-text {
        color: #cbd5e1;
        font-size: 0.95rem;
        font-weight: 500;
    }

    .kyc-primary-btn {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        width: 100%;
        padding: 16px;
        border-radius: 14px;
        font-size: 1.1rem;
        font-weight: 700;
        text-decoration: none;
        display: block;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .kyc-primary-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(79, 70, 229, 0.4);
        filter: brightness(1.1);
    }

    .kyc-skip-btn {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 0.95rem;
        font-weight: 500;
        margin-top: 20px;
        cursor: pointer;
        padding: 8px 16px;
        transition: all 0.23s;
        border-radius: 8px;
    }

    .kyc-skip-btn:hover {
        color: #94a3b8;
        background: rgba(255, 255, 255, 0.05);
    }

    @keyframes kycFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes kycSlideUp { 
        from { transform: translateY(40px) scale(0.95); opacity: 0; } 
        to { transform: translateY(0) scale(1); opacity: 1; } 
    }

    @media (max-width: 480px) {
        .investor-kyc-card { padding: 30px 20px; }
        .kyc-title { font-size: 1.5rem; }
    }
</style>

<div class="investor-kyc-overlay" id="investorKycOverlay">
    <div class="investor-kyc-card">
        <div class="kyc-glow"></div>
        <div class="kyc-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Account Security
        </div>
        
        <h2 class="kyc-title">Unlock Investor Access</h2>
        <p class="kyc-desc">Complete your one-time identity verification to start investing in high-growth startups.</p>

        <div class="kyc-perks">
            <div class="perk-item">
                <div class="perk-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
                </div>
                <div class="perk-text">Immediate Portfolio Access</div>
            </div>
            <div class="perk-item">
                <div class="perk-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="perk-text">Secure Escrow Transactions</div>
            </div>
            <div class="perk-item">
                <div class="perk-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 11-7.6-14.7 8.38 8.38 0 013.8.9"/><path d="M22 4 12 14.01l-3-3"/></svg>
                </div>
                <div class="perk-text">Verified Badge on Account</div>
            </div>
        </div>

        <a href="../KYC/Investor-kyc.php" class="kyc-primary-btn">
            Verify Identity Now
        </a>
        
        <button class="kyc-skip-btn" onclick="document.getElementById('investorKycOverlay').style.display='none'">
            Skip for now
        </button>
    </div>
</div>
<?php endif; ?>

</body>
</html>