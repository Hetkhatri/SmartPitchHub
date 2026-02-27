// KYC Status Page - Vanilla JavaScript

// SVG Icons
const icons = {
  clock:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  checkCircle:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  alertTriangle:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
  checkCircleLarge:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  clockLarge:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  alertTriangleLarge:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
  xCircle:
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
  fileText:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>',
  headphones:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>',
  arrowRight:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
  upload:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>',
  eye: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
  userCheck:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>',
  building:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
  landmark:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg>',
  presentation:
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg>',
  piggyBank:
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h3v2h4v-4c1-.5 1.7-1 2-2h2v-4h-2c0-1-.5-1.5-1-2z"/><path d="M2 9v1c0 1.1.9 2 2 2h1"/><path d="M16 11h.01"/></svg>',
  wallet:
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>',
  checkSmall:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
};

// Current status state
let currentStatus = "under_review";

// Status configurations
const statusConfig = {
  under_review: {
    label: "Under Review",
    icon: icons.clock,
    badgeClass: "status-badge-review",
  },
  approved: {
    label: "Approved",
    icon: icons.checkCircle,
    badgeClass: "status-badge-approved",
  },
  rejected: {
    label: "Action Required",
    icon: icons.alertTriangle,
    badgeClass: "status-badge-rejected",
  },
};

// Get step state based on current status
function getStepState(stepId, status) {
  if (status === "approved") {
    return "complete";
  }
  if (status === "rejected") {
    return stepId <= 3 ? "complete" : "pending";
  }
  // under_review
  if (stepId === 1) return "complete";
  if (stepId === 2) return "active";
  return "pending";
}

// Update timeline based on status
function updateTimeline(status) {
  const steps = document.querySelectorAll(".timeline-step");
  steps.forEach((step) => {
    const stepId = parseInt(step.dataset.step);
    const state = getStepState(stepId, status);
    const dot = step.querySelector(".timeline-dot");
    const label = step.querySelector(".timeline-label");

    // Remove existing state classes
    dot.classList.remove(
      "timeline-dot-pending",
      "timeline-dot-active",
      "timeline-dot-complete",
    );

    // Add new state class
    dot.classList.add(`timeline-dot-${state}`);

    // Update label styling
    if (state === "pending") {
      label.classList.add("timeline-label-pending");
    } else {
      label.classList.remove("timeline-label-pending");
    }
  });
}

// Update status badge
function updateStatusBadge(status) {
  const badge = document.getElementById("status-badge");
  const config = statusConfig[status];

  badge.className = `status-badge ${config.badgeClass}`;
  badge.innerHTML = `${config.icon}<span>${config.label}</span>`;
}

// Generate Under Review content
function getUnderReviewContent() {
  return `
    <div class="card-elevated status-card animate-fade-in">
      <div class="status-header">
        <div class="status-icon status-icon-warning">
          ${icons.clockLarge}
        </div>
        <div>
          <h3 class="status-title">Your KYC is Currently Under Review</h3>
          <p class="status-description">
            Our compliance team is carefully reviewing your submitted documents. 
            This process ensures a secure investment ecosystem for all users.
          </p>
        </div>
      </div>
      
      <div class="info-grid">
        <div class="info-item">
          <p>Submission Date</p>
          <p>January 10, 2026</p>
        </div>
        <div class="info-item">
          <p>Reference ID</p>
          <p class="mono">KYC-2026-78542</p>
        </div>
        <div class="info-item">
          <p>Estimated Completion</p>
          <p>January 12, 2026</p>
        </div>
      </div>
    </div>
    
    <div class="card-elevated status-card">
      <h4 class="section-title">Features Pending Verification</h4>
      <div class="features-grid">
        <div class="feature-card feature-disabled">
          ${icons.presentation}
          <span class="feature-label">Create Pitch</span>
        </div>
        <div class="feature-card feature-disabled">
          ${icons.piggyBank}
          <span class="feature-label">Receive Investments</span>
        </div>
        <div class="feature-card feature-disabled">
          ${icons.wallet}
          <span class="feature-label">Withdraw Funds</span>
        </div>
      </div>
    </div>
    
    <div class="button-group">
      <button class="btn btn-outline">
        ${icons.fileText}
        View Submitted KYC
      </button>
      <button class="btn btn-outline">
        ${icons.headphones}
        Contact Support
      </button>
    </div>
  `;
}

