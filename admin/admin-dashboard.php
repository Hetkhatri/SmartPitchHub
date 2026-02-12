<?php
// =========================================================
// 1. INITIALIZATION & DATABASE
// =========================================================
session_start();
// Adjust path if needed
require_once '../db.php'; 

// =========================================================
// 2. HANDLE ACTIONS (MUST BE AT THE TOP)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'], $_POST['pitch_db_id'])) {
    $db_id = (int)$_POST['pitch_db_id'];
    $action = $_POST['action_type'];
    
    if ($action === 'release_escrow') {
        // Advanced Escrow Release Logic
        $conn->begin_transaction();
        try {
            // 1. Get Investment Details
            $stmt = $conn->prepare("SELECT i.amount, i.pitch_id, p.entrepreneur_id, i.payout_status FROM investments i JOIN pitches p ON i.pitch_id = p.id WHERE i.id = ?");
            $stmt->bind_param("i", $db_id);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();
            
            if (!$inv || $inv['payout_status'] !== 'escrow') {
                throw new Exception("Invalid investment or already released.");
            }
            
            $total_amount = $inv['amount'];
            $entrepreneur_id = $inv['entrepreneur_id'];
            $commission = $total_amount * 0.05;
            $net_amount = $total_amount - $commission;
            
            // 2. Update Investment Status
            $upd = $conn->prepare("UPDATE investments SET payout_status = 'released' WHERE id = ?");
            $upd->bind_param("i", $db_id);
            $upd->execute();
            
            // 3. Credit Entrepreneur Wallet
            $conn->query("UPDATE wallets SET balance = balance + $net_amount WHERE user_id = $entrepreneur_id AND user_role = 'entrepreneur'");
            
            // 4. Credit Admin Wallet (Commission)
            // Use admin_id from admins table
            $admin_res = $conn->query("SELECT admin_id FROM admins LIMIT 1");
            $admin_id = $admin_res->fetch_assoc()['admin_id'];
            $conn->query("UPDATE wallets SET balance = balance + $commission WHERE user_role = 'admin'");
            
            // 5. Log Transactions
            // Credit to Entrepreneur
            $stmt_txn = $conn->prepare("INSERT INTO wallet_transactions (user_id, user_role, txn_type, amount, source, status) VALUES (?, 'entrepreneur', 'credit', ?, 'Investment Payout', 'success')");
            $stmt_txn->bind_param("id", $entrepreneur_id, $net_amount);
            $stmt_txn->execute();
            
            // Credit to Admin (Commission)
            $stmt_adm = $conn->prepare("INSERT INTO wallet_transactions (user_id, user_role, txn_type, amount, source, status) VALUES (?, 'admin', 'credit', ?, 'Platform Commission', 'success')");
            $stmt_adm->bind_param("id", $admin_id, $commission);
            $stmt_adm->execute();
            
            $conn->commit();
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=escrow_released");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            die("Error: " . $e->getMessage());
        }
    }

    if ($action === 'refund_escrow') {
        // Advanced Escrow Refund/Return Logic
        $conn->begin_transaction();
        try {
            // 1. Get Investment Details
            $stmt = $conn->prepare("SELECT amount, investor_id, payout_status FROM investments WHERE id = ?");
            $stmt->bind_param("i", $db_id);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();
            
            if (!$inv || $inv['payout_status'] !== 'escrow') {
                throw new Exception("Invalid investment or cannot be refunded.");
            }
            
            $amount = $inv['amount'];
            $investor_id = $inv['investor_id'];
            
            // 2. Update Investment Status
            $upd = $conn->prepare("UPDATE investments SET payout_status = 'refunded', status = 'failed' WHERE id = ?");
            $upd->bind_param("i", $db_id);
            $upd->execute();
            
            // 3. Credit Investor Wallet (100% Return)
            $conn->query("UPDATE wallets SET balance = balance + $amount WHERE user_id = $investor_id AND user_role = 'investor'");
            
            // 4. Log Transaction
            $stmt_txn = $conn->prepare("INSERT INTO wallet_transactions (user_id, user_role, txn_type, amount, source, status) VALUES (?, 'investor', 'credit', ?, 'Investment Refunded', 'success')");
            $stmt_txn->bind_param("id", $investor_id, $amount);
            $stmt_txn->execute();
            
            $conn->commit();
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=escrow_refunded");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            die("Error: " . $e->getMessage());
        }
    }
    
    // Check valuation status before allowing approval
    if ($action === 'approve') {
        $val_check = $conn->prepare("
            SELECT vr.status 
            FROM valuation_requests vr
            JOIN pitches p ON vr.entrepreneur_id = p.entrepreneur_id
            WHERE p.id = ?
            ORDER BY vr.created_at DESC LIMIT 1
        ");
        $val_check->bind_param("i", $db_id);
        $val_check->execute();
        $v_res = $val_check->get_result()->fetch_assoc();
        
        if ($v_res && $v_res['status'] === 'pending') {
            die("Error: Valuation must be verified before approving pitch. Please go to View Details.");
        }
    }

    // 1 = Approved, 2 = Rejected, 0 = Pending
    $new_status = ($action === 'approve') ? 1 : 2;
    
    $stmt = $conn->prepare("UPDATE pitches SET is_approved = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $db_id);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit;
    }
    $stmt->close();
}

// =========================================================
// 3. FETCH STATISTICS (Fixes Undefined Variable Errors)
// =========================================================

// --- A. USERS (Fixes $grand_total_users, $total_ent, $total_inv) ---
$ent_stats_q = $conn->query("SELECT COUNT(*) as total FROM entrepreneurs");
$ent_stats = $ent_stats_q->fetch_assoc();
$total_ent = $ent_stats['total'] ?? 0;

$inv_stats_q = $conn->query("SELECT COUNT(*) as total FROM investors");
$inv_stats = $inv_stats_q->fetch_assoc();
$total_inv = $inv_stats['total'] ?? 0;

$grand_total_users = $total_ent + $total_inv;

// --- B. PITCHES STATS (Total & Trends) ---
// 1. Total Pitches
$pitch_total_q = $conn->query("SELECT COUNT(*) as total FROM pitches");
$total_pitches = $pitch_total_q->fetch_assoc()['total'] ?? 0;

