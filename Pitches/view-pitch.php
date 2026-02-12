<?php
// ==========================================
// 1. BACKEND LOGIC
// ==========================================
session_start();
require_once '../db.php'; 
// Define the default back URL (Dashboard)
// Adjust the path "../dashboards/..." based on your folder structure
$back_url = '../dashboards/investor-dashboard.php?page=explore';

// Optional: Only respect the "Back" history if it came from the dashboard
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'investor-dashboard.php') !== false) {
    $back_url = $_SERVER['HTTP_REFERER'];
}
// Check login status
$is_logged_in = isset($_SESSION['user_id']);

// Check roles
$is_entrepreneur_view = false;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'entrepreneur') {
    $is_entrepreneur_view = true;
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<div style='color: white; text-align: center; margin-top: 50px;'>Error: No pitch ID provided.</div>");
}

$id = (int)$_GET['id'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// A. Fetch Main Pitch Data
$sql = "SELECT p.*, e.name as founder_name, e.email as founder_email, ov.status as kyc_status 
        FROM pitches p 
        JOIN entrepreneurs e ON p.entrepreneur_id = e.id 
        LEFT JOIN otp_verifications ov ON e.email = ov.email
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div style='color: white; text-align: center; margin-top: 50px;'>Pitch not found.</div>");
}

$pitch = $result->fetch_assoc();

// --- NEW: Define missing variables ---
$is_verified = (isset($pitch['kyc_status']) && $pitch['kyc_status'] === 'verified');
$funding_fmt = '₹' . number_format($pitch['funding_goal'] ?? 0);
$startup_initial = !empty($pitch['startup_name']) ? strtoupper(substr($pitch['startup_name'], 0, 1)) : 'P';

// --- NEW: Check if Pitch is Saved ---
$is_saved = false;
if ($is_logged_in) {
    $save_check = $conn->prepare("SELECT id FROM saved_pitches WHERE user_id = ? AND pitch_id = ?");
    $save_check->bind_param("ii", $user_id, $id);
    $save_check->execute();
    if ($save_check->get_result()->num_rows > 0) {
        $is_saved = true;
    }
    $save_check->close();
}
// ------------------------------------

// Increment View Count
$conn->query("UPDATE pitches SET views = views + 1 WHERE id = $id");

// B. Fetch Sub-data (Docs, Funds)
$doc_stmt = $conn->prepare("SELECT * FROM pitch_documents WHERE pitch_id = ?");
$doc_stmt->bind_param("i", $id);
$doc_stmt->execute();
$doc_res = $doc_stmt->get_result();

$fund_stmt = $conn->prepare("SELECT * FROM pitch_funds_usage WHERE pitch_id = ?");
$fund_stmt->bind_param("i", $id);
$fund_stmt->execute();
$fund_res = $fund_stmt->get_result();

// C. Formatters for View
$shares_issued = (int)$pitch['shares_issued'];
$shares_available = (int)$pitch['shares_issued'] - (int)($pitch['shares_sold'] ?? 0);

// Fetch Wallet Balance for logged in user
$wallet_balance = 0;
if ($is_logged_in) {
    $w_stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? AND user_role = 'investor'");
    $w_stmt->bind_param("i", $user_id);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result()->fetch_assoc();
    $wallet_balance = $w_res['balance'] ?? 0;
    $w_stmt->close();
}

// --- NEW: Investor KYC Check for Restriction ---
$investor_kyc_approved = false;
if ($is_logged_in && $_SESSION['user_role'] === 'investor') {
    $kyc_check_sql = "SELECT status FROM investor_kyc_details WHERE investor_id = ?";
    if ($stmtK = $conn->prepare($kyc_check_sql)) {
        $stmtK->bind_param("i", $user_id);
        $stmtK->execute();
        $resK = $stmtK->get_result();
        if ($rowK = $resK->fetch_assoc()) {
            if ($rowK['status'] === 'approved') {
                $investor_kyc_approved = true;
            }
        }
        $stmtK->close();
    }
}

$stmt->close();



// 1. Define descriptions
$round_info_map = [
    'Pre-Seed' => 'Funding to build a prototype and hire the founding team.',
    'Seed'     => 'Capital to validate product-market fit and initial user growth.',
    'Series A' => 'Funding to scale the user base and optimize the business model.',
    'Series B' => 'Expansion capital for growing market share and team.',
    'Bridge'   => 'Interim financing to reach the next major funding milestone.'
];

// 2. Get the description
$round_desc = isset($round_info_map[$pitch['round_name']]) 
              ? $round_info_map[$pitch['round_name']] 
              : "Investment opportunity to fund company growth.";

// 3. COLOR LOGIC: Default is Gold, but upgrade to Platinum for Series A+
$badge_class = 'badge-gold'; 

if (in_array($pitch['round_name'], ['Series A', 'Series B', 'Series C'])) {
    $badge_class = 'badge-platinum'; // Switches to Purple
}

// 4. ROUNDS LOGIC: Calculate Time Remaining
$expiry_date = isset($pitch['expiry_date']) ? $pitch['expiry_date'] : null;
$days_left = 0;
$hours_left = 0;
$is_expired = false;

if ($expiry_date) {
    $now = new DateTime();
    $expiry = new DateTime($expiry_date);
    if ($now < $expiry) {
        $interval = $now->diff($expiry);
        $days_left = $interval->format('%a');
        $hours_left = $interval->format('%h');
    } else {
        $is_expired = true;
    }
}

// Calculate Percentage (Avoid division by zero)
$raised = isset($pitch['amount_raised']) ? $pitch['amount_raised'] : 0;
$goal   = isset($pitch['funding_goal']) && $pitch['funding_goal'] > 0 ? $pitch['funding_goal'] : 1;

$percent_val = ($raised / $goal) * 100;

