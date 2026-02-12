<?php
session_start();
require_once '../db.php'; 

// 1. HANDLE API ACTIONS (Approve/Reject) VIA AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean buffer to prevent HTML leakage into JSON
    ob_clean();
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !isset($input['action'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    $kyc_id = intval($input['id']);
    $action = $input['action']; // 'approve' or 'reject'
    $reason = isset($input['reason']) ? $input['reason'] : '';
    
    // Status for the KYC_DETAILS table (This remains 'approved' or 'rejected')
    $kyc_table_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $conn->begin_transaction();
    try {
        // Step A: Update the KYC Request Table
        $stmt1 = $conn->prepare("UPDATE entrepreneur_kyc_details SET status = ?, rejection_reason = ? WHERE id = ?");
        $stmt1->bind_param("ssi", $kyc_table_status, $reason, $kyc_id);
        if (!$stmt1->execute()) {
            throw new Exception("Failed to update KYC details: " . $stmt1->error);
        }
        $stmt1->close();

        // Step B: Get Entrepreneur ID
        $ent_id = 0;
        $stmt2 = $conn->prepare("SELECT entrepreneur_id FROM entrepreneur_kyc_details WHERE id = ?");
        $stmt2->bind_param("i", $kyc_id);
        $stmt2->execute();
        $stmt2->bind_result($ent_id);
        $stmt2->fetch();
        $stmt2->close();

        if ($ent_id === 0) {
            throw new Exception("Entrepreneur ID not found for this KYC request.");
        }

        // Step C: Update Main Entrepreneur Table
        // FIX: We set this to 'verified' because your database (ID 10, 11) uses 'verified'
        $main_user_status = ($action === 'approve') ? 'verified' : 'rejected';

        $stmt3 = $conn->prepare("UPDATE entrepreneurs SET kyc_status = ? WHERE id = ?");
        $stmt3->bind_param("si", $main_user_status, $ent_id);
        if (!$stmt3->execute()) {
            throw new Exception("Failed to update User status: " . $stmt3->error);
        }
        $stmt3->close();

        // Step D: Commit All Changes
        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. FETCH DATA FOR VIEW
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kyc-requests.php");
    exit;
}

$kyc_id = $_GET['id'];
$kyc = [];

// Fetch data joined with entrepreneur info
$sql = "SELECT k.*, e.email, e.contact 
        FROM entrepreneur_kyc_details k 
        JOIN entrepreneurs e ON k.entrepreneur_id = e.id 
        WHERE k.id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $kyc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $kyc = $result->fetch_assoc();
    $stmt->close();
}

// Redirect if invalid ID
if (!$kyc) {
    header("Location: kyc-requests.php");
    exit;
}

// Helper for file paths
function getFile($path) {
    if (empty($path)) return '#';
    // If path is already relative (starts with ../ or uploads/), assume it's good
    if (strpos($path, '../') === 0 || strpos($path, 'uploads/') === 0) {
        // If it starts with uploads/ but needs to go up a level
        if(strpos($path, 'uploads/') === 0) return '../' . $path;
        return $path;
    }
    // Default fallback
    return '../uploads/kyc/' . ltrim($path, '/');
}

// Helper to check if file is PDF
function isPdf($path) {
    return stripos($path, '.pdf') !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KYC Verification Review - SmartPitchHub Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* CSS Reset & Base */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --background: hsl(210, 20%, 98%);
      --foreground: hsl(222, 47%, 11%);
      --card: hsl(0, 0%, 100%);
      --card-foreground: hsl(222, 47%, 11%);
      --primary: hsl(168, 76%, 36%);
      --primary-foreground: hsl(0, 0%, 100%);
      --secondary: hsl(210, 20%, 96%);
      --secondary-foreground: hsl(222, 47%, 11%);
      --muted: hsl(210, 20%, 96%);
      --muted-foreground: hsl(215, 16%, 47%);
      --accent: hsl(168, 50%, 95%);
      --accent-foreground: hsl(168, 76%, 26%);
      --destructive: hsl(0, 84%, 60%);
      --destructive-foreground: hsl(0, 0%, 100%);
      --success: hsl(160, 84%, 39%);
      --success-foreground: hsl(0, 0%, 100%);
      --warning: hsl(38, 92%, 50%);
      --warning-foreground: hsl(0, 0%, 100%);
      --border: hsl(214, 32%, 91%);
      --radius: 0.75rem;
      --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
      --card-shadow-hover: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    h1, h2, h3, h4, h5, h6 { font-weight: 600; letter-spacing: -0.025em; }
    .container { max-width: 72rem; margin: 0 auto; padding: 0 1.5rem; }
    .header { position: sticky; top: 0; z-index: 40; background-color: var(--card); border-bottom: 1px solid var(--border); }
    .header-content { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; }
    .header-left { display: flex; align-items: center; gap: 1rem; }
    .back-btn { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--muted-foreground); background: none; border: none; cursor: pointer; transition: color 0.2s; }
    .back-btn:hover { color: var(--foreground); }
    .divider { width: 1px; height: 1.5rem; background-color: var(--border); }
    .breadcrumb { display: flex; align-items: center; font-size: 0.875rem; color: var(--muted-foreground); }
    .breadcrumb span { cursor: pointer; transition: color 0.2s; }
    .breadcrumb span:hover { color: var(--foreground); }
    .breadcrumb .active { color: var(--foreground); font-weight: 500; }
    .breadcrumb svg { margin: 0 0.25rem; }
    .page-title-section { background-color: var(--card); border-bottom: 1px solid var(--border); padding: 1.5rem 0; }
    .page-title-content { display: flex; align-items: flex-start; gap: 1rem; }
    .page-icon { width: 3rem; height: 3rem; border-radius: 0.75rem; background-color: hsla(168, 76%, 36%, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary); }
    .page-title h1 { font-size: 1.5rem; color: var(--foreground); }
    .page-title p { color: var(--muted-foreground); margin-top: 0.25rem; }
    .status-badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .status-dot { width: 0.375rem; height: 0.375rem; border-radius: 9999px; margin-right: 0.5rem; }
    .status-pending { background-color: hsla(38, 92%, 50%, 0.15); color: var(--warning); }
    .status-pending .status-dot { background-color: var(--warning); }
    .status-approved { background-color: hsla(160, 84%, 39%, 0.15); color: var(--success); }
    .status-approved .status-dot { background-color: var(--success); }
    .status-rejected { background-color: hsla(0, 84%, 60%, 0.15); color: var(--destructive); }
    .status-rejected .status-dot { background-color: var(--destructive); }
    main { padding: 2rem 0; }
    .sections { display: flex; flex-direction: column; gap: 1.5rem; }
    .kyc-card { background-color: var(--card); border-radius: var(--radius); border: 1px solid var(--border); padding: 1.5rem; box-shadow: var(--card-shadow); transition: box-shadow 0.2s; }
    .kyc-card:hover { box-shadow: var(--card-shadow-hover); }
    .card-header { margin-bottom: 1.25rem; }
    .card-header h2 { font-size: 1.125rem; color: var(--foreground); }
    .card-header p { font-size: 0.875rem; color: var(--muted-foreground); margin-top: 0.25rem; }
    .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    @media (min-width: 768px) { .info-grid { grid-template-columns: repeat(4, 1fr); } .info-grid-3 { grid-template-columns: repeat(3, 1fr); } }
    .info-field { display: flex; flex-direction: column; gap: 0.25rem; }
    .info-label { font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted-foreground); }
    .info-value { font-size: 0.875rem; font-weight: 500; color: var(--foreground); }
    .info-value a { color: var(--primary); text-decoration: none; }
    .info-value a:hover { text-decoration: underline; }
    .role-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.125rem 0.5rem; border-radius: 9999px; background-color: var(--accent); color: var(--accent-foreground); font-size: 0.75rem; font-weight: 500; }
    .full-width { grid-column: 1 / -1; }
    @media (min-width: 768px) { .full-width { grid-column: span 3; } }
    .image-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-top: 1.5rem; }
    @media (min-width: 768px) { .image-grid { grid-template-columns: repeat(3, 1fr); } }
    .image-preview-container { display: flex; flex-direction: column; gap: 0.5rem; }
    .image-preview { position: relative; border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--border); background-color: hsla(210, 20%, 96%, 0.3); cursor: pointer; }
    .image-preview img { width: 100%; height: 10rem; object-fit: cover; display: block; }
    .image-overlay { position: absolute; inset: 0; background-color: transparent; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s; }
    .image-preview:hover .image-overlay { background-color: hsla(222, 47%, 11%, 0.1); }
    .zoom-icon { opacity: 0; background-color: var(--card); border-radius: 9999px; padding: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); transition: opacity 0.2s; }
    .image-preview:hover .zoom-icon { opacity: 1; }
    .image-modal { position: fixed; inset: 0; z-index: 50; background-color: hsla(222, 47%, 11%, 0.8); display: none; align-items: center; justify-content: center; padding: 2rem; animation: fadeIn 0.3s ease-out; }
    .image-modal.active { display: flex; }
    .image-modal img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: scaleIn 0.2s ease-out; }
    .modal-close { position: absolute; top: 1.5rem; right: 1.5rem; background-color: var(--card); border-radius: 9999px; padding: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: none; cursor: pointer; transition: background-color 0.2s; }
    .modal-close:hover { background-color: var(--secondary); }
    .bank-note { margin-top: 1rem; padding: 0.75rem; border-radius: 0.5rem; background-color: hsla(168, 50%, 95%, 0.5); display: flex; align-items: flex-start; gap: 0.5rem; }
    .bank-note svg { flex-shrink: 0; color: var(--primary); margin-top: 0.125rem; }
    .bank-note p { font-size: 0.75rem; color: var(--muted-foreground); }
    .documents-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .document-card { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border-radius: 0.5rem; background-color: hsla(210, 20%, 96%, 0.5); border: 1px solid var(--border); transition: border-color 0.2s; }
    .document-card:hover { border-color: hsla(168, 76%, 36%, 0.3); }
    .document-info { display: flex; align-items: center; gap: 0.75rem; }
    .document-icon { width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; background-color: hsla(0, 84%, 60%, 0.1); display: flex; align-items: center; justify-content: center; color: var(--destructive); }
    .document-name { font-size: 0.875rem; font-weight: 500; color: var(--foreground); }
    .document-meta { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }
    .file-type-badge { font-size: 0.75rem; font-weight: 500; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background-color: hsla(0, 84%, 60%, 0.1); color: var(--destructive); text-transform: uppercase; }
    .file-size { font-size: 0.75rem; color: var(--muted-foreground); }
    .document-actions { display: flex; align-items: center; gap: 0.5rem; }
    .view-btn { display: flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; font-weight: 500; color: var(--primary); background: none; border: none; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; }
    .view-btn:hover { background-color: hsla(168, 76%, 36%, 0.1); }
    .download-btn { display: flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; font-weight: 500; color: var(--muted-foreground); background: none; border: none; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; }
    .download-btn:hover { background-color: var(--secondary); }
    .action-buttons { display: flex; align-items: center; gap: 1rem; }
    .approve-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 500; color: var(--success-foreground); background-color: var(--success); border: none; border-radius: 0.5rem; cursor: pointer; transition: opacity 0.2s; }
    .approve-btn:hover { opacity: 0.9; }
    .reject-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 500; color: var(--destructive); background-color: transparent; border: 2px solid var(--destructive); border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; }
    .reject-btn:hover { background-color: var(--destructive); color: var(--destructive-foreground); }
    .rejection-form { display: none; animation: fadeIn 0.3s ease-out; }
    .rejection-form.active { display: block; }
    .rejection-box { padding: 1rem; border-radius: 0.5rem; background-color: hsla(0, 84%, 60%, 0.05); border: 1px solid hsla(0, 84%, 60%, 0.2); }
    .rejection-title { font-size: 0.875rem; font-weight: 600; color: var(--destructive); margin-bottom: 0.75rem; }
    .rejection-options { display: flex; flex-direction: column; gap: 0.5rem; }
    .rejection-option { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; }
    .rejection-option:hover { background-color: hsla(0, 84%, 60%, 0.05); }
    .rejection-option input { width: 1rem; height: 1rem; accent-color: var(--destructive); }
    .rejection-option span { font-size: 0.875rem; color: var(--foreground); }
    .rejection-textarea { width: 100%; margin-top: 0.75rem; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border); background-color: var(--card); font-size: 0.875rem; font-family: inherit; resize: none; outline: none; }
    .rejection-textarea:focus { box-shadow: 0 0 0 2px hsla(0, 84%, 60%, 0.3); }
    .rejection-actions { display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem; }
    .cancel-btn { padding: 0.625rem 1rem; font-size: 0.875rem; font-weight: 500; color: var(--muted-foreground); background: none; border: none; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; }
    .cancel-btn:hover { background-color: var(--secondary); }
    .reject-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .status-message { display: none; }
    .status-message.active { display: flex; align-items: center; gap: 0.75rem; animation: fadeIn 0.3s ease-out; }
    .status-message-approved { background-color: hsla(160, 84%, 39%, 0.05); border-color: hsla(160, 84%, 39%, 0.2); }
    .status-message-rejected { background-color: hsla(0, 84%, 60%, 0.05); border-color: hsla(0, 84%, 60%, 0.2); }
    .status-icon { width: 2.5rem; height: 2.5rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; }
    .status-icon-approved { background-color: hsla(160, 84%, 39%, 0.2); color: var(--success); }
    .status-icon-rejected { background-color: hsla(0, 84%, 60%, 0.2); color: var(--destructive); }
    .status-text h3 { font-weight: 600; }
    .status-text-approved h3 { color: var(--success); }
    .status-text-rejected h3 { color: var(--destructive); }
    .status-text p { font-size: 0.875rem; color: var(--muted-foreground); }
    footer { border-top: 1px solid var(--border); margin-top: 3rem; padding: 1rem 0; }
    footer p { font-size: 0.75rem; color: var(--muted-foreground); text-align: center; }
    .toast-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 100; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.25rem; border-radius: 0.5rem; background-color: var(--card); border: 1px solid var(--border); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); animation: slideIn 0.3s ease-out; }
    .toast-success { border-left: 4px solid var(--success); }
    .toast-error { border-left: 4px solid var(--destructive); }
    .toast-info { border-left: 4px solid var(--primary); }
    .toast-icon { width: 1.25rem; height: 1.25rem; }
    .toast-success .toast-icon { color: var(--success); }
    .toast-error .toast-icon { color: var(--destructive); }
    .toast-info .toast-icon { color: var(--primary); }
    .toast-content h4 { font-size: 0.875rem; font-weight: 600; color: var(--foreground); }
    .toast-content p { font-size: 0.75rem; color: var(--muted-foreground); margin-top: 0.125rem; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes scaleIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .hidden { display: none !important; }
  </style>
</head>
<body>
  <header class="header">
    <div class="container">
      <div class="header-content">
        <div class="header-left">
          <button class="back-btn" onclick="window.location.href='kyc-requests.php'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Back
          </button>
          <div class="divider"></div>
          <nav class="breadcrumb">
            <span>Dashboard</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span>KYC Requests</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span class="active">View KYC</span>
          </nav>
        </div>
        <?php 
            $s = $kyc['status'];
            $sCls = 'status-pending';
            if($s=='approved') $sCls='status-approved';
            if($s=='rejected') $sCls='status-rejected';
        ?>
        <div id="statusBadge" class="status-badge <?php echo $sCls; ?>">
          <span class="status-dot"></span>
          <span id="statusText"><?php echo ucfirst($s); ?> Review</span>
        </div>
      </div>
    </div>
  </header>

  <div class="page-title-section">
    <div class="container">
      <div class="page-title-content">
        <div class="page-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
        </div>
        <div class="page-title">
          <h1>KYC Verification Review</h1>
          <p>Reviewing application for: <strong><?php echo htmlspecialchars($kyc['full_name']); ?></strong></p>
        </div>
      </div>
    </div>
  </div>

  <main>
    <div class="container">
      <div class="sections">
        
        <section class="kyc-card">
          <div class="card-header">
            <h2>User Basic Information</h2>
            <p>Personal details submitted by the user</p>
          </div>
          <div class="info-grid">
            <div class="info-field"><span class="info-label">KYC ID</span><span class="info-value"><?php echo $kyc['id']; ?></span></div>
            <div class="info-field"><span class="info-label">User ID</span><span class="info-value"><?php echo $kyc['entrepreneur_id']; ?></span></div>
            <div class="info-field"><span class="info-label">Full Name</span><span class="info-value"><?php echo htmlspecialchars($kyc['full_name']); ?></span></div>
            <div class="info-field"><span class="info-label">Email Address</span><span class="info-value"><?php echo htmlspecialchars($kyc['email']); ?></span></div>
            <div class="info-field"><span class="info-label">Mobile Number</span><span class="info-value"><?php echo htmlspecialchars($kyc['contact']); ?></span></div>
            <div class="info-field"><span class="info-label">Date of Birth</span><span class="info-value"><?php echo htmlspecialchars($kyc['dob']); ?></span></div>
            <div class="info-field"><span class="info-label">User Role</span>
              <span class="info-value">
                <span class="role-badge">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/></svg>
                  Entrepreneur
                </span>
              </span>
            </div>
            <div class="info-field"><span class="info-label">KYC Submitted Date</span><span class="info-value"><?php echo date("d M Y", strtotime($kyc['submission_date'])); ?></span></div>
          </div>
        </section>

        <section class="kyc-card">
          <div class="card-header">
            <h2>Identity Verification Details</h2>
            <p>Government-issued identification documents</p>
          </div>
          
          <div class="info-grid info-grid-3">
            <div class="info-field"><span class="info-label">Document Type</span><span class="info-value">Government ID</span></div>
            <div class="info-field"><span class="info-label">Issuing Country</span><span class="info-value"><?php echo htmlspecialchars($kyc['country']); ?></span></div>
          </div>

          <div class="image-grid">
            
            <div class="image-preview-container">
              <span class="info-label">ID Document Front</span>
              <?php if (isPdf($kyc['gov_id_path'])): ?>
                  <div class="image-preview" style="display:flex; align-items:center; justify-content:center; background:#f0f9ff;" onclick="window.open('<?php echo getFile($kyc['gov_id_path']); ?>', '_blank')">
                    <div style="text-align:center; color:var(--primary);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <p style="font-size:0.8rem; font-weight:600; margin-top:0.5rem;">View PDF Document</p>
                    </div>
                  </div>
              <?php else: ?>
                  <div class="image-preview" onclick="openImageModal('<?php echo getFile($kyc['gov_id_path']); ?>')">
                    <img src="<?php echo getFile($kyc['gov_id_path']); ?>" alt="Gov ID" onerror="this.src='../assets/placeholder.png'">
                    <div class="image-overlay"><div class="zoom-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg></div></div>
                  </div>
              <?php endif; ?>
            </div>

            <div class="image-preview-container">
              <span class="info-label">Selfie / Live Verification</span>
              <div class="image-preview" onclick="openImageModal('<?php echo getFile($kyc['selfie_path']); ?>')">
                <img src="<?php echo getFile($kyc['selfie_path']); ?>" alt="Selfie Verification" onerror="this.src='../assets/placeholder.png'">
                <div class="image-overlay"><div class="zoom-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg></div></div>
              </div>
            </div>

          </div>
        </section>

        <section class="kyc-card">
          <div class="card-header">
            <h2>Address Details</h2>
            <p>Residential address information</p>
          </div>
          <div class="info-grid info-grid-3">
            <div class="info-field full-width"><span class="info-label">Address Line</span><span class="info-value"><?php echo htmlspecialchars($kyc['residential_address']); ?></span></div>
            <div class="info-field"><span class="info-label">City</span><span class="info-value"><?php echo htmlspecialchars($kyc['city']); ?></span></div>
            <div class="info-field"><span class="info-label">State</span><span class="info-value"><?php echo htmlspecialchars($kyc['state']); ?></span></div>
            <div class="info-field"><span class="info-label">Country</span><span class="info-value"><?php echo htmlspecialchars($kyc['country']); ?></span></div>
            <div class="info-field"><span class="info-label">Pincode</span><span class="info-value"><?php echo htmlspecialchars($kyc['postal_code']); ?></span></div>
          </div>
        </section>

        <section class="kyc-card" id="businessSection">
          <div class="card-header">
            <h2>Business Details</h2>
            <p>Startup and business registration information</p>
          </div>
          <div class="info-grid info-grid-3">
            <div class="info-field"><span class="info-label">Startup Name</span><span class="info-value"><?php echo htmlspecialchars($kyc['legal_name']); ?></span></div>
            <div class="info-field"><span class="info-label">Business Type</span><span class="info-value"><?php echo htmlspecialchars($kyc['business_type']); ?></span></div>
            <div class="info-field"><span class="info-label">Registration Number</span><span class="info-value"><?php echo htmlspecialchars($kyc['cin']); ?></span></div>
            <div class="info-field"><span class="info-label">Industry Category</span><span class="info-value"><?php echo htmlspecialchars($kyc['industry']); ?></span></div>
            <div class="info-field"><span class="info-label">Address</span><span class="info-value"><?php echo htmlspecialchars($kyc['business_address']); ?></span></div>
          </div>
        </section>

        <section class="kyc-card">
          <div class="card-header">
            <h2>Bank Account Details</h2>
            <p>Banking information for fund settlements</p>
          </div>
          <div class="info-grid info-grid-3">
            <div class="info-field"><span class="info-label">Account Holder Name</span><span class="info-value"><?php echo htmlspecialchars($kyc['account_holder_name']); ?></span></div>
            <div class="info-field"><span class="info-label">Bank Name</span><span class="info-value"><?php echo htmlspecialchars($kyc['bank_name']); ?></span></div>
            <div class="info-field"><span class="info-label">Account Number</span><span class="info-value"><?php echo htmlspecialchars($kyc['account_number']); ?></span></div>
            <div class="info-field"><span class="info-label">IFSC Code</span><span class="info-value"><?php echo htmlspecialchars($kyc['ifsc_code']); ?></span></div>
            <div class="info-field"><span class="info-label">Account Type</span><span class="info-value"><?php echo htmlspecialchars($kyc['account_type']); ?></span></div>
          </div>
          <div class="bank-note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <p>Bank details are verified for secure fund settlements. Ensure all information matches the uploaded bank proof document.</p>
          </div>
        </section>

       <section class="kyc-card">
          <div class="card-header">
            <h2>Uploaded Documents</h2>
            <p>PDF documents submitted for verification</p>
          </div>
          <div class="documents-list">
            
            <?php if(!empty($kyc['coi_path'])): ?>
            <div class="document-card">
              <div class="document-info">
                <div class="document-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div>
                  <div class="document-name">Certificate of Incorporation</div>
                  <div class="document-meta"><span class="file-type-badge">DOCUMENT</span></div>
                </div>
              </div>
              <div class="document-actions">
                <a href="<?php echo getFile($kyc['coi_path']); ?>" target="_blank" class="view-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> 
                  View
                </a>
              </div>
            </div>
            <?php endif; ?>

            <?php if(!empty($kyc['gst_certificate_path'])): ?>
            <div class="document-card">
              <div class="document-info">
                <div class="document-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div>
                  <div class="document-name">GST Certificate</div>
                  <div class="document-meta"><span class="file-type-badge">DOCUMENT</span></div>
                </div>
              </div>
              <div class="document-actions">
                <a href="<?php echo getFile($kyc['gst_certificate_path']); ?>" target="_blank" class="view-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> 
                  View
                </a>
              </div>
            </div>
            <?php endif; ?>

            <?php if(!empty($kyc['bank_proof_path'])): ?>
            <div class="document-card">
              <div class="document-info">
                <div class="document-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div>
                  <div class="document-name">Bank Proof (Cheque/Statement)</div>
                  <div class="document-meta"><span class="file-type-badge">DOCUMENT</span></div>
                </div>
              </div>
              <div class="document-actions">
                <a href="<?php echo getFile($kyc['bank_proof_path']); ?>" target="_blank" class="view-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> 
                  View
                </a>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </section>

        <?php if($kyc['status'] == 'pending'): ?>
        <section class="kyc-card" id="actionPanel">
          <div class="card-header">
            <h2>Admin Action</h2>
            <p>Review all documents and information before making a decision</p>
          </div>
          <div id="actionButtons" class="action-buttons">
            <button class="approve-btn" onclick="submitDecision('approve')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Approve KYC
            </button>
            <button class="reject-btn" onclick="showRejectForm()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              Reject KYC
            </button>
          </div>
          <div id="rejectionForm" class="rejection-form">
            <div class="rejection-box">
              <h3 class="rejection-title">Rejection Reason</h3>
              <textarea id="rejectReason" class="rejection-textarea" rows="3" placeholder="Please specify the rejection reason..."></textarea>
              <div class="rejection-actions">
                <button id="submitRejectBtn" class="reject-btn" onclick="submitDecision('reject')">Submit Rejection</button>
                <button class="cancel-btn" onclick="hideRejectForm()">Cancel</button>
              </div>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <?php if($kyc['status'] == 'approved'): ?>
        <section class="kyc-card status-message status-message-approved active">
          <div class="status-icon status-icon-approved"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg></div>
          <div class="status-text status-text-approved"><h3>KYC Approved</h3><p>This KYC verification has been approved.</p></div>
        </section>
        <?php endif; ?>

        <?php if($kyc['status'] == 'rejected'): ?>
        <section class="kyc-card status-message status-message-rejected active">
          <div class="status-icon status-icon-rejected"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg></div>
          <div class="status-text status-text-rejected">
            <h3>KYC Rejected</h3>
            <p>This KYC verification has been rejected.</p>
            <?php if(!empty($kyc['rejection_reason'])): ?>
              <p style="margin-top: 10px; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; border-left: 3px solid #ef4444;">
                <strong>Reason:</strong> <?php echo htmlspecialchars($kyc['rejection_reason']); ?>
              </p>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <button class="modal-close" onclick="closeImageModal()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
    <img id="modalImage" src="" alt="Zoomed Image">
  </div>

  <div id="toastContainer" class="toast-container"></div>

  <script>
    const kycId = <?php echo $kyc_id; ?>;

    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.add('active');
    }
    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('active');
    }
    function showRejectForm() {
        document.getElementById('actionButtons').style.display = 'none';
        document.getElementById('rejectionForm').classList.add('active');
    }
    function hideRejectForm() {
        document.getElementById('actionButtons').style.display = 'flex';
        document.getElementById('rejectionForm').classList.remove('active');
    }

    async function submitDecision(action) {
        let reason = '';
        if(action === 'reject') {
            reason = document.getElementById('rejectReason').value;
            if(!reason) { alert('Please enter a rejection reason'); return; }
        }

        if(!confirm("Are you sure you want to " + action + " this KYC?")) return;

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: kycId, action: action, reason: reason })
            });
            const data = await res.json();
            if(data.success) {
                alert("Success!");
                location.reload(); 
            } else {
                alert("Error: " + data.message);
            }
        } catch(e) {
            console.error(e);
            alert("Network error");
        }
    }
  </script>
</body>
</html>