// 2. Pitch Trend
$pitch_trend_q = $conn->query("SELECT COUNT(*) as recent FROM pitches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
$new_pitches_this_month = $pitch_trend_q->fetch_assoc()['recent'] ?? 0;
$prev_total_pitches = $total_pitches - $new_pitches_this_month;
$pitch_growth = ($prev_total_pitches > 0) ? ($new_pitches_this_month / $prev_total_pitches) * 100 : 0;
// Format Variables
$pitch_growth_formatted = number_format($pitch_growth, 1);
$trend_color_class = ($pitch_growth >= 0) ? 'trend-up' : 'trend-down';
$trend_sign = ($pitch_growth >= 0) ? '+' : '';

// --- C. PENDING PITCHES STATS ---
$pending_total_q = $conn->query("SELECT COUNT(*) as total FROM pitches WHERE is_approved = 0"); 
$total_pending = $pending_total_q->fetch_assoc()['total'] ?? 0;

$pending_recent_q = $conn->query("SELECT COUNT(*) as recent FROM pitches WHERE is_approved = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
$new_pending_this_month = $pending_recent_q->fetch_assoc()['recent'] ?? 0;
$prev_pending = $total_pending - $new_pending_this_month;
$pending_growth = ($prev_pending > 0) ? ($new_pending_this_month / $prev_pending) * 100 : 0;

$pending_growth_formatted = number_format(abs($pending_growth), 1); 
$pending_trend_class = ($pending_growth >= 0) ? 'trend-up' : 'trend-down';
$pending_trend_sign = ($pending_growth >= 0) ? '+' : '-';

// --- D. APPROVED PITCHES STATS ---
$approved_total_q = $conn->query("SELECT COUNT(*) as total FROM pitches WHERE is_approved = 1");
$total_approved = $approved_total_q->fetch_assoc()['total'] ?? 0;

$approved_recent_q = $conn->query("SELECT COUNT(*) as recent FROM pitches WHERE is_approved = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
$new_approved_this_month = $approved_recent_q->fetch_assoc()['recent'] ?? 0;
$prev_approved = $total_approved - $new_approved_this_month;
$approved_growth = ($prev_approved > 0) ? ($new_approved_this_month / $prev_approved) * 100 : 0;

$approved_growth_formatted = number_format(abs($approved_growth), 1);
$approved_trend_class = ($approved_growth >= 0) ? 'trend-up' : 'trend-down';
$approved_trend_sign = ($approved_growth >= 0) ? '+' : '-';

// --- E. INVESTMENTS & REVENUE (Fixes $revenue_growth, $total_investment_display) ---
$total_investment_val = 0; 
$total_investment_display = "₹0";
$inv_growth = 0; $inv_growth_formatted = "0.0"; $inv_trend_class = "trend-up"; $inv_trend_sign = "+";

$total_revenue_val = 0;
$total_revenue_display = "₹0";
$revenue_growth = 0; $revenue_growth_formatted = "0.0"; $revenue_trend_class = "trend-up"; $revenue_trend_sign = "+";

$check_inv = $conn->query("SHOW TABLES LIKE 'investments'");
if ($check_inv && $check_inv->num_rows > 0) {
    // Investment Total
    $inv_total_q = $conn->query("SELECT SUM(amount) as total FROM investments WHERE status = 'completed'");
    $total_investment_val = $inv_total_q->fetch_assoc()['total'] ?? 0;
    
    // Investment Trend
    $inv_recent_q = $conn->query("SELECT SUM(amount) as recent FROM investments WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $new_inv_this_month = $inv_recent_q->fetch_assoc()['recent'] ?? 0;
    $prev_inv = $total_investment_val - $new_inv_this_month;
    $inv_growth = ($prev_inv > 0) ? ($new_inv_this_month / $prev_inv) * 100 : 0;
    
    // Formatting Investment
    if ($total_investment_val >= 10000000) { $total_investment_display = '₹' . number_format($total_investment_val / 10000000, 2) . ' Cr'; }
    elseif ($total_investment_val >= 100000) { $total_investment_display = '₹' . number_format($total_investment_val / 100000, 2) . ' L'; }
    else { $total_investment_display = '₹' . number_format($total_investment_val); }
    
    $inv_growth_formatted = number_format(abs($inv_growth), 1);
    $inv_trend_class = ($inv_growth >= 0) ? 'trend-up' : 'trend-down';
    $inv_trend_sign = ($inv_growth >= 0) ? '+' : '-';

    // Revenue (5% of Investment)
    $total_revenue_val = $total_investment_val * 0.05;
    $new_rev_this_month = $new_inv_this_month * 0.05;
    $prev_rev = $total_revenue_val - $new_rev_this_month;
    $rev_growth = ($prev_rev > 0) ? ($new_rev_this_month / $prev_rev) * 100 : 0;
    
    // Formatting Revenue
    if ($total_revenue_val >= 10000000) { $total_revenue_display = '₹' . number_format($total_revenue_val / 10000000, 2) . ' Cr'; }
    elseif ($total_revenue_val >= 100000) { $total_revenue_display = '₹' . number_format($total_revenue_val / 100000, 2) . ' L'; }
    else { $total_revenue_display = '₹' . number_format($total_revenue_val); }
    
    $revenue_growth_formatted = number_format(abs($rev_growth), 1);
    $revenue_trend_class = ($rev_growth >= 0) ? 'trend-up' : 'trend-down';
    $revenue_trend_sign = ($rev_growth >= 0) ? '+' : '-';
}

// =========================================================
// 4. FETCH CHARTS & LISTS
// =========================================================

// --- Chart Data ---
$chart_labels = []; $chart_data = [];
if ($check_inv && $check_inv->num_rows > 0) {
    $chart_sql = "SELECT DATE_FORMAT(created_at, '%b') as month, SUM(amount) as total FROM investments WHERE status = 'completed' AND YEAR(created_at) = YEAR(CURRENT_DATE()) GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)";
    $chart_res = $conn->query($chart_sql);
    while($row = $chart_res->fetch_assoc()) {
        $chart_labels[] = $row['month'];
        $chart_data[] = round($row['total'] / 10000000, 2); 
    }
}
$json_chart_labels = json_encode($chart_labels);
$json_chart_data = json_encode($chart_data);

// --- RECENT PITCHES (Fixes $recent_pitches_res error) ---
$recent_pitches_sql = "
    SELECT 
        p.startup_name,
        p.funding_goal,
        p.is_approved,
        e.name AS entrepreneur_name
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    ORDER BY p.created_at DESC
    LIMIT 5
";
$recent_pitches_res = $conn->query($recent_pitches_sql);

// --- ALL PITCHES (For Management Table) ---
$all_pitches_sql = "
    SELECT 
        p.id, p.startup_name, p.industry, p.funding_goal, p.is_approved, p.created_at, p.description, 
        e.name as founder_name, e.email as founder_email,
        wz.survival_score,
        vr.status as valuation_status
    FROM pitches p 
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id 
    LEFT JOIN (
        SELECT pitch_id, MAX(survival_score) as survival_score 
        FROM warzone_sessions 
        GROUP BY pitch_id
    ) wz ON p.id = wz.pitch_id
    LEFT JOIN (
        SELECT vr_inner.entrepreneur_id, vr_inner.status
        FROM valuation_requests vr_inner
        INNER JOIN (
            SELECT entrepreneur_id, MAX(created_at) as max_date
            FROM valuation_requests
            GROUP BY entrepreneur_id
        ) latest ON vr_inner.entrepreneur_id = latest.entrepreneur_id AND vr_inner.created_at = latest.max_date
    ) vr ON p.entrepreneur_id = vr.entrepreneur_id
    ORDER BY p.created_at DESC";
$all_pitches_res = $conn->query($all_pitches_sql);
$pitches_data = [];

if ($all_pitches_res) {
    while ($row = $all_pitches_res->fetch_assoc()) {
        $status_map = [0 => 'pending', 1 => 'approved', 2 => 'rejected'];
        $pitches_data[] = [
            'id' => "PIT" . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'db_id' => $row['id'],
            'startupName' => $row['startup_name'],
            'founderName' => $row['founder_name'],
            'founderEmail' => $row['founder_email'],
            'category' => $row['industry'],
            'fundingAsk' => (float)$row['funding_goal'],
            'status' => $status_map[$row['is_approved']] ?? 'pending',
            'valuationStatus' => $row['valuation_status'] ?? 'none',
            'submissionDate' => date('Y-m-d', strtotime($row['created_at'])),
            'description' => $row['description'],
            'warzoneScore' => $row['survival_score'] ?? 'N/A'
        ];
    }
}

// --- ENTREPRENEURS TABLE DATA ---
$ent_res = $conn->query("SELECT e.id, e.name, e.email, e.kyc_status, e.status AS account_status, e.total_pitches, COALESCE(w.balance, 0) as wallet_balance FROM entrepreneurs e LEFT JOIN wallets w ON e.id = w.user_id AND w.user_role = 'entrepreneur' ORDER BY e.id DESC");
$entrepreneurs_data = [];
while($row = $ent_res->fetch_assoc()) {
    $entrepreneurs_data[] = ['name'=>$row['name'], 'email'=>$row['email'], 'kycStatus'=>$row['kyc_status'], 'totalPitches'=>$row['total_pitches'], 'accountStatus'=>$row['account_status'], 'walletBalance'=>$row['wallet_balance']];
}

// --- INVESTORS TABLE DATA ---
$inv_res = $conn->query("
    SELECT i.id, i.name, i.email, i.kyc_status, i.status AS account_status, 
           COALESCE(w.balance, 0) as wallet_balance,
           (SELECT COUNT(*) FROM investments WHERE investor_id = i.id) as total_investments,
           0 as bids_purchased
    FROM investors i 
    LEFT JOIN wallets w ON i.id = w.user_id AND w.user_role = 'investor' 
    ORDER BY i.id DESC
");
$investors_data = [];
while($row = $inv_res->fetch_assoc()) {
    $investors_data[] = [
        'name'=>$row['name'], 
        'email'=>$row['email'], 
        'kycStatus'=>$row['kyc_status'], 
        'accountStatus'=>$row['account_status'],
        'walletBalance'=>$row['wallet_balance'],
        'totalInvestments'=>$row['total_investments'],
        'bidsPurchased'=>$row['bids_purchased']
    ];
}

// --- ADMIN WALLET & ESCROW DATA ---
$admin_wallet_balance = 0;
$pending_settlements = 0;
$today_credits = 0;
$today_debits = 0;

$admin_wallet_q = $conn->query("SELECT balance FROM wallets WHERE user_role = 'admin' LIMIT 1");
if ($admin_wallet_q && $admin_wallet_q->num_rows > 0) {
    $admin_wallet_balance = $admin_wallet_q->fetch_assoc()['balance'];
}

$pending_settlement_q = $conn->query("SELECT SUM(amount) as total FROM investments WHERE payout_status = 'escrow' AND status = 'completed'");
if ($pending_settlement_q) {
    $pending_settlements = $pending_settlement_q->fetch_assoc()['total'] ?? 0;
}

$today_credits_q = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE user_role = 'admin' AND txn_type = 'credit' AND DATE(created_at) = CURDATE() AND status = 'success'");
if ($today_credits_q) {
    $today_credits = $today_credits_q->fetch_assoc()['total'] ?? 0;
}

$today_debits_q = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE user_role = 'admin' AND txn_type = 'debit' AND DATE(created_at) = CURDATE() AND status = 'success'");
if ($today_debits_q) {
    $today_debits = $today_debits_q->fetch_assoc()['total'] ?? 0;
}

// --- CURRENCY FORMATTING HELPER ---
function formatRupees($amount) {
    if ($amount >= 10000000) return '₹' . number_format($amount / 10000000, 2) . ' Cr';
    if ($amount >= 100000) return '₹' . number_format($amount / 100000, 2) . ' L';
    return '₹' . number_format($amount, 2);
}

// --- TRANSACTION HISTORY ---
$transactions_data = [];
$txns_res = $conn->query("
    SELECT t.*, 
        CASE 
            WHEN t.user_role = 'investor' THEN (SELECT name FROM investors WHERE id = t.user_id)
            WHEN t.user_role = 'entrepreneur' THEN (SELECT name FROM entrepreneurs WHERE id = t.user_id)
            WHEN t.user_role = 'admin' THEN (SELECT name FROM admins WHERE admin_id = t.user_id)
            ELSE 'User'
        END as user_name
    FROM wallet_transactions t 
    ORDER BY t.created_at DESC 
    LIMIT 50
");
if ($txns_res) {
    while($row = $txns_res->fetch_assoc()) {
        $transactions_data[] = [
            'id' => "TXN" . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'userType' => ucfirst($row['user_role']),
            'userName' => $row['user_name'] ?? 'System',
            'type' => $row['txn_type'],
            'amount' => (float)$row['amount'],
            'reason' => $row['source'],
            'status' => $row['status'],
            'date' => date('Y-m-d', strtotime($row['created_at']))
        ];
    }
}

// --- INVESTMENT TRACKING ---
$investments_tracking_data = [];
$inv_track_res = $conn->query("
    SELECT i.*, inv.name as investor_name, p.startup_name 
    FROM investments i 
    JOIN investors inv ON i.investor_id = inv.id 
    JOIN pitches p ON i.pitch_id = p.id 
    ORDER BY i.created_at DESC 
    LIMIT 50
");
if ($inv_track_res) {
    while($row = $inv_track_res->fetch_assoc()) {
        $investments_tracking_data[] = [
            'id' => "INV" . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'db_id' => $row['id'],
            'investorName' => $row['investor_name'],
            'pitchName' => $row['startup_name'],
            'amount' => (float)$row['amount'],
            'date' => date('Y-m-d', strtotime($row['created_at'])),
            'status' => $row['payout_status'] // using payout_status like escrow/released
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartPitchHub - Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* YOUR ORIGINAL CSS - PRESERVED */
    :root {
      --background: #f8fafc;
      --foreground: #0f172a;
      --card: #ffffff;
      --card-foreground: #0f172a;
      --primary: #14b8a6;
      --primary-foreground: #ffffff;
      --secondary: #f1f5f9;
      --secondary-foreground: #0f172a;
      --muted: #f1f5f9;
      --muted-foreground: #64748b;
      --border: #e2e8f0;
      --success: #22c55e;
      --warning: #f59e0b;
      --destructive: #ef4444;
      --sidebar-bg: #0f172a;
      --sidebar-fg: #f1f5f9;
      --sidebar-accent: #1e293b;
      --sidebar-muted: #64748b;
      --radius: 8px;
    }
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--background);
      color: var(--foreground);
      line-height: 1.5;
    }
    
    /* Layout */
    .app-container { display: flex; min-height: 100vh; }
    
    /* Sidebar */
    .sidebar {
      width: 256px;
      background: var(--sidebar-bg);
      color: var(--sidebar-fg);
      position: fixed;
      height: 100vh;
      overflow-y: auto;
      transition: width 0.3s;
      z-index: 50;
    }
    .sidebar.collapsed { width: 64px; }
    .sidebar-header {
      height: 64px;
      display: flex;
      align-items: center;
      padding: 0 16px;
      border-bottom: 1px solid var(--sidebar-accent);
      gap: 12px;
    }
    .sidebar-logo {
      width: 32px;
      height: 32px;
      background: var(--primary);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .sidebar-logo svg { width: 20px; height: 20px; color: white; }
    .sidebar-title { font-weight: 600; font-size: 18px; }
    .sidebar.collapsed .sidebar-title { display: none; }
    
    .sidebar-nav { padding: 16px 12px; }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 12px;
      border-radius: var(--radius);
      color: var(--sidebar-muted);
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 4px;
      text-decoration: none;
    }
    .nav-item:hover { background: var(--sidebar-accent); color: var(--sidebar-fg); }
    .nav-item.active { background: var(--primary); color: white; }
    .nav-item svg { width: 20px; height: 20px; flex-shrink: 0; }
    .nav-item span { white-space: nowrap; }
    .sidebar.collapsed .nav-item span { display: none; }
    
    .collapse-btn {
      position: absolute;
      right: -12px;
      top: 80px;
      width: 24px;
      height: 24px;
      background: var(--sidebar-bg);
      border: 1px solid var(--sidebar-accent);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--sidebar-fg);
    }
    .collapse-btn svg { width: 12px; height: 12px; }
    
    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: 256px;
      transition: margin-left 0.3s;
    }
    .sidebar.collapsed + .main-content { margin-left: 64px; }
    
    /* Top Nav */
    .top-nav {
      height: 64px;
      background: var(--card);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      position: sticky;
      top: 0;
      z-index: 40;
    }
    .page-title { font-size: 20px; font-weight: 600; }
    .top-nav-right { display: flex; align-items: center; gap: 16px; }
    .search-box {
      position: relative;
      display: none;
    }
    @media (min-width: 768px) { .search-box { display: block; } }
    .search-box input {
      width: 256px;
      padding: 8px 12px 8px 36px;
      border: 1px solid transparent;
      border-radius: var(--radius);
      background: var(--muted);
      font-size: 14px;
    }
    .search-box input:focus { outline: none; border-color: var(--primary); }
    .search-box svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      color: var(--muted-foreground);
    }
    
    .icon-btn {
      width: 40px;
      height: 40px;
      border: none;
      background: transparent;
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      color: var(--muted-foreground);
    }
    .icon-btn:hover { background: var(--muted); }
    .notification-badge {
      position: absolute;
      top: 4px;
      right: 4px;
      width: 18px;
      height: 18px;
      background: var(--destructive);
      color: white;
      font-size: 10px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .avatar {
      width: 32px;
      height: 32px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
    }
    .avatar-orange { background: #ea580c; }
    .avatar-green { background: #16a34a; }

    .profile-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 4px 8px;
      border: none;
      background: transparent;
      border-radius: var(--radius);
      cursor: pointer;
    }
    .profile-btn:hover { background: var(--muted); }
    .profile-info { text-align: left; display: none; }
    @media (min-width: 768px) { .profile-info { display: block; } }
    .profile-name { font-size: 14px; font-weight: 500; }
    .profile-role { font-size: 12px; color: var(--muted-foreground); }
    
    /* Page Content */
    .page-content { padding: 24px; }
    .page { display: none; animation: fadeIn 0.3s ease; }
    .page.active { display: block; }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    /* BID PACKAGE CARD STYLES */
.package-card { padding: 1.5rem; display: flex; flex-direction: column; transition: transform 0.2s; position: relative; }
.package-card:hover { transform: translateY(-5px); border-color: var(--primary); }
.package-card.inactive { opacity: 0.7; }

.package-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.package-icon { 
    width: 48px; height: 48px; 
    background: rgba(20, 184, 166, 0.1); 
    color: var(--primary);
    border-radius: 12px; 
    display: flex; align-items: center; justify-content: center; 
}
.package-card.inactive .package-icon { background: rgba(148, 163, 184, 0.1); color: var(--text-muted); }

/* Toggle Switch */
.toggle { width: 44px; height: 24px; background: var(--border); border-radius: 99px; position: relative; cursor: pointer; transition: 0.3s; }
.toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: 0.3s; }
.toggle.active { background: var(--primary); }
.toggle.active::after { left: 22px; }

.package-title { font-size: 1.125rem; font-weight: 700; margin-bottom: 0.25rem; }
.package-desc { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem; min-height: 40px; }

.package-details { margin-top: auto; border-top: 1px solid var(--border); padding-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
.package-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
.package-label { color: var(--text-muted); }
.package-value { font-weight: 600; }
    /* Cards */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .card-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
    }
    .card-title { font-size: 16px; font-weight: 600; }
    .card-content { padding: 24px; }
    
    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(1, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    @media (min-width: 640px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } }
    
    .stat-card { transition: transform 0.2s; }
    .stat-card:hover { transform: scale(1.02); }
    .stat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    .stat-label { font-size: 14px; color: var(--muted-foreground); }
    .stat-icon {
      width: 36px;
      height: 36px;
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .stat-icon svg { width: 18px; height: 18px; }
    .stat-value { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
    .stat-trend {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
    }
    .stat-trend svg { width: 14px; height: 14px; }
    .trend-up { color: var(--success); }
    .trend-down { color: var(--destructive); }
    
    /* Tables */
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }
    th {
      font-size: 12px;
      font-weight: 500;
      color: var(--muted-foreground);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    td { font-size: 14px; }
    tr:hover { background: var(--muted); }
    
    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 12px;
      font-weight: 500;
    }
    .badge-success { background: rgba(34, 197, 94, 0.1); color: var(--success); }
    .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
    .badge-destructive { background: rgba(239, 68, 68, 0.1); color: var(--destructive); }
    .badge-outline { background: transparent; border: 1px solid var(--border); color: var(--foreground); }
    .badge-secondary { background: var(--secondary); color: var(--secondary-foreground); }
    
    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 8px 16px;
      border: none;
      border-radius: var(--radius);
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn svg { width: 16px; height: 16px; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { opacity: 0.9; }
    .btn-secondary { background: var(--secondary); color: var(--secondary-foreground); }
    .btn-secondary:hover { background: var(--muted); }
    .btn-ghost { background: transparent; }
    .btn-ghost:hover { background: var(--muted); }
    .btn-destructive { background: var(--destructive); color: white; }
    .btn-success { background: var(--success); color: white; }
    .btn-outline { background: transparent; border: 1px solid var(--border); }
    .btn-outline:hover { background: var(--muted); }
    .btn-icon { width: 36px; height: 36px; padding: 0; }
    
    /* Inputs */
    .input-group { position: relative; }
    .input-group svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      color: var(--muted-foreground);
    }
    input, select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 14px;
      background: var(--card);
    }
    input:focus, select:focus { outline: none; border-color: var(--primary); }
    .input-icon { padding-left: 40px; }
    
    /* Filter Bar */
    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 24px;
    }
    .filter-bar .input-group { flex: 1; min-width: 200px; }
    .filter-bar select { width: auto; min-width: 150px; }
    
    /* Charts Grid */
    .charts-grid {
      display: grid;
      gap: 24px;
      margin-bottom: 24px;
    }
    @media (min-width: 1024px) { .charts-grid { grid-template-columns: repeat(2, 1fr); } }
    .chart-container { height: 300px; position: relative; }
    
    /* Modal */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
      background: var(--card);
      border-radius: var(--radius);
      width: 100%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
      animation: modalIn 0.2s ease;
    }
    @keyframes modalIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    .modal-title { font-size: 18px; font-weight: 600; }
    .modal-desc { font-size: 14px; color: var(--muted-foreground); margin-top: 4px; }
    .modal-body { padding: 24px; }
    .modal-footer {
      padding: 16px 24px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }
    
    /* Grid Layouts */
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .grid-3 { display: grid; gap: 24px; }
    @media (min-width: 1024px) { .grid-3 { grid-template-columns: 1fr 2fr; } }
    
    /* Activity List */
    .activity-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px;
      background: var(--muted);
      border-radius: var(--radius);
      margin-bottom: 8px;
    }
    .activity-info h4 { font-size: 14px; font-weight: 500; }
    .activity-info p { font-size: 12px; color: var(--muted-foreground); }
    .activity-right { text-align: right; }
    .activity-amount { font-size: 14px; font-weight: 500; }
    
    /* Transaction Icon */
    .txn-icon {
      width: 36px;
      height: 36px;
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .txn-icon.credit { background: rgba(34, 197, 94, 0.1); color: var(--success); }
    .txn-icon.debit { background: rgba(239, 68, 68, 0.1); color: var(--destructive); }
    
    /* Package Cards */
    .packages-grid {
      display: grid;
      gap: 16px;
      margin-bottom: 24px;
    }
    @media (min-width: 640px) { .packages-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .packages-grid { grid-template-columns: repeat(4, 1fr); } }
    
    .package-card { padding: 20px; }
    .package-card.inactive { opacity: 0.6; }
    .package-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .package-icon {
      width: 40px;
      height: 40px;
      background: rgba(20, 184, 166, 0.1);
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
    }
    .package-title { font-size: 16px; font-weight: 600; margin: 12px 0 4px; }
    .package-desc { font-size: 13px; color: var(--muted-foreground); }
    .package-details { margin-top: 16px; }
    .package-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
    .package-row:last-child { border: none; }
    .package-label { color: var(--muted-foreground); font-size: 14px; }
    .package-value { font-weight: 600; font-size: 14px; }
    
    /* Toggle Switch */
    .toggle {
      width: 44px;
      height: 24px;
      background: var(--muted);
      border-radius: 12px;
      position: relative;
      cursor: pointer;
      transition: background 0.2s;
    }
    .toggle.active { background: var(--primary); }
    .toggle::after {
      content: '';
      position: absolute;
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      top: 2px;
      left: 2px;
      transition: transform 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .toggle.active::after { transform: translateX(20px); }
    
    /* Ticket List */
    .ticket-list { max-height: 500px; overflow-y: auto; }
    .ticket-item {
      padding: 12px;
      background: var(--muted);
      border-radius: var(--radius);
      margin-bottom: 8px;
      cursor: pointer;
      transition: all 0.2s;
      border: 2px solid transparent;
    }
    .ticket-item:hover { background: var(--secondary); }
    .ticket-item.active { background: rgba(20, 184, 166, 0.1); border-color: rgba(20, 184, 166, 0.3); }
    .ticket-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
    .ticket-id { font-size: 12px; font-family: monospace; color: var(--muted-foreground); }
    .ticket-subject { font-size: 14px; font-weight: 500; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ticket-footer { display: flex; justify-content: space-between; align-items: center; }
    .ticket-user { font-size: 12px; color: var(--muted-foreground); }
    
    /* Chat */
    .chat-container { display: flex; flex-direction: column; height: 100%; }
    .chat-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
    }
    .chat-subject { font-size: 16px; font-weight: 600; }
    .chat-meta { display: flex; gap: 16px; margin-top: 8px; }
    .chat-meta span { font-size: 12px; color: var(--muted-foreground); }
    .chat-messages {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      max-height: 350px;
    }
    .message {
      display: flex;
      gap: 12px;
      margin-bottom: 16px;
    }
    .message.support { flex-direction: row-reverse; }
    .message-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      flex-shrink: 0;
    }
    .message.user .message-avatar { background: var(--muted); color: var(--muted-foreground); }
    .message.support .message-avatar { background: var(--primary); color: white; }
    .message-content {
      max-width: 70%;
      padding: 12px 16px;
      border-radius: var(--radius);
    }
    .message.user .message-content { background: var(--muted); }
    .message.support .message-content { background: var(--primary); color: white; }
    .message-text { font-size: 14px; }
    .message-time { font-size: 11px; margin-top: 4px; opacity: 0.7; }
    .chat-input {
      padding: 16px 20px;
      border-top: 1px solid var(--border);
      display: flex;
      gap: 8px;
    }
    .chat-input input { flex: 1; }
    
    /* Settings */
    .settings-section { margin-bottom: 32px; }
    .settings-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
    }
    .settings-header svg { width: 20px; height: 20px; color: var(--primary); }
    .settings-title { font-size: 16px; font-weight: 600; }
    .settings-desc { font-size: 14px; color: var(--muted-foreground); margin-top: 2px; }
    .setting-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 0;
      border-bottom: 1px solid var(--border);
    }
    .setting-row:last-child { border: none; }
    .setting-label { font-weight: 500; }
    .setting-help { font-size: 13px; color: var(--muted-foreground); margin-top: 2px; }
    
    /* Dropdown */
    .dropdown { position: relative; }
    .dropdown-menu {
      position: absolute;
      top: 100%;
      right: 0;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      min-width: 180px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      z-index: 50;
      display: none;
    }
    .dropdown-menu.active { display: block; }
    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .dropdown-item:hover { background: var(--muted); }
    .dropdown-item svg { width: 16px; height: 16px; }
    .dropdown-item.destructive { color: var(--destructive); }
    .dropdown-item.success { color: var(--success); }
    .dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }
    
    /* Form */
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
    textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 14px;
      font-family: inherit;
      resize: vertical;
      min-height: 80px;
    }
    textarea:focus { outline: none; border-color: var(--primary); }
    
    /* Wallet Hero Card */
    .wallet-hero {
      background: linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(20, 184, 166, 0.05));
      border: 1px solid rgba(20, 184, 166, 0.2);
    }
    
    /* Toast */
    .toast-container {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 200;
    }
    .toast {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 20px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      margin-top: 8px;
      animation: slideIn 0.3s ease;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      min-width: 300px;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }
    .toast-title { font-weight: 600; font-size: 14px; }
    .toast-desc { font-size: 13px; color: var(--muted-foreground); margin-top: 2px; }
    
    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted-foreground);
    }
    .empty-state svg { width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5; }
    
    /* Responsive */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.mobile-open { transform: translateX(0); }
      .main-content { margin-left: 0 !important; }
      .mobile-menu-btn { display: flex !important; }
    }
    .mobile-menu-btn { display: none; }
    
    /* Print styles */
    @media print {
      .sidebar, .top-nav { display: none; }
      .main-content { margin-left: 0; }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <span class="sidebar-title">SmartPitchHub</span>
      </div>
      <button class="collapse-btn" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <nav class="sidebar-nav">
        <a class="nav-item active" onclick="showPage('dashboard')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          <span>Dashboard</span>
        </a>
        <a class="nav-item" href="pitch-approvals.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>Pitch Approval</span>
        </a>
        <a class="nav-item" onclick="showPage('entrepreneurs')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Entrepreneurs</span>
        </a>
        <a class="nav-item" onclick="showPage('investors')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
          <span>Investors</span>
        </a>
        <a class="nav-item" onclick="showPage('wallet')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          <span>Wallet & Transactions</span>
        </a>
        <a class="nav-item" onclick="showPage('bids')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          <span>Bid Packages</span>
        </a>
        <a class="nav-item" onclick="showPage('investments')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          <span>Investments</span>
        </a>
        <a class="nav-item" href="../KYC/kyc-requests.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          <span>KYC Approval</span>
        </a>
        <a class="nav-item" href="viewValuationRequest.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>
          <span>Valuation Requests</span>
        </a>
        <a class="nav-item" onclick="showPage('support')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
          <span>Support & Tickets</span>
        </a>
        <a class="nav-item" onclick="showPage('settings')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span>Settings</span>
        </a>
        <a class="nav-item" href="../logout.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span>Logout</span>
        </a>
      </nav>
    </aside>

    <main class="main-content">
      <header class="top-nav">
        <button class="icon-btn mobile-menu-btn" onclick="toggleMobileSidebar()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <h1 class="page-title" id="pageTitle">Dashboard</h1>
        <div class="top-nav-right">
          <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search...">
          </div>
          <button class="icon-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notification-badge">3</span>
          </button>
          <button class="profile-btn">
            <div class="avatar">AD</div>
            <div class="profile-info">
              <div class="profile-name"><?php echo $_SESSION['admin_name'] ?></div>
              <div class="profile-role">Super Admin</div>
            </div>
          </button>
        </div>
      </header>

      <div class="page-content">
        <div class="page active" id="page-dashboard">
          <div class="stats-grid">
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header">
                  <span class="stat-label">Total Users</span>
                  <div class="stat-icon" style="background: rgba(20, 184, 166, 0.1); color: var(--primary);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                  </div>
                </div>
                <div class="stat-value"><?php echo number_format($grand_total_users); ?></div>
                <div class="stat-trend trend-up">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                  +12% <span style="color: var(--muted-foreground)">vs last month</span>
                </div>
              </div>
            </div>
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header">
                  <span class="stat-label">Entrepreneurs</span>
                  <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                  </div>
                </div>
                <div class="stat-value"><?php echo number_format($total_ent); ?></div>
                <div class="stat-trend trend-up">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                  +8% <span style="color: var(--muted-foreground)">vs last month</span>
                </div>
              </div>
            </div>
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header">
                  <span class="stat-label">Investors</span>
                  <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                  </div>
                </div>
                <div class="stat-value"><?php echo number_format($total_inv); ?></div>
                <div class="stat-trend trend-up">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                  +15% <span style="color: var(--muted-foreground)">vs last month</span>
                </div>
              </div>
            </div>
            <div class="card stat-card">
            <div class="card-content">
              <div class="stat-header">
                <span class="stat-label">Total Pitches</span>
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                  </svg>
                </div>
              </div>
              
               <div class="stat-value"><?php echo number_format($total_pitches); ?></div>
    
                <div class="stat-trend <?php echo $trend_color_class; ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                  </svg>
                  <?php echo $trend_sign . $pitch_growth_formatted; ?>% 
                  <span style="color: var(--muted-foreground)">vs last month</span>
                </div>
              </div>
            </div>
              <div class="card stat-card">
            <div class="card-content">
              <div class="stat-header">
                <span class="stat-label">Pending Pitches</span>
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                  </svg>
                </div>
              </div>
    
              <div class="stat-value"><?php echo number_format($total_pending); ?></div>
              
              <div class="stat-trend <?php echo $pending_trend_class; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <?php if($pending_growth >= 0): ?>
                      <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                  <?php else: ?>
                      <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                  <?php endif; ?>
                </svg>
                <?php echo $pending_trend_sign . $pending_growth_formatted; ?>% 
                <span style="color: var(--muted-foreground)">vs last month</span>
              </div>
            </div>
          </div>
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header">
                  <span class="stat-label">Approved Pitches</span>
                  <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                      <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                  </div>
                </div>
                
                <div class="stat-value"><?php echo number_format($total_approved); ?></div>
                
                <div class="stat-trend <?php echo $approved_trend_class; ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <?php if($approved_growth >= 0): ?>
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <?php else: ?>
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                    <?php endif; ?>
                  </svg>
                  <?php echo $approved_trend_sign . $approved_growth_formatted; ?>% 
                  <span style="color: var(--muted-foreground)">vs last month</span>
                </div>
              </div>
            </div>
           <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header">
                  <span class="stat-label">Total Investments</span>
                  <div class="stat-icon" style="background: rgba(20, 184, 166, 0.1); color: var(--primary);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                      <polyline points="17 6 23 6 23 12"/>
                    </svg>
                  </div>
                </div>
                
                <div class="stat-value"><?php echo $total_investment_display; ?></div>
                
                <div class="stat-trend <?php echo $inv_trend_class; ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <?php if($inv_growth >= 0): ?>
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <?php else: ?>
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                    <?php endif; ?>
                  </svg>
                  <?php echo $inv_trend_sign . $inv_growth_formatted; ?>% 
                  <span style="color: var(--muted-foreground)">vs last month</span>
                </div>
              </div>
            </div>
            <div class="card stat-card">
  <div class="card-content">
    <div class="stat-header">
      <span class="stat-label">Platform Revenue</span>
      <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="1" y="4" width="22" height="16" rx="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </div>
    </div>
    
    <div class="stat-value"><?php echo $total_revenue_display; ?></div>
    
    <div class="stat-trend <?php echo $revenue_trend_class; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <?php if($revenue_growth >= 0): ?>
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
        <?php else: ?>
            <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
        <?php endif; ?>
      </svg>
      <?php echo $revenue_trend_sign . $revenue_growth_formatted; ?>% 
      <span style="color: var(--muted-foreground)">vs last month</span>
    </div>
  </div>
</div>          
</div>

          <div class="charts-grid">
  <div class="card">
    <div class="card-header"><h3 class="card-title">Monthly Investment Trends</h3></div>
    <div class="card-content">
      <div class="chart-container">
        <canvas id="investmentChart"></canvas>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Recent Pitches</h3></div>
    <div class="card-content">
      <?php if ($recent_pitches_res && $recent_pitches_res->num_rows > 0): ?>
        <?php while($pitch = $recent_pitches_res->fetch_assoc()): ?>
          <?php 
            // Determine Status Badge Logic
            if ($pitch['is_approved'] == 1) {
                $status_label = 'Approved';
                $badge_class = 'badge-success';
            } elseif ($pitch['is_approved'] == 2) { // Assuming 2 is rejected
                $status_label = 'Rejected';
                $badge_class = 'badge-destructive';
            } else {
                $status_label = 'Pending';
                $badge_class = 'badge-warning';
            }

            // Format Amount (e.g., 5000000 -> ₹50 L or ₹5 Cr)
            $amount = $pitch['funding_goal'];
            if ($amount >= 10000000) {
                $fmt_amount = '₹' . number_format($amount / 10000000, 1) . ' Cr';
            } elseif ($amount >= 100000) {
                $fmt_amount = '₹' . number_format($amount / 100000, 1) . ' L';
            } else {
                $fmt_amount = '₹' . number_format($amount);
            }
          ?>
          <div class="activity-item">
            <div class="activity-info">
              <h4><?php echo htmlspecialchars($pitch['startup_name']); ?></h4>
              <p><?php echo htmlspecialchars($pitch['entrepreneur_name']); ?></p>
            </div>
            <div class="activity-right">
              <span class="activity-amount"><?php echo $fmt_amount; ?></span>
              <span class="badge <?php echo $badge_class; ?>"><?php echo $status_label; ?></span>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p style="text-align: center; color: var(--muted-foreground); padding: 20px;">No recent pitches found.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

          <div class="card">
            <div class="card-header"><h3 class="card-title">Recent Transactions</h3></div>
            <div class="card-content">
              <div class="activity-item">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="txn-icon credit"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg></div>
                  <div class="activity-info"><h4>Wallet Top-up</h4><p>Ananya Gupta • Investor</p></div>
                </div>
                <div class="activity-right"><span class="activity-amount" style="color: var(--success);">+₹5,00,000</span><span style="font-size: 12px; color: var(--muted-foreground);">2024-01-20</span></div>
              </div>
              <div class="activity-item">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="txn-icon debit"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg></div>
                  <div class="activity-info"><h4>Pitch Submission Fee</h4><p>Rahul Sharma • Entrepreneur</p></div>
                </div>
                <div class="activity-right"><span class="activity-amount" style="color: var(--destructive);">-₹50,000</span><span style="font-size: 12px; color: var(--muted-foreground);">2024-01-19</span></div>
              </div>
              <div class="activity-item">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="txn-icon debit"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg></div>
                  <div class="activity-info"><h4>Investment - TechVenture AI</h4><p>Rajesh Mehta • Investor</p></div>
                </div>
                <div class="activity-right"><span class="activity-amount" style="color: var(--destructive);">-₹25,00,000</span><span style="font-size: 12px; color: var(--muted-foreground);">2024-01-18</span></div>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-pitches">
          <div class="card" style="margin-bottom: 24px;">
            <div class="card-content">
              <div class="filter-bar">
                <div class="input-group">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                  <input type="text" class="input-icon" placeholder="Search by startup, founder, or ID..." id="pitchSearch" oninput="filterPitches()">
                </div>
                <select id="pitchStatusFilter" onchange="filterPitches()">
                  <option value="all">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="card-title">All Pitches (<span id="pitchCount">8</span>)</h3></div>
            <div class="card-content">
              <div class="table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Pitch ID</th>
                      <th>Startup Name</th>
                      <th>Founder</th>
                      <th>Category</th>
                      <th>Funding Ask</th>
                      <th>AI Warzone</th>
                      <th>Status</th>
                      <th>Date</th>
                      <th style="text-align: right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="pitchesTable"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-entrepreneurs">
          <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="card stat-card"><div class="card-content"><div class="stat-value"><?php echo $ent_stats['total'] ?? 0; ?></div><p class="stat-label">Total Entrepreneurs</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--success);"><?php echo $ent_stats['verified'] ?? 0; ?></div><p class="stat-label">KYC Verified</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--warning);"><?php echo $ent_stats['pending'] ?? 0; ?></div><p class="stat-label">KYC Pending</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--destructive);"><?php echo $ent_stats['suspended'] ?? 0; ?></div><p class="stat-label">Suspended</p></div></div>
          </div>

          <div class="card" style="margin-bottom: 24px;">
            <div class="card-content">
              <div class="input-group">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="input-icon" placeholder="Search by name or email..." onkeyup="filterTable('entrepreneursTable', this.value)">
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="card-title">All Entrepreneurs</h3></div>
            <div class="card-content">
              <div class="table-container">
                <table>
                  <thead>
                    <tr><th>Name</th><th>Email</th><th>KYC Status</th><th>Wallet Balance</th><th>Total Pitches</th><th>Account Status</th><th style="text-align: right;">Actions</th></tr>
                  </thead>
                  <tbody id="entrepreneursTable"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-investors">
          <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="card stat-card"><div class="card-content"><div class="stat-value"><?php echo $inv_stats['total'] ?? 0; ?></div><p class="stat-label">Total Investors</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--success);"><?php echo $inv_stats['verified'] ?? 0; ?></div><p class="stat-label">KYC Verified</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--primary);">0</div><p class="stat-label">Total Investments</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value"><?php echo $inv_stats['total_bids'] ?? 0; ?></div><p class="stat-label">Bids Purchased</p></div></div>
          </div>

          <div class="card" style="margin-bottom: 24px;">
            <div class="card-content">
              <div class="input-group">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="input-icon" placeholder="Search by name or email..." onkeyup="filterTable('investorsTable', this.value)">
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="card-title">All Investors</h3></div>
            <div class="card-content">
              <div class="table-container">
                <table>
                  <thead>
                    <tr><th>Name</th><th>Email</th><th>KYC Status</th><th>Wallet Balance</th><th>Total Investments</th><th>Bids Purchased</th><th>Status</th><th style="text-align: right;">Actions</th></tr>
                  </thead>
                  <tbody id="investorsTable"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-wallet">
          <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="card stat-card wallet-hero">
              <div class="card-content">
                <div class="stat-header"><span class="stat-label">Platform Balance</span><div class="stat-icon" style="background: rgba(20, 184, 166, 0.1); color: var(--primary);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div></div>
                <div class="stat-value" style="color: var(--primary);"><?php echo formatRupees($admin_wallet_balance); ?></div>
              </div>
            </div>
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header"><span class="stat-label">Pending Settlements</span><div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg></div></div>
                <div class="stat-value" style="color: var(--warning);"><?php echo formatRupees($pending_settlements); ?></div>
              </div>
            </div>
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header"><span class="stat-label">Today's Credits</span><div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg></div></div>
                <div class="stat-value" style="color: var(--success);"><?php echo formatRupees($today_credits); ?></div>
              </div>
            </div>
            <div class="card stat-card">
              <div class="card-content">
                <div class="stat-header"><span class="stat-label">Today's Debits</span><div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--destructive);"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg></div></div>
                <div class="stat-value" style="color: var(--destructive);"><?php echo formatRupees($today_debits); ?></div>
              </div>
            </div>
          </div>

          <div class="card" style="margin-bottom: 24px;">
            <div class="card-content">
              <div class="filter-bar">
                <div class="input-group">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                  <input type="text" class="input-icon" placeholder="Search by user, ID, or reason...">
                </div>
                <select><option>All Types</option><option>Credit</option><option>Debit</option></select>
                <select><option>All Status</option><option>Completed</option><option>Pending</option><option>Failed</option></select>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="card-title">Transaction History (7)</h3></div>
            <div class="card-content">
              <div class="table-container">
                <table>
                  <thead><tr><th>Transaction ID</th><th>User Type</th><th>User Name</th><th>Type</th><th>Amount</th><th>Reason</th><th>Status</th><th>Date</th></tr></thead>
                  <tbody id="transactionsTable"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-bids">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="font-size: 18px; font-weight: 600;">Bid Packages</h2>
            <button class="btn btn-primary" onclick="openModal('packageModal')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Package</button>
          </div>
          <div class="packages-grid" id="packagesGrid"></div>
        </div>

        <div class="page" id="page-investments">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Investment Tracking</h3></div>
            <div class="card-content">
              <div class="table-container">
                <table>
                  <thead><tr><th>Investment ID</th><th>Investor</th><th>Pitch</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                  <tbody id="investmentsTable"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-analytics">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div><h2 style="font-size: 18px; font-weight: 600;">Platform Analytics</h2><p style="color: var(--muted-foreground); font-size: 14px;">Comprehensive overview of platform performance</p></div>
            <button class="btn btn-outline"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export to CSV</button>
          </div>
          <div class="charts-grid">
            <div class="card"><div class="card-header"><h3 class="card-title">Monthly Investment Trends</h3></div><div class="card-content"><div class="chart-container"><canvas id="analyticsLineChart"></canvas></div></div></div>
            <div class="card"><div class="card-header"><h3 class="card-title">Category-wise Pitch Distribution</h3></div><div class="card-content"><div class="chart-container"><canvas id="categoryBarChart"></canvas></div></div></div>
            <div class="card"><div class="card-header"><h3 class="card-title">Pitch Approval Ratio</h3></div><div class="card-content"><div class="chart-container"><canvas id="approvalPieChart"></canvas></div></div></div>
            <div class="card"><div class="card-header"><h3 class="card-title">User Growth Over Time</h3></div><div class="card-content"><div class="chart-container"><canvas id="userGrowthChart"></canvas></div></div></div>
          </div>
          <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--primary);">₹405.5 Cr</div><p class="stat-label">Total Yearly Investments</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: #0ea5e9;">221</div><p class="stat-label">Total Pitches</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--success);">87.0%</div><p class="stat-label">Pitch Approval Rate</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value">2,730</div><p class="stat-label">New Users This Year</p></div></div>
          </div>
        </div>

        <div class="page" id="page-support">
          <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="card stat-card"><div class="card-content"><div class="stat-value">4</div><p class="stat-label">Total Tickets</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--destructive);">2</div><p class="stat-label">Open</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--warning);">1</div><p class="stat-label">In Progress</p></div></div>
            <div class="card stat-card"><div class="card-content"><div class="stat-value" style="color: var(--success);">1</div><p class="stat-label">Resolved</p></div></div>
          </div>

          <div class="grid-3">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Tickets</h3>
                <div style="display: flex; gap: 8px; margin-top: 12px;">
                  <select style="font-size: 12px; padding: 4px 8px;"><option>All Status</option><option>Open</option><option>In Progress</option><option>Resolved</option></select>
                  <select style="font-size: 12px; padding: 4px 8px;"><option>All Priority</option><option>High</option><option>Medium</option><option>Low</option></select>
                </div>
              </div>
              <div class="card-content">
                <div class="ticket-list" id="ticketList"></div>
              </div>
            </div>
            <div class="card" id="ticketChat">
              <div class="chat-container">
                <div class="chat-header">
                  <div class="chat-subject" id="chatSubject">Select a ticket</div>
                  <div class="chat-meta" id="chatMeta"></div>
                </div>
                <div class="chat-messages" id="chatMessages">
                  <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <p>Select a ticket to view conversation</p>
                  </div>
                </div>
                <div class="chat-input">
                  <input type="text" placeholder="Type your reply...">
                  <button class="btn btn-primary btn-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="page" id="page-settings">
          <div class="card settings-section">
            <div class="card-content">
              <div class="settings-header"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg><div><div class="settings-title">Commission Settings</div><div class="settings-desc">Configure platform commission on investments</div></div></div>
              <div class="grid-2">
                <div class="form-group">
                  <label class="form-label">Platform Commission (%)</label>
                  <input type="number" value="5">
                  <p style="font-size: 12px; color: var(--muted-foreground); margin-top: 4px;">Commission charged on each successful investment</p>
                </div>
              </div>
            </div>
          </div>

          <div class="card settings-section">
            <div class="card-content">
              <div class="settings-header"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><div><div class="settings-title">Wallet Configuration</div><div class="settings-desc">Set wallet limits and constraints</div></div></div>
              <div class="grid-2">
                <div class="form-group"><label class="form-label">Minimum Wallet Balance (₹)</label><input type="number" value="10000"></div>
                <div class="form-group"><label class="form-label">Maximum Wallet Balance (₹)</label><input type="number" value="50000000"></div>
              </div>
            </div>
          </div>

          <div class="card settings-section">
            <div class="card-content">
              <div class="settings-header"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg><div><div class="settings-title">Bid Configuration</div><div class="settings-desc">Configure bid purchase requirements</div></div></div>
              <div class="grid-2">
                <div class="form-group"><label class="form-label">Minimum Bid Purchase</label><input type="number" value="10"><p style="font-size: 12px; color: var(--muted-foreground); margin-top: 4px;">Minimum number of bids an investor must purchase</p></div>
              </div>
            </div>
          </div>

          <div class="card settings-section">
            <div class="card-content">
              <div class="settings-header"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="5" width="22" height="14" rx="7" ry="7"/><circle cx="16" cy="12" r="3"/></svg><div><div class="settings-title">Feature Toggles</div><div class="settings-desc">Enable or disable platform features</div></div></div>
              <div class="setting-row"><div><div class="setting-label">Maintenance Mode</div><div class="setting-help">Disable user access during maintenance</div></div><div class="toggle" onclick="this.classList.toggle('active')"></div></div>
              <div class="setting-row"><div><div class="setting-label">New Registrations</div><div class="setting-help">Allow new users to register</div></div><div class="toggle active" onclick="this.classList.toggle('active')"></div></div>
              <div class="setting-row"><div><div class="setting-label">KYC Required</div><div class="setting-help">Require KYC verification for transactions</div></div><div class="toggle active" onclick="this.classList.toggle('active')"></div></div>
              <div class="setting-row"><div><div class="setting-label">Auto-approve Verified KYC</div><div class="setting-help">Automatically approve users with verified documents</div></div><div class="toggle" onclick="this.classList.toggle('active')"></div></div>
            </div>
          </div>

          <div class="card settings-section">
            <div class="card-content">
              <div class="settings-header"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><div><div class="settings-title">Admin Role Permissions</div><div class="settings-desc">Manage admin roles and access levels</div></div></div>
              <div class="table-container">
                <table>
                  <thead><tr><th>Role Name</th><th>Permissions</th><th>Users</th><th style="text-align: right;">Actions</th></tr></thead>
                  <tbody>
                    <tr><td style="font-weight: 500;">Super Admin</td><td><span class="badge badge-outline">all</span></td><td>2</td><td style="text-align: right;"><button class="btn btn-ghost btn-sm">Edit</button></td></tr>
                    <tr><td style="font-weight: 500;">Finance Manager</td><td><span class="badge badge-outline">wallet</span> <span class="badge badge-outline">transactions</span> <span class="badge badge-outline">investments</span></td><td>3</td><td style="text-align: right;"><button class="btn btn-ghost btn-sm">Edit</button></td></tr>
                    <tr><td style="font-weight: 500;">Support Executive</td><td><span class="badge badge-outline">tickets</span> <span class="badge badge-outline">users</span></td><td>5</td><td style="text-align: right;"><button class="btn btn-ghost btn-sm">Edit</button></td></tr>
                    <tr><td style="font-weight: 500;">Content Moderator</td><td><span class="badge badge-outline">pitches</span> <span class="badge badge-outline">users</span></td><td>4</td><td style="text-align: right;"><button class="btn btn-ghost btn-sm">Edit</button></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div style="text-align: right;"><button class="btn btn-primary" onclick="showToast('Settings Saved', 'Platform settings have been updated successfully.')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes</button></div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal-overlay" id="viewPitchModal">
    <div class="modal" style="max-width: 600px;">
      <div class="modal-header">
        <div><h3 class="modal-title" id="modalPitchName"></h3><p class="modal-desc" id="modalPitchId"></p></div>
        <button class="btn btn-ghost btn-icon" onclick="closeModal('viewPitchModal')"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body" id="modalPitchContent"></div>
      <div class="modal-footer" id="modalPitchFooter"></div>
    </div>
  </div>

  <div class="modal-overlay" id="confirmModal">
    <div class="modal" style="max-width: 400px;">
      <div class="modal-header">
        <div><h3 class="modal-title" id="confirmTitle"></h3><p class="modal-desc" id="confirmDesc"></p></div>
        <button class="btn btn-ghost btn-icon" onclick="closeModal('confirmModal')"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
        <button class="btn" id="confirmBtn" onclick="executeConfirmAction()">Confirm</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="packageModal">
    <div class="modal">
      <div class="modal-header">
        <div><h3 class="modal-title">Add New Package</h3><p class="modal-desc">Create a new bid package</p></div>
        <button class="btn btn-ghost btn-icon" onclick="closeModal('packageModal')"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Package Name</label><input type="text" placeholder="Enter package name"></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Price (₹)</label><input type="number" placeholder="10000"></div>
          <div class="form-group"><label class="form-label">Bid Count</label><input type="number" placeholder="10"></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea placeholder="Package description..."></textarea></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('packageModal')">Cancel</button>
        <button class="btn btn-primary" onclick="closeModal('packageModal'); showToast('Package Created', 'New bid package has been created successfully.')">Create Package</button>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>
  <form id="pitchActionForm" method="POST" style="display:none;">
    <input type="hidden" name="action_type" id="formActionType">
    <input type="hidden" name="pitch_db_id" id="formPitchDbId">