// Cap the visual bar at 100% (so it doesn't overflow), but show the real text
$width_val = min($percent_val, 100); 


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pitch['startup_name']); ?> | SmartPitchHub</title>
  <meta name="description" content="<?php echo htmlspecialchars($pitch['tagline']); ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===== CSS VARIABLES / DESIGN SYSTEM ===== */
    :root {
      --background: hsl(230, 45%, 8%);
      --foreground: hsl(0, 0%, 100%);
      --card: hsl(230, 40%, 12%);
      --card-foreground: hsl(0, 0%, 100%);
      --primary: hsl(263, 70%, 76%);
      --primary-foreground: hsl(230, 45%, 8%);
      --secondary: hsl(230, 35%, 18%);
      --secondary-foreground: hsl(0, 0%, 100%);
      --muted: hsl(230, 30%, 20%);
      --muted-foreground: hsl(230, 15%, 55%);
      --border: hsl(230, 30%, 20%);
      --success: hsl(142, 76%, 45%);
      --purple-glow: hsl(263, 70%, 50%);
      --radius: 0.75rem;
    }

    /* ===== RESET & BASE ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background-color: var(--background); color: var(--foreground); line-height: 1.6; -webkit-font-smoothing: antialiased; }
    a { color: inherit; text-decoration: none; }
    img { max-width: 100%; display: block; }

    /* ===== UTILITIES ===== */
    .container { max-width: 1400px; margin: 0 auto; padding: 0 1rem; }
    .gradient-primary { background: linear-gradient(135deg, hsl(263, 70%, 66%) 0%, hsl(280, 80%, 60%) 100%); }
    .gradient-text { background: linear-gradient(135deg, hsl(263, 70%, 76%) 0%, hsl(280, 80%, 70%) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .card-glow { box-shadow: 0 4px 30px -10px hsla(263, 70%, 50%, 0.15); }
    .card-glow:hover { box-shadow: 0 8px 40px -10px hsla(263, 70%, 50%, 0.3); }

    /* ===== NAVBAR (GUEST ONLY) ===== */
    .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 50; border-bottom: 1px solid hsla(230, 30%, 20%, 0.5); background: hsla(230, 45%, 8%, 0.8); backdrop-filter: blur(12px); }
    .navbar-inner { display: flex; align-items: center; justify-content: space-between; height: 64px; }
    .navbar-logo { display: flex; align-items: center; gap: 0.5rem; color: var(--primary); font-weight: 700; font-size: 1.25rem; transition: opacity 0.2s; }
    .navbar-logo:hover { opacity: 0.8; }
    .navbar-logo svg { width: 24px; height: 24px; }
    .navbar-links { display: none; align-items: center; gap: 2rem; }
    @media (min-width: 768px) { .navbar-links { display: flex; } }
    .navbar-links a { font-size: 0.875rem; font-weight: 500; color: var(--muted-foreground); transition: color 0.2s; }
    .navbar-links a:hover { color: var(--foreground); }
    .navbar-actions { display: flex; align-items: center; gap: 1rem; }

    /* ===== BUTTONS ===== */
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: var(--radius); font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
    .btn-primary { background: linear-gradient(135deg, hsl(263, 70%, 66%) 0%, hsl(280, 80%, 60%) 100%); color: var(--primary-foreground); box-shadow: 0 4px 15px -3px hsla(263, 70%, 50%, 0.4); }
    .btn-primary:hover { opacity: 0.9; box-shadow: 0 6px 20px -3px hsla(263, 70%, 50%, 0.5); }
    .btn-outline { background: transparent; border: 1px solid hsla(263, 70%, 76%, 0.5); color: var(--foreground); }
    .btn-outline:hover { background: hsla(263, 70%, 76%, 0.1); }
    .btn-ghost { background: transparent; color: var(--muted-foreground); }
    .btn-ghost:hover { color: var(--foreground); background: var(--secondary); }
    .btn-icon { padding: 0.5rem; width: 40px; height: 40px; }
    .btn-lg { padding: 0.875rem 1.5rem; font-size: 1rem; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    /* ===== BADGES ===== */
    .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .badge-outline { border: 1px solid var(--primary); color: var(--primary); }
    .badge-secondary { background: var(--secondary); color: var(--foreground); }

    /* ===== CARDS ===== */
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; transition: all 0.3s; }
    @media (min-width: 1024px) { .card { padding: 2rem; } }
    .card-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; }

    /* ===== MAIN LAYOUT ===== */
    .main { padding-top: <?php echo $is_logged_in ? '2rem' : '6rem'; ?>; padding-bottom: 3rem; }
    .main-grid { display: grid; gap: 2rem; }
    @media (min-width: 1024px) { .main-grid { grid-template-columns: 1fr 320px; } }
    .main-content { display: flex; flex-direction: column; gap: 2rem; animation: fadeIn 0.5s ease-out; }
    .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
    @media (min-width: 1024px) { .sidebar { position: sticky; top: <?php echo $is_logged_in ? '2rem' : '6rem'; ?>; height: fit-content; } }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* ===== HERO CARD ===== */
    .hero-card { position: relative; overflow: hidden; padding: 2rem; }
    .hero-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, hsla(263, 70%, 76%, 0.05) 0%, transparent 100%); pointer-events: none; }
    .hero-card-inner { position: relative; display: flex; flex-direction: column; align-items: center; gap: 1.5rem; text-align: center; }
    @media (min-width: 1024px) { .hero-card-inner { flex-direction: row; text-align: left; } }
    .hero-logo-wrapper { position: relative; }
    .hero-logo { width: 112px; height: 112px; border-radius: 1rem; border: 2px solid hsla(263, 70%, 76%, 0.3); background: var(--secondary); padding: 0.25rem; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary); font-weight: bold; }
    .hero-logo img { width: 100%; height: 100%; border-radius: 0.75rem; object-fit: cover; }
    .hero-verified-badge { position: absolute; bottom: -0.5rem; right: -0.5rem; width: 24px; height: 24px; border-radius: 50%; background: var(--success); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: var(--background); }
    .hero-info { flex: 1; }
    .hero-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 0.75rem; }
    @media (min-width: 1024px) { .hero-header { justify-content: flex-start; } }
    .hero-name { font-size: 1.875rem; font-weight: 700; }
    .hero-tagline { color: var(--muted-foreground); max-width: 36rem; margin-bottom: 1rem; }
    .hero-location { display: flex; align-items: center; justify-content: center; gap: 0.25rem; font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 1rem; }
    @media (min-width: 1024px) { .hero-location { justify-content: flex-start; } }
    .hero-funding { padding-top: 0.5rem; }
    .hero-funding-label { font-size: 0.875rem; color: var(--muted-foreground); }
    .hero-funding-amount { font-size: 1.875rem; font-weight: 700; }
    .hero-ctas { display: flex; flex-direction: column; gap: 0.75rem; }
    @media (min-width: 1024px) { .hero-ctas { align-items: flex-end; } }
    .hero-ctas .btn { min-width: 180px; }

    /* ===== TRUST BADGES ===== */
    .trust-badges { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 1rem; }
    @media (min-width: 1024px) { .trust-badges { justify-content: flex-start; } }
    .trust-badge { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 9999px; border: 1px solid var(--border); background: var(--card); font-size: 0.875rem; font-weight: 500; transition: border-color 0.2s; }
    .trust-badge:hover { border-color: hsla(263, 70%, 76%, 0.5); }
    .trust-badge svg { width: 20px; height: 20px; }
    .trust-badge.success svg { color: var(--success); }
    .trust-badge.primary svg { color: var(--primary); }

    /* ===== OVERVIEW SECTION ===== */
    .overview-grid { display: grid; gap: 1.5rem; }
    @media (min-width: 768px) { .overview-grid { grid-template-columns: repeat(2, 1fr); } }
    .overview-item h3 { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); margin-bottom: 0.5rem; }
    .overview-item p { color: var(--muted-foreground); line-height: 1.7; }

    /* ===== BUSINESS MODEL ===== */
    .business-grid { display: grid; gap: 1.5rem; }
    @media (min-width: 768px) { .business-grid { grid-template-columns: repeat(2, 1fr); } }
    .business-item { display: flex; align-items: flex-start; gap: 0.75rem; }
    .business-icon { padding: 0.5rem; border-radius: 0.5rem; background: hsla(263, 70%, 76%, 0.1); }
    .business-icon svg { width: 20px; height: 20px; color: var(--primary); }
    .business-item h3 { font-weight: 600; margin-bottom: 0.25rem; }
    .business-item p { font-size: 0.875rem; color: var(--muted-foreground); }
    .business-item ul { margin-top: 0.5rem; list-style: none; }
    .business-item li { font-size: 0.875rem; color: var(--muted-foreground); padding: 0.125rem 0; }
    .business-item li::before { content: '• '; }

    /* ===== FUNDING DETAILS ===== */
    .funding-stats { display: grid; gap: 1rem; margin-bottom: 1.5rem; }
    @media (min-width: 768px) { .funding-stats { grid-template-columns: repeat(3, 1fr); } }
    .funding-stat { text-align: center; padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--border); background: hsla(230, 35%, 18%, 0.3); }
    .funding-stat svg { width: 32px; height: 32px; color: var(--primary); margin: 0 auto 0.5rem; }
    .funding-stat-label { font-size: 0.875rem; color: var(--muted-foreground); }
    .funding-stat-value { font-size: 1.5rem; font-weight: 700; }
    .funding-breakdown h3 { font-weight: 600; margin-bottom: 1rem; }
    .funding-bar { margin-bottom: 0.75rem; }
    .funding-bar-header { display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem; }
    .funding-bar-label { color: var(--muted-foreground); }
    .funding-bar-value { font-weight: 500; }
    .funding-bar-track { height: 8px; border-radius: 9999px; background: var(--secondary); overflow: hidden; }
    .funding-bar-fill { height: 100%; border-radius: 9999px; background: linear-gradient(135deg, hsl(263, 70%, 66%) 0%, hsl(280, 80%, 60%) 100%); transition: width 0.5s ease-out; }

    /* ===== FOUNDER PROFILE ===== */
    .founder-card { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid var(--border); background: hsla(230, 35%, 18%, 0.3); margin-bottom: 1.5rem; }
    @media (min-width: 640px) { .founder-card { flex-direction: row; align-items: flex-start; text-align: left; } }
    .founder-avatar { width: 96px; height: 96px; border-radius: 50%; border: 2px solid hsla(263, 70%, 76%, 0.3); overflow: hidden; flex-shrink: 0; background: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary); }
    .founder-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .founder-info { flex: 1; text-align: center; }
    @media (min-width: 640px) { .founder-info { text-align: left; } }
    .founder-name { font-size: 1.25rem; font-weight: 700; }
    .founder-role { color: var(--primary); }
    .founder-bio { font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.5rem; }
    .founder-experience { display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.75rem; }
    @media (min-width: 640px) { .founder-experience { justify-content: flex-start; } }
    .founder-experience svg { width: 16px; height: 16px; }

    /* ===== ENGAGEMENT METRICS ===== */
    .metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .metric { text-align: center; }
    .metric-icon { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background: hsla(263, 70%, 76%, 0.1); margin-bottom: 0.5rem; }
    .metric-icon svg { width: 20px; height: 20px; color: var(--primary); }
    .metric-value { font-size: 1.5rem; font-weight: 700; }
    .metric-label { font-size: 0.75rem; color: var(--muted-foreground); }
    .progress-section { margin-top: 0.5rem; }
    .progress-header { display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.5rem; }
    .progress-label { display: flex; align-items: center; gap: 0.25rem; color: var(--muted-foreground); }
    .progress-label svg { width: 16px; height: 16px; }
    .progress-value { font-weight: 600; color: var(--primary); }
    .progress-track { height: 12px; border-radius: 9999px; background: var(--secondary); overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 9999px; background: linear-gradient(135deg, hsl(263, 70%, 66%) 0%, hsl(280, 80%, 60%) 100%); animation: pulseGlow 2s ease-in-out infinite; }
    @keyframes pulseGlow { 0%, 100% { box-shadow: 0 0 20px -5px hsla(263, 70%, 50%, 0.3); } 50% { box-shadow: 0 0 30px -5px hsla(263, 70%, 50%, 0.5); } }

    /* ===== ACTION SIDEBAR ===== */
    .action-sidebar h3 { font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; }
    .action-buttons { display: flex; flex-direction: column; gap: 0.75rem; }
    .action-buttons .btn { width: 100%; }
    .action-note { margin-top: 1rem; text-align: center; font-size: 0.75rem; color: var(--muted-foreground); }

    /* ===== DOCUMENTS ===== */
    .documents-grid { display: grid; gap: 1rem; }
    @media (min-width: 640px) { .documents-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .documents-grid { grid-template-columns: repeat(3, 1fr); } }
    .document-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--border); background: hsla(230, 35%, 18%, 0.3); transition: border-color 0.2s; }
    .document-item:hover { border-color: hsla(263, 70%, 76%, 0.5); }
    .document-info { display: flex; align-items: center; gap: 0.75rem; }
    .document-icon { padding: 0.5rem; border-radius: 0.5rem; background: hsla(263, 70%, 76%, 0.1); }
    .document-icon svg { width: 20px; height: 20px; color: var(--primary); }
    .document-name { font-weight: 500; }
    .document-type { font-size: 0.75rem; color: var(--muted-foreground); }
    .document-action { color: var(--primary); background: transparent; border: none; cursor: pointer; padding: 0.5rem; border-radius: 0.25rem; transition: background 0.2s; }
    .document-action:hover { background: hsla(263, 70%, 76%, 0.1); }
    .document-action.locked { color: var(--muted-foreground); cursor: not-allowed; }
    .document-action svg { width: 16px; height: 16px; }
    .documents-note { margin-top: 1rem; text-align: center; font-size: 0.875rem; color: var(--muted-foreground); }

    /* ===== FOOTER ===== */
    .footer { border-top: 1px solid var(--border); background: var(--card); padding: 2rem 0; text-align: center; }
    .footer p { font-size: 0.875rem; color: var(--muted-foreground); }
    .footer p:last-child { margin-top: 0.5rem; }




