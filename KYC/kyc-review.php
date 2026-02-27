<?php
ob_start();
session_start();
require_once '../db.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Fetch KYC details
$kyc_data = null;
$sql = "SELECT * FROM entrepreneur_kyc_details WHERE entrepreneur_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $kyc_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 3. If no record exists, they haven't submitted yet - redirect to the KYC form
if (!$kyc_data || empty($kyc_data['submission_date'])) {
    header("Location: Enterpreneur-kyc.php");
    ob_end_clean();
    exit;
}

$status = $kyc_data['status']; // 'under_review', 'approved', 'rejected'
$rejection_reason = $kyc_data['rejection_reason'] ?? '';
$submission_date = date("F j, Y at g:i A", strtotime($kyc_data['submission_date']));
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrepreneur KYC Verification Status</title>
  <meta name="description" content="View your KYC verification status for the fintech investment platform">
  <link rel="stylesheet" href="../css/kyc-review.css">
</head>
<body>
  <div class="container">
    <a href="../dashboards/entrepreneur-dashboard.php" class="back-nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5"></path>
            <path d="M12 19l-7-7 7-7"></path>
        </svg>
        Back to Dashboard
    </a>    <!-- Demo State Switcher -->
    <div class="demo-switcher">
      <p class="demo-label">Demo: Switch KYC Status</p>
      <div class="demo-buttons">
        <button class="demo-btn active" data-status="under_review">Under Review</button>
        <button class="demo-btn" data-status="approved">Approved</button>
        <button class="demo-btn" data-status="rejected">Rejected</button>
      </div>
    </div>

    <!-- Page Header -->
    <header class="page-header animate-fade-in">
      <div class="header-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
      </div>
      <h1 class="page-title">Entrepreneur KYC Verification Status</h1>
      <p class="page-subtitle">We verify all entrepreneurs to maintain a secure investment ecosystem</p>
      <div id="status-badge" class="status-badge status-badge-review">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Under Review</span>
      </div>
    </header>

    <!-- Progress Timeline -->
    <div class="card-elevated timeline-card">
      <h3 class="section-title">Verification Progress</h3>
      <div class="timeline">
        <div class="timeline-line"></div>
        <div class="timeline-steps">
          <div class="timeline-step" data-step="1">
            <div class="timeline-dot timeline-dot-complete">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
            </div>
            <span class="timeline-label">KYC Submitted</span>
          </div>
          <div class="timeline-step" data-step="2">
            <div class="timeline-dot timeline-dot-active">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <span class="timeline-label">Document Verification</span>
          </div>
          <div class="timeline-step" data-step="3">
            <div class="timeline-dot timeline-dot-pending">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
            </div>
            <span class="timeline-label">Compliance Review</span>
          </div>
          <div class="timeline-step" data-step="4">
            <div class="timeline-dot timeline-dot-pending">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="timeline-label">Final Decision</span>
          </div>
        </div>
      </div>
      <p class="timeline-note">Verification usually completes within 24–48 hours</p>
    </div>

    <!-- Status Content Container -->
    <div id="status-content">
      <!-- Content is dynamically inserted here -->
    </div>

    <!-- KYC Summary -->
    <div class="card-elevated summary-card animate-slide-up">
      <h3 class="section-title">Submitted KYC Summary</h3>
      
      <div class="summary-sections">
        <!-- Personal Details -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Personal Details</span>
          </div>
         <div class="summary-content">
  <div class="summary-field">
    <span class="summary-label">Full Name</span>
    <span class="summary-value" id="kyc-name">Loading...</span> 
  </div>
  <div class="summary-field">
    <span class="summary-label">Email Address</span>
    <span class="summary-value" id="kyc-email">Loading...</span>
  </div>
  <div class="summary-field">
    <span class="summary-label">Phone Number</span>
    <span class="summary-value" id="kyc-phone">Loading...</span>
  </div>
  <div class="summary-field">
    <span class="summary-label">Nationality</span>
    <span class="summary-value" id="kyc-country">Loading...</span>
  </div>
</div>
        <!-- Business Details -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
            <span>Business Details</span>
          </div>
         <div class="summary-content">
          <div class="summary-field">
            <span class="summary-label">Business Name</span>
            <span class="summary-value" id="kyc-startup">Loading...</span>
          </div>
          <div class="summary-field">
            <span class="summary-label">Business Type</span>
            <span class="summary-value" id="kyc-type">Loading...</span>
          </div>
          <div class="summary-field">
            <span class="summary-label">Registration Number</span>
            <span class="summary-value" id="kyc-reg">Loading...</span>
          </div>
          <div class="summary-field">
            <span class="summary-label">Business Address</span>
            <span class="summary-value" id="kyc-address">Loading...</span>
          </div>
        </div>
        <!-- Uploaded Documents -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
            <span>Uploaded Documents</span>
          </div>
          <div class="documents-grid" id="kyc-docs-list">
            <div class="document-item">
              <div class="document-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              </div>
              <div class="document-info">
                <p class="document-name">Government ID (Passport)</p>
                <p class="document-meta">PDF • 1.2 MB</p>
              </div>
              <button class="btn-icon" aria-label="View document">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="document-item">
              <div class="document-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              </div>
              <div class="document-info">
                <p class="document-name">Business Registration Certificate</p>
                <p class="document-meta">PDF • 856 KB</p>
              </div>
              <button class="btn-icon" aria-label="View document">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="document-item">
              <div class="document-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              </div>
              <div class="document-info">
                <p class="document-name">Proof of Address</p>
                <p class="document-meta">Image • 2.1 MB</p>
              </div>
              <button class="btn-icon" aria-label="View document">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="document-item">
              <div class="document-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              </div>
              <div class="document-info">
                <p class="document-name">Tax ID Document</p>
                <p class="document-meta">PDF • 445 KB</p>
              </div>
              <button class="btn-icon" aria-label="View document">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Bank Details -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg>
            <span>Bank Details (Masked)</span>
          </div>
          <div class="summary-field">
      <span class="summary-label">Bank Name</span>
      <span class="summary-value" id="bank-name">Loading...</span>
    </div>

    <div class="summary-field">
      <span class="summary-label">Account Holder</span>
      <span class="summary-value" id="bank-holder">Loading...</span>
    </div>

    <div class="summary-field">
      <span class="summary-label">Account Number</span>
      <span class="summary-value mono" id="bank-acc">Loading...</span>
    </div>

    <div class="summary-field">
      <span class="summary-label">Routing/IFSC Number</span>
      <span class="summary-value mono" id="bank-ifsc">Loading...</span>
    </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Security Footer -->
    <footer class="security-footer animate-fade-in">
      <div class="footer-left">
        <div class="footer-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Your data is encrypted and securely stored</span>
        </div>
        <div class="footer-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
          <span>KYC verified as per regulatory standards</span>
        </div>
      </div>
      <div class="footer-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Last updated: January 12, 2026 at 10:45 AM</span>
      </div>
    </footer>
  </div>

  <script src="../js/kyc-review.js"></script>
</body>
</html>
