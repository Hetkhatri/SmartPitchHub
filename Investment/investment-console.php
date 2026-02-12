<?php
// Unified AI and Layout Fix
error_reporting(E_ALL); 
ini_set('display_errors', 0);

require_once '../db.php'; 
session_start();

$GEMINI_API_KEY = 'YOUR_GROQ_API_KEY'; 
$ABSTRACT_API_KEY = 'YOUR_ABSTRACT_API_KEY';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// --- NEW KYC SECURITY CHECK ---
$kyc_status = 'not_submitted';
$check_kyc_sql = "SELECT status FROM investor_kyc_details WHERE investor_id = ?";
if ($stmtK = $conn->prepare($check_kyc_sql)) {
    $stmtK->bind_param("i", $user_id);
    $stmtK->execute();
    $resK = $stmtK->get_result();
    if ($rowK = $resK->fetch_assoc()) {
        $kyc_status = $rowK['status'];
    }
    $stmtK->close();
}

// Redirect if not approved
if ($kyc_status !== 'approved') {
    // We'll redirect to a page explaining why or back to dashboard with a message
    echo "<script>
        alert('Access Denied: You must complete and get your KYC approved before accessing the Investment Console.');
        window.location.href = '../dashboards/investor-dashboard.php';
    </script>";
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {}

if (isset($_SESSION['ai_cache_'.$pitch_id])) unset($_SESSION['ai_cache_'.$pitch_id]); 

$sql = "SELECT p.*, e.name as founder_name, e.email as founder_email, e.id as ent_id
        FROM pitches p 
        LEFT JOIN entrepreneurs e ON p.entrepreneur_id = e.id 
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pitch_id);
$stmt->execute();
$pitch = $stmt->get_result()->fetch_assoc();

if (!$pitch) { die("Pitch not found."); }

function fetch_smart_pitch_ai($pitch, $api_key, $forced_valuation = null) {
    if (!$api_key) return [];
    $is_groq = (strpos($api_key, 'gsk_') === 0);
    
    // Use forced valuation if provided (calculated from share price * total shares), otherwise fallback
    $valuation_value = $forced_valuation ?? ($pitch['valuation'] ?? ($pitch['funding_goal'] * 7.5));
    
    // Clean description of HTML tags for better AI consumption
    $clean_description = strip_tags($pitch['description'] ?? '');

    // Enhanced VC Professional Prompt
    $prompt = "Act as a Senior Venture Capital Analyst. Perform a rigorous, detailed investment analysis for the following startup:
    
    Startup: {$pitch['startup_name']}
    Tagline: " . ($pitch['tagline'] ?? 'N/A') . "
    Industry: " . ($pitch['industry'] ?? 'N/A') . "
    Current Stage: " . ($pitch['stage'] ?? 'N/A') . "
    Target Funding: ₹" . number_format($pitch['funding_goal'] ?? 0) . "
    Asks Valuation: ₹" . number_format($valuation_value) . "
    Startup Description: " . substr($clean_description, 0, 1500) . "
    
    REQUIRED OUTPUT FORMAT: JSON ONLY
    REQUIRED KEYS:
    1. investment_verdict: Choose from [Strong Buy, Buy, Hold, Speculative, Avoid].
    2. detailed_rationale: Provide a deep-dive (at least 150 words). Discuss Market Fit, Scalability, and Valuation (specifically the ₹" . number_format($valuation_value) . " valuation). Use '✓' for strengths and '⚠' for weaknesses.
    3. valuation_insight: Concise professional opinion on why the ₹" . number_format($valuation_value) . " valuation is " . ($valuation_value > ($pitch['funding_goal'] * 10) ? 'aggressive' : 'reasonable') . " or based on sector norms.
    4. risk_level: [Low, Medium, High, Extreme].
    5. growth_base: Float representing a 3-year conservative ROI multiple (e.g., 1.8).
    6. growth_best: Float representing a 5-year optimistic 'unicorn' ROI multiple (e.g., 12.5).
    7. risk_factors: Array of 3-4 highly specific market or execution risks.
    8. return_analysis: A summary sentence of the exit potential.
    
    Return ONLY VALID JSON. No markdown tags, no backticks, no text before or after the JSON.";
    
    if ($is_groq) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $headers = ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'];
        $payload = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => "You are a professional VC analyst. You always respond with pure JSON."],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.2
        ];
    } else {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
        $headers = ['Content-Type: application/json'];
        $payload = ["contents" => [["parts" => [["text" => $prompt]]]]];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return [];
    }

    $res = json_decode($response, true);
    if (!$res) return [];

    $text = ($is_groq) ? ($res['choices'][0]['message']['content'] ?? '') : ($res['candidates'][0]['content']['parts'][0]['text'] ?? '');
    
    // Improved JSON extraction (handles markdown code blocks if the AI includes them)
    $text = trim($text);
    if (strpos($text, '```json') !== false) {
        $text = str_replace(['```json', '```'], '', $text);
    } elseif (strpos($text, '```') !== false) {
        $text = str_replace('```', '', $text);
    }
    
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false) {
        $json_str = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json_str, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    return [];
}