// Generate Approved content
function getApprovedContent() {
  return `
    <div class="card-elevated status-card border-success animate-fade-in">
      <div class="status-header">
        <div class="status-icon status-icon-success">
          ${icons.checkCircleLarge}
        </div>
        <div style="flex: 1;">
          <h3 class="status-title">KYC Verified Successfully</h3>
          <p class="status-description">
            Congratulations! Your identity and business have been verified. 
            You now have full access to all entrepreneur features.
          </p>
        </div>
      </div>
      
      <div class="verification-badges">
        <div class="verification-badge">
          ${icons.userCheck}
          <span>Identity Verified</span>
        </div>
        <div class="verification-badge">
          ${icons.building}
          <span>Business Verified</span>
        </div>
        <div class="verification-badge">
          ${icons.landmark}
          <span>Bank Verified</span>
        </div>
      </div>
      
      <div class="info-grid info-grid-4">
        <div class="info-item">
          <p>Verified Name</p>
          <p>Sarah Mitchell</p>
        </div>
        <div class="info-item">
          <p>Business Type</p>
          <p>Private Limited</p>
        </div>
        <div class="info-item">
          <p>Approval Date</p>
          <p>January 11, 2026</p>
        </div>
        <div class="info-item">
          <p>KYC ID</p>
          <p class="mono">VRF-2026-78542</p>
        </div>
      </div>
    </div>
    
    <div class="card-elevated status-card">
      <h4 class="section-title">Enabled Features</h4>
      <div class="features-grid">
        <div class="feature-card feature-enabled">
          ${icons.presentation}
          <span class="feature-label">Create Pitches</span>
          <span class="feature-check">${icons.checkSmall}</span>
        </div>
        <div class="feature-card feature-enabled">
          ${icons.piggyBank}
          <span class="feature-label">Receive Investments</span>
          <span class="feature-check">${icons.checkSmall}</span>
        </div>
        <div class="feature-card feature-enabled">
          ${icons.wallet}
          <span class="feature-label">Wallet Withdrawals</span>
          <span class="feature-check">${icons.checkSmall}</span>
        </div>
      </div>
    </div>
     <a href='../Pitches/createPitch.php'>
    <div class="button-group">
      <button class="btn btn-primary">
        Create Your First Pitch
        ${icons.arrowRight}
      </button>
    </div>
    </a>
    <br>
  `;
}

// Generate Rejected content
function getRejectedContent(rejectionData) {
  const reason =
    rejectionData?.reason ||
    "Multiple documents failed verification. Please ensure all documents are clear, valid, and match your registered business details.";

  return `
    <div class="card-elevated status-card border-destructive animate-fade-in">
      <div class="status-header">
        <div class="status-icon status-icon-error">
          ${icons.alertTriangleLarge}
        </div>
        <div>
          <h3 class="status-title">KYC Verification Incomplete</h3>
          <p class="status-description">
            We couldn't verify your documents due to the issues listed below. 
            Please review and resubmit the required documents.
          </p>
        </div>
      </div>
      
      <div class="rejection-box">
        <div class="rejection-label">
          ${icons.fileText}
          Admin Rejection Reason
        </div>
        <p class="rejection-text">
          ${reason}
        </p>
      </div>
    </div>
    
    <div class="card-elevated status-card">
      <h4 class="section-title">Instructions</h4>
      <div class="issues-list">
          <div class="issue-item">
            <span class="issue-icon">${icons.xCircle}</span>
            <div>
              <p class="issue-title">Required Action</p>
              <p class="issue-description">Click the "Resubmit KYC" button below to correct your information and upload clearer documents.</p>
            </div>
          </div>
      </div>
    </div>
    
    <div class="button-group">
      <a href="Enterpreneur-kyc.php" class="btn btn-primary" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
        ${icons.upload}
        Resubmit KYC
      </a>
      <button class="btn btn-outline" onclick="window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'})">
        ${icons.eye}
        View Submitted Data
      </button>
    </div>
  `;
}

