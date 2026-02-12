<?php
session_start();
require_once '../db.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'entrepreneur') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Entrepreneur';

// --- 2. FETCH STATS ---
// Initial defaults
$stats = [
    'raised' => 0, 'goal' => 0, 'valuation' => 0, 'equity' => 0, 'progress' => 0,
    'investors' => 0, 'total_pitches' => 0, 'views' => 0, 
    'share_price' => 0, 'shares_issued' => 0, 'shares_remaining' => 0, 'total_shares' => 0,
    'latest_id' => 0, 'latest_title' => 'No Pitch Created', 'latest_tagline' => 'Create your first pitch to get started.',
    'status' => 'No Pitch', 'status_badge' => 'secondary',
    'kyc_status' => 'not_submitted',
    'escrow_balance' => 0
];

// Fetch Entrepreneur Share Data & KYC Status
$u_sql = "SELECT total_shares, available_shares, kyc_status FROM entrepreneurs WHERE id = ?";
if ($stmt = $conn->prepare($u_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $stats['total_shares'] = $res['total_shares'];
        // Note: shares_remaining is set to 0 by default. 
        // We will only populate it if a pitch exists (Shares in Round)
        // or we can use another variable for the global pool.
        $stats['pool_available'] = $res['available_shares']; 
        $stats['kyc_status'] = $res['kyc_status'] ?? 'not_submitted';
    }
}

