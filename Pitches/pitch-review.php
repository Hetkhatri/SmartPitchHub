<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Startup Pitch Review Status | SmartPitchHub</title>
  <meta name="description" content="View your startup pitch approval status">
  <link rel="stylesheet" href="../css/kyc-review.css">
  <style>
    /* Specific overrides for pitch review if needed */
    .summary-section {
        margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="../dashboards/Entrepreneur-dashboard.php" class="back-nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5"></path>
            <path d="M12 19l-7-7 7-7"></path>
        </svg>
        Back to Dashboard
    </a>

    <!-- Page Header -->
    <header class="page-header animate-fade-in">
      <div class="header-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
        </svg>
      </div>
      <h1 class="page-title">Startup Pitch Review Status</h1>
      <p class="page-subtitle">Every pitch is reviewed to ensure high quality and compliance for our investors</p>
      <div id="status-badge" class="status-badge status-badge-review">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Under Review</span>
      </div>
    </header>

    <!-- Progress Timeline -->
    <div class="card-elevated timeline-card">
      <h3 class="section-title">Approval Progress</h3>
      <div class="timeline">
        <div class="timeline-line"></div>
        <div class="timeline-steps">
          <div class="timeline-step" data-step="1">
            <div class="timeline-dot timeline-dot-complete">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
            </div>
            <span class="timeline-label">Pitch Submitted</span>
          </div>
          <div class="timeline-step" data-step="2">
            <div class="timeline-dot timeline-dot-active">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <span class="timeline-label">Initial Screening</span>
          </div>
          <div class="timeline-step" data-step="3">
            <div class="timeline-dot timeline-dot-pending">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="timeline-label">Quality Check</span>
          </div>
          <div class="timeline-step" data-step="4">
            <div class="timeline-dot timeline-dot-pending">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
            </div>
            <span class="timeline-label">Final Approval</span>
          </div>
        </div>
      </div>
      <p class="timeline-note">Pitches are typically reviewed within 3-5 business days</p>
    </div>

    <!-- Status Content Container -->
    <div id="status-content">
      <!-- Content is dynamically inserted here -->
    </div>

    <!-- Pitch Summary -->
    <div class="card-elevated summary-card animate-slide-up">
      <h3 class="section-title">Submitted Pitch Summary</h3>
      
      <div class="summary-sections">
        <!-- Basic Info -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
            <span>Startup Details</span>
          </div>
          <div class="summary-content">
            <div class="summary-field">
              <span class="summary-label">Startup Name</span>
              <span class="summary-value" id="pitch-name">Loading...</span> 
            </div>
            <div class="summary-field">
              <span class="summary-label">Industry</span>
              <span class="summary-value" id="pitch-industry">Loading...</span>
            </div>
            <div class="summary-field">
              <span class="summary-label">Current Stage</span>
              <span class="summary-value" id="pitch-stage">Loading...</span>
            </div>
            <div class="summary-field">
              <span class="summary-label">Location</span>
              <span class="summary-value" id="pitch-location">Loading...</span>
            </div>
          </div>
        </div>

        <!-- Funding Details -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span>Funding Details</span>
          </div>
          <div class="summary-content">
            <div class="summary-field">
              <span class="summary-label">Funding Goal</span>
              <span class="summary-value" id="pitch-goal">Loading...</span>
            </div>
            <div class="summary-field">
              <span class="summary-label">Estimated Valuation</span>
              <span class="summary-value" id="pitch-valuation">Loading...</span>
            </div>
            <div class="summary-field">
              <span class="summary-label">Share Price</span>
              <span class="summary-value mono" id="pitch-share-price">Loading...</span>
            </div>
            <div class="summary-field">
              <span class="summary-label">Total Shares Issued</span>
              <span class="summary-value mono" id="pitch-shares">Loading...</span>
            </div>
          </div>
          
          <!-- Admin Notice -->
          <div id="admin-notice-box" style="margin-top: 15px; padding: 12px; border-radius: 8px;">
            <!-- Content dynamic via JS -->
          </div>
        </div>

        <!-- Documents -->
        <div class="summary-section">
          <div class="summary-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
            <span>Pitch Documents</span>
          </div>
          <div class="documents-grid" id="pitch-docs-list">
            <!-- Dynamically loaded -->
          </div>
        </div>
      </div>
    </div>

    <!-- Security Footer -->
    <footer class="security-footer animate-fade-in">
      <div class="footer-left">
        <div class="footer-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Your startup data is commercially confidential</span>
        </div>
        <div class="footer-item">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
          <span>Admin verification in progress</span>
        </div>
      </div>
      <div class="footer-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span id="last-updated">Last checked: Just now</span>
      </div>
    </footer>
  </div>

  <script src="../js/pitch-review.js"></script>
</body>
</html>