/* Tooltip Container */
.tooltip-container {
    position: relative;
    cursor: help;
}

/* THE BOX (Positioned BELOW now) */
.tooltip-container::before {
    content: attr(data-tooltip);
    position: absolute;
    
    /* CHANGED: Top instead of Bottom */
    top: 140%; 
    left: 50%;
    transform: translateX(-50%);
    
    background: #1f2937;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    white-space: pre-wrap;
    width: max-content;
    max-width: 240px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    
    /* Hidden by default */
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    z-index: 100;
    pointer-events: none;
}

/* THE ARROW (Pointing UP now) */
.tooltip-container::after {
    content: '';
    position: absolute;
    
    /* CHANGED: Top instead of Bottom */
    top: 120%;
    left: 50%;
    transform: translateX(-50%);
    
    border: 6px solid transparent;
    border-bottom-color: #1f2937; /* Color match */
    
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
}

/* Show on Hover */
.tooltip-container:hover::before,
.tooltip-container:hover::after {
    opacity: 1;
    visibility: visible;
}

/* Existing Gold Badge */
.badge-gold {
    background: rgba(251, 191, 36, 0.15);
    border: 1px solid rgba(251, 191, 36, 0.5);
    color: #fbbf24;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 8px;
    display: inline-flex;
    align-items: center;
}