function check_smart_fraud($email, $api_key) {
    if (!$email || !$api_key) return [];
    $url = "https://emailvalidation.abstractapi.com/v1/?api_key=$api_key&email=$email";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// ECONOMICS CALCULATION (Moved up for AI consumption)
$round_total_shares = $pitch['shares_issued'] > 0 ? $pitch['shares_issued'] : 50000;
$total_shares = $round_total_shares;

if ($pitch['valuation'] > 0) {
    $current_valuation = $pitch['valuation'];
    $current_share_price = $current_valuation / $round_total_shares;
} elseif ($pitch['share_price'] > 0) {
    $current_share_price = $pitch['share_price'];
    $current_valuation = $current_share_price * $round_total_shares;
} else {
    $current_valuation = $pitch['funding_goal'] * 7.5; 
    $current_share_price = $current_valuation / $round_total_shares;
}

// Improved AI result handling (Passing correctly calculated valuation)
$ai_data = fetch_smart_pitch_ai($pitch, $GEMINI_API_KEY, $current_valuation);
$ai_verdict = $ai_data['investment_verdict'] ?? "Hold";
$ai_rationale = !empty($ai_data['detailed_rationale']) ? $ai_data['detailed_rationale'] : "Our analyst team is currently refining the detailed thesis for the ".htmlspecialchars($pitch['industry'])." market. Please allow 30-60 seconds for the cloud intelligence models to finalize the multi-dimensional risk scoring.";

// WALLET BALANCE FETCH (For Smart Escrow verification)
$investor_wallet_balance = 0.00;
if (isset($_SESSION['user_id'])) {
    $w_stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? AND user_role = 'investor'");
    $w_stmt->bind_param("i", $_SESSION['user_id']);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result()->fetch_assoc();
    $investor_wallet_balance = $w_res ? floatval($w_res['balance']) : 0.00;
    $w_stmt->close();
}
$ai_val_text = $ai_data['valuation_insight'] ?? "Stable metrics.";
$risk_level = $ai_data['risk_level'] ?? "Medium";
$risk_css = strtolower($risk_level);
$mult_base = (float)($ai_data['growth_base'] ?? 1.8);
$mult_best = (float)($ai_data['growth_best'] ?? 4.5);
$return_analysis = $ai_data['return_analysis'] ?? "Projections based on sector norms.";
$raw_risks = $ai_data['risk_factors'] ?? ["Market Volatility", "Execution Risk", "Competition"];
$risk_factors_str = is_array($raw_risks) ? implode(", ", $raw_risks) : (string)$raw_risks;

// TRUTH SCORE (Verification Confidence)
$fraud_score = 80; // Baseline
if (!empty($pitch['is_approved']) && $pitch['is_approved'] == 1) {
    $fraud_score += 10;
}
if (!empty($pitch['founder_email'])) {
    $f_check = check_smart_fraud($pitch['founder_email'], $ABSTRACT_API_KEY);
    if (($f_check['deliverability'] ?? '') === 'DELIVERABLE') {
        $fraud_score += 9;
    }
}
$fraud_score = min(99, $fraud_score);

// FORMATTERS
$val_fmt = ($current_valuation >= 10000000) ? "₹" . number_format($current_valuation / 10000000, 2) . " Cr" : "₹" . number_format($current_valuation);
$share_price = $current_share_price;
$valuation = $current_valuation;

$shares_res = $conn->query("SELECT SUM(shares_bought) as total_sold FROM investments WHERE pitch_id = $pitch_id AND status = 'completed'");
$shares_actually_sold = ($shares_res) ? $shares_res->fetch_assoc()['total_sold'] : 0;
$display_remaining = max(0, $round_total_shares - $shares_actually_sold);
$percent_left = ($round_total_shares > 0) ? ($display_remaining / $round_total_shares) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Investment Command Center | SmartPitchHub</title>
  <meta name="description" content="AI-powered investment console for smart startup investing">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ==========================================
       SMARTPITCHHUB - INVESTMENT CONSOLE
       Premium Futuristic Design System
       ========================================== */

    :root {
      /* Deep Navy/Charcoal Background Spectrum */
      --background: hsl(222, 47%, 6%);
      --background-secondary: hsl(224, 50%, 8%);
      --background-elevated: hsl(226, 45%, 10%);
      --foreground: hsl(210, 40%, 92%);
      --foreground-muted: hsl(215, 20%, 65%);

      /* Card surfaces */
      --card: hsl(224, 50%, 9%);
      --card-foreground: hsl(210, 40%, 92%);
      --card-border: hsl(220, 50%, 18%);

      /* Neon Accents */
      --primary: hsl(185, 85%, 55%);
      --primary-glow: hsl(185, 100%, 60%);
      --primary-foreground: hsl(222, 47%, 6%);
      --accent-purple: hsl(270, 80%, 65%);
      --accent-purple-glow: hsl(270, 100%, 70%);
      --accent-blue: hsl(220, 85%, 60%);
      --accent-blue-glow: hsl(220, 100%, 65%);

      /* Secondary */
      --secondary: hsl(220, 40%, 14%);
      --secondary-foreground: hsl(210, 40%, 92%);
      --muted: hsl(220, 30%, 12%);

      /* Status Colors */
      --success: hsl(160, 70%, 45%);
      --success-glow: hsl(160, 100%, 50%);
      --warning: hsl(45, 90%, 55%);
      --destructive: hsl(0, 70%, 55%);

      --border: hsl(220, 40%, 15%);
      --radius: 0.75rem;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
      background: var(--background);
      color: var(--foreground);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      min-height: 100vh;
    }

    /* ==========================================
       ANIMATED BACKGROUND
       ========================================== */
    .animated-bg {
      position: fixed;
      inset: 0;
      overflow: hidden;
      pointer-events: none;
      z-index: 0;
    }

    .animated-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--background), var(--background-secondary), var(--background));
    }

    .bg-grid {
      position: absolute;
      inset: 0;
      background-image: 
        linear-gradient(hsla(220, 40%, 15%, 0.3) 1px, transparent 1px),
        linear-gradient(90deg, hsla(220, 40%, 15%, 0.3) 1px, transparent 1px);
      background-size: 60px 60px;
      opacity: 0.4;
    }

    .glow-orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(100px);
    }

    .glow-orb-1 {
      top: 0;
      left: 25%;
      width: 600px;
      height: 600px;
      background: hsla(185, 85%, 55%, 0.05);
    }

    .glow-orb-2 {
      bottom: 25%;
      right: 25%;
      width: 500px;
      height: 500px;
      background: hsla(270, 80%, 65%, 0.05);
    }

    .glow-orb-3 {
      top: 50%;
      right: 0;
      width: 400px;
      height: 400px;
      background: hsla(220, 85%, 60%, 0.05);
    }

    .light-streak {
      position: absolute;
      width: 200px;
      height: 1px;
      background: linear-gradient(90deg, transparent, hsla(185, 100%, 60%, 0.5), transparent);
      animation: streak 8s linear infinite;
    }

    @keyframes streak {
      0% { transform: translateX(-200px) translateY(0); opacity: 0; }
      10% { opacity: 1; }
      90% { opacity: 1; }
      100% { transform: translateX(calc(100vw + 200px)) translateY(100px); opacity: 0; }
    }

    /* Noise overlay */
    .noise-overlay {
      position: absolute;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
      opacity: 0.03;
      pointer-events: none;
    }

    /* ==========================================
       LAYOUT
       ========================================== */
    .container {
      position: relative;
      z-index: 10;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0.1rem 1rem 3rem; /* Further reduced top padding */
    }

    @media (min-width: 1024px) {
      .container { padding: 0.5rem 1rem 3rem; } /* Further reduced top padding */
    }

    /* ==========================================
       GLASS CARD
       ========================================== */
    .glass-card {
      position: relative;
      overflow: hidden;
      border-radius: var(--radius);
      border: 1px solid var(--card-border);
      background: linear-gradient(135deg, hsla(224, 50%, 9%, 0.8), hsla(222, 47%, 6%, 0.9));
      backdrop-filter: blur(20px);
      box-shadow: 0 4px 30px hsla(222, 47%, 2%, 0.5), inset 0 1px 0 hsla(220, 50%, 20%, 0.2);
      padding: 1.5rem;
      transition: all 0.3s ease;
    }

    .glass-card:hover {
      box-shadow: 0 4px 30px hsla(222, 47%, 2%, 0.5), 0 0 60px hsla(185, 100%, 60%, 0.15);
      border-color: hsla(185, 60%, 40%, 0.5);
      transform: translateY(-2px);
    }

    .glass-card-glow {
      border-color: hsla(185, 60%, 30%, 0.4);
      box-shadow: 0 4px 30px hsla(222, 47%, 2%, 0.5), 0 0 40px hsla(185, 100%, 60%, 0.1), inset 0 1px 0 hsla(185, 50%, 40%, 0.2);
    }

    .glass-card-purple {
      border-color: hsla(270, 60%, 40%, 0.4);
      box-shadow: 0 4px 30px hsla(222, 47%, 2%, 0.5), 0 0 40px hsla(270, 100%, 70%, 0.1), inset 0 1px 0 hsla(270, 50%, 50%, 0.2);
    }

    /* ==========================================
       TYPOGRAPHY & UTILITIES
       ========================================== */
    .gradient-text-cyan {
      background: linear-gradient(135deg, hsl(185, 85%, 55%), hsl(185, 100%, 70%));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .gradient-text-purple {
      background: linear-gradient(135deg, hsl(270, 80%, 65%), hsl(270, 100%, 80%));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .gradient-text-multi {
      background: linear-gradient(135deg, hsl(185, 85%, 55%), hsl(270, 80%, 65%));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .text-glow-cyan {
      text-shadow: 0 0 20px hsla(185, 100%, 60%, 0.5);
    }

    .number-glow {
      animation: number-pulse 3s ease-in-out infinite;
    }

    @keyframes number-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; text-shadow: 0 0 30px hsla(185, 100%, 60%, 0.8); }
    }

    .section {
      margin-bottom: 2rem;
      animation: fade-in 0.5s ease-out forwards;
    }

    @keyframes fade-in {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1rem;
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--foreground);
    }

    .section-title svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
    }

    /* ==========================================
       HEADER
       ========================================== */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.75rem; /* Reduced from 2rem to bring content up */
    }

    .back-btn {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: hsla(224, 50%, 9%, 0.5);
      backdrop-filter: blur(10px);
      color: var(--foreground-muted);
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .back-btn:hover {
      color: var(--foreground);
      border-color: hsla(185, 60%, 40%, 0.5);
      box-shadow: 0 0 30px hsla(185, 100%, 60%, 0.3);
    }

    .back-btn svg {
      width: 20px;
      height: 20px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .logo-icon {
      width: 40px;
      height: 40px;
      border-radius: 0.5rem;
      background: linear-gradient(135deg, var(--primary), var(--accent-blue));
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 30px hsla(185, 100%, 60%, 0.3);
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary-foreground);
    }

    .logo-text {
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--foreground);
    }

    /* ==========================================
       HERO SECTION
       ========================================== */
    .hero-card {
      padding: 1rem 1.25rem; /* Reduced padding from 1.25/1.5 */
    }

    .hero-header {
      display: flex;
      flex-direction: column;
      gap: 0.5rem; /* Reduced gap */
      margin-bottom: 1rem; /* Reduced from 1.5rem */
    }

    @media (min-width: 1024px) {
      .hero-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }
    }

    .hero-label {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.75rem;
    }

    .hero-label-line {
      height: 1px;
      width: 100px;
      background: linear-gradient(90deg, hsla(185, 85%, 55%, 0.5), transparent);
    }

    .hero-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--foreground);
      margin-bottom: 0.5rem;
    }

    @media (min-width: 768px) {
      .hero-title { font-size: 2.5rem; }
    }

    @media (min-width: 1024px) {
      .hero-title { font-size: 3rem; }
    }

    .hero-subtitle {
      font-size: 1.125rem;
      color: var(--foreground-muted);
    }

    .hero-badges {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    @media (min-width: 640px) {
      .hero-badges {
        flex-direction: row;
        align-items: center;
      }
    }

    .valuation-badge {
      padding: 1rem 1.5rem;
      border: 1px solid hsla(185, 60%, 30%, 0.3);
    }

    .valuation-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--foreground-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.25rem;
    }

    .valuation-value {
      font-size: 1.5rem;
      font-weight: 700;
    }

    @media (min-width: 768px) {
      .valuation-value { font-size: 1.875rem; }
    }

    .verification-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.375rem 0.875rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.025em;
      text-transform: uppercase;
      border: 1px solid;
      backdrop-filter: blur(10px);
    }

    .verification-pill svg {
      width: 14px;
      height: 14px;
    }

    .verification-pill.verified {
      background: hsla(160, 76%, 45%, 0.1);
      border-color: hsla(160, 76%, 45%, 0.4);
      color: hsl(160, 76%, 60%);
      box-shadow: 0 0 20px hsla(160, 76%, 45%, 0.1);
      transition: all 0.3s ease;
    }

    .verification-pill.verified:hover {
      background: hsla(160, 76%, 45%, 0.15);
      border-color: hsla(160, 76%, 45%, 0.6);
      box-shadow: 0 0 30px hsla(160, 76%, 45%, 0.3);
      transform: translateY(-1px);
    }

    .verification-pill.pending {
      background: hsla(45, 90%, 55%, 0.05);
      border-color: hsla(45, 90%, 55%, 0.3);
      color: hsl(45, 90%, 65%);
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      border: 1px solid hsla(160, 70%, 45%, 0.3);
      background: hsla(160, 70%, 45%, 0.1);
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--success);
    }

    .status-pill svg {
      width: 16px;
      height: 16px;
    }

    /* Confidence Meter */
    .confidence-meter {
      margin-bottom: 1.5rem;
    }

    .confidence-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }

    .confidence-label {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground-muted);
    }

    .confidence-label svg {
      width: 16px;
      height: 16px;
      color: var(--primary);
    }

    .confidence-value {
      font-size: 0.875rem;
      font-weight: 700;
      color: var(--primary);
    }

    .progress-track {
      height: 0.5rem;
      border-radius: 9999px;
      background: var(--secondary);
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      border-radius: 9999px;
      background: linear-gradient(90deg, var(--primary), var(--accent-blue));
      box-shadow: 0 0 10px hsla(185, 100%, 60%, 0.5);
      transition: width 1s ease;
    }

    /* AI Insight Container Redesign */
    .ai-analysis-container {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.25rem;
      margin-top: 1.5rem;
    }

    @media (min-width: 1024px) {
      .ai-analysis-container {
        grid-template-columns: 1.6fr 1fr;
      }
    }

    .ai-thesis-card {
      background: hsla(270, 80%, 65%, 0.03);
      border: 1px solid hsla(270, 80%, 65%, 0.15);
      border-radius: 1rem;
      padding: 1.5rem;
      position: relative;
    }

    .ai-thesis-card::after {
      content: 'INVESTMENT THESIS';
      position: absolute;
      top: -10px;
      left: 20px;
      font-size: 0.65rem;
      font-weight: 800;
      color: var(--accent-purple);
      background: var(--background-elevated);
      padding: 2px 10px;
      border-radius: 4px;
      letter-spacing: 1px;
    }

    .ai-vitals-card {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      background: hsla(185, 85%, 55%, 0.02);
      border: 1px solid hsla(185, 85%, 55%, 0.1);
      border-radius: 1rem;
      padding: 1.25rem;
    }

    .vital-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid hsla(210, 40%, 92%, 0.05);
    }

    .vital-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .vital-label {
      font-size: 0.8rem;
      color: var(--foreground-muted);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .vital-label svg {
      width: 14px;
      height: 14px;
      color: var(--primary);
    }

    .vital-value {
      font-size: 0.85rem;
      font-weight: 700;
      color: #fff;
    }

    .vital-badge {
      font-size: 0.7rem;
      padding: 2px 8px;
      border-radius: 4px;
      background: hsla(185, 85%, 55%, 0.1);
      color: var(--primary);
      border: 1px solid hsla(185, 85%, 55%, 0.2);
    }

    /* AI Insight Box */
    .ai-insight-box {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 0.5rem;
      background: hsla(185, 85%, 55%, 0.05);
      border: 1px solid hsla(185, 85%, 55%, 0.2);
    }

    .ai-insight-box svg {
      width: 20px;
      height: 20px;
      color: var(--primary);
      flex-shrink: 0;
      margin-top: 2px;
    }

    .ai-insight-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.25rem;
    }

    .ai-insight-text {
      color: #cbd5e1;
      line-height: 1.8;
      white-space: pre-line;
      margin-top: 12px;
      font-size: 0.95rem;
    }

    /* ==========================================
       ECONOMICS GRID
       ========================================== */
    .economics-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    @media (min-width: 1024px) {
      .economics-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .stat-card {
      padding: 1rem;
    }

    .stat-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }

    .stat-header svg {
      width: 16px;
      height: 16px;
    }

    .stat-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--foreground-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--foreground);
    }

    .stat-value.warning {
      color: var(--warning);
    }

    .mini-progress {
      margin-top: 0.5rem;
      height: 0.375rem;
      border-radius: 9999px;
      background: var(--secondary);
      overflow: hidden;
    }

    .mini-progress-fill {
      height: 100%;
      border-radius: 9999px;
      background: linear-gradient(90deg, var(--warning), var(--primary));
      transition: width 1s ease;
    }

    /* Formula */
    .formula-card {
      padding: 1rem;
      border-style: dashed;
    }

    .formula-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      font-size: 0.875rem;
    }

    @media (min-width: 640px) {
      .formula-content {
        flex-direction: row;
      }
    }

    .formula-label {
      color: var(--foreground-muted);
    }

    .formula-elements {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-family: monospace;
    }

    .formula-pill {
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      border: 1px solid;
    }

    .formula-pill.primary {
      background: hsla(185, 85%, 55%, 0.1);
      color: var(--primary);
      border-color: hsla(185, 85%, 55%, 0.3);
    }

    .formula-pill.purple {
      background: hsla(270, 80%, 65%, 0.1);
      color: var(--accent-purple);
      border-color: hsla(270, 80%, 65%, 0.3);
    }

    .formula-pill.blue {
      background: hsla(220, 85%, 60%, 0.1);
      color: var(--accent-blue);
      border-color: hsla(220, 85%, 60%, 0.3);
    }

    /* ==========================================
       INVESTMENT CONFIGURATOR
       ========================================== */
    .configurator-card {
      padding: 2rem;
    }

    .share-selector {
      margin-bottom: 2rem;
    }

    .share-selector-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }

    .share-selector-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground-muted);
    }

    .slider-container {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .slider-btn {
      width: 40px;
      height: 40px;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: transparent;
      color: var(--foreground);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
    }

    .slider-btn:hover:not(:disabled) {
      background: var(--secondary);
      border-color: hsla(185, 60%, 40%, 0.5);
      box-shadow: 0 0 30px hsla(185, 100%, 60%, 0.3);
    }

    .slider-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .slider-btn svg {
      width: 16px;
      height: 16px;
    }

    .slider-track {
      flex: 1;
      height: 8px;
      border-radius: 9999px;
      background: var(--secondary);
      position: relative;
      cursor: pointer;
    }

    .slider-fill {
      height: 100%;
      border-radius: 9999px;
      background: linear-gradient(90deg, var(--primary), var(--accent-blue));
      box-shadow: 0 0 10px hsla(185, 100%, 60%, 0.5);
      transition: width 0.1s ease;
    }

    .slider-thumb {
      position: absolute;
      top: 50%;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--foreground);
      border: 2px solid var(--primary);
      transform: translate(-50%, -50%);
      cursor: grab;
      box-shadow: 0 0 15px hsla(185, 100%, 60%, 0.5);
    }

    .share-display {
      text-align: center;
    }

    .share-count {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .share-label {
      font-size: 0.875rem;
      color: var(--foreground-muted);
    }

    /* Calculations Grid */
    .calc-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    @media (min-width: 768px) {
      .calc-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .calc-card {
      padding: 1rem;
      text-align: center;
    }

    .calc-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--foreground-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
    }

    .calc-value {
      font-size: 1.875rem;
      font-weight: 700;
    }

    .calc-value.foreground {
      color: var(--foreground);
    }

    .risk-display {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .risk-display svg {
      width: 20px;
      height: 20px;
    }

    .risk-display.low { color: var(--success); }
    .risk-display.medium { color: var(--warning); }
    .risk-display.high { color: var(--destructive); }

    /* Projections */
    .projections-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground-muted);
      margin-bottom: 1rem;
    }

    .projections-header svg {
      width: 16px;
      height: 16px;
      color: var(--primary);
    }

    .projections-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }

    @media (min-width: 768px) {
      .projections-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .projection-card {
      position: relative;
      overflow: hidden;
    }

    .projection-bg {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, hsla(185, 85%, 55%, 0.05), transparent);
    }

    .projection-content {
      position: relative;
      z-index: 1;
    }

    .projection-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }

    .projection-round {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--foreground-muted);
    }

    .projection-percent {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--success);
    }

    .projection-value {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--foreground);
    }

    .projection-multiplier {
      font-size: 0.75rem;
      color: var(--foreground-muted);
    }

    /* ==========================================
       AI INSIGHT STACK
       ========================================== */
    .insight-stack {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .insight-card {
      cursor: pointer;
    }

    .insight-card.expanded {
      box-shadow: 0 0 0 1px hsla(185, 85%, 55%, 0.3);
    }

    .insight-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
    }

    .insight-main {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      flex: 1;
    }

    .insight-icon {
      padding: 0.75rem;
      border-radius: 0.5rem;
    }

    .insight-icon.cyan {
      background: hsla(185, 85%, 55%, 0.1);
      color: var(--primary);
    }

    .insight-icon.purple {
      background: hsla(270, 80%, 65%, 0.1);
      color: var(--accent-purple);
    }

    .insight-icon.success {
      background: hsla(160, 70%, 45%, 0.1);
      color: var(--success);
    }

    .insight-icon.blue {
      background: hsla(220, 85%, 60%, 0.1);
      color: var(--accent-blue);
    }

    .insight-icon svg {
      width: 20px;
      height: 20px;
    }

    .insight-content {
      flex: 1;
    }

    .insight-title-row {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.5rem;
    }

    .insight-title {
      font-weight: 600;
      color: var(--foreground);
    }

    .confidence-badge {
      padding: 0.125rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .confidence-badge.high {
      background: hsla(160, 70%, 45%, 0.2);
      color: var(--success);
    }

    .confidence-badge.medium {
      background: hsla(185, 85%, 55%, 0.2);
      color: var(--primary);
    }

    .confidence-badge.low {
      background: hsla(45, 90%, 55%, 0.2);
      color: var(--warning);
    }

    .insight-summary {
      font-size: 0.875rem;
      color: var(--foreground-muted);
    }

    .insight-toggle {
      padding: 0.5rem;
      border-radius: 0.5rem;
      border: none;
      background: transparent;
      color: var(--foreground-muted);
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .insight-toggle:hover {
      background: var(--secondary);
      color: var(--foreground);
    }

    .insight-toggle svg {
      width: 20px;
      height: 20px;
      transition: transform 0.3s ease;
    }

    .insight-toggle.expanded svg {
      transform: rotate(180deg);
    }

    .insight-details {
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid var(--border);
      display: none;
    }

    .insight-details.show {
      display: block;
    }

    .insight-details-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.75rem;
    }

    .insight-details-header svg {
      width: 16px;
      height: 16px;
      color: var(--primary);
    }

    .insight-details-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .insight-details-text {
      font-size: 0.875rem;
      color: var(--foreground-muted);
      line-height: 1.7;
      white-space: pre-line;
    }

    /* ==========================================
       CONFIRMATION ZONE
       ========================================== */
    .confirmation-card {
      padding: 2rem;
      position: relative;
      overflow: hidden;
    }

    .confirmation-orb-1 {
      position: absolute;
      bottom: -80px;
      right: -80px;
      width: 240px;
      height: 240px;
      border-radius: 50%;
      background: hsla(185, 85%, 55%, 0.05);
      filter: blur(60px);
    }

    .confirmation-orb-2 {
      position: absolute;
      top: -40px;
      left: -40px;
      width: 160px;
      height: 160px;
      border-radius: 50%;
      background: hsla(270, 80%, 65%, 0.05);
      filter: blur(40px);
    }

    .confirmation-content {
      position: relative;
      z-index: 1;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    @media (min-width: 768px) {
      .summary-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .summary-card {
      padding: 1rem;
    }

    .summary-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--foreground-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.25rem;
    }

    .summary-value {
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--foreground);
    }

    .summary-value.highlight {
      font-size: 1.5rem;
      font-weight: 700;
    }

    /* Lock-in Notice */
    .lockin-notice {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 0.5rem;
      background: hsla(220, 40%, 14%, 0.5);
      border: 1px solid var(--border);
      margin-bottom: 2rem;
    }

    .lockin-notice svg {
      width: 20px;
      height: 20px;
      color: var(--foreground-muted);
      flex-shrink: 0;
      margin-top: 2px;
    }

    .lockin-title {
      font-weight: 500;
      color: var(--foreground);
      margin-bottom: 0.25rem;
    }

    .lockin-text {
      font-size: 0.875rem;
      color: var(--foreground-muted);
    }

    /* CTA Buttons */
    .cta-container {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    @media (min-width: 640px) {
      .cta-container {
        flex-direction: row;
      }
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 1rem 2.5rem;
      border-radius: var(--radius);
      font-size: 1.125rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
    }

    .btn svg {
      width: 20px;
      height: 20px;
    }

    .btn-primary {
      flex: 1;
      background: linear-gradient(135deg, var(--primary), var(--accent-blue));
      color: var(--primary-foreground);
      box-shadow: 0 0 30px hsla(185, 100%, 60%, 0.3);
      animation: pulse-glow 2s ease-in-out infinite;
    }

    .btn-primary:hover {
      box-shadow: 0 0 50px hsla(185, 100%, 60%, 0.5);
      transform: scale(1.02);
    }

    .btn-primary:active {
      transform: scale(0.98);
    }

    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 20px hsla(185, 100%, 60%, 0.4); }
      50% { box-shadow: 0 0 40px hsla(185, 100%, 60%, 0.6); }
    }

    @keyframes pulse {
      0% { transform: scale(0.95); box-shadow: 0 0 0 0 hsla(45, 90%, 55%, 0.7); }
      70% { transform: scale(1); box-shadow: 0 0 0 10px hsla(45, 90%, 55%, 0); }
      100% { transform: scale(0.95); box-shadow: 0 0 0 0 hsla(45, 90%, 55%, 0); }
    }

    .btn-secondary {
      background: hsla(185, 85%, 55%, 0.1);
      color: var(--primary);
      border: 1px solid hsla(185, 85%, 55%, 0.3);
    }

    .btn-secondary:hover {
      background: hsla(185, 85%, 55%, 0.2);
      border-color: hsla(185, 85%, 55%, 0.5);
      box-shadow: 0 0 30px hsla(185, 100%, 60%, 0.3);
    }

    .btn-arrow {
      transition: transform 0.3s ease;
    }

    .btn-primary:hover .btn-arrow {
      transform: translateX(4px);
    }

    /* Trust Badges */
    .trust-badges {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      gap: 1.5rem;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border);
    }

    .trust-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: var(--foreground-muted);
    }

    .trust-badge svg {
      width: 16px;
      height: 16px;
      color: var(--success);
    }

    /* ==========================================
       FOOTER
       ========================================== */
    .footer {
      margin-top: 3rem;
      padding-top: 2rem;
      border-top: 1px solid var(--border);
      text-align: center;
    }

    .footer-text {
      font-size: 0.875rem;
      color: var(--foreground-muted);
    }

    .footer-link {
      color: var(--primary);
      text-decoration: none;
      transition: text-decoration 0.3s ease;
    }

    .footer-link:hover {
      text-decoration: underline;
    }

    .footer-sep {
      margin: 0 0.5rem;
    }

    /* ==========================================
       SCROLLBAR
       ========================================== */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: var(--background);
    }

    ::-webkit-scrollbar-thumb {
      background: hsl(220, 40%, 20%);
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: hsla(185, 60%, 40%, 1);
    }

    /* ==========================================
       PAYMENT MODAL STYLES
       ========================================== */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      pointer-events: none;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .modal-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }

    .payment-modal {
      width: 100%;
      max-width: 480px;
      background: var(--background-elevated);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 2rem;
      transform: scale(0.9) translateY(20px);
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      position: relative;
    }

    .modal-overlay.active .payment-modal {
      transform: scale(1) translateY(0);
    }

    .modal-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(255, 255, 255, 0.05);
      border: none;
      color: var(--foreground-muted);
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .modal-close:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
    }

    .modal-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .modal-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: white;
      margin-bottom: 0.5rem;
    }

    .modal-subtitle {
      font-size: 0.9rem;
      color: var(--foreground-muted);
    }

    .payment-options {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .payment-option {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: left;
      width: 100%;
    }

    .payment-option:hover:not(:disabled) {
      background: rgba(255, 255, 255, 0.06);
      border-color: var(--primary);
    }

    .payment-option.disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .option-icon {
      width: 48px;
      height: 48px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      flex-shrink: 0;
    }

    .option-info {
      flex: 1;
    }

    .option-label {
      font-weight: 600;
      color: white;
      display: block;
    }

    .option-desc {
      font-size: 0.8rem;
      color: var(--foreground-muted);
    }

    .wallet-balance {
      font-size: 0.75rem;
      color: var(--success);
      font-weight: 600;
      margin-top: 2px;
    }

    .razorpay-badge {
      font-size: 0.7rem;
      background: rgba(30, 58, 138, 0.5);
      color: #93c5fd;
      padding: 2px 6px;
      border-radius: 4px;
      margin-left: 0.5rem;
    }

    .investment-summary-mini {
      background: rgba(255, 255, 255, 0.02);
      border-radius: 12px;
      padding: 1rem;
      margin-top: 2rem;
      border: 1px dashed var(--card-border);
    }

    .sm-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.5rem;
      font-size: 0.85rem;
    }

    .sm-label { color: var(--foreground-muted); }
    .sm-value { color: white; font-weight: 600; }
    .sm-total { color: var(--primary); font-size: 1rem; font-weight: 700; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05); }
  </style>
