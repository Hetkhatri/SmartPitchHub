<?php
session_start();
require_once "../../db.php";

// 1. Security Check (Admin Only)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// 2. Get Request ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Invalid Request ID.");
}

// 3. Fetch Valuation Details
$sql = "SELECT v.*, e.name as entrepreneur_name, e.email as entrepreneur_email, e.contact as entrepreneur_contact
        FROM valuation_requests v 
        JOIN entrepreneurs e ON v.entrepreneur_id = e.id 
        WHERE v.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Valuation request not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review Valuation #<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?> - SmartPitchHub Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --background: hsl(0, 0%, 98%);
      --foreground: hsl(222, 47%, 11%);
      --card: hsl(0, 0%, 100%);
      --card-foreground: hsl(222, 47%, 11%);
      --primary: hsl(217, 91%, 50%);
      --primary-foreground: hsl(0, 0%, 100%);
      --secondary: hsl(210, 40%, 96%);
      --muted: hsl(210, 40%, 96%);
      --muted-foreground: hsl(215, 16%, 47%);
      --border: hsl(214, 32%, 91%);
      --destructive: hsl(0, 84%, 60%);
      --success: hsl(142, 76%, 36%);
      --warning: hsl(45, 93%, 47%);
      --info: hsl(199, 89%, 48%);
      --radius: 0.5rem;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    /* Header */
    .header {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 24px 0;
    }

    .container {
      max-width: 1152px;
      margin: 0 auto;
      padding: 0 24px;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      margin-left: -12px;
      margin-bottom: 16px;
      background: none;
      border: none;
      color: var(--muted-foreground);
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      border-radius: var(--radius);
      transition: all 0.2s;
    }

    .back-btn:hover {
      background: var(--muted);
      color: var(--foreground);
    }

    .page-title {
      font-size: 24px;
      font-weight: 600;
      color: var(--foreground);
    }

    .page-subtitle {
      color: var(--muted-foreground);
      margin-top: 4px;
      font-size: 14px;
    }

    /* Main Content */
    .main-content {
      padding: 32px 0;
    }

    .section-stack {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    /* Cards */
    .admin-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .admin-card-header {
      padding: 16px 24px;
      border-bottom: 1px solid var(--border);
    }

    .admin-card-body {
      padding: 20px 24px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--foreground);
    }

    .section-subtitle {
      font-size: 14px;
      color: var(--muted-foreground);
      margin-top: 2px;
    }

    /* Summary Cards */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    @media (max-width: 768px) {
      .summary-grid {
        grid-template-columns: 1fr;
      }
    }

    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      display: flex;
      align-items: flex-start;
      gap: 16px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .stat-icon.primary { background: hsla(217, 91%, 50%, 0.1); color: var(--primary); }
    .stat-icon.success { background: hsla(142, 76%, 36%, 0.1); color: var(--success); }
    .stat-icon.warning { background: hsla(45, 93%, 47%, 0.1); color: var(--warning); }
    .stat-icon.destructive { background: hsla(0, 84%, 60%, 0.1); color: var(--destructive); }
    .stat-icon.info { background: hsla(199, 89%, 48%, 0.1); color: var(--info); }

    .stat-label {
      font-size: 14px;
      color: var(--muted-foreground);
    }

    .stat-value {
      font-size: 20px;
      font-weight: 600;
      color: var(--foreground);
      margin-top: 2px;
    }

    /* Details Grid */
    .details-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
    }

    @media (max-width: 768px) {
      .details-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    .detail-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .detail-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--radius);
      background: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--muted-foreground);
    }

    .field-label {
      font-size: 14px;
      font-weight: 500;
      color: var(--muted-foreground);
      margin-bottom: 4px;
    }

    .field-value {
      font-size: 14px;
      font-weight: 600;
      color: var(--foreground);
    }

    /* Two Column Layout */
    .two-column {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px;
    }

    @media (max-width: 768px) {
      .two-column {
        grid-template-columns: 1fr;
      }
    }

    .column-title {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--foreground);
      margin-bottom: 16px;
    }

    .field-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }

    .field-row:last-child {
      border-bottom: none;
    }

    .field-row-label {
      font-size: 14px;
      color: var(--muted-foreground);
    }

    .field-row-value {
      font-size: 14px;
      font-weight: 600;
      color: var(--foreground);
    }

    /* Justification Box */
    .justification-box {
      background: var(--muted);
      border-radius: var(--radius);
      padding: 16px;
      margin-top: 24px;
    }

    .justification-box p {
      font-size: 14px;
      color: var(--foreground);
      line-height: 1.6;
    }

    /* Document List */
    .document-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .document-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px;
      background: hsla(210, 40%, 96%, 0.5);
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }

    .document-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .document-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--radius);
      background: hsla(0, 84%, 60%, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--destructive);
    }

    .document-name {
      font-size: 14px;
      font-weight: 500;
      color: var(--foreground);
    }

    .document-meta {
      font-size: 12px;
      color: var(--muted-foreground);
    }

    .document-actions {
      display: flex;
      gap: 8px;
    }

    .btn-ghost {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      background: none;
      border: none;
      color: var(--foreground);
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      border-radius: var(--radius);
      transition: all 0.2s;
    }

    .btn-ghost:hover {
      background: var(--muted);
    }

    /* AI Insights Card */
    .ai-insight-card {
      background: hsla(199, 89%, 48%, 0.05);
      border: 1px solid hsla(199, 89%, 48%, 0.2);
      border-radius: var(--radius);
      padding: 20px;
    }

    .ai-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 16px;
    }

    .ai-title-group {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .ai-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--radius);
      background: hsla(199, 89%, 48%, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--info);
    }

    .ai-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--foreground);
    }

    .ai-subtitle {
      font-size: 12px;
      color: var(--muted-foreground);
    }

    .ai-badge {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--muted-foreground);
      background: hsla(199, 89%, 48%, 0.1);
      padding: 4px 8px;
      border-radius: 4px;
    }

    .ai-metrics {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .ai-metrics {
        grid-template-columns: 1fr;
      }
    }

    .ai-metric-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
    }

    .ai-metric-label {
      font-size: 12px;
      color: var(--muted-foreground);
      margin-bottom: 4px;
    }

    .ai-metric-value {
      font-size: 18px;
      font-weight: 600;
      color: var(--foreground);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 12px;
      font-weight: 500;
      margin-top: 4px;
    }

    .badge-low {
      background: hsla(142, 76%, 36%, 0.1);
      color: var(--success);
      border: 1px solid hsla(142, 76%, 36%, 0.2);
    }

    .badge-medium {
      background: hsla(45, 93%, 47%, 0.1);
      color: var(--warning);
      border: 1px solid hsla(45, 93%, 47%, 0.2);
    }

    .badge-high {
      background: hsla(0, 84%, 60%, 0.1);
      color: var(--destructive);
      border: 1px solid hsla(0, 84%, 60%, 0.2);
    }

    .confidence-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .observations-title {
      font-size: 14px;
      font-weight: 500;
      color: var(--foreground);
      margin-bottom: 8px;
    }

    .observations-list {
      list-style: none;
    }

    .observations-list li {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 14px;
      color: var(--muted-foreground);
      margin-bottom: 8px;
    }

    .observations-list li::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--info);
      border-radius: 50%;
      margin-top: 8px;
      flex-shrink: 0;
    }

    /* Decision Panel */
    .decision-options {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .decision-option {
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .decision-option:hover {
      border-color: hsla(217, 91%, 50%, 0.4);
    }

    .decision-option.selected {
      border-color: var(--primary);
      background: hsla(217, 91%, 50%, 0.05);
      box-shadow: 0 0 0 1px hsla(217, 91%, 50%, 0.2);
    }

    .decision-header {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .radio-circle {
      width: 20px;
      height: 20px;
      border: 2px solid var(--border);
      border-radius: 50%;
      flex-shrink: 0;
      margin-top: 2px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .decision-option.selected .radio-circle {
      border-color: var(--primary);
      background: var(--primary);
    }

    .radio-inner {
      width: 8px;
      height: 8px;
      background: white;
      border-radius: 50%;
      display: none;
    }

    .decision-option.selected .radio-inner {
      display: block;
    }

    .decision-content {
      flex: 1;
    }

    .decision-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 500;
      color: var(--foreground);
    }

    .decision-title svg {
      width: 16px;
      height: 16px;
    }

    .decision-title.approve svg { color: var(--success); }
    .decision-title.modify svg { color: var(--primary); }
    .decision-title.reject svg { color: var(--destructive); }

    .decision-desc {
      font-size: 14px;
      color: var(--muted-foreground);
      margin-top: 4px;
    }

    .decision-fields {
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }

    .form-group {
      margin-bottom: 12px;
    }

    .form-group:last-child {
      margin-bottom: 0;
    }

    .form-label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: var(--muted-foreground);
      margin-bottom: 6px;
    }

    .form-label .required {
      color: var(--destructive);
    }

    .form-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 14px;
      font-family: inherit;
      transition: all 0.2s;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px hsla(217, 91%, 50%, 0.1);
    }

    .form-textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 14px;
      font-family: inherit;
      resize: none;
      transition: all 0.2s;
    }

    .form-textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px hsla(217, 91%, 50%, 0.1);
    }

    /* Action Buttons */
    .action-bar {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
      padding-top: 24px;
      border-top: 1px solid var(--border);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border: none;
      border-radius: var(--radius);
      font-size: 14px;
      font-weight: 500;
      font-family: inherit;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .btn-success {
      background: var(--success);
      color: white;
    }

    .btn-success:hover:not(:disabled) {
      background: hsl(142, 76%, 30%);
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover:not(:disabled) {
      background: hsl(217, 91%, 45%);
    }

    .btn-destructive {
      background: var(--destructive);
      color: white;
    }

    .btn-destructive:hover:not(:disabled) {
      background: hsl(0, 84%, 55%);
    }

    /* Toast Notification */
    .toast-container {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 1000;
    }

    .toast {
      background: var(--foreground);
      color: white;
      padding: 16px 20px;
      border-radius: var(--radius);
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      display: none;
      max-width: 360px;
    }

    .toast.show {
      display: block;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        transform: translateY(20px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .toast-title {
      font-weight: 600;
      margin-bottom: 4px;
    }

    .toast-desc {
      font-size: 14px;
      opacity: 0.9;
    }

    /* SVG Icons inline */
    .icon {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
    }

    .icon-sm {
      width: 16px;
      height: 16px;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header class="header">
    <div class="container">
      <button class="back-btn" onclick="history.back()">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Valuation Requests
      </button>
      <div>
        <h1 class="page-title">Valuation Review: <?php echo htmlspecialchars($data['entity_name'] ?? 'Startup'); ?></h1>
        <p class="page-subtitle">Status: <span class="badge <?php 
          echo $data['status'] === 'pending' ? 'badge-pending' : ($data['status'] === 'verified' ? 'badge-success' : 'badge-danger'); 
        ?>"><?php echo ucfirst($data['status']); ?></span></p>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content">
    <div class="container">
      <div class="section-stack">

        <!-- Summary Cards -->
        <div class="summary-grid">
          <div class="stat-card">
            <div class="stat-icon primary">
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div>
              <p class="stat-label">Valuation Asked</p>
              <p class="stat-value">₹<?php echo number_format($data['valuation_ask']); ?></p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon success">
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
            </div>
            <div>
              <p class="stat-label">Funding Goal</p>
              <p class="stat-value">₹<?php echo number_format($data['fundraise_amount']); ?></p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon warning">
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <div>
              <p class="stat-label">Business Stage</p>
              <p class="stat-value"><?php echo htmlspecialchars($data['stage']); ?></p>
            </div>
          </div>
        </div>

        <!-- Startup & Founder Details -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h2 class="section-title">Startup & Founder Details</h2>
          </div>
          <div class="admin-card-body">
            <div class="details-grid">
              <div class="detail-item">
                <div class="detail-icon">
                  <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                </div>
                <div>
                  <p class="field-label">Startup Name</p>
                  <p class="field-value"><?php echo htmlspecialchars($data['entity_name']); ?></p>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
                <div>
                  <p class="field-label">Founder Name</p>
                  <p class="field-value"><?php echo htmlspecialchars($data['entrepreneur_name']); ?></p>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
                <div>
                  <p class="field-label">Role</p>
                  <p class="field-value">Entrepreneur</p>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                </div>
                <div>
                  <p class="field-label">Industry</p>
                  <p class="field-value"><?php echo htmlspecialchars($data['industry']); ?></p>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                </div>
                <div>
                  <p class="field-label">Contact</p>
                  <p class="field-value"><?php echo htmlspecialchars($data['entrepreneur_contact']); ?></p>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <div>
                  <p class="field-label">Submitted Date</p>
                  <p class="field-value"><?php echo date('M d, Y', strtotime($data['created_at'])); ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Valuation Input Details -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h2 class="section-title">Valuation Input Details</h2>
            <p class="section-subtitle">Entrepreneur-provided data (read-only)</p>
          </div>
          <div class="admin-card-body">
            <div class="two-column">
              <div>
                <h3 class="column-title">Financial Metrics</h3>
                <div class="field-row">
                  <span class="field-row-label">Trailing 12-Month Revenue</span>
                  <span class="field-row-value">₹<?php echo number_format($data['ttm_revenue']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Projected Revenue (Next 12 Months)</span>
                  <span class="field-row-value">₹<?php echo number_format($data['projected_revenue']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Monthly Cash Burn</span>
                  <span class="field-row-value">₹<?php echo number_format($data['monthly_burn']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Active Users / Customers</span>
                  <span class="field-row-value"><?php echo number_format($data['active_users']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Growth Rate (%)</span>
                  <span class="field-row-value"><?php echo htmlspecialchars($data['growth_rate']); ?>%</span>
                </div>
              </div>
              <div>
                <h3 class="column-title">Valuation Proposal</h3>
                <div class="field-row">
                  <span class="field-row-label">Pre-Money Valuation Asked</span>
                  <span class="field-row-value">₹<?php echo number_format($data['valuation_ask']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Target Fundraise Amount</span>
                  <span class="field-row-value">₹<?php echo number_format($data['fundraise_amount']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Previous Capital Raised</span>
                  <span class="field-row-value">₹<?php echo number_format($data['previous_capital']); ?></span>
                </div>
                <div class="field-row">
                  <span class="field-row-label">Valuation Basis</span>
                  <span class="field-row-value"><?php echo htmlspecialchars($data['valuation_basis']); ?></span>
                </div>
              </div>
            </div>
            <div class="justification-box" style="margin-top: 24px;">
              <p class="field-label" style="margin-bottom: 8px;">Founder Justification</p>
              <p><?php echo nl2br(htmlspecialchars($data['justification'])); ?></p>
            </div>
          </div>
        </div>

        <!-- Document Verification -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h2 class="section-title">Document Verification</h2>
            <p class="section-subtitle">Review submitted documents</p>
          </div>
          <div class="admin-card-body">
            <div class="document-list">
              <?php if ($data['deck_path']): ?>
              <div class="document-item">
                <div class="document-info">
                  <div class="document-icon">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="document-name">Pitch Deck Document</p>
                    <p class="document-meta">Project Presentation</p>
                  </div>
                </div>
                <div class="document-actions">
                  <?php 
                    $deckUrl = "../../" . ltrim($data['deck_path'], "./");
                  ?>
                  <a href="<?php echo htmlspecialchars($deckUrl); ?>" target="_blank" class="btn-ghost" style="text-decoration: none;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    View
                  </a>
                  <a href="<?php echo htmlspecialchars($deckUrl); ?>" download class="btn-ghost" style="text-decoration: none;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                  </a>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($data['financials_path']): ?>
              <div class="document-item">
                <div class="document-info">
                  <div class="document-icon" style="background: hsla(142, 76%, 36%, 0.1); color: var(--success);">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="document-name">Financial Statements</p>
                    <p class="document-meta">Financial Proofs</p>
                  </div>
                </div>
                <div class="document-actions">
                  <?php 
                    $finUrl = "../../" . ltrim($data['financials_path'], "./");
                  ?>
                  <a href="<?php echo htmlspecialchars($finUrl); ?>" target="_blank" class="btn-ghost" style="text-decoration: none;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    View
                  </a>
                  <a href="<?php echo htmlspecialchars($finUrl); ?>" download class="btn-ghost" style="text-decoration: none;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                  </a>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- AI Insights -->
        <div class="ai-insight-card">
          <div class="ai-header">
            <div class="ai-title-group">
              <div class="ai-icon">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
              </div>
              <div>
                <p class="ai-title">AI Valuation Insights</p>
                <p class="ai-subtitle">Advisory analysis only</p>
              </div>
            </div>
            <div class="ai-badge">
              <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Admin-only
            </div>
          </div>

          <div class="ai-metrics">
            <div class="ai-metric-card">
              <p class="ai-metric-label">Estimated Benchmark</p>
              <p class="ai-metric-value" id="ai-benchmark">Analyzing...</p>
            </div>
            <div class="ai-metric-card">
              <p class="ai-metric-label">Market Status</p>
              <span id="ai-status-badge">
                <span class="badge badge-pending">Loading...</span>
              </span>
            </div>
            <div class="ai-metric-card">
              <p class="ai-metric-label">Confidence Score</p>
              <div class="confidence-row">
                <svg width="16" height="16" fill="none" stroke="var(--success)" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                <span class="ai-metric-value" id="ai-confidence">--%</span>
              </div>
            </div>
          </div>

          <div>
            <p class="observations-title">AI Analysis Remarks</p>
            <div id="ai-remarks" style="font-size: 14px; line-height: 1.6; color: var(--muted-foreground);">
              Generating real-time analysis based on startup metrics...
            </div>
          </div>
        </div>

        <!-- Admin Decision Panel -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h2 class="section-title">Admin Decision</h2>
            <p class="section-subtitle">
              <?php if ($data['status'] === 'pending'): ?>
                Select an action for this valuation request
              <?php else: ?>
                This request has been processed.
              <?php endif; ?>
            </p>
          </div>
          <div class="admin-card-body">
            <?php if ($data['status'] === 'pending'): ?>
            <div class="decision-options">
              <!-- Approve Option -->
              <div class="decision-option" id="option-approve" onclick="selectDecision('approve')">
                <div class="decision-header">
                  <div class="radio-circle">
                    <div class="radio-inner"></div>
                  </div>
                  <div class="decision-content">
                    <div class="decision-title approve">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                      </svg>
                      Approve Valuation
                    </div>
                    <p class="decision-desc">Use the entrepreneur's submitted valuation as the final approved amount.</p>
                  </div>
                </div>
              </div>

              <!-- Modify Option -->
              <div class="decision-option" id="option-modify" onclick="selectDecision('modify')">
                <div class="decision-header">
                  <div class="radio-circle">
                    <div class="radio-inner"></div>
                  </div>
                  <div class="decision-content">
                    <div class="decision-title modify">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                      Approve with Modification
                    </div>
                    <p class="decision-desc">Approve with a different valuation amount than requested.</p>
                    <div class="decision-fields" id="modify-fields" style="display: none;">
                      <div class="form-group">
                        <label class="form-label">Approved Valuation <span class="required">*</span></label>
                        <input type="text" class="form-input" id="modified-valuation" placeholder="e.g., ₹4,500,000">
                      </div>
                      <div class="form-group">
                        <label class="form-label">Comment (Optional)</label>
                        <textarea class="form-textarea" id="modify-comment" rows="3" placeholder="Reason for the modified valuation..."></textarea>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Reject Option -->
              <div class="decision-option" id="option-reject" onclick="selectDecision('reject')">
                <div class="decision-header">
                  <div class="radio-circle">
                    <div class="radio-inner"></div>
                  </div>
                  <div class="decision-content">
                    <div class="decision-title reject">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                      Reject Valuation
                    </div>
                    <p class="decision-desc">Decline the valuation request with a detailed reason.</p>
                    <div class="decision-fields" id="reject-fields" style="display: none;">
                      <div class="form-group">
                        <label class="form-label">Rejection Reason <span class="required">*</span></label>
                        <textarea class="form-textarea" id="reject-reason" rows="3" placeholder="Provide a detailed reason for rejection..."></textarea>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-bar" id="action-bar">
              <!-- Buttons will be added dynamically -->
            </div>
            <?php else: ?>
              <div class="final-decision-box <?php echo $data['status']; ?>">
                <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                  <?php if ($data['status'] === 'verified'): ?>
                    <svg style="color: var(--success); width: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Approved
                  <?php else: ?>
                    <svg style="color: var(--destructive); width: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Rejected
                  <?php endif; ?>
                </h3>
                <div style="background: var(--muted); padding: 16px; border-radius: var(--radius); border-left: 4px solid <?php echo $data['status'] === 'verified' ? 'var(--success)' : 'var(--destructive)'; ?>">
                  <p class="field-label">Admin Remarks</p>
                  <p style="white-space: pre-wrap; margin-top: 8px;"><?php echo htmlspecialchars($data['admin_remarks'] ?: 'No internal remarks provided.'); ?></p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- Toast Container -->
  <div class="toast-container">
    <div class="toast" id="toast">
      <p class="toast-title" id="toast-title"></p>
      <p class="toast-desc" id="toast-desc"></p>
    </div>
  </div>

  <script>
    let currentDecision = null;

    function selectDecision(decision) {
      currentDecision = decision;

      // Remove selected class from all options
      document.querySelectorAll('.decision-option').forEach(opt => {
        opt.classList.remove('selected');
      });

      // Add selected class to clicked option
      document.getElementById(`option-${decision}`).classList.add('selected');

      // Hide all fields
      document.getElementById('modify-fields').style.display = 'none';
      document.getElementById('reject-fields').style.display = 'none';

      // Show relevant fields
      if (decision === 'modify') {
        document.getElementById('modify-fields').style.display = 'block';
      } else if (decision === 'reject') {
        document.getElementById('reject-fields').style.display = 'block';
      }

      // Update action buttons
      updateActionButtons();
    }

    function updateActionButtons() {
      const actionBar = document.getElementById('action-bar');
      actionBar.innerHTML = '';

      if (currentDecision === 'approve') {
        actionBar.innerHTML = `
          <button class="btn btn-success" onclick="handleApprove()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Approve Valuation
          </button>
        `;
      } else if (currentDecision === 'modify') {
        actionBar.innerHTML = `
          <button class="btn btn-primary" onclick="handleModify()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Approve with Modification
          </button>
        `;
      } else if (currentDecision === 'reject') {
        actionBar.innerHTML = `
          <button class="btn btn-destructive" onclick="handleReject()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Reject Valuation
          </button>
        `;
      }
    }

    function handleApprove() {
      processStatusUpdate('verified', 'Valuation Approved');
    }

    function handleModify() {
      const val = document.getElementById('modified-valuation').value;
      const comment = document.getElementById('modify-comment').value;
      if (!val) {
        showToast('Validation Error', 'Please enter the approved valuation amount.');
        return;
      }

      // Strip currency symbols and commas before sending
      const cleanVal = val.replace(/[^\d.]/g, '');
      
      const fullRemarks = `VALUATION MODIFIED TO: ₹${parseFloat(cleanVal).toLocaleString('en-IN')}\n\nNotes: ${comment}`;
      processStatusUpdate('verified', 'Valuation Modified & Approved', fullRemarks, cleanVal);
    }

    function handleReject() {
      const reason = document.getElementById('reject-reason').value;
      if (!reason) {
        showToast('Validation Error', 'Please provide a reason for rejection.');
        return;
      }
      processStatusUpdate('rejected', 'Valuation Rejected', reason);
    }

    function processStatusUpdate(status, successTitle, remarks = '', modifiedVal = null) {
      const formData = new FormData();
      formData.append('id', <?php echo $id; ?>);
      formData.append('status', status);
      formData.append('remarks', remarks);
      if (modifiedVal) {
        formData.append('modified_valuation', modifiedVal);
      }

      // Disable buttons
      document.querySelectorAll('.btn').forEach(b => b.disabled = true);

      fetch('../api_valuation_manager.php?action=update_status', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast(successTitle, data.message);
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast('Error', data.message);
          document.querySelectorAll('.btn').forEach(b => b.disabled = false);
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Fatal Error', 'Could not reach server.');
        document.querySelectorAll('.btn').forEach(b => b.disabled = false);
      });
    }

    function showToast(title, desc) {
      const toast = document.getElementById('toast');
      document.getElementById('toast-title').textContent = title;
      document.getElementById('toast-desc').textContent = desc;
      toast.classList.add('show');

      setTimeout(() => {
        toast.classList.remove('show');
      }, 4000);
    }

    // Prevent click propagation on form fields
    document.querySelectorAll('.decision-fields').forEach(field => {
      field.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    });

    // Real-time AI Analysis Call
    document.addEventListener('DOMContentLoaded', function() {
      const aiData = {
        industry: "<?php echo $data['industry']; ?>",
        stage: "<?php echo $data['stage']; ?>",
        ttmRevenue: <?php echo (float)$data['ttm_revenue']; ?>,
        growthRate: <?php echo (float)$data['growth_rate']; ?>,
        valuationAsk: <?php echo (float)$data['valuation_ask']; ?>
      };

      fetch('../../Pitches/api_valuation_advice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(aiData)
      })
      .then(res => res.json())
      .then(resBody => {
        if (resBody.status === 'success') {
          const ai = resBody.data;
          
          // Update Benchmark
          document.getElementById('ai-benchmark').textContent = new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 0
          }).format(ai.benchmark_valuation);

          // Update Status Badge
          let badgeClass = 'badge-pending';
          if (ai.status === 'Fair') badgeClass = 'badge-success';
          if (ai.status === 'Aggressive') badgeClass = 'badge-danger';
          if (ai.status === 'Conservative') badgeClass = 'badge-warning';
          
          document.getElementById('ai-status-badge').innerHTML = `<span class="badge ${badgeClass}">${ai.status}</span>`;
          
          // Update Confidence
          document.getElementById('ai-confidence').textContent = ai.confidence + '%';
          
          // Update Remarks
          document.getElementById('ai-remarks').innerHTML = `<p>${ai.advice}</p>`;
        } else {
          document.getElementById('ai-remarks').innerHTML = `<p style="color: var(--destructive)">AI Error: ${resBody.message}</p>`;
        }
      })
      .catch(err => {
        console.error('AI Fetch Error:', err);
        document.getElementById('ai-remarks').innerHTML = `<p style="color: var(--destructive)">Failed to contact AI Engine.</p>`;
      });
    });
  </script>
</body>
</html>