/* NEW: Platinum Badge (For Series A, B, C) */
.badge-platinum {
    background: rgba(139, 92, 246, 0.15); /* Purple Background */
    border: 1px solid rgba(139, 92, 246, 0.5); /* Purple Border */
    color: #a78bfa; /* Light Purple Text */
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 8px;
    display: inline-flex;
    align-items: center;
}
.badge-bids {
    background: rgba(99, 102, 241, 0.2); /* Indigo/Purple tint */
    border: 1px solid rgba(99, 102, 241, 0.6);
    color: #818cf8; /* Bright Indigo text */
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    margin-left: 8px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
  </style>
</head>
<body>

  <?php if (!$is_logged_in): ?>
  <nav class="navbar">
    <div class="container navbar-inner">
      <a href="index.html" class="navbar-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/>
          <path d="M9 18h6"/><path d="M10 22h4"/>
        </svg>
        SmartPitchHub
      </a>
      <div class="navbar-links">
        <a href="#features">Features</a><a href="#how-it-works">How It Works</a><a href="#about">About</a>
      </div>
      <div class="navbar-actions">
        <button class="btn btn-primary">Get Started</button>
      </div>
    </div>
  </nav>
  <?php endif; ?>

  <main class="main">
    <div class="container">
      <div class="main-grid">
        
        <div class="main-content">
          <div class="card card-glow hero-card">
            <div class="hero-card-inner">
              <div class="hero-logo-wrapper">
                <div class="hero-logo">
                  <?php 
                  // 1. Check which column name is correct (logo OR pitch_logo)
                  $logo_path = !empty($pitch['logo']) ? $pitch['logo'] : 
                              (!empty($pitch['pitch_logo']) ? $pitch['pitch_logo'] : '');

                  // 2. Display Image if found
                  if (!empty($logo_path)): 
                  ?>
                      <img src="../<?php echo htmlspecialchars($logo_path); ?>" alt="Startup Logo">
                  
                  <?php else: ?>
                      <?php echo $startup_initial; ?>
                  <?php endif; ?>
              </div>
                <?php if($is_verified): ?><div class="hero-verified-badge">✓</div><?php endif; ?>
              </div>
<div class="hero-info">
    <div class="hero-header">
        <h1 class="hero-name"><?php echo htmlspecialchars($pitch['startup_name']); ?></h1>
        
        <span class="badge badge-outline"><?php echo htmlspecialchars($pitch['industry']); ?></span>
        <span class="badge badge-secondary"><?php echo htmlspecialchars($pitch['stage']); ?></span>
        
        <span class="badge <?php echo $badge_class; ?> tooltip-container" data-tooltip="<?php echo htmlspecialchars($round_desc); ?>">
    <?php echo htmlspecialchars($pitch['round_name']); ?> Round
    <svg style="width:14px; height:14px; margin-left:4px; vertical-align:text-bottom; opacity:0.8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
</span>
<span class="badge badge-bids">
    ⚡ <?php echo htmlspecialchars($pitch['required_bids']); ?> Bids
</span>
    </div>

    <p class="hero-tagline"><?php echo htmlspecialchars($pitch['tagline']); ?></p>
    
    <div class="hero-location">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
        </svg>
        <?php echo htmlspecialchars($pitch['location']); ?>
    </div>
    
    <div class="hero-funding">
        <p class="hero-funding-label">Funding Ask</p>
        <p class="hero-funding-amount gradient-text"><?php echo $funding_fmt; ?></p>
    </div>

    <!-- TIME REMAINING WIDGET -->
    <div style="background: hsla(230, 30%, 20%, 0.4); border: 1px solid hsla(230, 30%, 30%, 0.5); padding: 1.25rem; border-radius: 1rem; margin-top: 1.5rem; backdrop-filter: blur(8px);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted-foreground); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Round Timeline
            </span>
            <span class="badge <?php echo $is_expired ? 'badge-outline' : 'badge-success'; ?>" style="font-size: 0.65rem; padding: 2px 8px;">
                <?php echo $is_expired ? 'Ended' : 'Live Now'; ?>
            </span>
        </div>
        
        <?php if (!$is_expired): ?>
            <div style="display: flex; gap: 1rem;">
                <div style="text-align: center; flex: 1;">
                    <p style="font-size: 1.5rem; font-weight: 800; color: white; margin-bottom: 0.15rem;"><?php echo $days_left; ?></p>
                    <p style="font-size: 0.65rem; color: var(--muted-foreground); text-transform: uppercase; font-weight: 600;">Days Left</p>
                </div>
                <div style="width: 1px; background: hsla(230, 30%, 30%, 0.5);"></div>
                <div style="text-align: center; flex: 1;">
                    <p style="font-size: 1.5rem; font-weight: 800; color: white; margin-bottom: 0.15rem;"><?php echo $hours_left; ?></p>
                    <p style="font-size: 0.65rem; color: var(--muted-foreground); text-transform: uppercase; font-weight: 600;">Hours Left</p>
                </div>
            </div>
            <p style="font-size: 0.7rem; color: var(--muted-foreground); margin-top: 0.75rem; text-align: center; font-style: italic;">
                Campaign ends on <?php echo date('M d, Y', strtotime($expiry_date)); ?>
            </p>
        <?php else: ?>
            <div style="text-align: center; padding: 0.5rem 0;">
                <p style="font-size: 1.1rem; font-weight: 700; color: #ef4444; margin-bottom: 0.15rem;">Campaign Completed</p>
                <p style="font-size: 0.75rem; color: var(--muted-foreground);">Wait for the next round or secondary market listing.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
      
              <div class="hero-ctas">
  <?php if ($is_entrepreneur_view): ?>
    <a href="../dashboards/Entrepreneur-dashboard.php" class="btn btn-outline btn-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
      Back
    </a>
    <a href="edit-pitch.php?id=<?php echo $id; ?>" class="btn btn-primary btn-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Edit Pitch
    </a>

  <?php else: ?>
    <?php if ($is_logged_in): ?>
    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-outline btn-lg" style="width: 100%;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
      Back to Dashboard
    </a>
    <?php endif; ?>

   <?php if (!$investor_kyc_approved): ?>
     <button class="btn btn-primary btn-lg" onclick="alert('Verification Required: Please complete your KYC verification and get it approved to unlock investment access.')" style="opacity: 0.7; cursor: not-allowed;">
        Invest Now
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
        </svg>
     </button>
   <?php elseif ($is_expired): ?>
     <button class="btn btn-primary btn-lg" onclick="alert('Campaign Ended: This investment round has closed. Please look for subsequent rounds or check the secondary market.')" style="opacity: 0.6; cursor: not-allowed; background: var(--muted); border-color: var(--border);">
        Round Closed
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
     </button>
   <?php else: ?>
     <a href="../investment/investment-console.php?id=<?php echo $pitch['id']; ?>" class="btn btn-primary btn-lg">
        Invest Now
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
        </svg>
    </a>
   <?php endif; ?>
    
    <button type="button" id="saveBtnHero" class="btn btn-outline btn-lg" onclick="toggleSavePitch(<?php echo $id; ?>)" style="<?php echo $is_saved ? 'background: hsla(263, 70%, 76%, 0.1); border-color: var(--primary); color: var(--primary);' : ''; ?>">
  <svg id="saveIconHero" width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $is_saved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
  <span id="saveTextHero"><?php echo $is_saved ? 'Saved' : 'Save Pitch'; ?></span>
</button>

  <?php endif; ?>
</div>
            </div>
          </div>

          <div class="trust-badges">
            <?php if($pitch['is_approved']): ?>
            <div class="trust-badge success">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
              Admin Approved
            </div>
            <?php endif; ?>
            <?php if($is_verified): ?>
            <div class="trust-badge primary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>
              KYC Verified
            </div>
            <div class="trust-badge primary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg>
              Documents Verified
            </div>
            <?php endif; ?>
          </div>

          <div class="card card-glow">
            <h2 class="card-title">Pitch Overview</h2>
            <div class="overview-grid">
              <div class="overview-item">
                <h3>Problem & Solution</h3>
                <div class="pitch-description">
                  <?php echo $pitch['description']; ?>
                </div>
              </div>
              <div class="overview-item">
                <h3>Market Opportunity</h3>
                <p>The <?php echo htmlspecialchars($pitch['industry']); ?> market is growing rapidly. (Dynamic market data placeholder).</p>
              </div>
              <div class="overview-item">
                <h3>Unique Value Proposition</h3>
                <p>Zero-code implementation, 10x faster deployment than competitors, and industry-leading 99.9% accuracy.</p>
              </div>
            </div>
          </div>

          <div class="card card-glow">
            <h2 class="card-title">Business Model & Traction</h2>
            <div class="business-grid">
              <div class="business-item">
                <div class="business-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div><h3>Revenue Model</h3><p>SaaS subscription model with tiered pricing.</p></div>
              </div>
              <div class="business-item">
                <div class="business-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
                <div><h3>Current Traction</h3><ul><li>12 paying customers</li><li>₹18L ARR</li></ul></div>
              </div>
            </div>
          </div>

          <div class="card card-glow">
            <h2 class="card-title">Funding Details</h2>
            <div class="funding-stats">
              <div class="funding-stat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                <p class="funding-stat-label">Funding Required</p>
                <p class="funding-stat-value gradient-text"><?php echo $funding_fmt; ?></p>
              </div>
              <div class="funding-stat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>
                </svg>
                <p class="funding-stat-label">Company Valuation</p>
                <p class="funding-stat-value">
                    ₹<?php echo number_format($pitch['valuation'] ?: ($pitch['share_price'] * $pitch['shares_issued'])); ?>
                </p>
              </div>

              <div class="funding-stat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                  <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <p class="funding-stat-label">Share Price</p>
                <p class="funding-stat-value">₹<?php echo number_format($pitch['share_price']); ?></p>
              </div>

              <div class="funding-stat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                  <polyline points="2 17 12 22 22 17"></polyline>
                  <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                <p class="funding-stat-label">Shares Available</p>
                <p class="funding-stat-value"><?php echo number_format($shares_available); ?></p>
              </div>
            </div>
            
            <div class="funding-breakdown">
              <h3>Use of Funds</h3>
              <?php if($fund_res->num_rows > 0): ?>
                <?php while($fund = $fund_res->fetch_assoc()): ?>
                  <div class="funding-bar">
                    <div class="funding-bar-header">
                      <span class="funding-bar-label"><?php echo htmlspecialchars($fund['label']); ?></span>
                      <span class="funding-bar-value"><?php echo $fund['percentage']; ?>%</span>
                    </div>
                    <div class="funding-bar-track">
                      <div class="funding-bar-fill" style="width: <?php echo $fund['percentage']; ?>%;"></div>
                    </div>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <div class="funding-bar"><div class="funding-bar-header"><span class="funding-bar-label">Product Development</span><span class="funding-bar-value">40%</span></div><div class="funding-bar-track"><div class="funding-bar-fill" style="width: 40%;"></div></div></div>
                <div class="funding-bar"><div class="funding-bar-header"><span class="funding-bar-label">Sales & Marketing</span><span class="funding-bar-value">30%</span></div><div class="funding-bar-track"><div class="funding-bar-fill" style="width: 30%;"></div></div></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card card-glow">
            <h2 class="card-title">Founder</h2>
            
            <div class="founder-card" style="margin-bottom: 0;">
              <div class="founder-avatar">
                <img src="<?php echo !empty($pitch['pitch_logo']) ? $pitch['pitch_logo'] : 'https://via.placeholder.com/100'; ?>" alt="Founder">
              </div>
              <div class="founder-info">
                <h3 class="founder-name"><?php echo htmlspecialchars($pitch['founder_name']); ?></h3>
                <p class="founder-role">Founder & CEO</p>
                <p class="founder-bio">Passionate about democratizing technology for businesses of all sizes.</p>
                <div class="founder-experience">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                  Ex-Google, IIT Delhi
                </div>
              </div>
            </div>
          </div>

          <div class="card card-glow">
            <h2 class="card-title">Documents & Attachments</h2>
            <div class="documents-grid">
              <?php if($doc_res->num_rows > 0): ?>
                <?php while($doc = $doc_res->fetch_assoc()): ?>
                  <div class="document-item">
                    <div class="document-info">
                      <div class="document-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      </div>
                      <div>
                        <p class="document-name"><?php echo htmlspecialchars($doc['name']); ?></p>
                        <p class="document-type"><?php echo htmlspecialchars($doc['type']); ?></p>
                      </div>
                    </div>
                    <button class="document-action <?php echo $doc['is_locked'] ? 'locked' : ''; ?>">
                      <?php if($doc['is_locked']): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                      <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                      <?php endif; ?>
                    </button>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                 <p style="color:var(--muted-foreground)">No documents available.</p>
              <?php endif; ?>
            </div>
            <p class="documents-note">Some documents require investment or bid tokens to access</p>
          </div>

        </div>

        <div class="sidebar">
          
          <!-- AI Deep-Dive Analyst Widget -->
          <div class="card card-glow ai-deep-dive-card" style="border: 1px solid var(--primary); background: hsla(263, 70%, 76%, 0.05);">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="background: var(--primary); color: var(--background); padding: 0.35rem; border-radius: 0.5rem; display: flex;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                </div>
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--primary); font-weight: 700;">AI Deep-Dive</h3>
            </div>
            <p id="ai-status-text" style="font-size: 0.85rem; color: var(--muted-foreground); margin-bottom: 1rem;">
                Get a comprehensive risk analysis and market outlook for this startup using our VC model.
            </p>
            <button id="btn-ai-dive" class="btn btn-primary" style="width: 100%; height: 44px;" onclick="runAIDeepDive(<?php echo $id; ?>)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Analyze Startup
            </button>

            <div id="ai-dive-results" style="display: none; margin-top: 1.5rem; border-top: 1px dashed var(--border); padding-top: 1.25rem;">
                <div class="ai-risk-meter" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.75rem; color: var(--muted-foreground); font-weight: 600; text-transform: uppercase;">Risk Score</span>
                        <span id="ai-risk-val" style="font-size: 0.9rem; font-weight: 700;">--</span>
                    </div>
                    <div style="height: 6px; background: var(--secondary); border-radius: 3px; overflow: hidden;">
                        <div id="ai-risk-bar" style="height: 100%; width: 0%; transition: width 1s ease; border-radius: 3px;"></div>
                    </div>
                </div>

                <div id="ai-summary" style="font-size: 0.85rem; color: var(--foreground); line-height: 1.5; margin-bottom: 1.25rem; background: hsla(230, 35%, 18%, 0.4); padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid var(--primary);">
                    --
                </div>

                <div class="ai-swot-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div style="background: rgba(34, 197, 94, 0.1); padding: 0.5rem; border-radius: 0.4rem; border: 1px solid rgba(34, 197, 94, 0.2);">
                        <div style="font-size: 0.65rem; color: var(--success); font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Strength</div>
                        <div id="ai-swot-s" style="font-size: 0.75rem;">--</div>
                    </div>
                    <div style="background: rgba(239, 68, 68, 0.1); padding: 0.5rem; border-radius: 0.4rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div style="font-size: 0.65rem; color: #ef4444; font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Threat</div>
                        <div id="ai-swot-t" style="font-size: 0.75rem;">--</div>
                    </div>
                </div>

                <div style="text-align: center; background: var(--primary); color: var(--background); padding: 0.5rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.8rem;">
                    AI Verdict: <span id="ai-recommendation">--</span>
                </div>
            </div>
          </div>

          <?php if (!$is_entrepreneur_view): ?>           
            <div class="card card-glow action-sidebar">
              <h3>Take Action</h3>
              <div class="action-buttons">
                
                <button class="btn btn-primary" onclick="window.location.href='../dashboards/components/Bids/bids.php'">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                  Buy Bid Tokens
                </button>
                
                <button class="btn btn-outline" onclick="openInvestModal()">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                  Invest via Wallet
                </button>
                <button class="btn btn-outline" style="border-color: var(--border);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                  </svg>                 
                  View Entrepreneur Profile 
                </button>
                <button class="btn btn-ghost" disabled>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                  Contact Founder <span style="font-size: 0.7rem; margin-left: 0.25rem;">(after bid)</span>
                </button>
              </div>
              <p class="action-note">Contact unlocks after investment or bid</p>
            </div>
          <?php endif; ?>

          <div class="card card-glow">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Investor Interest</h3>
            <div class="metrics-grid">
              <div class="metric">
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></div>
                <p class="metric-value"><?php echo $pitch['likes']; ?></p>
                <p class="metric-label">Likes</p>
              </div>
              <div class="metric">
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></div>
                <p class="metric-value"><?php echo $pitch['views']; ?></p>
                <p class="metric-label">Views</p>
              </div>
              <div class="metric">
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <p class="metric-value"><?php echo $pitch['interested_investors']; ?></p>
                <p class="metric-label">Interested</p>
              </div>
            </div>