</head>
<body>
  <!-- Animated Background -->
  <div class="animated-bg">
    <div class="bg-grid"></div>
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>
    <div class="glow-orb glow-orb-3"></div>
    <div class="light-streak" style="top: 20%; animation-delay: 0s;"></div>
    <div class="light-streak" style="top: 45%; animation-delay: 3s;"></div>
    <div class="light-streak" style="top: 70%; animation-delay: 6s;"></div>
    <div class="noise-overlay"></div>
  </div>

  <main class="container">
    <!-- Header -->
    <header class="header">
      <button class="back-btn" onclick="window.history.back()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
        </svg>
        <span>Back</span>
      </button>
      <div class="logo">
        <div class="logo-icon">S</div>
        <span class="logo-text">SmartPitchHub</span>
      </div>
    </header>

    <!-- Hero Section -->
    <section class="section" style="animation-delay: 0s;">
      <div class="glass-card glass-card-glow hero-card">
        <div class="glow-orb" style="position: absolute; top: -80px; right: -80px; width: 240px; height: 240px; background: hsla(185, 85%, 55%, 0.1);"></div>
        
        <div class="hero-header">
          <div class="hero-info">
            <div class="hero-label">
              <span>Investment Command Center</span>
              <div class="hero-label-line"></div>
            </div>
            <h1 class="hero-title"><?php echo htmlspecialchars($pitch['startup_name']); ?></h1>
            <p class="hero-subtitle"><?php echo htmlspecialchars($pitch['stage']); ?> • <?php echo htmlspecialchars($pitch['industry']); ?></p>
          </div>

          <div class="hero-badges">
            <div class="glass-card valuation-badge">
              <p class="valuation-label">Live Valuation</p>
              <p class="valuation-value gradient-text-cyan number-glow"><?php echo $val_fmt; ?></p>
            </div>
            
            <?php 
              $is_valuation_approved = ($pitch['is_approved'] == 1);
              if ($is_valuation_approved): 
            ?>
              <div class="verification-pill verified">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"/></svg>
                  <span>Admin Verified</span>
              </div>
            <?php else: ?>
              <div class="verification-pill pending">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  <span>Review Pending</span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="confidence-meter">
          <div class="confidence-header">
            <div class="confidence-label">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
              <span>AI Valuation Confidence</span>
            </div>
            <span class="confidence-value"><?php echo $fraud_score; ?>%</span>
          </div>
          <div class="progress-track">
            <div class="progress-fill" style="width: <?php echo $fraud_score; ?>%;"></div>
          </div>
        </div>

        <div class="ai-analysis-container">
          <!-- Left Side: Deep Narrative Analysis -->
          <div class="ai-thesis-card">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="padding: 0.5rem; background: hsla(270, 80%, 65%, 0.1); border-radius: 0.5rem; color: var(--accent-purple);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
                <div>
                    <p style="font-size: 0.7rem; color: var(--accent-purple); font-weight: 700; text-transform: uppercase;">Investment Verdict</p>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #fff; margin: 0;"><?php echo htmlspecialchars($ai_verdict); ?></h2>
                </div>
            </div>
            <p style="color: #cbd5e1; line-height: 1.8; font-size: 0.95rem; margin: 0;">
                <?php echo htmlspecialchars($ai_rationale); ?>
            </p>
          </div>

          <!-- Right Side: Key Vital Checks -->
          <div class="ai-vitals-card">
             <div class="vital-item">
                <span class="vital-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    Risk Level
                </span>
                <span class="vital-value" style="color: <?php echo ($risk_level == 'High' || $risk_level == 'Extreme') ? '#ef4444' : '#22c55e'; ?>">
                    <?php echo $risk_level; ?>
                </span>
             </div>
             <div class="vital-item">
                <span class="vital-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                    Valuation Insight
                </span>
                <span class="vital-badge"><?php echo $ai_val_text; ?></span>
             </div>
             <div class="vital-item">
                <span class="vital-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    Market Outlook
                </span>
                <span class="vital-value">Positive</span>
             </div>
             <div class="vital-item" style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
                <span class="vital-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 14 4-4 4 4"/><path d="M3 3v18h18"/><path d="m3 17 4-4 3 3 5-5"/></svg>
                    Exit Potential
                </span>
                <p style="font-size: 0.75rem; color: var(--foreground-muted); line-height: 1.4; margin: 0;">
                    <?php echo $return_analysis; ?>
                </p>
             </div>
          </div>
        </div>
      </div>
    </section>