// Fetch Latest Pitch
$p_sql = "SELECT * FROM pitches WHERE entrepreneur_id = ? ORDER BY created_at DESC LIMIT 1";
if ($stmt = $conn->prepare($p_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $p_res = $stmt->get_result()->fetch_assoc();
    if ($p_res) {
        $stats['latest_id'] = $p_res['id'];
        $stats['latest_title'] = $p_res['startup_name'];
        $stats['latest_tagline'] = $p_res['tagline'] ?? $p_res['description'];
        $stats['goal'] = $p_res['funding_goal'];
        $stats['raised'] = $p_res['amount_raised'];
        $stats['views'] = $p_res['views'];
        $stats['share_price'] = $p_res['share_price'] ?? 0;
        $stats['shares_issued'] = $p_res['shares_issued'] ?? 0;

        // Logic: Remaining In This Round = Shares Issued - Shares Sold
        // Shares Sold = Amount Raised / Share Price
        if ($stats['shares_issued'] > 0 && $stats['share_price'] > 0) {
             $shares_sold = floor($stats['raised'] / $stats['share_price']);
             $stats['shares_remaining'] = max(0, $stats['shares_issued'] - $shares_sold);
        } else {
             // If no shares issued in this pitch (how?), default to 0 for this metric
             $stats['shares_remaining'] = 0;
        }
        
        // Pitch Status Logic
        if ($p_res['is_approved']) {
            $stats['status'] = 'Live';
            $stats['status_badge'] = 'success';
        } else {
            $stats['status'] = ucfirst($p_res['status'] ?? 'Draft');
            $stats['status_badge'] = ($stats['status'] === 'Pending') ? 'warning' : 'secondary';
        }
        
        if ($stats['goal'] > 0) {
            $stats['progress'] = ($stats['raised'] / $stats['goal']) * 100;
        }
        if ($stats['total_shares'] > 0 && $stats['share_price'] > 0) {
            $stats['valuation'] = $stats['total_shares'] * $stats['share_price'];
             // Equity Offered = (Shares Allocated to Investors / Total Shares) * 100
             $stats['equity'] = ($stats['valuation'] > 0) ? ($stats['goal'] / $stats['valuation']) * 100 : 0;
        }
    }
}

// Fetch Investor Count (Unique investors for this entrepreneur)
$inv_sql = "SELECT COUNT(DISTINCT investor_id) as count FROM investments WHERE pitch_id IN (SELECT id FROM pitches WHERE entrepreneur_id = ?)";
if($stmt = $conn->prepare($inv_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['investors'] = $stmt->get_result()->fetch_assoc()['count'];
}

// Fetch Smart Escrow Balance
// Funds invested in this entrepreneur's pitches that are still in 'escrow' status
$escrow_sql = "SELECT SUM(i.amount) as escrow_total 
               FROM investments i 
               JOIN pitches p ON i.pitch_id = p.id 
               WHERE p.entrepreneur_id = ? AND i.payout_status = 'escrow'";
if ($stmt = $conn->prepare($escrow_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res_escrow = $stmt->get_result()->fetch_assoc();
    $stats['escrow_balance'] = $res_escrow['escrow_total'] ?? 0;
}

// Fetch Wallet Balance
$wallet_balance = 0.00;
$w_stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? AND user_role = 'entrepreneur'");
$w_stmt->bind_param("i", $user_id);
$w_stmt->execute();
$w_res = $w_stmt->get_result()->fetch_assoc();
if($w_res) $wallet_balance = floatval($w_res['balance']);

// Fetch Total Withdrawn (Real Data)
$withdrawn_total = 0.00;
$wd_sql = "SELECT SUM(amount) as total FROM wallet_transactions WHERE user_id = ? AND txn_type = 'debit'";
if ($stmt = $conn->prepare($wd_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wd_res = $stmt->get_result()->fetch_assoc();
    $withdrawn_total = floatval($wd_res['total'] ?? 0);
}

// Fetch Transaction History (Last 10)
$wallet_txns = [];
$txn_sql = "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
if ($stmt = $conn->prepare($txn_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $txn_res = $stmt->get_result();
    while ($row = $txn_res->fetch_assoc()) {
        $wallet_txns[] = $row;
    }
}

// Fetch Recent Investments (Top 5)
$investments_sql = "
    SELECT i.*, u.name as investor_name 
    FROM investments i 
    JOIN investors u ON i.investor_id = u.id 
    JOIN pitches p ON i.pitch_id = p.id
    WHERE p.entrepreneur_id = ? 
    ORDER BY i.created_at DESC LIMIT 5
";
$recent_investments = [];
if ($stmt = $conn->prepare($investments_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Fallback or join kyc table for image if needed, for now use name
        $row['profile_image'] = ''; 
        $recent_investments[] = $row;
    }
}

// Format Helper
function money($amount) {
    return '₹' . number_format($amount);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SmartPitchHub - Entrepreneur Dashboard</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <style>
      *,
      *::before,
      *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
      }

      :root {
        --background: hsl(230, 20%, 7%);
        --foreground: hsl(0, 0%, 98%);
        --card: hsl(230, 18%, 10%);
        --card-foreground: hsl(0, 0%, 98%);
        --primary: hsl(263, 70%, 76%);
        --primary-foreground: hsl(230, 20%, 7%);
        --secondary: hsl(230, 15%, 15%);
        --muted: hsl(230, 12%, 18%);
        --muted-foreground: hsl(230, 10%, 55%);
        --border: hsl(230, 15%, 18%);
        --success: hsl(142, 76%, 36%);
        --warning: hsl(38, 92%, 50%);
        --destructive: hsl(0, 72%, 51%);
        --sidebar-bg: hsl(230, 22%, 6%);
        --sidebar-border: hsl(230, 15%, 12%);
        --radius: 0.75rem;
        --gradient-primary: linear-gradient(
          135deg,
          hsl(263, 70%, 76%) 0%,
          hsl(280, 60%, 65%) 100%
        );
        --gradient-card: linear-gradient(
          145deg,
          hsl(230, 18%, 12%) 0%,
          hsl(230, 18%, 9%) 100%
        );
        --glow-primary: 0 0 30px hsla(263, 70%, 76%, 0.3);
        --glow-card: 0 0 40px hsla(263, 70%, 76%, 0.1);
      }

      .light {
        --background: hsl(0, 0%, 98%);
        --foreground: hsl(230, 20%, 10%);
        --card: hsl(0, 0%, 100%);
        --card-foreground: hsl(230, 20%, 10%);
        --primary: hsl(263, 70%, 50%);
        --primary-foreground: hsl(0, 0%, 100%);
        --secondary: hsl(230, 15%, 94%);
        --muted: hsl(230, 12%, 92%);
        --muted-foreground: hsl(230, 10%, 40%);
        --border: hsl(230, 15%, 88%);
        --sidebar-bg: hsl(230, 15%, 96%);
        --sidebar-border: hsl(230, 15%, 88%);
        --gradient-primary: linear-gradient(
          135deg,
          hsl(263, 70%, 50%) 0%,
          hsl(280, 60%, 45%) 100%
        );
        --gradient-card: linear-gradient(
          145deg,
          hsl(0, 0%, 100%) 0%,
          hsl(230, 15%, 98%) 100%
        );
        --glow-primary: 0 0 30px hsla(263, 70%, 50%, 0.2);
        --glow-card: 0 0 40px hsla(263, 70%, 50%, 0.05);
      }

      body {
        font-family: "Inter", sans-serif;
        background-color: var(--background);
        color: var(--foreground);
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
      }

      h1,
      h2,
      h3,
      h4,
      h5,
      h6 {
        font-family: "Space Grotesk", sans-serif;
      }

      .dashboard {
        display: flex;
        min-height: 100vh;
        width: 100%;
      }

      /* Sidebar */
      .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 260px;
        background: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        display: flex;
        flex-direction: column;
        z-index: 50;
        transition: transform 0.3s ease;
      }
      .sidebar-logo {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .logo-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .logo-icon svg {
        width: 20px;
        height: 20px;
        color: var(--primary-foreground);
      }
      .logo-text {
        font-family: "Space Grotesk", sans-serif;
        font-weight: 700;
        font-size: 1.25rem;
        color: var(--foreground);
      }
      .sidebar-nav {
        flex: 1;
        padding: 1rem 0.75rem;
        overflow-y: auto;
      }
      .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: var(--radius);
        color: var(--muted-foreground);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
      }
      .nav-item:hover {
        background: var(--muted);
        color: var(--foreground);
      }
      .nav-item.active {
        background: hsla(263, 70%, 76%, 0.1);
        color: var(--primary);
        border-left: 2px solid var(--primary);
      }
      .nav-item svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
      }
      .sidebar-bottom {
        padding: 1rem 0.75rem;
        border-top: 1px solid var(--sidebar-border);
      }
      .nav-item.logout {
        color: var(--destructive);
      }
      .upgrade-card {
        margin: 0.75rem;
        padding: 1rem;
        border-radius: 12px;
        background: hsla(263, 70%, 76%, 0.1);
        border: 1px solid hsla(263, 70%, 76%, 0.2);
      }
      .upgrade-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
      }
      .upgrade-header svg {
        width: 16px;
        height: 16px;
        color: var(--primary);
      }
      .upgrade-header span {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--foreground);
      }
      .upgrade-card p {
        font-size: 0.75rem;
        color: var(--muted-foreground);
        margin-bottom: 0.75rem;
      }
      .upgrade-btn {
        width: 100%;
        padding: 0.5rem;
        border-radius: var(--radius);
        background: var(--gradient-primary);
        color: var(--primary-foreground);
        font-size: 0.875rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease;
      }
      .upgrade-btn:hover {
        transform: scale(1.02);
      }

      /* Main Content */
      .main-content {
        flex: 1;
        margin-left: 260px;
      }
      .topbar {
        position: sticky;
        top: 0;
        z-index: 30;
        background: hsla(230, 20%, 7%, 0.8);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--border);
        padding: 1rem 1.5rem;
      }
      .light .topbar {
        background: hsla(0, 0%, 98%, 0.8);
      }
      .topbar-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }
      .topbar-title h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .topbar-title p {
        font-size: 0.875rem;
        color: var(--muted-foreground);
        margin-top: 0.125rem;
      }
      .topbar-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .icon-btn {
        padding: 0.625rem;
        border-radius: var(--radius);
        background: var(--card);
        border: none;
        cursor: pointer;
        color: var(--muted-foreground);
        transition: background 0.2s ease;
      }
      .icon-btn:hover {
        background: var(--muted);
      }
      .icon-btn svg {
        width: 20px;
        height: 20px;
      }
      .notification-btn {
        position: relative;
      }
      .notification-dot {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 8px;
        height: 8px;
        background: var(--primary);
        border-radius: 50%;
      }
      .user-menu {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        border-radius: var(--radius);
        cursor: pointer;
        transition: background 0.2s ease;
      }
      .user-menu:hover {
        background: var(--card);
      }
      .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid hsla(263, 70%, 76%, 0.3);
        object-fit: cover;
      }
      .user-info {
        text-align: left;
      }
      .user-info .name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--foreground);
      }
      .user-info .role {
        font-size: 0.75rem;
        color: var(--muted-foreground);
      }

      /* Dashboard Content */
      .dashboard-content {
        padding: 1.5rem;
        max-width: 1400px;
        margin: 0 auto;
      }
      .section-title {
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--foreground);
        margin-bottom: 1rem;
      }
      .metric-card {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        padding: 1.5rem;
        background: var(--gradient-card);
        box-shadow: var(--glow-card);
        transition: box-shadow 0.3s ease;
      }
      .metric-card:hover {
        box-shadow: var(--glow-primary);
      }
      .metric-card::before {
        content: "";
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity 0.3s ease;
        background: linear-gradient(
          135deg,
          hsla(263, 70%, 76%, 0.1) 0%,
          transparent 50%
        );
      }
      .metric-card:hover::before {
        opacity: 1;
      }

      /* KPI Grid */
      .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
      }
      .kpi-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
      }
      .kpi-icon {
        padding: 0.75rem;
        border-radius: 12px;
        background: hsla(263, 70%, 76%, 0.1);
      }
      .kpi-icon svg {
        width: 20px;
        height: 20px;
        color: var(--primary);
      }
      .kpi-trend {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
      }
      .kpi-trend.positive {
        color: var(--success);
      }
      .kpi-trend.negative {
        color: var(--destructive);
      }
      .kpi-trend svg {
        width: 16px;
        height: 16px;
      }
      .kpi-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
      }
      .kpi-badge.success {
        background: hsla(142, 76%, 36%, 0.2);
        color: var(--success);
      }
      .kpi-badge.warning {
        background: hsla(38, 92%, 50%, 0.2);
        color: var(--warning);
      }
      .kpi-badge.primary {
        background: hsla(263, 70%, 76%, 0.2);
        color: var(--primary);
      }
      .kpi-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--muted-foreground);
        margin-bottom: 0.25rem;
      }
      .kpi-value {
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .kpi-subtitle {
        font-size: 0.875rem;
        color: var(--muted-foreground);
        margin-top: 0.25rem;
      }

      /* Pitch Status */
      .pitch-section {
        margin-bottom: 2rem;
      }
      .pitch-card {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
      }
      @media (min-width: 1024px) {
        .pitch-card {
          flex-direction: row;
        }
      }
      .pitch-main {
        flex: 1;
      }
      .pitch-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }
      .pitch-logo {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .pitch-logo svg {
        width: 32px;
        height: 32px;
        color: var(--primary-foreground);
      }
      .pitch-info h3 {
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .pitch-info p {
        font-size: 0.875rem;
        color: var(--muted-foreground);
      }
      .pitch-badges {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
      }
      .pitch-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      @media (min-width: 768px) {
        .pitch-stats {
          grid-template-columns: repeat(4, 1fr);
        }
      }
      .stat-box {
        padding: 1rem;
        border-radius: var(--radius);
        background: hsla(230, 12%, 18%, 0.5);
      }
      .stat-box .label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--muted-foreground);
        margin-bottom: 0.25rem;
      }
      .stat-box .label svg {
        width: 16px;
        height: 16px;
      }
      .stat-box .value {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .stat-box .value.success {
        color: var(--success);
      }
      .feedback-card {
        width: 100%;
        padding: 1rem;
        border-radius: 12px;
        background: hsla(263, 70%, 76%, 0.05);
        border: 1px solid hsla(263, 70%, 76%, 0.2);
      }
      @media (min-width: 1024px) {
        .feedback-card {
          width: 320px;
        }
      }
      .feedback-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
      }
      .feedback-header svg {
        width: 16px;
        height: 16px;
        color: var(--primary);
      }
      .feedback-header h4 {
        font-weight: 600;
        color: var(--foreground);
      }
      .feedback-card > p {
        font-size: 0.875rem;
        color: var(--muted-foreground);
        margin-bottom: 1rem;
      }
      .feedback-meta {
        font-size: 0.75rem;
        color: var(--muted-foreground);
      }
      .view-pitch-btn {
        margin-top: 1rem;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border-radius: var(--radius);
        background: hsla(263, 70%, 76%, 0.1);
        color: var(--primary);
        font-size: 0.875rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: background 0.2s ease;
      }
      .view-pitch-btn:hover {
        background: hsla(263, 70%, 76%, 0.2);
      }
      .view-pitch-btn svg {
        width: 16px;
        height: 16px;
      }

      /* Funding Progress */
      .funding-section {
        margin-bottom: 2rem;
      }
      .funding-card {
        display: flex;
        flex-direction: column;
        gap: 2rem;
      }
      @media (min-width: 1024px) {
        .funding-card {
          flex-direction: row;
        }
      }
      .funding-main {
        flex: 1;
      }
      .funding-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
      }
      .funding-percentage {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .funding-percentage svg {
        width: 20px;
        height: 20px;
        color: var(--primary);
      }
      .funding-percentage span {
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .funding-header > span {
        font-size: 0.875rem;
        color: var(--muted-foreground);
      }
      .progress-bar {
        height: 16px;
        background: var(--muted);
        border-radius: 9999px;
        overflow: hidden;
        margin-bottom: 1.5rem;
      }
      .progress-fill {
        height: 100%;
        background: var(--gradient-primary);
        border-radius: 9999px;
        transition: width 0.5s ease;
        box-shadow: 0 0 20px hsla(263, 70%, 76%, 0.4);
      }
      .milestones {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
      }
      .milestone {
        display: flex;
        flex-direction: column;
        align-items: center;
      }
      .milestone-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--muted);
        margin-bottom: 0.5rem;
      }
      .milestone-dot.active {
        background: var(--primary);
        animation: glow-pulse 2s ease-in-out infinite;
      }
      @keyframes glow-pulse {
        0%,
        100% {
          box-shadow: 0 0 10px hsla(263, 70%, 76%, 0.3);
        }
        50% {
          box-shadow: 0 0 20px hsla(263, 70%, 76%, 0.5);
        }
      }
      .milestone-label {
        font-size: 0.75rem;
        color: var(--muted-foreground);
        text-align: center;
      }
      .funding-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
      }
      .funding-stat {
        text-align: center;
        padding: 1rem;
        border-radius: var(--radius);
        background: hsla(230, 12%, 18%, 0.5);
      }
      .funding-stat .label {
        font-size: 0.875rem;
        color: var(--muted-foreground);
        margin-bottom: 0.25rem;
      }
      .funding-stat .value {
        font-size: 1.25rem;
        font-weight: 700;
      }
      .funding-stat .value.success {
        color: var(--success);
      }
      .funding-stat .value.warning {
        color: var(--warning);
      }
      .funding-stat .value.default {
        color: var(--foreground);
      }
      .donut-chart {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        border-radius: 12px;
        background: hsla(230, 12%, 18%, 0.3);
      }
      @media (min-width: 1024px) {
        .donut-chart {
          width: 256px;
        }
      }
      .donut-container {
        position: relative;
        width: 160px;
        height: 160px;
      }
      .donut-container svg {
        width: 100%;
        height: 100%;
        transform: rotate(-90deg);
      }
      .donut-bg {
        fill: none;
        stroke: var(--muted);
        stroke-width: 12;
      }
      .donut-fill {
        fill: none;
        stroke: url(#donutGradient);
        stroke-width: 12;
        stroke-linecap: round;
        transition: stroke-dasharray 1s ease;
      }
      .donut-center {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
      }
      .donut-center .value {
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .donut-center .label {
        font-size: 0.75rem;
        color: var(--muted-foreground);
      }

      /* Two Column Sections */
      .two-col-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
      }
      @media (min-width: 1024px) {
        .two-col-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      /* Engagement Stats */
      .engagement-stats {
        display: flex;
        flex-direction: column;
        gap: 1rem;
      }
      .engagement-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        border-radius: var(--radius);
        background: hsla(230, 12%, 18%, 0.5);
      }
      .engagement-stat-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .engagement-icon {
        padding: 0.625rem;
        border-radius: var(--radius);
        background: hsla(263, 70%, 76%, 0.1);
      }
      .engagement-icon svg {
        width: 20px;
        height: 20px;
        color: var(--primary);
      }
      .engagement-stat-info .label {
        font-size: 0.875rem;
        color: var(--muted-foreground);
      }
      .engagement-stat-info .value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--foreground);
      }
      .engagement-trend {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--success);
      }
      .engagement-trend svg {
        width: 16px;
        height: 16px;
      }

      /* Investor List */
      .investor-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
      }
      .investor-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        border-radius: var(--radius);
        background: hsla(230, 12%, 18%, 0.5);
        transition: background 0.2s ease;
      }
      .investor-item:hover {
        background: hsla(230, 12%, 18%, 0.7);
      }
      .investor-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .investor-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid hsla(263, 70%, 76%, 0.2);
        object-fit: cover;
      }
      .investor-name {
        font-weight: 500;
        color: var(--foreground);
      }
      .investor-time {
        font-size: 0.75rem;
        color: var(--muted-foreground);
      }
      .investor-amount {
        font-weight: 600;
        color: var(--success);
      }
      .view-all-btn {
        margin-top: 1rem;
        width: 100%;
        padding: 0.625rem;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: transparent;
        color: var(--muted-foreground);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s ease;
      }
      .view-all-btn:hover {
        background: hsla(230, 12%, 18%, 0.5);
      }

      /* Wallet */
      .wallet-section {
        margin-bottom: 2rem;
      }
      .wallet-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      @media (min-width: 1024px) {
        .wallet-grid {
          grid-template-columns: 1fr 1fr 1fr;
        }
      }
      .wallet-balance {
        padding: 1.5rem;
        border-radius: 12px;
        background: var(--gradient-primary);
        position: relative;
        overflow: hidden;
      }
      .wallet-balance::before {
        content: "";
        position: absolute;
        right: -40px;
        top: -40px;
        width: 128px;
        height: 128px;
        border-radius: 50%;
        background: hsla(0, 0%, 100%, 0.1);
      }
      .wallet-balance::after {
        content: "";
        position: absolute;
        right: -20px;
        bottom: -20px;
        width: 96px;
        height: 96px;
        border-radius: 50%;
        background: hsla(0, 0%, 100%, 0.05);
      }
      .wallet-balance-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        position: relative;
        z-index: 1;
      }
      .wallet-balance-icon {
        padding: 0.5rem;
        border-radius: var(--radius);
        background: hsla(0, 0%, 100%, 0.2);
      }
      .wallet-balance-icon svg {
        width: 20px;
        height: 20px;
        color: var(--primary-foreground);
      }
      .wallet-balance-header span {
        font-size: 0.875rem;
        font-weight: 500;
        color: hsla(0, 0%, 100%, 0.8);
      }
      .wallet-balance .value {
        font-family: "Space Grotesk", sans-serif;
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--primary-foreground);
        position: relative;
        z-index: 1;
      }
      .wallet-balance .updated {
        font-size: 0.875rem;
        color: hsla(0, 0%, 100%, 0.6);
        position: relative;
        z-index: 1;
      }
      .wallet-stats {
        display: flex;
        flex-direction: column;
        gap: 1rem;
      }
      .wallet-stat {
        padding: 1rem;
        border-radius: var(--radius);
        background: hsla(230, 12%, 18%, 0.5);
      }
      .wallet-stat-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }
      .wallet-stat-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .wallet-stat-icon {
        padding: 0.5rem;
        border-radius: var(--radius);
      }
      .wallet-stat-icon.success {
        background: hsla(142, 76%, 36%, 0.1);
      }
      .wallet-stat-icon.warning {
        background: hsla(38, 92%, 50%, 0.1);
      }
      .wallet-stat-icon svg {
        width: 16px;
        height: 16px;
      }
      .wallet-stat-icon.success svg {
        color: var(--success);
      }
      .wallet-stat-icon.warning svg {
        color: var(--warning);
      }
      .wallet-stat-left span {
        font-size: 0.875rem;
        color: var(--muted-foreground);
      }
      .wallet-stat-content .value {
        font-weight: 700;
      }
      .wallet-stat-content .value.success {
        color: var(--success);
      }
      .wallet-stat-content .value.warning {
        color: var(--warning);
      }
      .wallet-actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
      }
      .primary-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border-radius: var(--radius);
        background: var(--gradient-primary);
        color: var(--primary-foreground);
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: opacity 0.2s ease;
      }
      .primary-btn:hover {
        opacity: 0.9;
      }
      .primary-btn svg {
        width: 16px;
        height: 16px;
      }
      .outline-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border-radius: var(--radius);
        background: transparent;
        border: 1px solid var(--border);
        color: var(--foreground);
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s ease;
      }
      .outline-btn:hover {
        background: var(--muted);
      }
      .outline-btn svg {
        width: 16px;
        height: 16px;
      }
      .razorpay-note {
        margin-top: auto;
        padding: 0.75rem;
        border-radius: var(--radius);
        background: hsla(263, 70%, 76%, 0.05);
        border: 1px solid hsla(263, 70%, 76%, 0.2);
      }
      .razorpay-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
      }
      .razorpay-header svg {
        width: 16px;
        height: 16px;
        color: var(--primary);
      }
      .razorpay-header span {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--foreground);
      }
      .razorpay-note > p {
        font-size: 0.75rem;
        color: var(--muted-foreground);
      }

      /* Pay Investors Table */
      .pay-investors-section {
        margin-bottom: 2rem;
      }
      .table-container {
        overflow-x: auto;
      }
      table {
        width: 100%;
        border-collapse: collapse;
      }
      th {
        text-align: left;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--muted-foreground);
        border-bottom: 1px solid var(--border);
      }
      td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
      }
      tr:hover {
        background: hsla(230, 12%, 18%, 0.3);
      }
      .table-investor {
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .table-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid var(--border);
        object-fit: cover;
      }
      .table-name {
        font-weight: 500;
        color: var(--foreground);
      }
      .table-amount {
        font-weight: 500;
        color: var(--foreground);
      }
      .table-equity {
        color: var(--muted-foreground);
      }
      .table-return {
        font-weight: 500;
        color: var(--success);
      }
      .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
      }
      .status-badge svg {
        width: 14px;
        height: 14px;
      }
      .status-badge.pending {
        background: hsla(38, 92%, 50%, 0.2);
        color: var(--warning);
      }
      .status-badge.paid {
        background: hsla(142, 76%, 36%, 0.2);
        color: var(--success);
      }
      .status-badge.processing {
        background: hsla(263, 70%, 76%, 0.2);
        color: var(--primary);
      }
      .status-badge.failed {
        background: hsla(0, 72%, 51%, 0.2);
        color: var(--destructive);
      }
      .pay-btn {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 0.75rem;
        border-radius: var(--radius);
        background: var(--gradient-primary);
        color: var(--primary-foreground);
        font-size: 0.875rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: opacity 0.2s ease;
      }
      .pay-btn:hover {
        opacity: 0.9;
      }
      .pay-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      .pay-btn svg {
        width: 14px;
        height: 14px;
      }

      /* Activity Timeline */
      .activity-section .metric-card {
        position: relative;
      }
      .timeline {
        position: relative;
        padding-left: 3rem;
      }
      .timeline::before {
        content: "";
        position: absolute;
        left: 22px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: var(--border);
      }
      .timeline-item {
        position: relative;
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }
      .timeline-item:last-child {
        margin-bottom: 0;
      }
      .timeline-icon {
        position: absolute;
        left: -3rem;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
      }
      .timeline-icon svg {
        width: 20px;
        height: 20px;
      }
      .timeline-icon.success {
        background: hsla(142, 76%, 36%, 0.1);
        color: var(--success);
      }
      .timeline-icon.primary {
        background: hsla(263, 70%, 76%, 0.1);
        color: var(--primary);
      }
      .timeline-icon.warning {
        background: hsla(38, 92%, 50%, 0.1);
        color: var(--warning);
      }
      .timeline-icon.muted {
        background: var(--muted);
        color: var(--muted-foreground);
      }
      .timeline-content {
        flex: 1;
        padding-top: 0.25rem;
      }
      .timeline-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
      }
      .timeline-title {
        font-weight: 500;
        color: var(--foreground);
      }
      .timeline-desc {
        font-size: 0.875rem;
        color: var(--muted-foreground);
        margin-top: 0.125rem;
      }
      .timeline-time {
        font-size: 0.75rem;
        color: var(--muted-foreground);
        flex-shrink: 0;
      }

      /* Quick Actions */
      .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      @media (min-width: 640px) {
        .quick-actions-grid {
          grid-template-columns: repeat(4, 1fr);
        }
      }
      .quick-action-card {
        text-align: left;
        cursor: pointer;
        transition: transform 0.2s ease;
      }
      .quick-action-card:hover {
        transform: scale(1.02);
      }
      .quick-action-icon {
        padding: 0.75rem;
        border-radius: 12px;
        width: fit-content;
        margin-bottom: 1rem;
        transition: background 0.2s ease;
      }
      .quick-action-icon.primary {
        background: var(--gradient-primary);
      }
      .quick-action-icon.default {
        background: hsla(263, 70%, 76%, 0.1);
      }
      .quick-action-card:hover .quick-action-icon.default {
        background: hsla(263, 70%, 76%, 0.2);
      }
      .quick-action-icon svg {
        width: 20px;
        height: 20px;
      }
      .quick-action-icon.primary svg {
        color: var(--primary-foreground);
      }
      .quick-action-icon.default svg {
        color: var(--primary);
      }
      .quick-action-card h3 {
        font-weight: 600;
        color: var(--foreground);
        margin-bottom: 0.25rem;
      }
      .quick-action-card p {
        font-size: 0.875rem;
        color: var(--muted-foreground);
      }

      /* Mobile Styles */
      .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 50;
        padding: 0.5rem;
        border-radius: var(--radius);
        background: var(--card);
        border: none;
        cursor: pointer;
      }
      .mobile-menu-btn svg {
        width: 24px;
        height: 24px;
        color: var(--foreground);
      }
      .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: hsla(230, 20%, 7%, 0.8);
        backdrop-filter: blur(4px);
        z-index: 40;
      }
      .sidebar-close {
        display: none;
        padding: 0.25rem;
        border-radius: var(--radius);
        background: transparent;
        border: none;
        cursor: pointer;
        color: var(--muted-foreground);
      }
      .sidebar-close:hover {
        background: var(--muted);
      }
      .sidebar-close svg {
        width: 20px;
        height: 20px;
      }
      @media (max-width: 1023px) {
        .mobile-menu-btn {
          display: block;
        }
        .sidebar {
          transform: translateX(-100%);
        }
        .sidebar.open {
          transform: translateX(0);
        }
        .sidebar-overlay.open {
          display: block;
        }
        .sidebar-close {
          display: block;
        }
        .main-content {
          margin-left: 0;
        }
        .topbar-title {
          margin-left: 3rem;
        }
        .user-info {
          display: none;
        }
      }

      /* Animation */
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
      .animate-fade-in {
        animation: fadeIn 0.4s ease-out forwards;
      }
      .delay-100 {
        animation-delay: 100ms;
      }
      .delay-200 {
        animation-delay: 200ms;
      }
      .delay-300 {
        animation-delay: 300ms;
      }
      .delay-400 {
        animation-delay: 400ms;
      }
      .delay-500 {
        animation-delay: 500ms;
      }
      .delay-600 {
        animation-delay: 600ms;
      }
      .delay-700 {
        animation-delay: 700ms;
      }
      .delay-800 {
        animation-delay: 800ms;
      }

      /* --- NEW STYLES FOR SWITCHING VIEWS --- */
      .dashboard-view {
        display: none;
        animation: fadeIn 0.4s ease-out forwards;
      }
      .dashboard-view.active {
        display: block;
      }
    </style>
  </head>
  <body>
    <div class="dashboard">
      <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M4 6h16M4 12h16M4 18h16"
          />
        </svg>
      </button>

      <div
        class="sidebar-overlay"
        id="sidebarOverlay"
        onclick="toggleSidebar()"
      ></div>

      <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
          <div class="logo-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M13 10V3L4 14h7v7l9-11h-7z"
              />
            </svg>
          </div>
          <span class="logo-text">SmartPitchHub</span>
          <button class="sidebar-close" onclick="toggleSidebar()">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        <nav class="sidebar-nav">
          <button class="nav-item active" data-item="dashboard">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
            </svg>
            <span>Dashboard</span>
          </button>
          <button class="nav-item" data-item="pitch">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>My Pitch</span>
          </button>
          <button class="nav-item" data-item="investors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span>Investors</span>
          </button>
          <button class="nav-item" data-item="wallet">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            <span>Wallet</span>
          </button>
          <button class="nav-item" data-item="pay-investors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
               <!-- Piggy bank or card icon -->
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Pay Investors</span>
          </button>
          <button class="nav-item" onclick="window.location.href='../KYC/kyc-review.php'">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>KYC Review</span>
          </button>
          <button class="nav-item" onclick="window.location.href='../Pitches/pitch-review.php'">
             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
             </svg>
            <span>Pitch Review</span>
          </button>
        </nav>

        <div class="sidebar-bottom">
          <button class="nav-item" data-item="settings">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Profile</span>
          </button>
          <button class="nav-item" data-item="support">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <span>Support</span>
          </button>
          <button
            class="nav-item logout"
            onclick="window.location.href='../logout.php'"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
              />
            </svg>
            <span>Logout</span>
          </button>
        </div>

        <div class="upgrade-card" style="background: hsla(142, 76%, 36%, 0.1); border: 1px solid hsla(142, 76%, 36%, 0.2);">
          <div class="upgrade-header">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--success);">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span style="color: var(--success);">KYC Verified</span>
          </div>
          <p style="color: var(--muted-foreground);">You have full access.</p>
          <button class="upgrade-btn" style="background: transparent; border: 1px solid var(--success); color: var(--success);" onclick="window.location.href='../KYC/Enterpreneur-kyc.php'">View Details</button>
        </div>
      </aside>

      <div class="main-content">
        <header class="topbar">
          <div class="topbar-content">
            <div class="topbar-title">
              <h1>Entrepreneur Dashboard</h1>
              <p>Manage your pitch, track funding, and engage with investors</p>
            </div>
            <div class="topbar-actions">
              <button class="icon-btn" onclick="toggleTheme()" id="themeBtn">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                  id="sunIcon"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                  />
                </svg>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                  id="moonIcon"
                  style="display: none"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                  />
                </svg>
              </button>
              <button class="icon-btn notification-btn">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                  />
                </svg>
                <span class="notification-dot"></span>
              </button>
              <div class="user-menu">
                <img
                  src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face"
                  alt="User"
                  class="user-avatar"
                />
                <div class="user-info">
                  <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
                  <div class="role" style="display:flex; align-items:center; gap:6px;">
                    Entrepreneur
                    <?php 
                        $kyc_color = 'var(--muted-foreground)';
                        if($stats['kyc_status'] === 'verified') $kyc_color = 'var(--success)';
                        elseif($stats['kyc_status'] === 'pending' || $stats['kyc_status'] === 'under_review') $kyc_color = 'var(--warning)';
                        elseif($stats['kyc_status'] === 'rejected') $kyc_color = 'var(--destructive)';
                    ?>
                    <span style="font-size:0.65rem; padding: 1px 6px; border-radius:4px; border:1px solid <?php echo $kyc_color; ?>; color: <?php echo $kyc_color; ?>;">
                        <?php echo strtoupper(str_replace('_', ' ', $stats['kyc_status'])); ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </header>

        <main class="dashboard-content">
          <div id="view-dashboard" class="dashboard-view active">
            <section class="animate-fade-in">
              <h2 class="section-title">Overview</h2>
              <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                
                <!-- 1. Available for Withdrawal -->
                <div class="metric-card" style="border: 1px solid hsla(217, 91%, 60%, 0.2); background: radial-gradient(circle at top right, hsla(217, 91%, 60%, 0.1), transparent 60%), var(--card);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon" style="background: hsla(217, 91%, 60%, 0.1);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #3b82f6;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #10b981; display: flex; align-items: center; gap: 4px;">
                            <span style="width: 6px; height: 6px; background: #10b981; border-radius: 50%;"></span> Live
                        </span>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Available for Withdrawal</div>
                    <div style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700; color: #3b82f6; margin-bottom: 0.25rem;">
                        <?php echo money($wallet_balance); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground);">Total Released Funds</div>
                </div>

                <!-- 2. Locked in Escrow -->
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon" style="background: hsla(38, 92%, 50%, 0.1);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #f59e0b;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Locked in Escrow</div>
                    <div style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-bottom: 0.25rem;">
                        <?php echo money($stats['escrow_balance']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground);">Raised but not released</div>
                </div>

                <!-- 3. Total Funding Raised -->
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: var(--foreground); display: flex; align-items: center; gap: 4px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:12px; height:12px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg> 0%
                        </span>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Total Funding Raised</div>
                    <div class="kpi-value"><?php echo money($stats['raised']); ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground);">From all investors</div>
                </div>

                <!-- 4. Funding Goal -->
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Funding Goal</div>
                    <div class="kpi-value"><?php echo money($stats['goal']); ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground);">Total Target</div>
                </div>

                <!-- 5. Funding Progress -->
                <div class="metric-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #10b981;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:12px; height:12px; display:inline;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg> On Track
                        </span>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Funding Progress</div>
                    <div class="kpi-value"><?php echo number_format($stats['progress'], 1); ?>%</div>
                    <div style="height: 4px; background: var(--muted); border-radius: 2px; margin-top: 1rem; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo min(100, $stats['progress']); ?>%; background: var(--primary);"></div>
                    </div>
                </div>

                <!-- 6. Share Capital Overview -->
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                        </div>
                        <span style="font-size: 0.7rem; color: var(--muted-foreground);">Valuation: <span style="color: var(--primary);"><?php echo money($stats['valuation']); ?></span></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--muted-foreground);">
                        <span>Share Price:</span>
                        <span style="color: var(--foreground); font-weight: 600;"><?php echo money($stats['share_price']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--muted-foreground);">
                        <span>Shares Issued:</span>
                        <span style="color: var(--foreground); font-weight: 600;"><?php echo number_format($stats['shares_issued']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 1rem; color: var(--muted-foreground);">
                        <span><?php echo ($stats['latest_id'] > 0) ? 'Remaining in Round:' : 'Unallocated Pool:'; ?></span>
                        <span style="color: #10b981; font-weight: 600;"><?php echo number_format($stats['shares_remaining']); ?></span>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground); margin-bottom: 0.5rem;">Share Capital Overview</div>
                    <div style="background: hsla(38, 92%, 50%, 0.1); border: 1px solid hsla(38, 92%, 50%, 0.2); padding: 0.5rem; border-radius: 4px; font-size: 0.65rem; color: #f59e0b; line-height: 1.2;">
                         Note: Final share structure and pricing are subject to final audit and approval by the administrative team during the review process.
                    </div>
                </div>

                <!-- 7. Active Investors -->
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #10b981; display:flex; align-items:center; gap:4px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"> <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /> </svg> Active
                        </span>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Active Investors</div>
                    <div class="kpi-value"><?php echo $stats['investors']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground);">Invested in your startup</div>
                </div>

                <!-- 8. Pitch Status -->
                <div class="metric-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="kpi-badge <?php echo $stats['status_badge']; ?>"><?php echo $stats['status']; ?></span>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 0.25rem;">Pitch Status</div>
                    <div class="kpi-value" style="font-size: 1.25rem;"><?php echo $stats['status']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted-foreground); margin-bottom: 0.5rem;">Latest Submission</div>
                    <a href="#" onclick="document.querySelector('[data-item=pitch-status]').click();" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">Click to view status &rarr;</a>
                </div>
                </div>
            </section>

            <section class="animate-fade-in delay-800" style="margin-top: 2rem">
              <h2 class="section-title">Quick Actions</h2>
              <div class="quick-actions-grid">
                <?php
                    $actionUrl = '../Pitches/createPitch.php';
                    $actionTitle = 'Create/Edit Pitch';
                    $actionDesc = 'Update your pitch details and documents';
                    
                    if ($stats['progress'] >= 100) {
                        $actionUrl = '../Pitches/createPitch.php?mode=next_round';
                        $actionTitle = 'Launch Series A';
                        $actionDesc = 'Goal reached! Start your next round.';
                    }
                ?>
                <div
                  class="metric-card quick-action-card"
                  onclick="window.location.href='<?php echo $actionUrl; ?>'"
                >
                  <div class="quick-action-icon primary">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                      />
                    </svg>
                  </div>
                  <h3><?php echo $actionTitle; ?></h3>
                  <p><?php echo $actionDesc; ?></p>
                </div>
                <div class="metric-card quick-action-card">
                  <div class="quick-action-icon default">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                      />
                    </svg>
                  </div>
                  <h3>Upload Documents</h3>
                  <p>Add financial docs or presentations</p>
                </div>
                <div
                  class="metric-card quick-action-card"
                  onclick="window.location.href='../Pitches/view-pitch.php?id=<?php echo $stats['latest_id']; ?>'"
                >
                  <div class="quick-action-icon default">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                      />
                    </svg>
                  </div>
                  <h3>View Public Pitch</h3>
                  <p>See how investors view your pitch</p>
                </div>
                <div class="metric-card quick-action-card">
                  <div class="quick-action-icon default">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                  </div>
                  <h3>Contact Support</h3>
                  <p>Get help from our team</p>
                </div>
              </div>
            </section>

            
            <!-- Recent Investments Section (Moved) -->
            <section class="animate-fade-in delay-700" style="margin-top: 2rem;">
                 <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 class="section-title" style="margin-bottom: 0;">Recent Investments</h2>
                    <button class="primary-btn" style="padding: 0.5rem 1rem; font-size: 0.875rem;" onclick="document.querySelector('[data-item=investors]').click()">View All</button>
                 </div>
                 
                 <div class="metric-card" style="padding: 0; overflow: hidden;">
                     <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: hsla(230, 12%, 18%, 0.3);">
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Investor</th>
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Amount</th>
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Equity</th>
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Date</th>
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_investments)): ?>
                                    <tr>
                                        <td colspan="5" style="padding: 2rem; text-align: center; color: var(--muted-foreground);">No investments received yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($recent_investments as $inv): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <img src="<?php echo !empty($inv['profile_image']) ? '../uploads/'.$inv['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($inv['investor_name']); ?>" 
                                                     alt="Investor" 
                                                     style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                                <div>
                                                    <div style="font-weight: 500; color: var(--foreground); font-size: 0.875rem;"><?php echo htmlspecialchars($inv['investor_name']); ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--muted-foreground);">Angel Investor</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; font-weight: 600; color: var(--foreground); font-family: 'Space Grotesk', sans-serif;">
                                            <?php echo money($inv['amount']); ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--muted-foreground); font-size: 0.875rem;">
                                            <?php 
                                                 // Calculate equity based on valuation at that time? Roughly estimate for now
                                                 $eq = ($stats['valuation'] > 0) ? ($inv['amount'] / $stats['valuation']) * 100 : 0;
                                                 echo number_format($eq, 4) . '%';
                                            ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--muted-foreground); font-size: 0.875rem;"><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                                        <td style="padding: 1rem;">
                                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 9999px; background: hsla(142, 76%, 36%, 0.1); color: var(--success); font-size: 0.75rem; font-weight: 500;">
                                                <span style="width: 6px; height: 6px; background: var(--success); border-radius: 50%;"></span> Completed
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                     </div>
                 </div>
            </section>
          </div> 


          <div id="view-pitch" class="dashboard-view">
            <section class="pitch-section animate-fade-in delay-200">
              <h2 class="section-title">Pitch Status</h2>
              <div class="metric-card pitch-card">
                <div class="pitch-main">
                  <div class="pitch-header">
                    <div class="pitch-logo">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                        />
                      </svg>
                    </div>
                    <div class="pitch-info">
                      <h3><?php echo htmlspecialchars($stats['latest_title']); ?></h3>
                      <p><?php echo htmlspecialchars($stats['latest_tagline']); ?></p>
                      <div class="pitch-badges">
                        <span class="kpi-badge <?php echo $stats['status_badge']; ?>"><?php echo $stats['status']; ?></span>
                        <span class="kpi-badge primary">Round: <?php echo ($stats['shares_issued'] > 0) ? 'Active' : 'Preparation'; ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="pitch-stats">
                    <div class="stat-box">
                      <div class="label">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        Funding Goal
                      </div>
                      <div class="value"><?php echo money($stats['goal']); ?></div>
                    </div>
                    <div class="stat-box">
                      <div class="label">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                          />
                        </svg>
                        Valuation
                      </div>
                      <div class="value"><?php echo money($stats['valuation']); ?></div>
                    </div>
                    <div class="stat-box">
                      <div class="label">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                          />
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"
                          />
                        </svg>
                        Equity
                      </div>
                      <div class="value"><?php echo number_format($stats['equity'], 1); ?>%</div>
                    </div>
                    <div class="stat-box">
                      <div class="label">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        Status
                      </div>
                      <div class="value <?php echo $stats['status_badge']; ?>"><?php echo $stats['status']; ?></div>
                    </div>
                  </div>
                </div>

                <div class="feedback-card">
                  <div class="feedback-header">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                      />
                    </svg>
                    <h4>Admin Feedback</h4>
                  </div>
                  <p>
                    "Excellent pitch deck and strong financials. Your traction
                    metrics are impressive. Consider adding more details about
                    your go-to-market strategy for Q2."
                  </p>
                  <div class="feedback-meta">Last updated: 2 days ago</div>
                  <button class="view-pitch-btn" onclick="window.location.href='../Pitches/view-pitch.php?id=<?php echo $stats['latest_id']; ?>'">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                      />
                    </svg>
                    View Public Pitch
                  </button>
                </div>
              </div>
            </section>

            <section class="funding-section animate-fade-in delay-300">
              <h2 class="section-title">Funding Progress</h2>
              <div class="metric-card funding-card">
                <div class="funding-main">
                  <div class="funding-header">
                    <div class="funding-percentage">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                        />
                      </svg>
                      <span><?php echo number_format($stats['progress'], 1); ?>%</span>
                    </div>
                    <span>of goal reached</span>
                  </div>

                  <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, $stats['progress']); ?>%"></div>
                  </div>

                  <div class="milestones">
                    <div class="milestone">
                      <div class="milestone-dot <?php echo ($stats['progress'] > 0) ? 'active' : ''; ?>"></div>
                      <div class="milestone-label">Seed Start</div>
                    </div>
                    <div class="milestone">
                      <div class="milestone-dot <?php echo ($stats['progress'] >= 25) ? 'active' : ''; ?>"></div>
                      <div class="milestone-label">25% Funded</div>
                    </div>
                    <div class="milestone">
                      <div class="milestone-dot <?php echo ($stats['progress'] >= 75) ? 'active' : ''; ?>"></div>
                      <div class="milestone-label">Almost There</div>
                    </div>
                    <div class="milestone">
                      <div class="milestone-dot <?php echo ($stats['progress'] >= 100) ? 'active' : ''; ?>"></div>
                      <div class="milestone-label">Goal Reached</div>
                    </div>
                  </div>

                  <div class="funding-stats">
                    <div class="funding-stat">
                      <div class="label">Raised</div>
                      <div class="value success"><?php echo money($stats['raised']); ?></div>
                    </div>
                    <div class="funding-stat">
                      <div class="label">Goal</div>
                      <div class="value default"><?php echo money($stats['goal']); ?></div>
                    </div>
                    <div class="funding-stat">
                      <div class="label">Remaining</div>
                      <div class="value warning"><?php echo money(max(0, $stats['goal'] - $stats['raised'])); ?></div>
                    </div>
                  </div>
                </div>

                <div class="donut-chart">
                  <div class="donut-container">
                    <svg viewBox="0 0 100 100">
                      <defs>
                        <linearGradient
                          id="donutGradient"
                          x1="0%"
                          y1="0%"
                          x2="100%"
                          y2="0%"
                        >
                          <stop offset="0%" stop-color="hsl(263, 70%, 76%)" />
                          <stop offset="100%" stop-color="hsl(280, 60%, 65%)" />
                        </linearGradient>
                      </defs>
                      <circle class="donut-bg" cx="50" cy="50" r="40" />
                      <?php 
                        $donutStroke = ($stats['progress'] / 100) * 251;
                      ?>
                      <circle
                        class="donut-fill"
                        cx="50"
                        cy="50"
                        r="40"
                        stroke-dasharray="<?php echo $donutStroke; ?> 251"
                      />
                    </svg>
                    <div class="donut-center">
                      <div class="value"><?php echo number_format($stats['progress'], 1); ?>%</div>
                      <div class="label">Funded</div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div id="view-pitch-status" class="dashboard-view">
            <section class="pitch-section animate-fade-in delay-200">
                <h2 class="section-title">Pitch Status Tracking</h2>
                <div class="metric-card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="color: var(--muted-foreground);">Pitch/Startup</th>
                                    <th style="color: var(--muted-foreground);">Stage</th>
                                    <th style="color: var(--muted-foreground);">Submission Date</th>
                                    <th style="color: var(--muted-foreground);">Status</th>
                                    <th style="color: var(--muted-foreground);">Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $status_sql = "SELECT * FROM pitches WHERE entrepreneur_id = ? ORDER BY created_at DESC";
                                if ($st_stmt = $conn->prepare($status_sql)) {
                                    $st_stmt->bind_param("i", $user_id);
                                    $st_stmt->execute();
                                    $res_pitches = $st_stmt->get_result();
                                    
                                    while ($pt = $res_pitches->fetch_assoc()) {
                                        // Determine Status Badge Color
                                        $badge_class = 'processing'; // default blue
                                        $status_text = 'Pending';
                                        
                                        if ($pt['is_approved']) {
                                            $badge_class = 'paid'; // green
                                            $status_text = 'Approved & Live';
                                        } elseif (isset($pt['status']) && $pt['status'] === 'rejected') {
                                            $badge_class = 'failed'; // red
                                            $status_text = 'Rejected';
                                        } elseif (isset($pt['status']) && $pt['status'] === 'pending') {
                                            $badge_class = 'pending'; // orange
                                            $status_text = 'Under Review';
                                        }

                                        echo "<tr>";
                                        echo "<td>
                                                <div class='table-investor'>
                                                    <div class='pitch-logo' style='width:36px; height:36px; border-radius:8px;'>
                                                        <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2' style='width:18px;height:18px;'>
                                                            <path stroke-linecap='round' stroke-linejoin='round' d='M13 10V3L4 14h7v7l9-11h-7z' />
                                                        </svg>
                                                    </div>
                                                    <span class='table-name'>" . htmlspecialchars($pt['startup_name']) . "</span>
                                                </div>
                                              </td>";
                                        echo "<td class='table-amount'>" . ($pt['shares_issued'] > 0 ? 'Funding Round' : 'Preparation') . "</td>";
                                        echo "<td class='table-equity'>" . date('M j, Y', strtotime($pt['created_at'])) . "</td>";
                                        echo "<td>
                                                <span class='status-badge $badge_class'>
                                                    $status_text
                                                </span>
                                              </td>";
                                        // Simple feedback hook (can be expanded)
                                        $feedbackMsg = $pt['is_approved'] ? 'View Live' : 'Awaiting Review';
                                        echo "<td style='text-align: right; color: var(--muted-foreground); font-size: 0.875rem;'>$feedbackMsg</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
          </div>

          <div id="view-kyc-status" class="dashboard-view">
            <section class="animate-fade-in delay-200">
                <h2 class="section-title">KYC Status & Compliance</h2>
                <div class="two-col-grid">
                    <?php
                        // Fetch KYC Details
                        $kyc_sql = "SELECT * FROM entrepreneur_kyc_details WHERE entrepreneur_id = ?";
                        $kyc_data = null;
                        if ($k_stmt = $conn->prepare($kyc_sql)) {
                            $k_stmt->bind_param("i", $user_id);
                            $k_stmt->execute();
                            $kyc_data = $k_stmt->get_result()->fetch_assoc();
                        }
                        
                        $kyc_status_display = $kyc_data ? $kyc_data['status'] : 'Not Submitted';
                        
                        // Icon & Color Logic
                        $k_icon = 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'; // Warning
                        $k_color = 'var(--warning)';
                        $k_title = 'Pending Submission';
                        
                        if ($kyc_status_display === 'verified') {
                            $k_icon = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'; // Check
                            $k_color = 'var(--success)';
                            $k_title = 'Verified';
                        } elseif ($kyc_status_display === 'rejected') {
                             $k_icon = 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'; // Cross
                             $k_color = 'var(--destructive)';
                             $k_title = 'Application Rejected';
                        } elseif ($kyc_status_display === 'under_review') {
                             $k_icon = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'; // Clock
                             $k_color = 'var(--primary)';
                             $k_title = 'Under Review';
                        }
                    ?>
                    
                    <!-- Status Card -->
                    <div class="metric-card" style="text-align: center; padding: 3rem;">
                        <div style="
                            width: 80px; 
                            height: 80px; 
                            background: <?php echo $k_color; ?>; 
                            border-radius: 50%; 
                            display: flex; 
                            align-items: center; 
                            justify-content: center; 
                            margin: 0 auto 1.5rem auto;
                            box-shadow: 0 0 20px <?php echo $k_color; ?>40;
                        ">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" style="width: 40px; height: 40px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $k_icon; ?>" />
                            </svg>
                        </div>
                        <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--foreground); margin-bottom: 0.5rem;">
                            <?php echo $k_title; ?>
                        </h3>
                        <p style="color: var(--muted-foreground); margin-bottom: 2rem;">
                            Current Status: <?php echo ucfirst(str_replace('_', ' ', $kyc_status_display)); ?>
                        </p>
                        
                        <?php if($kyc_status_display === 'Not Submitted' || $kyc_status_display === 'rejected'): ?>
                            <button onclick="window.location.href='../KYC/Enterpreneur-kyc.php'" class="primary-btn" style="max-width: 200px; margin: 0 auto;">
                                <?php echo ($kyc_status_display === 'rejected') ? 'Resubmit KYC' : 'Complete KYC Now'; ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Details Card -->
                    <div class="metric-card">
                        <h3 style="font-weight: 600; color: var(--foreground); margin-bottom: 1.5rem;">Submission Details</h3>
                        
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                                <span style="color: var(--muted-foreground);">Submitted On</span>
                                <span style="font-weight: 500;"><?php echo $kyc_data ? date('M j, Y, g:i a', strtotime($kyc_data['submission_date'])) : '-'; ?></span>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                                <span style="color: var(--muted-foreground);">Legal Name</span>
                                <span style="font-weight: 500;"><?php echo $kyc_data ? htmlspecialchars($kyc_data['legal_name']) : '-'; ?></span>
                            </div>

                            <div style="display: flex; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                                <span style="color: var(--muted-foreground);">Gov ID Proof</span>
                                <span style="font-weight: 500;">
                                    <?php echo ($kyc_data && !empty($kyc_data['gov_id_path'])) ? 'Uploaded ✅' : 'Pending ❌'; ?>
                                </span>
                            </div>
                            
                             <div style="display: flex; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                                <span style="color: var(--muted-foreground);">Business Proof</span>
                                <span style="font-weight: 500;">
                                    <?php echo ($kyc_data && !empty($kyc_data['coi_path'])) ? 'Uploaded ✅' : 'Pending ❌'; ?>
                                </span>
                            </div>
                            
                            <?php if($kyc_status_display === 'rejected'): ?>
                            <div style="background: hsla(0, 72%, 51%, 0.1); padding: 1rem; border-radius: 8px; margin-top: 1rem; border: 1px solid hsla(0, 72%, 51%, 0.2);">
                                <strong style="color: var(--destructive); display: block; margin-bottom: 0.5rem; font-size: 0.875rem;">Rejection Reason:</strong>
                                <p style="font-size: 0.875rem; color: var(--foreground);">
                                    <?php echo htmlspecialchars($kyc_data['rejection_reason'] ?? 'Document verification failed.'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
          </div>

          <div id="view-investors" class="dashboard-view">
            <section class="animate-fade-in delay-400">
              <h2 class="section-title">Investor Engagement</h2>
              <div class="two-col-grid">
                <div class="metric-card">
                  <h3
                    style="
                      font-weight: 600;
                      color: var(--foreground);
                      margin-bottom: 1rem;
                    "
                  >
                    Engagement Stats
                  </h3>
                  <div class="engagement-stats">
                    <div class="engagement-stat">
                      <div class="engagement-stat-left">
                        <div class="engagement-icon">
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                          </svg>
                        </div>
                        <div class="engagement-stat-info">
                          <div class="label">Interested Investors</div>
                          <div class="value">124</div>
                        </div>
                      </div>
                      <div class="engagement-trend">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                          />
                        </svg>
                        +18
                      </div>
                    </div>
                    <div class="engagement-stat">
                      <div class="engagement-stat-left">
                        <div class="engagement-icon">
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                          </svg>
                        </div>
                        <div class="engagement-stat-info">
                          <div class="label">Active Investors</div>
                          <div class="value">47</div>
                        </div>
                      </div>
                      <div class="engagement-trend">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                          />
                        </svg>
                        +5
                      </div>
                    </div>
                    <div class="engagement-stat">
                      <div class="engagement-stat-left">
                        <div class="engagement-icon">
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                            />
                          </svg>
                        </div>
                        <div class="engagement-stat-info">
                          <div class="label">New This Week</div>
                          <div class="value">12</div>
                        </div>
                      </div>
                      <div class="engagement-trend">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                          />
                        </svg>
                        +3
                      </div>
                    </div>
                  </div>
                </div>

                <div class="metric-card">
                  <h3
                    style="
                      font-weight: 600;
                      color: var(--foreground);
                      margin-bottom: 1rem;
                    "
                  >
                    Recent Investors
                  </h3>
                  <div class="investor-list">
                    <div class="investor-item">
                      <div class="investor-left">
                        <img
                          src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop&crop=face"
                          alt="Sarah Mitchell"
                          class="investor-avatar"
                        />
                        <div>
                          <div class="investor-name">Sarah Mitchell</div>
                          <div class="investor-time">2 hours ago</div>
                        </div>
                      </div>
                      <div class="investor-amount">$150,000</div>
                    </div>
                    <div class="investor-item">
                      <div class="investor-left">
                        <img
                          src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face"
                          alt="James Wilson"
                          class="investor-avatar"
                        />
                        <div>
                          <div class="investor-name">James Wilson</div>
                          <div class="investor-time">5 hours ago</div>
                        </div>
                      </div>
                      <div class="investor-amount">$75,000</div>
                    </div>
                    <div class="investor-item">
                      <div class="investor-left">
                        <img
                          src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&crop=face"
                          alt="Emily Chen"
                          class="investor-avatar"
                        />
                        <div>
                          <div class="investor-name">Emily Chen</div>
                          <div class="investor-time">1 day ago</div>
                        </div>
                      </div>
                      <div class="investor-amount">$200,000</div>
                    </div>
                    <div class="investor-item">
                      <div class="investor-left">
                        <img
                          src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face"
                          alt="Michael Brown"
                          class="investor-avatar"
                        />
                        <div>
                          <div class="investor-name">Michael Brown</div>
                          <div class="investor-time">2 days ago</div>
                        </div>
                      </div>
                      <div class="investor-amount">$100,000</div>
                    </div>
                  </div>
                  <button class="view-all-btn">View All Investors</button>
                </div>
              </div>
            </section>
          </div>

          <div id="view-wallet" class="dashboard-view">
            <section class="wallet-section animate-fade-in delay-500">
              <h2 class="section-title">Wallet Overview</h2>
              <div class="metric-card">
                <div class="wallet-grid">
                  <div class="wallet-balance">
                    <div class="wallet-balance-header">
                      <div class="wallet-balance-icon">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                          />
                        </svg>
                      </div>
                      <span>Available Balance</span>
                    </div>
                    <div class="value"><?php echo money($wallet_balance); ?></div>
                    <div class="updated">Updated just now</div>

                    <!-- Smart Escrow Integration -->
                    <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,0.15);">
                        <div class="wallet-balance-header" style="margin-bottom: 0.5rem;">
                            <div class="wallet-balance-icon" style="padding: 0.35rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <span style="opacity: 0.9;">In Smart Escrow</span>
                        </div>
                        <div class="value" style="font-size: 1.5rem;"><?php echo money($stats['escrow_balance']); ?></div>
                        <div class="updated" style="font-size: 0.75rem;">Funds locked until milestones are met</div>
                    </div>
                  </div>

                  <div class="wallet-stats">
                    <div class="wallet-stat">
                      <div class="wallet-stat-content">
                        <div class="wallet-stat-left">
                          <div class="wallet-stat-icon success">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 14l-7 7m0 0l-7-7m7 7V3"
                              />
                            </svg>
                          </div>
                          <span>Total Received</span>
                        </div>
                        <div class="value success"><?php echo money($stats['raised']); ?></div>
                      </div>
                    </div>
                    <div class="wallet-stat">
                      <div class="wallet-stat-content">
                        <div class="wallet-stat-left">
                          <div class="wallet-stat-icon warning">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 10l7-7m0 0l7 7m-7-7v18"
                              />
                            </svg>
                          </div>
                          <span>Withdrawn</span>
                        </div>
                        <div class="value warning"><?php echo money($withdrawn_total); ?></div>
                      </div>
                    </div>
                  </div>

                  <div class="wallet-actions">
                    <button class="primary-btn" onclick="openModal('withdrawModal')">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M5 10l7-7m0 0l7 7m-7-7v18"
                        />
                      </svg>
                      Withdraw Funds
                    </button>
                    <div class="razorpay-note">
                      <div class="razorpay-header">
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                          />
                        </svg>
                        <span>Powered by Razorpay</span>
                      </div>
                      <p>
                        Secure payment processing with bank-grade encryption
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Transaction History -->
              <div style="margin-top: 2rem;">
                  <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Recent Transactions</h3>
                  <div class="metric-card" style="padding: 0; overflow: hidden;">
                     <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: hsla(230, 12%, 18%, 0.3); border-bottom: 1px solid var(--border);">
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Type</th>
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Source/Reference</th>
                                    <th style="text-align: left; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Transaction ID</th>
                                    <th style="text-align: right; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Amount</th>
                                    <th style="text-align: right; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Status</th>
                                    <th style="text-align: right; padding: 1rem; color: var(--muted-foreground); font-weight: 500; font-size: 0.875rem;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($wallet_txns)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--muted-foreground);">No transactions found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($wallet_txns as $txn): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1rem;">
                                            <?php if($txn['txn_type'] == 'credit'): ?>
                                                <span style="color: var(--success); display: flex; align-items: center; gap: 6px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                                                    Credit
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--destructive); display: flex; align-items: center; gap: 6px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
                                                    Debit
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; font-weight: 500;"><?php echo htmlspecialchars($txn['source']); ?></td>
                                        <td style="padding: 1rem; font-family: monospace; color: var(--muted-foreground);"><?php echo htmlspecialchars($txn['reference_id']); ?></td>
                                        <td style="padding: 1rem; text-align: right; font-weight: 600; font-family: 'Space Grotesk', sans-serif; <?php echo $txn['txn_type']=='credit' ? 'color:var(--success);' : 'color:var(--foreground);'; ?>">
                                            <?php echo ($txn['txn_type']=='credit' ? '+' : '-') . money($txn['amount']); ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: right;">
                                            <span style="font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: hsla(142, 70%, 50%, 0.1); color: var(--success); border: 1px solid hsla(142, 70%, 50%, 0.2);">
                                                <?php echo ucfirst($txn['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; text-align: right; color: var(--muted-foreground); font-size: 0.85rem;">
                                            <?php echo date('M d, H:i', strtotime($txn['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                     </div>
                  </div>
              </div>

            </section>
          </div>

          <div id="view-pay-investors" class="dashboard-view">
            <section class="pay-investors-section animate-fade-in delay-600">
              <h2 class="section-title">Pay Investors</h2>
              <div class="metric-card">
                <div class="table-container">
                  <table>
                    <thead>
                      <tr>
                        <th>Investor</th>
                        <th>Invested Amount</th>
                        <th>Equity %</th>
                        <th>Return Amount</th>
                        <th>Status</th>
                        <th style="text-align: right">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>
                          <div class="table-investor">
                            <img
                              src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop&crop=face"
                              alt="Sarah Mitchell"
                              class="table-avatar"
                            />
                            <span class="table-name">Sarah Mitchell</span>
                          </div>
                        </td>
                        <td class="table-amount">$150,000</td>
                        <td class="table-equity">1.5%</td>
                        <td class="table-return">$22,500</td>
                        <td>
                          <span class="status-badge pending">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                              />
                            </svg>
                            Pending
                          </span>
                        </td>
                        <td style="text-align: right">
                          <button class="pay-btn">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                              />
                            </svg>
                            Pay Now
                          </button>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="table-investor">
                            <img
                              src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face"
                              alt="James Wilson"
                              class="table-avatar"
                            />
                            <span class="table-name">James Wilson</span>
                          </div>
                        </td>
                        <td class="table-amount">$75,000</td>
                        <td class="table-equity">0.75%</td>
                        <td class="table-return">$11,250</td>
                        <td>
                          <span class="status-badge paid">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                              />
                            </svg>
                            Paid
                          </span>
                        </td>
                        <td style="text-align: right">
                          <button class="pay-btn" disabled>
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                              />
                            </svg>
                            Pay Now
                          </button>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="table-investor">
                            <img
                              src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&crop=face"
                              alt="Emily Chen"
                              class="table-avatar"
                            />
                            <span class="table-name">Emily Chen</span>
                          </div>
                        </td>
                        <td class="table-amount">$200,000</td>
                        <td class="table-equity">2.0%</td>
                        <td class="table-return">$30,000</td>
                        <td>
                          <span class="status-badge pending">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                              />
                            </svg>
                            Pending
                          </span>
                        </td>
                        <td style="text-align: right">
                          <button class="pay-btn">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                              />
                            </svg>
                            Pay Now
                          </button>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="table-investor">
                            <img
                              src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face"
                              alt="Michael Brown"
                              class="table-avatar"
                            />
                            <span class="table-name">Michael Brown</span>
                          </div>
                        </td>
                        <td class="table-amount">$100,000</td>
                        <td class="table-equity">1.0%</td>
                        <td class="table-return">$15,000</td>
                        <td>
                          <span class="status-badge processing">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                              />
                            </svg>
                            Processing
                          </span>
                        </td>
                        <td style="text-align: right">
                          <button class="pay-btn" disabled>
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                              />
                            </svg>
                            Pay Now
                          </button>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="table-investor">
                            <img
                              src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=face"
                              alt="Lisa Anderson"
                              class="table-avatar"
                            />
                            <span class="table-name">Lisa Anderson</span>
                          </div>
                        </td>
                        <td class="table-amount">$50,000</td>
                        <td class="table-equity">0.5%</td>
                        <td class="table-return">$7,500</td>
                        <td>
                          <span class="status-badge failed">
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                              />
                            </svg>
                            Failed
                          </span>
                        </td>
                        <td style="text-align: right">
                          <button class="pay-btn" disabled>
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                              />
                            </svg>
                            Pay Now
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          </div>

          <div id="view-transactions" class="dashboard-view">
            <section class="activity-section animate-fade-in delay-700">
              <h2 class="section-title">Recent Activity</h2>
              <div class="metric-card">
                <div class="timeline">
                  <div class="timeline-item">
                    <div class="timeline-icon success">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                      </svg>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div>
                          <div class="timeline-title">
                            New Investment Received
                          </div>
                          <div class="timeline-desc">
                            Sarah Mitchell invested $150,000
                          </div>
                        </div>
                        <div class="timeline-time">2 hours ago</div>
                      </div>
                    </div>
                  </div>

                  <div class="timeline-item">
                    <div class="timeline-icon primary">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                        />
                      </svg>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div>
                          <div class="timeline-title">
                            New Interested Investor
                          </div>
                          <div class="timeline-desc">
                            David Park showed interest in your pitch
                          </div>
                        </div>
                        <div class="timeline-time">4 hours ago</div>
                      </div>
                    </div>
                  </div>

                  <div class="timeline-item">
                    <div class="timeline-icon warning">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                      </svg>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div>
                          <div class="timeline-title">Pitch Updated</div>
                          <div class="timeline-desc">
                            Your pitch deck was updated successfully
                          </div>
                        </div>
                        <div class="timeline-time">6 hours ago</div>
                      </div>
                    </div>
                  </div>

                  <div class="timeline-item">
                    <div class="timeline-icon success">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                      </svg>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div>
                          <div class="timeline-title">Document Approved</div>
                          <div class="timeline-desc">
                            Financial projections document approved
                          </div>
                        </div>
                        <div class="timeline-time">1 day ago</div>
                      </div>
                    </div>
                  </div>

                  <div class="timeline-item">
                    <div class="timeline-icon primary">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                        />
                      </svg>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div>
                          <div class="timeline-title">Admin Message</div>
                          <div class="timeline-desc">
                            New feedback on your pitch from admin
                          </div>
                        </div>
                        <div class="timeline-time">1 day ago</div>
                      </div>
                    </div>
                  </div>

                  <div class="timeline-item">
                    <div class="timeline-icon muted">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                        />
                      </svg>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <div>
                          <div class="timeline-title">Profile Views</div>
                          <div class="timeline-desc">
                            Your pitch was viewed 47 times this week
                          </div>
                        </div>
                        <div class="timeline-time">2 days ago</div>
                      </div>
                    </div>
                  </div>
                </div>
                <button class="view-all-btn" style="margin-top: 1.5rem">
                  View All Activity
                </button>
              </div>
            </section>
          </div>

          <div id="view-settings" class="dashboard-view">
            <h2 class="section-title">Settings</h2>
            <p style="color: var(--muted-foreground)">
              Account settings coming soon...
            </p>
          </div>
          <div id="view-support" class="dashboard-view">
            <h2 class="section-title">Support</h2>
            <p style="color: var(--muted-foreground)">
              Contact support form coming soon...
            </p>
          </div>
        </main>
      </div>
    </div>

    <!-- WALLET MODALS -->
    <style>
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-backdrop.show {
            display: flex;
            opacity: 1;
        }
        .modal-card {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-backdrop.show .modal-card {
            transform: scale(1);
        }
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--foreground);
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--muted-foreground);
            font-size: 0.875rem;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--foreground);
            font-family: inherit;
            font-size: 1rem;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px hsla(263, 70%, 76%, 0.2);
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn-full {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 2000;
        }
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--destructive); }
    </style>


    <!-- Withdraw Modal -->
    <div id="withdrawModal" class="modal-backdrop">
        <div class="modal-card">
            <h3 class="modal-title">Withdraw Funds</h3>
            <p style="color: var(--muted-foreground); margin-bottom: 1.5rem; font-size: 0.9rem;">
                Available to withdraw: <strong style="color: var(--foreground);"><?php echo money($wallet_balance); ?></strong>
            </p>
            <form id="withdrawForm" onsubmit="handleWalletAction(event, 'withdraw_money')">
                <div class="form-group">
                    <label class="form-label">Amount (₹)</label>
                    <input type="number" name="amount" class="form-input" placeholder="e.g. 25000" max="<?php echo $wallet_balance; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Withdraw to</label>
                    <select name="method" class="form-input">
                        <option value="Primary Bank Account">Primary Bank Account</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="outline-btn btn-full" onclick="closeModal('withdrawModal')">Cancel</button>
                    <button type="submit" class="primary-btn btn-full">Withdraw</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <div id="toast-icon"></div>
        <div id="toast-message">Action successful</div>
    </div>

    <script>
      // Wallet Functions
      function openModal(id) {
          const modal = document.getElementById(id);
          modal.style.display = 'flex';
          setTimeout(() => modal.classList.add('show'), 10);
      }

      function closeModal(id) {
          const modal = document.getElementById(id);
          modal.classList.remove('show');
          setTimeout(() => modal.style.display = 'none', 300);
      }

      // Close modal on outside click
      window.onclick = function(event) {
          if (event.target.classList.contains('modal-backdrop')) {
              closeModal(event.target.id);
          }
      }

      function showToast(message, type = 'success') {
          const toast = document.getElementById('toast');
          const msg = document.getElementById('toast-message');
          const icon = document.getElementById('toast-icon');
          
          toast.className = `toast ${type}`;
          msg.textContent = message;
          icon.innerHTML = type === 'success' 
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--success)"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--destructive)"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
          
          toast.classList.add('show');
          setTimeout(() => toast.classList.remove('show'), 3000);
      }

      async function handleWalletAction(e, action) {
          e.preventDefault();
          const form = e.target;
          const btn = form.querySelector('button[type="submit"]');
          const originalText = btn.innerHTML;
          
          // Loading State
          btn.disabled = true;
          btn.innerHTML = '<svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:16px;height:16px;"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';

          const formData = new FormData(form);
          formData.append('action', action);

          try {
              const response = await fetch('wallet_handler.php', {
                  method: 'POST',
                  body: formData
              });
              
              const result = await response.json();
              
              if (result.success) {
                  showToast(result.message, 'success');
                  closeModal(action === 'add_money' ? 'addMoneyModal' : 'withdrawModal');
                  setTimeout(() => location.reload(), 1500); // Reload to show new balance/txns
              } else {
                  showToast(result.message, 'error');
              }
          } catch (error) {
              console.error(error);
              showToast('Something went wrong', 'error');
          } finally {
              btn.disabled = false;
              btn.innerHTML = originalText;
          }
      }

      // Theme Toggle
      let isDark = true;
      const sunIcon = document.getElementById("sunIcon");
      const moonIcon = document.getElementById("moonIcon");

      function toggleTheme() {
        isDark = !isDark;
        document.body.classList.toggle("light", !isDark);
        sunIcon.style.display = isDark ? "block" : "none";
        moonIcon.style.display = isDark ? "none" : "block";
      }

      // Sidebar Toggle (Mobile)
      function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("sidebarOverlay");
        sidebar.classList.toggle("open");
        overlay.classList.toggle("open");
      }

      // --- VIEW SWITCHING LOGIC ---
      document.querySelectorAll(".nav-item").forEach((item) => {
        item.addEventListener("click", function () {
          // Ignore if it's the logout button (handled by onclick)
          if (this.classList.contains("logout")) return;

          // 1. Remove active class from all sidebar items
          document
            .querySelectorAll(".nav-item")
            .forEach((i) => i.classList.remove("active"));

          // 2. Add active class to clicked item
          this.classList.add("active");

          // 3. Get the 'data-item' value
          const viewName = this.getAttribute("data-item");

          // 4. Hide all Dashboard Views
          document.querySelectorAll(".dashboard-view").forEach((view) => {
            view.classList.remove("active");
          });

          // 5. Show the specific view matching the ID
          const targetView = document.getElementById(`view-${viewName}`);
          if (targetView) {
            targetView.classList.add("active");
          }

          // Close sidebar on mobile after selection
          if (window.innerWidth < 1024) {
            toggleSidebar();
          }
        });
      });
    </script>
  </body>
</html>