</form>

 <script>
    // ===========================================
    // 1. INJECT REAL DATA FROM PHP
    // ===========================================
    // We use PHP to echo the JSON directly into these JS variables.
    // If the PHP array is empty, it defaults to an empty JS array [].
    const pitches = <?php echo !empty($pitches_data) ? json_encode($pitches_data) : '[]'; ?>;
    const entrepreneurs = <?php echo !empty($entrepreneurs_data) ? json_encode($entrepreneurs_data) : '[]'; ?>;
    const investors = <?php echo !empty($investors_data) ? json_encode($investors_data) : '[]'; ?>;

    // Chart Data Injection
    const chartLabels = <?php echo !empty($json_chart_labels) ? $json_chart_labels : '[]'; ?>;
    const chartData = <?php echo !empty($json_chart_data) ? $json_chart_data : '[]'; ?>;

    // --- DATA FOR OTHER SECTIONS ---
    const transactions = <?php echo !empty($transactions_data) ? json_encode($transactions_data) : '[]'; ?>;

    const bidPackages = [
      { id: "BID001", name: "Starter Pack", price: 10000, bidCount: 10, status: "active", description: "Perfect for new investors" },
      { id: "BID002", name: "Growth Pack", price: 45000, bidCount: 50, status: "active", description: "Best value for active investors" },
      { id: "BID003", name: "Premium Pack", price: 80000, bidCount: 100, status: "active", description: "For serious investors" },
      { id: "BID004", name: "Enterprise Pack", price: 150000, bidCount: 200, status: "inactive", description: "Unlimited access" }
    ];

    const investmentsList = <?php echo !empty($investments_tracking_data) ? json_encode($investments_tracking_data) : '[]'; ?>;

    const tickets = [
      { id: "TKT001", subject: "Unable to submit pitch", userName: "Rahul Sharma", userType: "Entrepreneur", priority: "high", status: "open", createdAt: "2024-01-20 10:30", messages: [{ sender: "user", message: "I'm getting an error.", time: "10:30" }] },
      { id: "TKT002", subject: "KYC verification taking too long", userName: "Kavita Iyer", userType: "Investor", priority: "medium", status: "in_progress", createdAt: "2024-01-19 15:45", messages: [] }
    ];

    // ===========================================
    // 2. HELPER FUNCTIONS
    // ===========================================
    function formatCurrency(amount) {
        if (amount >= 10000000) return '₹' + (amount / 10000000).toFixed(2) + ' Cr';
        if (amount >= 100000) return '₹' + (amount / 100000).toFixed(2) + ' L';
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(amount);
    }

    function getStatusBadge(status) {
        const classes = { 
            pending: 'badge-warning', approved: 'badge-success', rejected: 'badge-destructive', 
            completed: 'badge-success', active: 'badge-success', suspended: 'badge-destructive', 
            verified: 'badge-success', open: 'badge-destructive', in_progress: 'badge-warning', 
            resolved: 'badge-success', inactive: 'badge-secondary' 
        };
        const s = status || 'pending';
        return `<span class="badge ${classes[s] || 'badge-secondary'}">${s.replace('_', ' ')}</span>`;
    }

    function getPriorityBadge(priority) {
        const classes = { high: 'badge-destructive', medium: 'badge-warning', low: 'badge-secondary' };
        return `<span class="badge ${classes[priority] || 'badge-secondary'}">${priority}</span>`;
    }

    // ===========================================
    // 3. RENDER PITCH MANAGEMENT (Connected to DB)
    // ===========================================
    function renderPitchesTable(data) {
        const tbody = document.getElementById('pitchesTable');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No pitches found in database.</td></tr>';
            const countEl = document.getElementById('pitchCount');
            if(countEl) countEl.innerText = "0";
            return;
        }

        tbody.innerHTML = data.map(p => `
            <tr>
                <td style="font-family: monospace; font-size: 13px;">${p.id}</td>
                <td style="font-weight: 500;">${p.startupName}</td>
                <td>
                    <div>${p.founderName}</div>
                    <div style="font-size:11px; color:#94a3b8;">${p.founderEmail}</div>
                </td>
                <td><span class="badge badge-outline">${p.category}</span></td>
                <td>${formatCurrency(p.fundingAsk)}</td>
                <td>${getStatusBadge(p.status)}</td>
                <td style="color:#94a3b8;">${p.submissionDate}</td>
                <td style="text-align: right;">
                    <button class="btn btn-ghost btn-icon" onclick="viewPitch('${p.id}')" title="View Details">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    
                    ${p.status === 'pending' ? `
                        <button class="btn btn-ghost btn-icon" 
                                style="color: #22c55e; ${p.valuationStatus === 'pending' ? 'opacity:0.3; cursor:not-allowed;' : ''}" 
                                onclick="${p.valuationStatus === 'pending' ? "showToast('Valuation Pending', 'You must verify the valuation in View Details before approving.', 'destructive')" : `confirmAction('approve', ${p.db_id}, '${p.startupName}')`}" 
                                title="${p.valuationStatus === 'pending' ? 'Verify Valuation First' : 'Approve'}">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </button>
                        <button class="btn btn-ghost btn-icon" 
                                style="color: #ef4444; ${p.valuationStatus === 'pending' ? 'opacity:0.3; cursor:not-allowed;' : ''}" 
                                onclick="${p.valuationStatus === 'pending' ? "showToast('Valuation Pending', 'You must verify/reject the valuation in View Details before taking action.', 'destructive')" : `confirmAction('reject', ${p.db_id}, '${p.startupName}')`}" 
                                title="${p.valuationStatus === 'pending' ? 'Verify Valuation First' : 'Reject'}">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `).join('');
        
        const countEl = document.getElementById('pitchCount');
        if(countEl) countEl.innerText = data.length;
    }

    // ===========================================
    // 4. ACTION HANDLING (Submits to PHP)
    // ===========================================
    let currentConfirmAction = null;

    function confirmAction(type, db_id, name) {
        currentConfirmAction = { type, db_id };
        
        let title = "Confirm Action";
        let desc = `Are you sure you want to proceed with "${type}" for ${name}?`;
        let btnText = "Confirm";
        let btnClass = "btn-primary";

        if (type === 'approve') {
            title = "Approve Pitch";
            desc = `Are you sure you want to approve the pitch "${name}"?`;
            btnText = "Approve";
            btnClass = "btn-success";
        } else if (type === 'reject') {
            title = "Reject Pitch";
            desc = `Are you sure you want to reject the pitch "${name}"?`;
            btnText = "Reject";
            btnClass = "btn-destructive";
        } else if (type === 'release_escrow') {
            title = "Release Funds";
            desc = `Are you sure you want to release funds for "${name}" to the entrepreneur? (5% commission will be deducted)`;
            btnText = "Release Now";
            btnClass = "btn-primary";
        } else if (type === 'refund_escrow') {
            title = "Refund Investment";
            desc = `Are you sure you want to REFUND the investment for "${name}"? Funds will be returned to the investor's wallet.`;
            btnText = "Process Refund";
            btnClass = "btn-destructive";
        }
        
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmDesc').innerText = desc;
        
        const btn = document.getElementById('confirmBtn');
        btn.className = `btn ${btnClass}`;
        btn.innerText = btnText;
        
        document.getElementById('confirmModal').classList.add('active');
    }

    function executeConfirmAction() {
        if(!currentConfirmAction) return;

        // Populate the hidden form with the stored data
        document.getElementById('formActionType').value = currentConfirmAction.type;
        document.getElementById('formPitchDbId').value = currentConfirmAction.db_id;
        
        // Submit the form (This reloads the page via PHP)
        document.getElementById('pitchActionForm').submit();
        
        // Modal will close automatically when page reloads, but we can close it visually too
        document.getElementById('confirmModal').classList.remove('active');
    }

    // ===========================================
    // 5. VIEW DETAILS MODAL
    // ===========================================
    function viewPitch(id) {
        const p = pitches.find(x => x.id === id);
        if(!p) return;
        
        document.getElementById('modalPitchName').innerText = p.startupName;
        document.getElementById('modalPitchContent').innerHTML = `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div><small style="color:#94a3b8">Founder</small><div style="font-weight:500">${p.founderName}</div></div>
                <div><small style="color:#94a3b8">Category</small><div style="font-weight:500">${p.category}</div></div>
                <div><small style="color:#94a3b8">Ask</small><div style="font-weight:500">${formatCurrency(p.fundingAsk)}</div></div>
                <div><small style="color:#94a3b8">Status</small><div>${getStatusBadge(p.status)}</div></div>
            </div>
            <div><small style="color:#94a3b8">Description</small><p style="margin-top:5px">${p.description}</p></div>
        `;
        document.getElementById('viewPitchModal').classList.add('active');
    }

    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // ===========================================
    // 6. FILTERS & NAVIGATION
    // ===========================================
    function filterPitches() {
        const term = document.getElementById('pitchSearch').value.toLowerCase();
        const status = document.getElementById('pitchStatusFilter').value;
        
        const filtered = pitches.filter(p => {
            const matchesSearch = p.startupName.toLowerCase().includes(term) || p.founderName.toLowerCase().includes(term) || p.id.toLowerCase().includes(term);
            const matchesStatus = status === 'all' || p.status === status;
            return matchesSearch && matchesStatus;
        });
        renderPitchesTable(filtered);
    }

    function showPage(id) {
        document.querySelectorAll('.page').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        
        const page = document.getElementById('page-' + id);
        if(page) page.classList.add('active');
        
        const nav = document.querySelector(`.nav-item[onclick="showPage('${id}')"]`);
        if(nav) nav.classList.add('active');
        
        // Re-render tables when switching to tabs to ensure fresh data
        if(id === 'pitches') renderPitchesTable(pitches);
        if(id === 'entrepreneurs') renderEntrepreneursTable();
        if(id === 'investors') renderInvestorsTable();
        if(id === 'wallet') renderTransactionsTable();
        if(id === 'investments') renderInvestmentsTable();
        if(id === 'analytics') initAnalyticsCharts();
    }

    // ===========================================
    // 7. OTHER TABLES (Ent/Inv/Trans)
    // ===========================================
    function renderEntrepreneursTable() {
        const tbody = document.getElementById('entrepreneursTable');
        if(!tbody) return;
        if (!entrepreneurs || entrepreneurs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No entrepreneurs found.</td></tr>';
            return;
        }
        tbody.innerHTML = entrepreneurs.map(u => `
            <tr>
                <td><div style="display: flex; align-items: center; gap: 12px;"><div class="avatar avatar-orange" style="width: 32px; height: 32px; font-size: 11px;">${u.name ? u.name.charAt(0).toUpperCase() : 'U'}</div><span style="font-weight: 500;">${u.name}</span></div></td>
                <td style="color: #94a3b8;">${u.email}</td>
                <td>${getStatusBadge(u.kycStatus)}</td>
                <td>${formatCurrency(u.walletBalance)}</td>
                <td>${u.totalPitches}</td>
                <td>${getStatusBadge(u.accountStatus)}</td>
                <td style="text-align: right;"><button class="btn btn-ghost btn-icon">•••</button></td>
            </tr>
        `).join('');
    }

    function renderInvestorsTable() {
        const tbody = document.getElementById('investorsTable');
        if(!tbody) return;
        if (!investors || investors.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">No investors found.</td></tr>';
            return;
        }
        tbody.innerHTML = investors.map(u => `
            <tr>
                <td><div style="display: flex; align-items: center; gap: 12px;"><div class="avatar avatar-green" style="width: 32px; height: 32px; font-size: 11px;">${u.name ? u.name.charAt(0).toUpperCase() : 'U'}</div><span style="font-weight: 500;">${u.name}</span></div></td>
                <td style="color: #94a3b8;">${u.email}</td>
                <td>${getStatusBadge(u.kycStatus)}</td>
                <td>${formatCurrency(u.walletBalance)}</td>
                <td>${u.totalInvestments}</td>
                <td>${u.bidsPurchased}</td>
                <td>${getStatusBadge(u.accountStatus)}</td>
                <td style="text-align: right;"><button class="btn btn-ghost btn-icon">•••</button></td>
            </tr>
        `).join('');
    }

    function renderTransactionsTable() {
        const tbody = document.getElementById('transactionsTable');
        if(!tbody) return;
        tbody.innerHTML = transactions.map(t => `
            <tr>
                <td style="font-family: monospace; font-size: 13px;">${t.id}</td>
                <td><span class="badge badge-outline">${t.userType}</span></td>
                <td style="font-weight: 500;">${t.userName}</td>
                <td><span style="color: ${t.type === 'credit' ? '#22c55e' : '#ef4444'};">${t.type === 'credit' ? '↓' : '↑'} ${t.type}</span></td>
                <td style="font-weight: 500; color: ${t.type === 'credit' ? '#22c55e' : '#ef4444'};">${t.type === 'credit' ? '+' : '-'}${formatCurrency(t.amount)}</td>
                <td>${t.reason}</td>
                <td>${getStatusBadge(t.status)}</td>
                <td style="color: #94a3b8;">${t.date}</td>
            </tr>
        `).join('');
    }

    function renderPackagesGrid() {
      const grid = document.getElementById('packagesGrid');
      if(!grid) return;
      
      grid.innerHTML = bidPackages.map(p => `
        <div class="card package-card ${p.status === 'inactive' ? 'inactive' : ''}">
          <div class="package-header">
            <div class="package-icon">
               <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
            <div class="toggle ${p.status === 'active' ? 'active' : ''}" onclick="this.classList.toggle('active')"></div>
          </div>
          
          <div class="package-title">${p.name}</div>
          <div class="package-desc">${p.description}</div>
          
          <div class="package-details">
            <div class="package-row">
                <span class="package-label">Price</span>
                <span class="package-value">${formatCurrency(p.price)}</span>
            </div>
            <div class="package-row">
                <span class="package-label">Bids Included</span>
                <span class="package-value">${p.bidCount}</span>
            </div>
            <div class="package-row">
                <span class="package-label">Price/Bid</span>
                <span class="package-value">${formatCurrency(p.price / p.bidCount)}</span>
            </div>
          </div>
          
          <button class="btn btn-outline" style="width: 100%; margin-top: 1rem; justify-content: center;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> 
            Edit Package
          </button>
        </div>
      `).join('');
    }
    function renderInvestmentsTable() {
        const tbody = document.getElementById('investmentsTable');
        if(!tbody) return;
        if (!investmentsList || investmentsList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">No investments found.</td></tr>';
            return;
        }
        tbody.innerHTML = investmentsList.map(i => `
            <tr>
                <td style="font-family: monospace; font-size: 13px;">${i.id}</td>
                <td style="font-weight: 500;">${i.investorName}</td>
                <td>${i.pitchName}</td>
                <td style="font-weight: 500; color: #22c55e;">${formatCurrency(i.amount)}</td>
                <td style="color: #94a3b8;">${i.date}</td>
                <td>${getStatusBadge(i.status)}</td>
                <td>
                    ${i.status === 'escrow' ? `
                        <div style="display:flex; gap:4px;">
                            <button class="btn btn-primary btn-sm" onclick="confirmAction('release_escrow', ${i.db_id}, '${i.pitchName}')" style="font-size: 10px; padding: 4px 6px;">Release</button>
                            <button class="btn btn-destructive btn-sm" onclick="confirmAction('refund_escrow', ${i.db_id}, '${i.pitchName}')" style="font-size: 10px; padding: 4px 6px;">Refund</button>
                        </div>
                    ` : '<span style="color:#94a3b8; font-size:11px;">Processed</span>'}
                </td>
            </tr>
        `).join('');
    }

    // ===========================================
    // 8. CHART INITIALIZATION
    // ===========================================
    function initDashboardChart() {
        const ctx = document.getElementById('investmentChart').getContext('2d');
        // Use PHP injected variables
        const labels = chartLabels.length > 0 ? chartLabels : ['No Data'];
        const data = chartData.length > 0 ? chartData : [0];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{ 
                    label: 'Investment (Cr)', 
                    data: data, 
                    borderColor: '#14b8a6', 
                    backgroundColor: 'rgba(20, 184, 166, 0.1)', 
                    fill: true, 
                    tension: 0.4 
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        ticks: { callback: v => '₹' + v + 'Cr' },
                        grid: { color: '#334155' }
                    },
                    x: { grid: { display: false } }
                } 
            }
        });
    }

    function initAnalyticsCharts() {
        // Analytics placeholders
    }

    // ===========================================
    // 9. STARTUP (DOM LOAD)
    // ===========================================
    document.addEventListener('DOMContentLoaded', () => {
        renderPitchesTable(pitches);
        renderEntrepreneursTable();
        renderInvestorsTable();
        renderTransactionsTable();
        renderPackagesGrid();
        renderInvestmentsTable();
        
        // Render Tickets (using simple helper since not dynamic yet)
        const ticketList = document.getElementById('ticketList');
        if(ticketList) {
            ticketList.innerHTML = tickets.map((t, i) => `
                <div class="ticket-item" style="padding:10px; border-bottom:1px solid #334155; cursor:pointer;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="font-family:monospace; color:#94a3b8;">${t.id}</span>
                        ${getPriorityBadge(t.priority)}
                    </div>
                    <div style="font-weight:500;">${t.subject}</div>
                </div>
            `).join('');
        }

        initDashboardChart();
    });

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('active'); });
    });
</script>
</body>
</html>