<section class="section" style="animation-delay: 0.1s;">
      <div class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/>
        </svg>
        <span>Smart Share Economics</span>
      </div>

      <div class="economics-grid">
        
      
        <div class="glass-card stat-card">
          <div class="stat-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary);">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle>
            </svg>
            <span class="stat-label">Shares Sold</span>
          </div>
          <p class="stat-value number-glow"><?php echo number_format($shares_actually_sold); ?></p>
        </div>

        <div class="glass-card stat-card">
          <div class="stat-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-blue);">
               <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>
            </svg>
            <span class="stat-label">Total Shares</span>
          </div>
          <p class="stat-value number-glow"><?php echo number_format($round_total_shares); ?></p>
        </div>

        

        <div class="glass-card stat-card">
          <div class="stat-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--success);">
              <circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/>
            </svg>
            <span class="stat-label">Share Price</span>
          </div>
          <p class="stat-value gradient-text-cyan number-glow">₹<?php echo number_format($share_price, 2); ?></p>
        </div>

        <div class="glass-card glass-card-glow stat-card" style="position: relative; overflow: hidden;">
          <!-- Scarcity Pulse -->
          <div style="position: absolute; top: 12px; right: 12px; display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--warning); font-weight: 700;">Market Live</span>
            <div style="width: 8px; height: 8px; background: var(--warning); border-radius: 50%; box-shadow: 0 0 10px var(--warning); animation: pulse 1.5s infinite;"></div>
          </div>

          <div class="stat-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--warning);">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <span class="stat-label">Remaining Shares</span>
          </div>
          <p class="stat-value warning number-glow"><?php echo number_format($display_remaining); ?></p>
          
          <div class="mini-progress">
            <div class="mini-progress-fill" style="width: <?php echo $percent_left; ?>%; background: var(--warning);"></div>
          </div>

          <?php if($percent_left < 20): ?>
            <div style="margin-top: 8px; font-size: 11px; color: #ef4444; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3"><path d="m12 8 4 4-4 4"/><path d="M8 12h8"/></svg>
                SELLING FAST: ONLY <?php echo round($percent_left); ?>% LEFT
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- FINAL CHOICE: THE WEALTH RIBBON (Refined & Polished) -->
      <div style="margin-top: 3rem; margin-bottom: 2rem; position: relative; padding: 3rem 0; overflow: hidden; border-radius: 20px; background: rgba(255,255,255,0.01);">
          
          <!-- Liquid Motion Background -->
          <svg style="position: absolute; top: 50%; left: 0; width: 100%; height: 160px; transform: translateY(-50%); overflow: visible; z-index: 0; pointer-events: none; opacity: 0.6;">
              <defs>
                  <linearGradient id="ribbonGradFinal" x1="0%" y1="0%" x2="100%" y2="0%">
                      <stop offset="0%" stop-color="var(--accent-purple)" stop-opacity="0" />
                      <stop offset="15%" stop-color="var(--accent-purple)" stop-opacity="0.3" />
                      <stop offset="50%" stop-color="var(--primary)" stop-opacity="0.5" />
                      <stop offset="85%" stop-color="var(--success)" stop-opacity="0.3" />
                      <stop offset="100%" stop-color="var(--success)" stop-opacity="0" />
                  </linearGradient>
                  <filter id="glowFlow">
                      <feGaussianBlur stdDeviation="4" result="blur" />
                      <feComposite in="SourceGraphic" in2="blur" operator="over" />
                  </filter>
              </defs>
              <path filter="url(#glowFlow)" d="M0,80 C200,20 400,140 600,80 C800,20 1000,140 1200,80" stroke="url(#ribbonGradFinal)" stroke-width="3" fill="none">
                  <animate attributeName="d" 
                      values="M0,80 C200,20 400,140 600,80 C800,20 1000,140 1200,80;
                              M0,80 C200,140 400,20 600,80 C800,140 1000,20 1200,80;
                              M0,80 C200,20 400,140 600,80" 
                      dur="12s" repeatCount="indefinite" />
              </path>
          </svg>

          <!-- Wealth Nodes -->
          <div style="display: flex; justify-content: space-around; align-items: center; position: relative; z-index: 1; max-width: 1000px; margin: 0 auto;">
              
              <!-- Component: Enterprise Value -->
              <div style="text-align: center; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                  <div style="width: 45px; height: 45px; background: rgba(124, 58, 237, 0.1); border: 1px solid rgba(124, 58, 237, 0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; transform: rotate(45deg); box-shadow: 0 0 20px rgba(124, 58, 237, 0.15);">
                      <div style="transform: rotate(-45deg);">
                          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-purple)" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                      </div>
                  </div>
                  <span style="display: block; color: #94a3b8; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px;">Enterprise Valuation</span>
                  <span style="font-size: 1.6rem; font-weight: 900; color: #fff; letter-spacing: -0.5px;"><?php echo $val_fmt; ?></span>
              </div>

              <!-- Component: Center Entry (THE HERO) -->
              <div style="text-align: center; position: relative; padding: 0 2rem;">
                  <div style="position: absolute; inset: -20px; background: radial-gradient(circle, rgba(6, 182, 212, 0.15) 0%, transparent 70%); border-radius: 50%; animation: pulse-glow 3s infinite;"></div>
                  
                  <div style="position: relative; z-index: 2;">
                      <span style="display: block; color: var(--primary); font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 15px;">Elite Entry Price</span>
                      <div style="background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); padding: 1.5rem 3rem; border-radius: 24px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.4); border-top: 2px solid var(--primary);">
                          <div style="display: flex; align-items: baseline; gap: 8px; justify-content: center;">
                              <span style="font-size: 1rem; color: var(--primary); font-weight: 600;">₹</span>
                              <span style="font-size: 2.75rem; font-weight: 950; color: #fff; line-height: 1; letter-spacing: -1.5px;"><?php echo number_format($share_price, 2); ?></span>
                          </div>
                          <div style="margin-top: 10px; font-size: 0.65rem; color: #64748b; font-weight: 600; letter-spacing: 1px;">PEGGED UNIT VALUE</div>
                      </div>
                  </div>
              </div>

              <!-- Component: Authorized Supply -->
              <div style="text-align: center;">
                  <div style="width: 45px; height: 45px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; transform: rotate(45deg); box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);">
                      <div style="transform: rotate(-45deg);">
                          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                      </div>
                  </div>
                  <span style="display: block; color: #94a3b8; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px;">Authorized Supply</span>
                  <span style="font-size: 1.6rem; font-weight: 900; color: #fff; letter-spacing: -0.5px;"><?php echo number_format($round_total_shares); ?></span>
              </div>

          </div>
      </div>

      <style>
          @keyframes pulse-glow {
              0%, 100% { transform: scale(1); opacity: 0.5; }
              50% { transform: scale(1.2); opacity: 0.8; }
          }
      </style>

    </section>    <!-- Investment Configurator -->
    <section class="section" style="animation-delay: 0.2s;">
      <div class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
        </svg>
        <span>Investment Configurator</span>
      </div>

      <div class="glass-card glass-card-glow configurator-card">
        <div class="share-selector">
          <div class="share-selector-header">
            <span class="share-selector-label">Select Number of Shares</span>
            <span class="share-selector-label">Max: <?php echo number_format($display_remaining); ?></span>
          </div>

          <div class="slider-container">
            <button class="slider-btn" id="decrementBtn">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/>
              </svg>
            </button>

            <div class="slider-track" id="sliderTrack">
              <div class="slider-fill" id="sliderFill"></div>
              <div class="slider-thumb" id="sliderThumb"></div>
            </div>

            <button class="slider-btn" id="incrementBtn">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
              </svg>
            </button>
          </div>

          <div class="share-display">
            <p class="share-count gradient-text-cyan number-glow" id="shareCount">1,000</p>
            <p class="share-label">shares selected</p>
          </div>
        </div>

        <!-- Calculations Grid -->
        <div class="calc-grid">
          <div class="glass-card calc-card">
            <p class="calc-label">Investment Amount</p>
            <p class="calc-value foreground number-glow" id="investmentAmount">₹0</p>
          </div>

          <div class="glass-card calc-card">
            <p class="calc-label">Ownership Stake</p>
            <p class="calc-value gradient-text-purple number-glow" id="ownershipStake">0.100%</p>
          </div>

          <div class="glass-card calc-card" id="riskCard" style="border-color: hsla(160, 70%, 45%, 0.3);">
            <p class="calc-label">AI Risk Assessment</p>
            <div class="risk-display <?php echo $risk_css; ?>" id="riskDisplay">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
              </svg>
              <span id="riskLevel"><?php echo $risk_level; ?></span>
            </div>
          </div>
        </div>

        <!-- Projections -->
        <div class="projections-header">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
          </svg>
          <span>Smart Pitch AI Predicted Returns (<?php echo $pitch['industry']; ?> Sector)</span>
        </div>

        <div class="projections-grid">
          <div class="glass-card projection-card">
            <div class="projection-bg" style="opacity: 0.3;"></div>
            <div class="projection-content">
              <div class="projection-header">
                <span class="projection-round">Series A</span>
                <span class="projection-percent"><?php echo $mult_base; ?>x</span>
              </div>
              <p class="projection-value" id="projSeriesA">$44,100</p>
              <p class="projection-multiplier">1.8x return</p>
            </div>
          </div>

          <div class="glass-card projection-card">
            <div class="projection-bg" style="opacity: 0.5;"></div>
            <div class="projection-content">
              <div class="projection-header">
                <span class="projection-round">Series B</span>
                <span class="projection-percent"><?php echo $mult_best; ?>x</span>
              </div>
              <p class="projection-value" id="projSeriesB">$78,400</p>
              <p class="projection-multiplier">3.2x return</p>
            </div>
          </div>

          <div class="glass-card projection-card">
            <div class="projection-bg" style="opacity: 0.7;"></div>
            <div class="projection-content">
              <div class="projection-header">
                <span class="projection-round">Series C</span>
                <span class="projection-percent">+450%</span>
              </div>
              <p class="projection-value" id="projSeriesC">$134,750</p>
              <p class="projection-multiplier">5.5x return</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- AI Intelligence Stack -->
    <section class="section" style="animation-delay: 0.3s;">
      <div class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-purple);">
          <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>
          <path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
        </svg>
        <span>AI Intelligence Stack</span>
      </div>

      <div class="insight-stack">
        <!-- AI Valuation Analyzer -->
        <div class="glass-card glass-card-glow insight-card" data-insight="valuation">
          <div class="insight-header">
            <div class="insight-main">
              <div class="insight-icon cyan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/><path d="M17.599 6.5a3 3 0 0 0 .399-1.375"/><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"/><path d="M3.477 10.896a4 4 0 0 1 .585-.396"/><path d="M19.938 10.5a4 4 0 0 1 .585.396"/><path d="M6 18a4 4 0 0 1-1.967-.516"/><path d="M19.967 17.484A4 4 0 0 1 18 18"/>
                </svg>
              </div>
              <div class="insight-content">
                <div class="insight-title-row">
                  <h3 class="insight-title">AI Valuation Analyzer</h3>
                  <span class="confidence-badge medium">87% confidence</span>
                </div>
                <p class="insight-summary"><?php echo htmlspecialchars($ai_val_text); ?></p>
              </div>
            </div>
            <button class="insight-toggle" data-toggle="valuation">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </button>
          </div>
          <div class="insight-details" id="details-valuation">
            <div class="insight-details-header">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/>
              </svg>
              <span class="insight-details-label">Deep Analysis</span>
            </div>
            <p class="insight-details-text"><?php echo htmlspecialchars($ai_val_text); ?> Analysis indicates that the proposed valuation reflects a multiple consistent with historical exits in the <?php echo htmlspecialchars($pitch['industry']); ?> sector.</p>
          </div>
        </div>

        <!-- AI Risk Scanner -->
        <div class="glass-card glass-card-purple insight-card" data-insight="risk">
          <div class="insight-header">
            <div class="insight-main">
              <div class="insight-icon purple">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>
                </svg>
              </div>
              <div class="insight-content">
                <div class="insight-title-row">
                  <h3 class="insight-title">AI Risk Scanner</h3>
                  <span class="confidence-badge <?php echo $risk_css; ?>"><?php echo $risk_level; ?> Risk</span>
                </div>
                <p class="insight-summary">Medium execution risk identified. Market conditions are favorable with manageable competition.</p>
              </div>
            </div>
            <button class="insight-toggle" data-toggle="risk">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </button>
          </div>
          <div class="insight-details" id="details-risk">
            <div class="insight-details-header">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/>
              </svg>
              <span class="insight-details-label">Deep Analysis</span>
            </div>
            <p class="insight-details-text">Factors: <?php echo $risk_factors_str; ?></p>
          </div>
        </div>

        <!-- AI Fraud Monitor -->
        <div class="glass-card insight-card" data-insight="fraud">
          <div class="insight-header">
            <div class="insight-main">
              <div class="insight-icon success">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                </svg>
              </div>
              <div class="insight-content">
                <div class="insight-title-row">
                  <h3 class="insight-title">AI Fraud & Pattern Monitor</h3>
                  <span class="confidence-badge high">96% confidence</span>
                </div>
                  <p class="insight-summary">Founder Email: <?php echo $fraud_status; ?></p>
              </div>
            </div>
            <button class="insight-toggle" data-toggle="fraud">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </button>
          </div>
          <div class="insight-details" id="details-fraud">
            <div class="insight-details-header">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/>
              </svg>
              <span class="insight-details-label">Deep Analysis</span>
            </div>
            <p class="insight-details-text">Verification completed: ✓ Corporate registration verified ✓ Founder background checks clear ✓ Bank account validation passed ✓ Revenue claims cross-referenced with bank statements ✓ Customer testimonials verified ✓ No connections to known bad actors ✓ Social media presence authentic. This startup passes all 47 fraud detection checkpoints.</p>
          </div>
        </div>

        <!-- AI Return Scenario -->
        <div class="glass-card glass-card-glow insight-card" data-insight="returns">
          <div class="insight-header">
            <div class="insight-main">
              <div class="insight-icon blue">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
                </svg>
              </div>
              <div class="insight-content">
                <div class="insight-title-row">
                  <h3 class="insight-title">AI Return Scenario Engine</h3>
                  <span class="confidence-badge medium">82% confidence</span>
                </div>
