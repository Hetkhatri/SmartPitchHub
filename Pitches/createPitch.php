<?php
session_start();
require_once '../db.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'entrepreneur') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Fetch Entrepreneur's Profile (for share data)
$total_shares = 50000; // Default
$available_shares = 50000;

$u_sql = "SELECT total_shares, available_shares FROM entrepreneurs WHERE id = ?";
if ($stmt = $conn->prepare($u_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $total_shares = $res['total_shares'] ?? 50000;
        $available_shares = $res['available_shares'] ?? 50000;
    }
}

// 3. Determine Investment Round Automatically & Check Status
$round_count = 0;
$can_create = true;
$error_msg = "";

$pitch_check_sql = "SELECT round_status, round_name, expiry_date FROM pitches WHERE entrepreneur_id = ? ORDER BY created_at DESC LIMIT 1";
if ($p_stmt = $conn->prepare($pitch_check_sql)) {
    $p_stmt->bind_param("i", $user_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    
    if ($p_res->num_rows > 0) {
        $last_pitch = $p_res->fetch_assoc();
        $is_expired = ($last_pitch['expiry_date'] && strtotime($last_pitch['expiry_date']) < time());
        
        if ($last_pitch['round_status'] === 'active' && !$is_expired) {
            $can_create = false;
            $error_msg = "You already have an active pitch in the " . htmlspecialchars($last_pitch['round_name']) . ". Please wait for it to conclude before starting a new round.";
        } else {
            // Count total pitches to determine the NEXT round
            $count_sql = "SELECT COUNT(*) as total FROM pitches WHERE entrepreneur_id = ?";
            $c_stmt = $conn->prepare($count_sql);
            $c_stmt->bind_param("i", $user_id);
            $c_stmt->execute();
            $round_count = $c_stmt->get_result()->fetch_assoc()['total'];
        }
    } else {
        $round_count = 0; // First round
    }
}

if (!$can_create) {
    // Redirect to dashboard with message
    $_SESSION['error'] = $error_msg;
    header("Location: ../dashboards/Entrepreneur-dashboard.php");
    exit;
}

$rounds_sequence = ['Seed Round', 'Series A', 'Series B', 'Series C', 'Series D'];
$current_round = ($round_count < count($rounds_sequence)) ? $rounds_sequence[$round_count] : "Series " . chr(65 + $round_count - 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Your Pitch — SmartPitchHub</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: hsl(228, 60%, 6%);
      --fg: hsl(210, 40%, 92%);
      --card-bg: hsl(225, 40%, 10%);
      --muted: hsl(215, 20%, 55%);
      --muted-bg: hsl(225, 30%, 15%);
      --border: hsl(225, 30%, 18%);
      --input-bg: hsl(225, 30%, 14%);
      --primary: hsl(185, 100%, 50%);
      --secondary: hsl(270, 91%, 65%);
      --accent: hsl(217, 91%, 60%);
      --radius: 0.75rem;
    }

    body {
      background: var(--bg);
      color: var(--fg);
      font-family: 'Inter', sans-serif;
      -webkit-font-smoothing: antialiased;
      line-height: 1.6;
    }

    h1, h2, h3, h4, h5, h6 { font-family: 'Space Grotesk', sans-serif; }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: hsl(225, 30%, 25%); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: hsl(225, 30%, 35%); }

    /* Animations */
    @keyframes glow-pulse {
      0%, 100% { opacity: 0.6; }
      50% { opacity: 1; }
    }
    @keyframes gradient-shift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    @keyframes fade-in-up {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .animate-glow-pulse { animation: glow-pulse 3s ease-in-out infinite; }
    .animate-gradient-shift { background-size: 200% 200%; animation: gradient-shift 4s ease infinite; }
    .fade-in-up { animation: fade-in-up 0.5s ease forwards; opacity: 0; }

    /* Ambient BG */
    .ambient { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
    .ambient-orb {
      position: absolute; border-radius: 50%; width: 600px; height: 600px;
    }
    .ambient-orb.purple {
      top: -160px; left: -160px; opacity: 0.2;
      background: radial-gradient(circle, hsl(270, 91%, 65%, 0.3), transparent 70%);
      animation: glow-pulse 3s ease-in-out infinite;
    }
    .ambient-orb.cyan {
      bottom: -160px; right: -160px; opacity: 0.15;
      background: radial-gradient(circle, hsl(185, 100%, 50%, 0.25), transparent 70%);
      animation: glow-pulse 3s ease-in-out infinite 1.5s;
    }

    /* Layout */
    .page { position: relative; z-index: 10; max-width: 56rem; margin: 0 auto; padding: 2rem 1rem; }

    /* Header */
    .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5rem; animation: fade-in-up 0.4s ease forwards; }
    .back-btn { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: var(--muted); background: none; border: none; cursor: pointer; transition: color 0.3s; }
    .back-btn:hover { color: var(--fg); }
    .brand { display: flex; align-items: center; gap: 0.75rem; }
    .brand-icon { width: 2rem; height: 2rem; border-radius: 0.5rem; background: hsl(185, 100%, 50%, 0.2); display: flex; align-items: center; justify-content: center; }
    .brand-name { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 1.125rem; }

    /* Title */
    .page-title { text-align: center; margin-bottom: 3rem; animation: fade-in-up 0.5s ease forwards; }
    .page-title h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.75rem; }
    .page-title h1 .cyan { color: hsl(185, 100%, 60%); text-shadow: 0 0 20px hsl(185, 100%, 50%, 0.4); }
    .page-title p { color: var(--muted); max-width: 28rem; margin: 0 auto; }

    /* Glass cards */
    .glass-card {
      position: relative; border-radius: var(--radius); border: 1px solid hsl(225, 30%, 18%, 0.5); padding: 1.5rem;
      background: linear-gradient(135deg, hsl(225, 40%, 10%, 0.8), hsl(225, 40%, 8%, 0.6));
      backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    }
    .glass-card-glow {
      box-shadow: 0 0 20px hsl(185, 100%, 50%, 0.08), 0 0 60px hsl(270, 91%, 65%, 0.05), inset 0 1px 0 hsl(0, 0%, 100%, 0.06);
    }
    .glass-card-strong {
      box-shadow: 0 0 30px hsl(185, 100%, 50%, 0.15), 0 0 80px hsl(270, 91%, 65%, 0.08), inset 0 1px 0 hsl(0, 0%, 100%, 0.08);
      border-color: hsl(185, 100%, 50%, 0.2);
    }

    /* Section titles */
    .section-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; }
    .section-header svg { width: 20px; height: 20px; color: var(--primary); }
    .section-title {
      font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em;
      background: linear-gradient(135deg, #fff, hsl(185, 100%, 80%));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }

    /* Sections container */
    .sections { display: flex; flex-direction: column; gap: 2.5rem; }
    .section { animation: fade-in-up 0.5s ease forwards; opacity: 0; }
    .section:nth-child(1) { animation-delay: 0.1s; }
    .section:nth-child(2) { animation-delay: 0.2s; }
    .section:nth-child(3) { animation-delay: 0.3s; }
    .section:nth-child(4) { animation-delay: 0.4s; }
    .section:nth-child(5) { animation-delay: 0.5s; }
    .section:nth-child(6) { animation-delay: 0.6s; }

    /* Grid helpers */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
    .grid-2x2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) {
      .grid-2, .grid-3, .grid-2x2 { grid-template-columns: 1fr; }
      .page-title h1 { font-size: 2rem; }
    }

    /* Form elements */
    .field { margin-bottom: 0; }
    .field + .field { margin-top: 1.25rem; }
    .neon-label {
      display: block; font-size: 0.75rem; font-weight: 500; text-transform: uppercase;
      letter-spacing: 0.1em; color: var(--muted); margin-bottom: 0.5rem;
    }
    .neon-input, .neon-select, .neon-textarea {
      width: 100%; border-radius: 0.5rem; border: 1px solid hsl(225, 30%, 18%, 0.5);
      background: hsl(225, 30%, 14%, 0.5); padding: 0.75rem 1rem; color: var(--fg);
      font-family: 'Inter', sans-serif; font-size: 0.875rem; outline: none; transition: all 0.3s;
    }
    .neon-input::placeholder, .neon-textarea::placeholder { color: var(--muted); }
    .neon-input:focus, .neon-select:focus, .neon-textarea:focus {
      border-color: hsl(185, 100%, 50%, 0.5);
      box-shadow: 0 0 15px hsl(185, 100%, 50%, 0.15), 0 0 30px hsl(185, 100%, 50%, 0.05);
    }
    .neon-textarea { resize: none; min-height: 120px; }
    .neon-select {
      appearance: none; cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2300f0ff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 16px center;
    }
    .neon-select option { background: hsl(225, 40%, 10%); color: var(--fg); }

    /* AI Button */
    .neon-btn-ai {
      display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 0.5rem;
      border: 1px solid hsl(185, 100%, 50%, 0.3); background: hsl(185, 100%, 50%, 0.1);
      padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; color: var(--primary);
      cursor: pointer; transition: all 0.3s; font-family: 'Inter', sans-serif;
    }
    .neon-btn-ai:hover {
      background: hsl(185, 100%, 50%, 0.2); border-color: hsl(185, 100%, 50%, 0.5);
      box-shadow: 0 0 15px hsl(185, 100%, 50%, 0.2);
    }
    .neon-btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }
    .neon-btn-ai svg { width: 14px; height: 14px; }

    /* Field header row */
    .field-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
    .field-header .neon-label { margin-bottom: 0; }

    /* Glow text */
    .glow-cyan { color: hsl(185, 100%, 60%); text-shadow: 0 0 20px hsl(185, 100%, 50%, 0.4); }

    /* Upload tiles */
    .upload-tile {
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem;
      min-height: 160px; cursor: pointer; transition: all 0.3s; border-radius: var(--radius); padding: 1.5rem;
      border: 2px dashed hsl(225, 30%, 25%);
      background: linear-gradient(135deg, hsl(225, 40%, 10%, 0.8), hsl(225, 40%, 8%, 0.6));
      backdrop-filter: blur(20px);
    }
    .upload-tile:hover {
      border-color: hsl(185, 100%, 50%, 0.4);
      box-shadow: 0 0 20px hsl(185, 100%, 50%, 0.1);
    }
    .upload-tile svg { width: 32px; height: 32px; color: var(--muted); }
    .upload-tile .name { font-size: 0.875rem; font-weight: 500; }
    .upload-tile .desc { font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; }
    .upload-tile .file-selected { font-size: 0.75rem; color: var(--primary); margin-top: 0.25rem; }
    .upload-tile input[type="file"] { display: none; }

    /* Checkout summary */
    .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .summary-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.5rem 0; border-bottom: 1px solid hsl(225, 30%, 18%, 0.3);
    }
    .summary-row:last-child { border-bottom: none; }
    .summary-label { font-size: 0.875rem; color: var(--muted); }
    .summary-value { font-size: 0.875rem; font-weight: 600; color: hsl(185, 100%, 60%); text-shadow: 0 0 20px hsl(185, 100%, 50%, 0.4); }

    /* Equity bar */
    .equity-bar-wrap { margin-top: 1.25rem; }
    .equity-bar-header { display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.5rem; }
    .equity-bar { height: 8px; border-radius: 9999px; background: hsl(225, 30%, 15%, 0.5); overflow: hidden; }
    .equity-bar-fill {
      height: 100%; border-radius: 9999px; transition: width 1s ease;
      background: linear-gradient(90deg, hsl(185, 100%, 50%), hsl(270, 91%, 65%));
    }

    .expiry { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--muted); margin-top: 1rem; }
    .expiry svg { width: 14px; height: 14px; }

    /* Verdict badges */
    .verdict-badge {
      display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 9999px;
      padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    }
    .verdict-fair { background: hsl(142, 76%, 36%, 0.2); color: hsl(142, 76%, 56%); border: 1px solid hsl(142, 76%, 36%, 0.3); }
    .verdict-overvalued { background: hsl(0, 84%, 60%, 0.2); color: hsl(0, 84%, 70%); border: 1px solid hsl(0, 84%, 60%, 0.3); }
    .verdict-undervalued { background: hsl(185, 100%, 50%, 0.15); color: hsl(185, 100%, 60%); border: 1px solid hsl(185, 100%, 50%, 0.3); }

    /* Applied verdict box */
    .applied-verdict {
      margin-top: 0.75rem; padding: 1rem; border-radius: 0.5rem;
      border: 1px solid hsl(185, 100%, 50%, 0.2);
      background: linear-gradient(135deg, hsl(225, 40%, 10%, 0.9), hsl(185, 100%, 50%, 0.05));
      box-shadow: 0 0 25px hsl(185, 100%, 50%, 0.1);
      animation: fade-in-up 0.3s ease forwards;
    }
    .applied-verdict .verdict-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
    .applied-verdict .verdict-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
    .applied-verdict p { font-size: 0.875rem; color: var(--muted); line-height: 1.6; }

    /* CTA */
    .neon-cta {
      position: relative; display: inline-flex; align-items: center; justify-content: center;
      border-radius: var(--radius); padding: 1rem 2.5rem; font-size: 1.125rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.05em; border: none; cursor: pointer;
      transition: all 0.5s; font-family: 'Space Grotesk', sans-serif;
      background: linear-gradient(135deg, hsl(185, 100%, 50%), hsl(270, 91%, 65%), hsl(217, 91%, 60%));
      color: hsl(228, 60%, 6%);
      box-shadow: 0 0 30px hsl(185, 100%, 50%, 0.3), 0 0 60px hsl(270, 91%, 65%, 0.15);
      background-size: 200% 200%; animation: gradient-shift 4s ease infinite;
    }
    .neon-cta:hover:not(:disabled) {
      box-shadow: 0 0 40px hsl(185, 100%, 50%, 0.5), 0 0 80px hsl(270, 91%, 65%, 0.25), 0 0 120px hsl(217, 91%, 60%, 0.15);
      transform: translateY(-2px);
    }
    .neon-cta:disabled { opacity: 0.4; cursor: not-allowed; }
    .neon-cta svg { width: 20px; height: 20px; margin-right: 0.5rem; }
    .cta-wrap { text-align: center; padding-bottom: 2.5rem; }
    .cta-wrap .hint { font-size: 0.75rem; color: var(--muted); margin-top: 0.75rem; }

    /* Modal */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center;
      padding: 1rem; background: hsl(228, 60%, 6%, 0.85); opacity: 0; transition: opacity 0.3s;
      pointer-events: none;
    }
    .modal-overlay.active { opacity: 1; pointer-events: auto; }
    .modal-content {
      width: 100%; max-width: 32rem; transform: scale(0.9); transition: transform 0.3s;
    }
    .modal-overlay.active .modal-content { transform: scale(1); }
    .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .modal-close {
      padding: 0.25rem; border-radius: 0.5rem; background: none; border: none;
      color: var(--muted); cursor: pointer; transition: background 0.2s;
    }
    .modal-close:hover { background: hsl(225, 30%, 15%, 0.5); }
    .modal-close svg { width: 20px; height: 20px; }
    .spinner {
      width: 40px; height: 40px; border: 2px solid hsl(185, 100%, 50%, 0.3);
      border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite;
    }
    .modal-body-loading { display: flex; flex-direction: column; align-items: center; padding: 2.5rem 0; gap: 1rem; }
    .modal-body-loading p { font-size: 0.875rem; color: var(--muted); }
    .modal-result { display: flex; flex-direction: column; gap: 1.25rem; }
    .modal-result .range { font-size: 1.5rem; font-weight: 700; font-family: 'Space Grotesk', sans-serif; }
    .modal-cta { width: 100%; padding: 0.75rem; font-size: 0.875rem; }

    /* SVG icon inline helper */
    .icon { display: inline-block; vertical-align: middle; }
  </style>