// Update status content based on current status
function updateStatusContent(status, data) {
  const container = document.getElementById("status-content");

  switch (status) {
    case "under_review":
      container.innerHTML = getUnderReviewContent();
      break;
    case "approved":
      container.innerHTML = getApprovedContent();
      break;
    case "rejected":
      container.innerHTML = getRejectedContent(data.rejection);
      break;
  }
}

// Update all UI elements based on status
function updateUI(status, data) {
  updateStatusBadge(status);
  updateTimeline(status);
  updateStatusContent(status, data);
}

// Initialize demo button handlers
function initDemoButtons() {
  const buttons = document.querySelectorAll(".demo-btn");

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const newStatus = button.dataset.status;

      // Update active state
      buttons.forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");

      // Update current status and UI
      currentStatus = newStatus;
      updateUI(currentStatus, {
        rejection: { reason: "Demo rejection reason." },
      });
    });
  });
}

// Initialize on DOM content loaded
// ==========================================
// NEW: FETCH REAL DATA FROM BACKEND
// ==========================================
async function fetchKYCStatus() {
  try {
    // Call the PHP API we just created
    const response = await fetch("../KYC/get_kyc_status.php");
    const data = await response.json();

    if (data.status === "success") {
      // NEW: Redirect if not submitted
      if (data.has_submitted === false || data.kyc_status === 'not_submitted') {
        window.location.href = "Enterpreneur-kyc.php";
        return;
      }

      // 1. Update Global Status Variable
      currentStatus = data.kyc_status; // 'under_review', 'approved', or 'rejected'

      // 2. Populate Data Fields (Personal & Business)
      populateKYCData(data);

      // 3. Update the UI Visuals
      updateUI(currentStatus, data);

      // 4. Update Timestamp in Footer
      const footerDate = document.querySelector(".footer-item span");
      if (footerDate)
        footerDate.textContent = `Last updated: ${data.dates.updated}`;
    } else {
      console.error("API Error:", data.message);
      // Fallback to demo mode if API fails
      initDemoButtons();
    }
  } catch (error) {
    console.error("Fetch Error:", error);
  }
}

// Helper to fill in the HTML text with real database values
// ==========================================
// FILL HTML WITH DATABASE DATA
// ==========================================
function populateKYCData(data) {
  // 1. Personal Details
  setText("kyc-name", data.personal.full_name);
  setText("kyc-email", data.personal.email);
  setText("kyc-phone", data.personal.phone);
  setText("kyc-country", data.personal.nationality);

  // 2. Business Details
  setText("kyc-startup", data.business.name);
  setText("kyc-type", data.business.type);
  setText("kyc-reg", data.business.reg_number);
  setText("kyc-address", data.business.address);

  // 3. Bank Details (NEW)
  setText("bank-name", data.bank.name);
  setText("bank-holder", data.bank.holder);
  setText("bank-acc", data.bank.account);
  setText("bank-ifsc", data.bank.ifsc);

  // 4. Render Documents (NEW)
  const docsContainer = document.getElementById("kyc-docs-list");
  if (docsContainer && data.documents.length > 0) {
    docsContainer.innerHTML = ""; // Clear hardcoded items

    data.documents.forEach((doc) => {
      const docHTML = `
                <div class="document-item">
                  <div class="document-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                  </div>
                  <div class="document-info">
                    <p class="document-name">${doc.name}</p>
                    <p class="document-meta">${doc.type}</p>
                  </div>
                  <a href="${doc.path}" target="_blank" class="btn-icon" aria-label="View document">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  </a>
                </div>
            `;
      docsContainer.innerHTML += docHTML;
    });
  } else if (docsContainer) {
    docsContainer.innerHTML =
      '<p class="text-muted">No documents uploaded.</p>';
  }

  // 5. Rejection Reason
  if (currentStatus === "rejected" && data.rejection.reason) {
    setTimeout(() => {
      const reasonBox = document.querySelector(".rejection-text");
      if (reasonBox) reasonBox.textContent = data.rejection.reason;
    }, 100);
  }
}

// Helper function to safely set text
function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
} // Initialize on DOM content loaded
document.addEventListener("DOMContentLoaded", () => {
  // Hide the Demo Switcher in Production
  const demoSwitcher = document.querySelector(".demo-switcher");
  if (demoSwitcher) demoSwitcher.style.display = "none";

  // Load Real Data
  fetchKYCStatus();
});