<div class="progress-section">
    <div class="progress-header">
        <span class="progress-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                <polyline points="16 7 22 7 22 13"/>
            </svg> 
            Funding Progress
        </span>
        <span class="progress-value"><?php echo number_format($percent_val, 1); ?>%</span>
    </div>
    
    <div class="progress-track">
        <div class="progress-fill" style="width: <?php echo $width_val; ?>%;"></div>
    </div>
</div>            </div>
          </div>

        </div>
      </div>
    </div>
  </main>

  <footer class="footer">
    <div class="container">
      <p>© 2024 SmartPitchHub. All rights reserved.</p>
      <p>Connecting Innovation with Investment</p>
    </div>
  </footer>
<script>
    // --- GLOBAL DATA ---
    const pitchId = <?php echo $id; ?>;
    const sharePrice = <?php echo floatval($pitch['share_price'] ?? 10); ?>;
    const availableBalance = <?php echo floatval($wallet_balance); ?>;

    // --- AI DEEP DIVE LOGIC ---
    async function runAIDeepDive(pitchId) {
        const btn = document.getElementById('btn-ai-dive');
        const results = document.getElementById('ai-dive-results');
        const statusText = document.getElementById('ai-status-text');
        
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span style="animation: spin 1s linear infinite; display: inline-block; margin-right: 8px;">↻</span> Analyzing...';
        statusText.innerText = "Consulting AI venture models...";

        try {
            // Add a small artificial delay for better UX perception
            await new Promise(r => setTimeout(r, 1500));
            
            const response = await fetch(`api_ai_deep_dive.php?id=${pitchId}`);
            const text = await response.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Invalid JSON response:", text);
                throw new Error("AI returned an invalid response format.");
            }

            if (data.success) {
                results.style.display = 'block';
                statusText.innerText = "Venture analysis complete.";
                
                // Risk Score
                document.getElementById('ai-risk-val').innerText = data.risk_score + '%';
                const riskBar = document.getElementById('ai-risk-bar');
                riskBar.style.width = data.risk_score + '%';
                
                if (data.risk_score < 40) riskBar.style.background = 'var(--success)';
                else if (data.risk_score < 70) riskBar.style.background = '#f59e0b';
                else riskBar.style.background = '#ef4444';
                
                // Content
                document.getElementById('ai-summary').innerText = data.summary;
                document.getElementById('ai-swot-s').innerText = data.swot.Strengths[0];
                document.getElementById('ai-swot-t').innerText = data.swot.Threats[0];
                document.getElementById('ai-recommendation').innerText = data.recommendation;
                
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Analysis Generated';
                btn.style.background = 'hsla(142, 76%, 45%, 0.2)';
                btn.style.color = 'var(--success)';
                btn.style.borderColor = 'hsla(142, 76%, 45%, 0.4)';
            } else {
                throw new Error(data.error);
            }
        } catch (err) {
            console.error("AI Error:", err);
            btn.disabled = false;
            btn.innerHTML = originalText;
            statusText.innerText = "AI Engine busy. Please try again.";
        }
    }

    // --- ANIMATIONS ---
    document.addEventListener('DOMContentLoaded', function() {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const bars = entry.target.querySelectorAll('.funding-bar-fill, .progress-fill');
            bars.forEach(bar => {
              const width = bar.style.width;
              bar.style.width = '0';
              setTimeout(() => { bar.style.width = width; }, 100);
            });
          }
        });
      }, { threshold: 0.5 });
      document.querySelectorAll('.card').forEach(card => observer.observe(card));
    });

    // --- SAVE PITCH LOGIC (FIXED) ---
    function toggleSavePitch(pitchId) {
        // 1. Prevent any default browser action (like refreshing)
        if(window.event) window.event.preventDefault();

        const btn = document.getElementById('saveBtnHero');
        if(!btn) return; 

        const icon = document.getElementById('saveIconHero');
        const text = document.getElementById('saveTextHero');

        // Optimistic UI Update (Update visuals immediately)
        const isCurrentlySaved = text.innerText === 'Saved';
        
        if (isCurrentlySaved) {
            text.innerText = 'Save Pitch';
            icon.setAttribute('fill', 'none');
            btn.style.background = 'transparent';
            btn.style.borderColor = 'hsla(263, 70%, 76%, 0.5)';
            btn.style.color = 'var(--foreground)';
        } else {
            text.innerText = 'Saved';
            icon.setAttribute('fill', 'currentColor');
            btn.style.background = 'hsla(263, 70%, 76%, 0.1)';
            btn.style.borderColor = 'var(--primary)';
            btn.style.color = 'var(--primary)';
        }

        // 2. Backend Request
        // IMPORTANT: Ensure 'toggle_save_pitch.php' is in the SAME FOLDER as this file
        fetch('toggle_save_pitch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pitch_id: pitchId })
        })
        .then(response => {
            // Check if file was found (404 means file path is wrong)
            if (!response.ok) {
                throw new Error("HTTP Error: " + response.status + " (File not found?)");
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                // If logic error (e.g., not logged in), revert UI and show message
                console.error("Server Error:", data.message);
                alert('Error: ' + data.message);
                // Only reload if absolutely necessary, otherwise just revert button
                if(data.message.includes("login")) location.reload();
            } else {
                console.log("Success:", data.action);
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('System Error: ' + error.message + '\nCheck console (F12) for details.');
        });
    }
  </script>
  <!-- INVESTMENT MODAL -->
  <div id="investModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card card-glow" style="max-width: 450px; width: 100%; position: relative;">
        <button onclick="closeInvestModal()" style="position: absolute; right: 20px; top: 20px; background: none; border: none; color: var(--muted-foreground); cursor: pointer;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="background: hsla(263, 70%, 76%, 0.1); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;" class="gradient-text">Secure Investment</h2>
            <p style="font-size: 0.875rem; color: var(--muted-foreground);">Funds will be held in Smart Escrow until the round completes.</p>
        </div>

        <div style="background: hsla(230, 35%, 18%, 0.4); padding: 1.25rem; border-radius: 0.75rem; border: 1px solid var(--border); margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.8rem; color: var(--muted-foreground);">
                <span>Available Balance</span>
                <span style="color: var(--success); font-weight: 600;">₹<?php echo number_format($wallet_balance, 2); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted-foreground);">
                <span>Share Price</span>
                <span style="color: var(--foreground); font-weight: 600;">₹<?php echo number_format($pitch['share_price'] ?? 10, 2); ?></span>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--primary); margin-bottom: 0.5rem;">Number of Shares</label>
            <div style="position: relative;">
                <input type="number" id="investShares" oninput="calculateInvestment()" placeholder="0" style="width: 100%; background: var(--secondary); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.75rem 1rem; color: white; font-weight: 700; font-size: 1.1rem; outline: none; transition: border-color 0.2s;">
                <div style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: var(--muted-foreground); font-weight: 600;">UNIT(S)</div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <p style="font-size: 0.7rem; color: var(--muted-foreground); text-transform: uppercase; font-weight: 600;">Total Investment</p>
                <h3 id="totalInvestmentDisplay" style="font-size: 1.5rem; font-weight: 800;">₹0.00</h3>
            </div>
            <div style="text-align: right;">
                <p style="font-size: 0.7rem; color: var(--muted-foreground); text-transform: uppercase; font-weight: 600;">Fee (0%)</p>
                <p style="color: var(--success); font-weight: 700;">₹0.00</p>
            </div>
        </div>

        <button id="btn-confirm-invest" onclick="processInvestment()" class="btn btn-primary" style="width: 100%; height: 52px; font-size: 1rem;">
            Confirm & Invest Now
        </button>
    </div>
  </div>

  <script>
    function openInvestModal() {
        if (!<?php echo $is_logged_in ? 'true' : 'false'; ?>) {
            alert("Please login as an investor to participate.");
            return;
        }
        
        // --- NEW: Block if KYC not approved ---
        if (!<?php echo $investor_kyc_approved ? 'true' : 'false'; ?>) {
            alert("Verification Required: Your KYC status is currently '<?php echo $kyc_status ?? 'Not Submitted'; ?>'. Please get it approved to invest.");
            return;
        }

        document.getElementById('investModal').style.display = 'flex';
    }

    function closeInvestModal() {
        document.getElementById('investModal').style.display = 'none';
    }

    function calculateInvestment() {
        const shares = parseInt(document.getElementById('investShares').value) || 0;
        const total = shares * sharePrice;
        document.getElementById('totalInvestmentDisplay').innerText = '₹' + total.toLocaleString();
        
        const btn = document.getElementById('btn-confirm-invest');
        if (total > availableBalance) {
            btn.innerHTML = 'Insufficient Balance';
            btn.style.opacity = '0.5';
            btn.disabled = true;
        } else {
            btn.innerHTML = 'Confirm & Invest Now';
            btn.style.opacity = '1';
            btn.disabled = false;
        }
    }

    async function processInvestment() {
        const shares = parseInt(document.getElementById('investShares').value);
        if (!shares || shares <= 0) {
            alert("Please enter a valid amount of shares.");
            return;
        }

        const btn = document.getElementById('btn-confirm-invest');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span style="animation: spin 1s linear infinite; display: inline-block; margin-right: 8px;">↻</span> Processing...';

        const formData = new FormData();
        formData.append('pitch_id', pitchId);
        formData.append('shares', shares);

        try {
            const response = await fetch('../Investment/process-investment.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                alert("🎉 " + data.message);
                location.reload();
            } else {
                alert("❌ Error: " + data.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            console.error("Investment Error:", err);
            alert("Connection error. Please try again.");
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
  </script>
</body>
</html>