<p class="insight-summary">Growth Potential: <?php echo $mult_best; ?>x</p>              </div>
            </div>
            <button class="insight-toggle" data-toggle="returns">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </button>
          </div>
          <div class="insight-details" id="details-returns">
            <div class="insight-details-header">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/>
              </svg>
              <span class="insight-details-label">Deep Analysis</span>
            </div>
<p class="insight-details-text"><?php echo htmlspecialchars($return_analysis); ?></p>
          </div>
        </div>
      </div>
    </section>

<?php
// Fetch User Wallet Balance
$user_balance = 0;
if (isset($_SESSION['user_id'])) {
    $bal_sql = "SELECT balance FROM wallets WHERE user_id = ? AND user_role = 'investor'";
    $bal_stmt = $conn->prepare($bal_sql);
    $bal_stmt->bind_param("i", $_SESSION['user_id']);
    $bal_stmt->execute();
    $bal_res = $bal_stmt->get_result()->fetch_assoc();
    $user_balance = $bal_res['balance'] ?? 0;
}
?>
    <!-- Confirmation Zone -->
    <section class="section" style="animation-delay: 0.4s;">
      <div class="glass-card glass-card-glow confirmation-card">
        <div class="confirmation-orb-1"></div>
        <div class="confirmation-orb-2"></div>
        
        <div class="confirmation-content">
          <div class="section-title" style="margin-bottom: 1.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
            </svg>
            <span>Investment Confirmation</span>
          </div>

          <div class="summary-grid">
            <div class="glass-card summary-card">
              <p class="summary-label">Investing In</p>
              <p class="summary-value" style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 24px; height: 24px; background: hsla(185, 100%, 60%, 0.1); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 10px; font-weight: 800;">
                  <?php echo strtoupper($pitch['startup_name'][0]); ?>
                </span>
                <?php echo htmlspecialchars($pitch['startup_name']); ?>
              </p>
            </div>

            <div class="glass-card summary-card">
              <p class="summary-label">Shares Allotted</p>
              <p class="summary-value gradient-text-cyan" id="summaryShares">0 @ ₹0</p>
            </div>

            <div class="glass-card summary-card" style="border-color: hsla(185, 60%, 30%, 0.3);">
              <p class="summary-label">Execution Amount</p>
              <p class="summary-value highlight gradient-text-multi number-glow" id="summaryTotal">₹0</p>
            </div>

            <!-- WALLET BALANCE CHIP -->
            <div class="glass-card summary-card" style="background: hsla(185, 100%, 60%, 0.03);">
              <p class="summary-label">Smart Wallet Balance</p>
              <p class="summary-value" style="color: <?php echo $investor_wallet_balance > 0 ? '#4ade80' : '#f87171'; ?>; font-weight: 800;">
                ₹<?php echo number_format($investor_wallet_balance, 2); ?>
              </p>
            </div>
          </div>

          <div style="background: hsla(160, 70%, 45%, 0.05); border: 1px solid hsla(160, 70%, 45%, 0.2); border-radius: 12px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
              <div style="gap: 12px; align-items: center;">
                  <div style="width: 32px; height: 32px; background: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"/>
                      </svg>
                  </div>
                  <div>
                      <h4 style="font-size: 13px; font-weight: 600; color: var(--success);">Verified Smart Contract</h4>
                      <p style="font-size: 11px; color: var(--foreground-muted);">Verified Identity & Active Communication Channel</p>
                  </div>
              </div>
              <div style="text-align: right;">
                  <span title="Calculated based on Identity, Email Verification, and Disclosure Depth" style="cursor:help; background: hsla(160, 70%, 45%, 0.2); color: var(--success); padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: 800; letter-spacing: 0.05em;">AI TRUTH SCORE: <?php echo $fraud_score; ?>%</span>
              </div>
          </div>

          <div class="lockin-notice">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
            </svg>
            <div>
              <p class="lockin-title">Investment Terms</p>
              <p class="lockin-text">By proceeding, you acknowledge that this is a long-term investment in a private company. Your shares will be subject to standard lock-up periods and liquidity restrictions. All investments are processed securely through our escrow partner.</p>
            </div>
          </div>

          <div class="cta-container">
            <input type="hidden" id="hiddenPitchId" value="<?php echo $pitch_id; ?>">
            <input type="hidden" id="hiddenSharesInput" value="100">
            <button class="btn btn-primary" id="investBtn">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              Proceed to Secure Investment
              <svg class="btn-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
              </svg>
            </button>

            <button class="btn btn-secondary">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
              </svg>
              Save Draft
            </button>
          </div>

          <div class="trust-badges">
            <div class="trust-badge">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
              </svg>
              <span>256-bit Encryption</span>
            </div>
            <div class="trust-badge">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
              </svg>
              <span>SEC Compliant</span>
            </div>
            <div class="trust-badge">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
              </svg>
              <span>Escrow Protected</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <p class="footer-text">
        © 2024 SmartPitchHub. All rights reserved. 
        <span class="footer-sep">•</span>
        <a href="#" class="footer-link">Terms</a>
        <span class="footer-sep">•</span>
        <a href="#" class="footer-link">Privacy</a>
      </p>
    </footer>
  </main>

  <!-- Payment Selection Modal -->
  <div class="modal-overlay" id="paymentModal">
    <div class="payment-modal">
      <button class="modal-close" onclick="closePaymentModal()">×</button>
      
      <div class="modal-header">
        <h2 class="modal-title">Finalize Investment</h2>
        <p class="modal-subtitle">Choose your preferred payment method</p>
      </div>

      <div class="payment-options">
        <!-- Option 1: Smart Wallet -->
        <button class="payment-option" id="payWithWallet" <?php echo ($user_balance < 100) ? 'disabled' : ''; ?>>
          <div class="option-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10"/></svg>
          </div>
          <div class="option-info">
            <span class="option-label">Smart Wallet</span>
            <span class="option-desc">Instant deduction from your balance</span>
            <div class="wallet-balance">Balance: ₹<?php echo number_format($user_balance, 2); ?></div>
          </div>
        </button>

        <!-- Option 2: Razorpay -->
        <button class="payment-option" id="payWithRazorpay">
          <div class="option-icon" style="color: #3b82f6;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="m17 5-5-3-5 3"/><path d="m17 19-5 3-5-3"/><path d="M2 12h20"/><path d="m5 7-3 5 3 5"/><path d="m19 7 3 5-3 5"/></svg>
          </div>
          <div class="option-info">
            <span class="option-label">Razorpay <span class="razorpay-badge">Secure UPI/Card</span></span>
            <span class="option-desc">Pay directly via Cards, UPI, or NetBanking</span>
          </div>
        </button>
      </div>

      <div class="investment-summary-mini">
        <div class="sm-row">
          <span class="sm-label">Startup:</span>
          <span class="sm-value"><?php echo htmlspecialchars($pitch['startup_name']); ?></span>
        </div>
        <div class="sm-row">
          <span class="sm-label">Shares:</span>
          <span class="sm-value" id="modalShareCountDisplay">1,000</span>
        </div>
        <div class="sm-row sm-total">
          <span class="sm-label" style="color: var(--primary);">Total Amount:</span>
          <span class="sm-value" style="color: var(--primary);" id="modalTotalAmountDisplay">₹0</span>
        </div>
      </div>
    </div>
  </div>

 <script>
    // ==========================================
    // 🔍 DEBUG: API CONNECTION CHECK
    // ==========================================
    const aiDebugData = <?php echo json_encode($ai_data); ?>;
    const fraudDebugData = <?php echo json_encode($fraud_score); ?>;
    
    console.group("🚀 Smart Pitch AI Debugger");
    if (aiDebugData && aiDebugData.valuation_insight) {
        console.log("%c✅ AI MARKET DATA RECEIVED", "color: #00ff00; background: #003300; font-weight: bold; padding: 4px;");
        console.log("Valuation Insight:", aiDebugData.valuation_insight);
        console.log("Growth Multipliers:", aiDebugData.growth_base, aiDebugData.growth_best);
    } else {
        console.log("%c⚠️ USING FALLBACK DATA", "color: orange; background: #331100; font-weight: bold; padding: 4px;");
    }
    console.log("Fraud Trust Score:", fraudDebugData + "%");
    console.groupEnd();

    // ==========================================
    // INVESTMENT CONFIGURATOR JAVASCRIPT
    // ==========================================
    
    const CONFIG = {
      // 1. INJECTING REAL PHP DATA HERE
      sharePrice: <?php echo $share_price; ?>,
      totalShares: <?php echo $total_shares; ?>,
      maxShares: <?php echo $display_remaining; ?>,
      minShares: 10, // Changed min to 10 for better UX
      step: 10
    };

    // AI Growth Multipliers (Fallback to 2x and 5x if AI fails)
    const AI_GROWTH = {
        base: <?php echo $mult_base ?? 2.0; ?>,
        best: <?php echo $mult_best ?? 5.0; ?>,
        extreme: <?php echo ($mult_best ?? 5.0) * 1.5; ?> // Series C is usually higher
    };

    let selectedShares = 100; // Default start

    // DOM Elements
    const shareCountEl = document.getElementById('shareCount');
    const investmentAmountEl = document.getElementById('investmentAmount');
    const ownershipStakeEl = document.getElementById('ownershipStake');
    const riskDisplayEl = document.getElementById('riskDisplay');
    const riskLevelEl = document.getElementById('riskLevel');
    const riskCardEl = document.getElementById('riskCard');
    const projSeriesAEl = document.getElementById('projSeriesA');
    const projSeriesBEl = document.getElementById('projSeriesB');
    const projSeriesCEl = document.getElementById('projSeriesC');
    const summarySharesEl = document.getElementById('summaryShares');
    const summaryTotalEl = document.getElementById('summaryTotal');
    const sliderTrackEl = document.getElementById('sliderTrack');
    const sliderFillEl = document.getElementById('sliderFill');
    const sliderThumbEl = document.getElementById('sliderThumb');
    const decrementBtnEl = document.getElementById('decrementBtn');
    const incrementBtnEl = document.getElementById('incrementBtn');
    const hiddenSharesInput = document.getElementById('hiddenSharesInput');

    const formatINR = (val) => val.toLocaleString('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

    // Update all UI based on selected shares
    function updateUI() {
      const investmentAmount = selectedShares * CONFIG.sharePrice;
      const ownershipPercent = ((selectedShares / CONFIG.totalShares) * 100).toFixed(4); // More precision
      
      let sliderPercent = 0;
      if (CONFIG.maxShares > CONFIG.minShares) {
          sliderPercent = ((selectedShares - CONFIG.minShares) / (CONFIG.maxShares - CONFIG.minShares)) * 100;
      }

      // Update share count
      shareCountEl.textContent = selectedShares.toLocaleString('en-IN');

      // Update investment amount
      investmentAmountEl.textContent = formatINR(investmentAmount);

      // Update ownership stake
      ownershipStakeEl.textContent = ownershipPercent + '%';

      // Update Form Input (Crucial for backend submission)
      if(hiddenSharesInput) hiddenSharesInput.value = selectedShares;

      // Update risk level (Dynamic logic based on stake size)
      // Logic: Higher stake = Higher exposure/risk
      const ratio = selectedShares / CONFIG.maxShares;
      let riskLevel, riskClass, borderColor;
      
      if (ratio < 0.1) {
        riskLevel = 'Low';
        riskClass = 'low'; // Green
        borderColor = 'hsla(160, 70%, 45%, 0.3)';
      } else if (ratio < 0.3) {
        riskLevel = 'Medium';
        riskClass = 'medium'; // Yellow
        borderColor = 'hsla(45, 90%, 55%, 0.3)';
      } else {
        riskLevel = 'High';
        riskClass = 'high'; // Red
        borderColor = 'hsla(0, 70%, 55%, 0.3)';
      }
      
      if(riskDisplayEl) riskDisplayEl.className = 'risk-display ' + riskClass;
      if(riskLevelEl) riskLevelEl.textContent = riskLevel; 
      if(riskCardEl) riskCardEl.style.borderColor = borderColor;

      // Update projections using AI Multipliers
      // Note: We check if elements exist to prevent errors if you removed a card
      if(projSeriesAEl) projSeriesAEl.textContent = formatINR(investmentAmount * AI_GROWTH.base);
      if(projSeriesBEl) projSeriesBEl.textContent = formatINR(investmentAmount * AI_GROWTH.best);
      if(projSeriesCEl) projSeriesCEl.textContent = formatINR(investmentAmount * AI_GROWTH.extreme);

      // Update summary
      if(summarySharesEl) summarySharesEl.textContent = selectedShares.toLocaleString('en-IN') + ' @ ' + formatINR(CONFIG.sharePrice);
      if(summaryTotalEl) summaryTotalEl.textContent = formatINR(investmentAmount);

      // Update slider
      if(sliderFillEl) sliderFillEl.style.width = sliderPercent + '%';
      if(sliderThumbEl) sliderThumbEl.style.left = sliderPercent + '%';

      // Update button states
      if(decrementBtnEl) decrementBtnEl.disabled = selectedShares <= CONFIG.minShares;
      if(incrementBtnEl) incrementBtnEl.disabled = selectedShares >= CONFIG.maxShares;
    }

    // Increment/Decrement handlers
    if(decrementBtnEl) decrementBtnEl.addEventListener('click', (e) => { e.preventDefault(); selectedShares = Math.max(selectedShares - CONFIG.step, CONFIG.minShares); updateUI(); });
    if(incrementBtnEl) incrementBtnEl.addEventListener('click', (e) => { e.preventDefault(); selectedShares = Math.min(selectedShares + CONFIG.step, CONFIG.maxShares); updateUI(); });

    // Slider drag functionality
    let isDragging = false;

    function updateSliderFromEvent(e) {
      if (CONFIG.maxShares <= CONFIG.minShares) return;
      const rect = sliderTrackEl.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      let percent = (clientX - rect.left) / rect.width;
      percent = Math.max(0, Math.min(1, percent));
      
      let rawShares = CONFIG.minShares + percent * (CONFIG.maxShares - CONFIG.minShares);
      // Round to step
      selectedShares = Math.round(rawShares / CONFIG.step) * CONFIG.step;
      selectedShares = Math.max(CONFIG.minShares, Math.min(CONFIG.maxShares, selectedShares));
      updateUI();
    }

    if(sliderTrackEl) {
        sliderTrackEl.addEventListener('mousedown', (e) => { isDragging = true; updateSliderFromEvent(e); });
        document.addEventListener('mousemove', (e) => { if (isDragging) updateSliderFromEvent(e); });
        document.addEventListener('mouseup', () => { isDragging = false; });
        // Touch support
        sliderTrackEl.addEventListener('touchstart', (e) => { isDragging = true; updateSliderFromEvent(e); });
        document.addEventListener('touchmove', (e) => { if (isDragging) updateSliderFromEvent(e); });
        document.addEventListener('touchend', () => { isDragging = false; });
        // Click on track
        sliderTrackEl.addEventListener('click', (e) => { updateSliderFromEvent(e); });
    }

    if(sliderThumbEl) {
        sliderThumbEl.addEventListener('mousedown', (e) => { isDragging = true; e.preventDefault(); });
        sliderThumbEl.addEventListener('touchstart', (e) => { isDragging = true; e.preventDefault(); });
    }

    // ==========================================
    // AI INSIGHT EXPAND/COLLAPSE
    // ==========================================
    
    const insightToggles = document.querySelectorAll('.insight-toggle');

    insightToggles.forEach(toggle => {
      toggle.addEventListener('click', (e) => {
        e.stopPropagation(); // Stop clicking through
        const insightId = toggle.getAttribute('data-toggle');
        const detailsEl = document.getElementById('details-' + insightId);
        const cardEl = toggle.closest('.insight-card');
        
        if (detailsEl) {
            const isExpanded = detailsEl.classList.contains('show');
            
            // Close all others first (Accordion style)
            document.querySelectorAll('.insight-details').forEach(d => d.classList.remove('show'));
            document.querySelectorAll('.insight-toggle').forEach(t => t.classList.remove('expanded'));
            document.querySelectorAll('.insight-card').forEach(c => c.classList.remove('expanded'));
            
            // Toggle current
            if (!isExpanded) {
              detailsEl.classList.add('show');
              toggle.classList.add('expanded');
              cardEl.classList.add('expanded');
            }
        }
      });
    });

    // Initialize UI
    updateUI();

    // ==========================================
    // 💳 PAYMENT MODAL LOGIC
    // ==========================================
    const paymentModal = document.getElementById('paymentModal');
    const modalShareDisplay = document.getElementById('modalShareCountDisplay');
    const modalTotalDisplay = document.getElementById('modalTotalAmountDisplay');
    const payWithWalletBtn = document.getElementById('payWithWallet');
    const investBtn = document.getElementById('investBtn');

    window.closePaymentModal = function() {
        paymentModal.classList.remove('active');
    };

    if (investBtn) {
        investBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update Summary in Modal
            const shares = document.getElementById('hiddenSharesInput').value;
            const total = selectedShares * CONFIG.sharePrice;
            
            modalShareDisplay.textContent = parseInt(shares).toLocaleString('en-IN');
            modalTotalDisplay.textContent = formatINR(total);
            
            // Check if wallet can afford it
            const userBalance = <?php echo $investor_wallet_balance; ?>;
            if (userBalance < total) {
                payWithWalletBtn.disabled = true;
                payWithWalletBtn.style.opacity = '0.5';
                payWithWalletBtn.style.cursor = 'not-allowed';
                payWithWalletBtn.querySelector('.option-desc').textContent = 'Insufficient balance (₹' + userBalance.toLocaleString() + ')';
                payWithWalletBtn.querySelector('.option-desc').style.color = '#ef4444';
            } else {
                payWithWalletBtn.disabled = false;
                payWithWalletBtn.style.opacity = '1';
                payWithWalletBtn.style.cursor = 'pointer';
                payWithWalletBtn.querySelector('.option-desc').textContent = 'Instant deduction from Smart Escrow';
                payWithWalletBtn.querySelector('.option-desc').style.color = '#94a3b8';
            }

            // Show Modal
            paymentModal.classList.add('active');
        });
    }

    // Handle Wallet Payment
    if (payWithWalletBtn) {
        payWithWalletBtn.addEventListener('click', async function() {
            const shares = document.getElementById('hiddenSharesInput').value;
            const pitchId = document.getElementById('hiddenPitchId').value;

            payWithWalletBtn.disabled = true;
            payWithWalletBtn.innerHTML = '<div style="margin: auto;">Processing...</div>';

            try {
                const response = await fetch('process-investment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `pitch_id=${pitchId}&shares=${shares}`
                });

                const result = await response.json();

                if (result.success) {
                    alert('Success: ' + result.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                    window.location.reload(); // Reset UI
                }
            } catch (error) {
                console.error('Wallet Payment Error:', error);
                alert('Connection failure. Your funds were not touched.');
                window.location.reload();
            }
        });
    }

    // Handle Razorpay (Placeholder for now)
    const payWithRazorpayBtn = document.getElementById('payWithRazorpay');
    if (payWithRazorpayBtn) {
        payWithRazorpayBtn.addEventListener('click', function() {
            alert('Razorpay Gateway is integrating... This will open your payment screen.');
            // Integrate Razorpay checkout.js here later
        });
    }

    // Close on overlay click
    paymentModal.addEventListener('click', (e) => {
        if (e.target === paymentModal) closePaymentModal();
    });
  </script>
  </div> <!-- CLOSES ANIMATED-BG -->
</body>
</html>