</head>
<body>

  <!-- Ambient Background -->
  <div class="ambient">
    <div class="ambient-orb purple animate-glow-pulse"></div>
    <div class="ambient-orb cyan animate-glow-pulse"></div>
  </div>

  <div class="page">

    <!-- Header -->
    <div class="header">
      <button class="back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
        Back
      </button>
      <div class="brand">
        <div class="brand-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary)"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
        </div>
        <span class="brand-name">SmartPitchHub</span>
      </div>
    </div>

    <!-- Page Title -->
    <div class="page-title">
      <h1><span class="cyan">Create</span> Your Pitch</h1>
      <p>Launch your fundraising round with AI-powered insights and investor-grade presentation.</p>
    </div>

    <div class="sections">

      <!-- SECTION 1: Startup Identity -->
      <div class="section" style="animation-delay: 0.1s">
        <div class="section-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
          <h2 class="section-title">Startup Identity</h2>
        </div>
        <div class="glass-card glass-card-glow">
          <div class="grid-2" style="margin-bottom:1.25rem">
            <div>
              <label class="neon-label">Startup Name</label>
              <input class="neon-input" id="name" placeholder="e.g. Nexus Quantum AI" />
            </div>
            <div>
              <label class="neon-label">Tagline</label>
              <input class="neon-input" id="tagline" placeholder="One-line pitch" />
            </div>
          </div>
          <div class="grid-2" style="margin-bottom:1.25rem">
            <div>
              <label class="neon-label">Industry</label>
              <select class="neon-select" id="industry">
                <option value="">Select Industry</option>
                <option>AI/ML</option><option>FinTech</option><option>HealthTech</option><option>EdTech</option>
                <option>SaaS</option><option>E-Commerce</option><option>CleanTech</option><option>BioTech</option>
                <option>Gaming</option><option>Other</option>
              </select>
            </div>
            <div>
              <label class="neon-label">Stage</label>
              <select class="neon-select" id="stage">
                <option value="">Select Stage</option>
                <option>Idea</option><option>MVP</option><option>Revenue</option><option>Growth</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label class="neon-label">Location</label>
              <input class="neon-input" id="location" placeholder="City, Country" />
            </div>
            <div>
               <label class="neon-label">Campaign Duration</label>
               <select class="neon-select" id="fundDuration">
                  <option value="30">30 Days</option>
                  <option value="45">45 Days</option>
                  <option value="60" selected>60 Days</option>
                  <option value="90">90 Days</option>
               </select>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 2: Pitch Details -->
      <div class="section" style="animation-delay: 0.2s">
        <div class="section-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
          <h2 class="section-title">Pitch Details</h2>
        </div>
        <div class="glass-card glass-card-glow" id="pitch-details-card"></div>
      </div>

      <!-- SECTION 3: Funding & Valuation -->
      <div class="section" style="animation-delay: 0.3s">
        <div class="section-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <h2 class="section-title">Funding & Valuation</h2>
        </div>
        <div class="glass-card glass-card-strong">
          <?php if ($round_count > 0): ?>
            <div style="background: rgba(185, 100, 50, 0.1); border-left: 3px solid var(--primary); padding: 0.75rem; margin-bottom: 1.25rem; border-radius: 4px;">
              <p style="font-size: 0.85rem; color: var(--primary); font-weight: 600;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="m12 14 4-4 4 4"/><path d="M4 14a8 8 0 0 1 12-7L8 20a8 8 0 0 0 12-7"/></svg>
                Round Transition: Your previous round has concluded. Please provide an updated valuation for your <?php echo $current_round; ?>.
              </p>
            </div>
          <?php endif; ?>
          <div class="grid-2" style="margin-bottom:1.25rem">
            <div>
              <label class="neon-label">Investment Round</label>
              <input class="neon-input" id="investmentRound" value="<?php echo htmlspecialchars($current_round); ?>" readonly style="cursor: not-allowed; opacity: 0.8; background: var(--muted-bg);" />
            </div>
            <div>
              <label class="neon-label">Funding Goal (₹)</label>
              <input class="neon-input" id="fundingGoal" placeholder="e.g. 75,00,000" />
            </div>
          </div>

          <div class="grid-2" style="margin-bottom:1.25rem">
            <div>
              <div class="field-header">
                <label class="neon-label">Valuation (₹)</label>
              </div>
              <div style="position: relative; display: flex; align-items: center;">
                <input class="neon-input" id="valuation" placeholder="e.g. 6,00,00,000" style="padding-right: 45px;" />
                <button id="lock-valuation-btn" type="button" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--primary); display: flex; align-items: center; justify-content: center; transition: all 0.3s;" title="Confirm Valuation">
                  <svg id="tick-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <svg id="lock-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </button>
              </div>
              <div id="valuation-action-area" style="margin-top: 10px; display: none;">
                <button type="button" class="neon-btn-ai" onclick="window.open('valuationVerify.php', 'ValuationBuilder', 'width=1250,height=900,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes')" style="width: 100%; justify-content: center; border-color: var(--secondary); background: rgba(168, 85, 247, 0.1);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                  Launch Detailed Valuation Builder
                </button>
                <p style="font-size: 0.75rem; color: var(--muted); margin-top: 6px; text-align: center;">Valuation confirmed. You can now use the builder to justify this number.</p>
              </div>
              <div id="applied-verdict-box"></div>
            </div>
          </div>
          <div class="grid-3">
            <div>
              <label class="neon-label">Share Price (₹)</label>
              <div class="neon-input" id="sharePriceDisplay" style="background: hsl(225,30%,15%,0.3); color: var(--primary); font-weight: 600;">—</div>
              <input type="hidden" id="sharePrice" value="0">
            </div>
            <div>
              <label class="neon-label">Shares to Sell</label>
              <div class="neon-input" id="sharesToSellDisplay" style="background: hsl(225,30%,15%,0.3); color: var(--primary); font-weight: 600;">—</div>
              <input type="hidden" id="sharesIssued" value="0">
            </div>
            <div>
              <label class="neon-label">Platform Fee (2%)</label>
              <div class="neon-input" id="platformFee" style="background: hsl(225,30%,15%,0.3); cursor: not-allowed; color: var(--muted);">—</div>
            </div>
          </div>
          <div style="margin-top: 1rem; font-size: 0.75rem; color: var(--muted);">
            Based on your company's total equity of <strong style="color:var(--fg)"><?php echo number_format($total_shares); ?></strong> shares. 
            Available to sell: <strong style="color:var(--fg)"><?php echo number_format($available_shares); ?></strong> shares.
          </div>
        </div>
      </div>

      <!-- SECTION 4: Documents -->
      <div class="section" style="animation-delay: 0.4s">
        <div class="section-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          <h2 class="section-title">Documents</h2>
        </div>
        <div class="grid-2x2" id="doc-tiles"></div>
      </div>

      <!-- SECTION 5: Investment Checkout Summary -->
      <div class="section" style="animation-delay: 0.5s">
        <div class="section-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
          <h2 class="section-title">Investment Checkout Summary</h2>
        </div>
        <div class="glass-card glass-card-strong">
          <div class="summary-grid" id="summary-grid"></div>
          <div class="equity-bar-wrap">
            <div class="equity-bar-header">
              <span style="color: var(--muted)">Equity Breakdown</span>
              <span class="glow-cyan" id="equity-label">0% offered</span>
            </div>
            <div class="equity-bar"><div class="equity-bar-fill" id="equity-fill" style="width: 0%"></div></div>
          </div>
          <div class="expiry">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Pitch expires in 30 days after submission</span>
          </div>
        </div>
      </div>

      <!-- CTA -->
      <div class="section cta-wrap" style="animation-delay: 0.6s">
        <button class="neon-cta" id="submit-btn" disabled>
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
          Submit Pitch & Pay Platform Fee
        </button>
        <p class="hint" id="cta-hint">Complete all required fields to submit</p>
      </div>
    </div>
  </div>

  <script>
    // ===== CONFIG FROM PHP =====
    const TOTAL_COMP_SHARES = <?php echo $total_shares; ?>;
    const AVAILABLE_SHARES = <?php echo $available_shares; ?>;

    // ===== DATA =====
    const pitchFields = [
      { key: 'problem', label: 'Problem Statement', placeholder: 'What problem are you solving?' },
      { key: 'solution', label: 'Solution', placeholder: 'How does your product solve this?' },
      { key: 'description', label: 'Short Pitch Description', placeholder: 'Describe your startup in 2-3 sentences...' },
      { key: 'traction', label: 'Current Traction', placeholder: 'Users, revenue, partnerships, milestones...' },
    ];
    const aiSamples = {
      problem: 'Small and medium enterprises struggle with access to intelligent financial planning tools, leading to poor cash flow management and missed growth opportunities.',
      solution: 'Our AI-powered platform provides real-time financial intelligence, automated cash flow forecasting, and smart investment recommendations tailored for growing businesses.',
      description: 'We are building the next-generation financial intelligence platform that empowers startups and SMEs to make data-driven financial decisions with AI-powered insights and automated planning tools.',
      traction: '500+ active users, ₹2.5 Cr ARR, partnerships with 3 major accelerators, 40% month-over-month growth in user acquisition.',
    };
    const docTypes = [
      { key: 'logo', label: 'Pitch Logo', accept: 'image/*', desc: 'PNG, JPG up to 5MB', icon: '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>' },
      { key: 'deck', label: 'Pitch Deck', accept: '.pdf', desc: 'PDF format', icon: '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>' },
      { key: 'financial', label: 'Financial Documents', accept: '.pdf,.xlsx,.xls', desc: 'PDF or Excel', icon: '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 18v-2"/><path d="M12 18v-4"/><path d="M16 18v-6"/></svg>' },
      { key: 'legal', label: 'Legal / Incorporation', accept: '.pdf', desc: 'PDF format', icon: '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>' },
    ];
    const selectedFiles = {};

    // ===== RENDER PITCH DETAILS =====
    const pitchCard = document.getElementById('pitch-details-card');
    pitchFields.forEach(({ key, label, placeholder }) => {
      const div = document.createElement('div');
      div.style.marginBottom = '1.25rem';
      div.innerHTML = `
        <div class="field-header">
          <label class="neon-label">${label}</label>
          <button class="neon-btn-ai" id="ai-${key}" onclick="generateAI('${key}')">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.287 1.288L3 12l5.8 1.9a2 2 0 0 1 1.288 1.287L12 21l1.9-5.8a2 2 0 0 1 1.287-1.288L21 12l-5.8-1.9a2 2 0 0 1-1.288-1.287Z"/></svg>
            Generate with AI
          </button>
        </div>
        <textarea class="neon-textarea" id="pitch-${key}" placeholder="${placeholder}"></textarea>
      `;
      pitchCard.appendChild(div);
    });

    // ===== RENDER DOCUMENT TILES =====
    const docContainer = document.getElementById('doc-tiles');
    docTypes.forEach(({ key, label, accept, desc, icon }) => {
      const tile = document.createElement('div');
      tile.className = 'upload-tile';
      tile.innerHTML = `
        <input type="file" accept="${accept}" id="file-${key}" />
        <div id="icon-${key}">${icon}</div>
        <div style="text-align:center">
          <p class="name">${label}</p>
          <p class="desc" id="desc-${key}">${desc}</p>
        </div>
      `;
      tile.addEventListener('click', () => document.getElementById('file-' + key).click());
      const input = tile.querySelector('input');
      input.addEventListener('change', (e) => {
        const f = e.target.files[0];
        if (f) {
          selectedFiles[key] = f;
          document.getElementById('desc-' + key).textContent = f.name;
          document.getElementById('desc-' + key).style.color = 'var(--primary)';
          if (key === 'logo' && f.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (ev) => {
              document.getElementById('icon-' + key).innerHTML = `<img src="${ev.target.result}" style="width:64px;height:64px;border-radius:0.5rem;object-fit:cover" />`;
            };
            reader.readAsDataURL(f);
          }
        }
      });
      input.addEventListener('click', (e) => e.stopPropagation());
      docContainer.appendChild(tile);
    });

    // ===== AI GENERATE =====
    function generateAI(key) {
      const btn = document.getElementById('ai-' + key);
      btn.disabled = true;
      btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.287 1.288L3 12l5.8 1.9a2 2 0 0 1 1.288 1.287L12 21l1.9-5.8a2 2 0 0 1 1.287-1.288L21 12l-5.8-1.9a2 2 0 0 1-1.288-1.287Z"/></svg> Generating...`;
      setTimeout(() => {
        document.getElementById('pitch-' + key).value = aiSamples[key] || '';
        btn.disabled = false;
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.287 1.288L3 12l5.8 1.9a2 2 0 0 1 1.288 1.287L12 21l1.9-5.8a2 2 0 0 1 1.287-1.288L21 12l-5.8-1.9a2 2 0 0 1-1.288-1.287Z"/></svg> Generate with AI`;
        updateSummary();
        checkValidity();
      }, 1500);
    }

    // ===== SUMMARY & LOGIC =====
    function updateSummary() {
      const name = document.getElementById('name').value || '—';
      const goal = parseFloat(document.getElementById('fundingGoal').value.replace(/[^\d.]/g, '')) || 0;
      const val = parseFloat(document.getElementById('valuation').value.replace(/[^\d.]/g, '')) || 0;
      const stage = document.getElementById('stage').value;
      
      // SHARE LOGIC CALCULATIONS
      let sp = 0;
      let si = 0;
      let equity = 0;
      let fee = goal * 0.02;

      if (val > 0) {
        sp = val / TOTAL_COMP_SHARES;
        if (goal > 0 && sp > 0) {
          si = Math.floor(goal / sp);
          equity = ((si / TOTAL_COMP_SHARES) * 100).toFixed(2);
        }
      }

      // Update Displays
      document.getElementById('sharePriceDisplay').textContent = sp > 0 ? '₹' + sp.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—';
      document.getElementById('sharePrice').value = sp;
      
      document.getElementById('sharesToSellDisplay').textContent = si > 0 ? si.toLocaleString() : '—';
      document.getElementById('sharesIssued').value = si;
      
      document.getElementById('platformFee').textContent = goal > 0 ? '₹' + fee.toLocaleString() : '—';

      // Summary Table
      const roundSelected = document.getElementById('investmentRound').value;
      const durationSelected = document.getElementById('fundDuration').value + ' Days';
      const items = [
        ['Startup Name', name],
        ['Investment Round', roundSelected],
        ['Campaign Timeline', durationSelected],
        ['Funding Goal', goal ? '₹' + goal.toLocaleString() : '—'],
        ['Valuation', val ? '₹' + val.toLocaleString() : '—'],
        ['Share Price', sp ? '₹' + sp.toLocaleString(undefined, {minimumFractionDigits: 2}) : '—'],
        ['Shares to Sell', si ? si.toLocaleString() : '—'],
        ['Equity Offered', equity + '%'],
        ['Platform Fee (2%)', fee ? '₹' + fee.toLocaleString() : '—'],
      ];

      const grid = document.getElementById('summary-grid');
      grid.innerHTML = items.map(([l, v]) => `<div class="summary-row"><span class="summary-label">${l}</span><span class="summary-value">${v}</span></div>`).join('');

      document.getElementById('equity-label').textContent = equity + '% offered';
      document.getElementById('equity-fill').style.width = Math.min(parseFloat(equity), 100) + '%';

      // Check if trying to sell more than available
      if (si > AVAILABLE_SHARES) {
        document.getElementById('sharesToSellDisplay').style.color = '#ef4444';
        document.getElementById('cta-hint').textContent = `Error: You only have ${AVAILABLE_SHARES.toLocaleString()} shares available.`;
        document.getElementById('cta-hint').style.display = 'block';
        return false;
      } else {
        document.getElementById('sharesToSellDisplay').style.color = 'var(--primary)';
        document.getElementById('cta-hint').textContent = 'Complete all required fields to submit';
        return true;
      }
    }

    // ===== LISTENERS =====
    ['fundingGoal', 'valuation', 'name', 'industry', 'stage', 'investmentRound', 'fundDuration'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', () => { updateSummary(); checkValidity(); });
      if (el) el.addEventListener('change', () => { updateSummary(); checkValidity(); });
    });

    const lockBtn = document.getElementById('lock-valuation-btn');
    const valInput = document.getElementById('valuation');
    const goalInput = document.getElementById('fundingGoal');
    const actionArea = document.getElementById('valuation-action-area');
    const tickIcon = document.getElementById('tick-icon');
    const lockIcon = document.getElementById('lock-icon');

    if (lockBtn) {
      lockBtn.addEventListener('click', () => {
        const isLocked = valInput.hasAttribute('readonly');
        
        if (!isLocked) {
          // Confirm & Lock Both
          [valInput, goalInput].forEach(el => {
            el.setAttribute('readonly', true);
            el.style.background = 'var(--muted-bg)';
            el.style.opacity = '0.8';
            el.style.cursor = 'not-allowed';
          });
          tickIcon.style.display = 'none';
          lockIcon.style.display = 'block';
          actionArea.style.display = 'block';
          lockBtn.title = "Unlock Parameters to Edit";
          lockBtn.style.color = 'var(--muted)';
        } else {
          // Unlock Both
          [valInput, goalInput].forEach(el => {
            el.removeAttribute('readonly');
            el.style.background = '';
            el.style.opacity = '';
            el.style.cursor = '';
          });
          tickIcon.style.display = 'block';
          lockIcon.style.display = 'none';
          actionArea.style.display = 'none';
          lockBtn.title = "Confirm Valuation";
          lockBtn.style.color = 'var(--primary)';
        }
      });
    }

    ['pitch-problem', 'pitch-solution'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', checkValidity);
    });

    // ===== VALIDATION =====
    function checkValidity() {
      const isShareValid = updateSummary();
      const filled =
        document.getElementById('name').value &&
        document.getElementById('industry').value &&
        document.getElementById('stage').value &&
        document.getElementById('investmentRound').value &&
        document.getElementById('fundDuration').value &&
        document.getElementById('pitch-problem').value &&
        document.getElementById('pitch-solution').value &&
        document.getElementById('fundingGoal').value &&
        document.getElementById('valuation').value &&
        isShareValid;
      
      document.getElementById('submit-btn').disabled = !filled;
      document.getElementById('cta-hint').style.display = filled ? 'none' : 'block';
    }
    updateSummary();

    // ===== SUBMIT (AJAX) =====
    document.getElementById('submit-btn').addEventListener('click', async () => {
      const btn = document.getElementById('submit-btn');
      const originalHtml = btn.innerHTML;
      
      btn.disabled = true;
      btn.innerHTML = `<div class="spinner" style="width:20px;height:20px;border-width:2px;margin-right:10px;"></div> Processing...`;

      const formData = new FormData();
      formData.append('startupName', document.getElementById('name').value);
      formData.append('tagline', document.getElementById('tagline').value);
      formData.append('category', document.getElementById('industry').value);
      formData.append('stage', document.getElementById('stage').value);
      formData.append('location', document.getElementById('location').value);
      
      formData.append('problem', document.getElementById('pitch-problem').value);
      formData.append('solution', document.getElementById('pitch-solution').value);
      formData.append('description', document.getElementById('pitch-description').value);
      formData.append('traction', document.getElementById('pitch-traction').value);

      // Clean numeric inputs (remove commas/currency symbols)
      const cleanGoal = document.getElementById('fundingGoal').value.replace(/[^\d.]/g, '');
      const cleanVal = document.getElementById('valuation').value.replace(/[^\d.]/g, '');

      formData.append('fundingRequired', cleanGoal);
      formData.append('valuation', cleanVal);
      formData.append('roundName', document.getElementById('investmentRound').value);
      formData.append('fundDuration', document.getElementById('fundDuration').value);
      formData.append('mode', 'new');

      // Add Files
      for (const [key, file] of Object.entries(selectedFiles)) {
          formData.append(key, file);
      }

      try {
          const response = await fetch('submit-pitch.php', {
              method: 'POST',
              body: formData
          });
          const result = await response.json();

          if (result.success) {
              btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Entry Granted!`;
              // NEW: Use the redirect provided by the server (AI Warzone)
              setTimeout(() => {
                window.location.href = result.redirect || '../dashboards/Entrepreneur-dashboard.php';
              }, 1500);
          } else {
              alert('Error: ' + result.message);
              btn.disabled = false;
              btn.innerHTML = originalHtml;
          }
      } catch (err) {
          console.error(err);
          alert('Submission failed. Please check your connection.');
          btn.disabled = false;
          btn.innerHTML = originalHtml;
      }
    });
  </script>
</body>
</html>