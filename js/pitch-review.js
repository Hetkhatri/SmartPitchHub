// Pitch Status Page - Vanilla JavaScript

const icons = {
  clock:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  checkCircle:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  alertTriangle:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
  clockLarge:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  checkCircleLarge:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  alertTriangleLarge:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
  fileText:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>',
  headphones:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>',
  arrowRight:
    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
  eye: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
};

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
  pending_warzone: {
    label: "Warzone Required",
    icon: icons.alertTriangle,
    badgeClass: "status-badge-review",
  },
};

function updateTimeline(status) {
  const steps = document.querySelectorAll(".timeline-step");
  steps.forEach((step) => {
    const stepId = parseInt(step.dataset.step);
    let state = "pending";
    if (status === "approved") state = "complete";
    else if (status === "pending_warzone") {
      if (stepId <= 3) state = "complete";
      else if (stepId === 4) state = "active";
    } else if (status === "under_review") {
      if (stepId === 1) state = "complete";
      else if (stepId === 2) state = "active";
    } else if (status === "rejected") {
      if (stepId <= 3) state = "complete";
    }

    const dot = step.querySelector(".timeline-dot");
    dot.classList.remove(
      "timeline-dot-pending",
      "timeline-dot-active",
      "timeline-dot-complete",
    );
    dot.classList.add(`timeline-dot-${state}`);
  });
}

function updateStatusBadge(status) {
  const badge = document.getElementById("status-badge");
  const config = statusConfig[status];
  badge.className = `status-badge ${config.badgeClass}`;
  badge.innerHTML = `${config.icon}<span>${config.label}</span>`;
}

function getContent(status, data) {
  if (status === "pending_warzone")
    return `
    <div class="card-elevated status-card animate-fade-in" style="border-left: 4px solid #f59e0b;">
      <div class="status-header">
        <div class="status-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">${icons.alertTriangleLarge}</div>
        <div style="flex: 1;">
          <h3 class="status-title">AI Warzone Audit Required</h3>
          <p class="status-description">Admin has approved your startup details! However, you must now survive the AI Warzone interrogation before the pitch goes live.</p>
        </div>
      </div>
      <div class="info-grid">
        <div class="info-item"><p>Pitch Status</p><p>Awaiting Audit</p></div>
        <div class="info-item"><p>Next Step</p><p>Complete interrogation</p></div>
      </div>
    </div>
    <div class="button-group">
      <a href="warzone.php?pitch_id=${data.pitch_id}" class="btn btn-primary">Start AI Warzone Audit now</a>
    </div>
  `;

  if (status === "approved")
    return `
    <div class="card-elevated status-card border-success animate-fade-in">
      <div class="status-header">
        <div class="status-icon status-icon-success">${icons.checkCircleLarge}</div>
        <div style="flex: 1;">
          <h3 class="status-title">Pitch Approved Successfully</h3>
          <p class="status-description">Congratulations! Your startup pitch has been approved and is now visible to active investors on the platform.</p>
        </div>
      </div>
      <div class="info-grid">
        <div class="info-item"><p>Approval Date</p><p>${data.dates.updated}</p></div>
        <div class="info-item"><p>Pitch ID</p><p class="mono">SPH-P-${String(data.pitch_details.startup_name.length).padStart(3, "0")}</p></div>
      </div>
    </div>
    <div class="button-group">
      <a href="../dashboards/Entrepreneur-dashboard.php" class="btn btn-primary">Go to Dashboard ${icons.arrowRight}</a>
    </div>
  `;

  if (status === "rejected")
    return `
    <div class="card-elevated status-card border-destructive animate-fade-in">
      <div class="status-header">
        <div class="status-icon status-icon-error">${icons.alertTriangleLarge}</div>
        <div>
          <h3 class="status-title">Action Required for Pitch</h3>
          <p class="status-description">Admin has requested some changes to your pitch before it can be approved.</p>
        </div>
      </div>
      <div class="rejection-box" style="margin-top: 1rem; background: rgba(239, 68, 68, 0.05); padding: 1.5rem; border-radius: 0.75rem; border: 1px solid rgba(239, 68, 68, 0.1);">
        <p style="color: #ef4444; font-weight: 600; margin-bottom: 0.5rem;">Feedback from Admin:</p>
        <p style="color: #6b7280;">${data.rejection.reason}</p>
      </div>
    </div>
    <div class="button-group">
      <button class="btn btn-primary" onclick="alert('Editing pitch will be available soon. Please contact admin for direct updates.')">Resubmit Pitch</button>
    </div>
  `;

  return `
    <div class="card-elevated status-card animate-fade-in">
      <div class="status-header">
        <div class="status-icon status-icon-warning">${icons.clockLarge}</div>
        <div>
          <h3 class="status-title">Your Pitch is Under Review</h3>
          <p class="status-description">Our team is reviewing your startup details, financial projections, and documents to ensure they meet our investor standards.</p>
        </div>
      </div>
      <div class="info-grid">
        <div class="info-item"><p>Submission Date</p><p>${data.dates.submitted}</p></div>
        <div class="info-item"><p>Estimated Completion</p><p>3-5 Business Days</p></div>
      </div>
    </div>
    <div class="button-group">
      <button class="btn btn-outline" onclick="location.reload()">Refresh Status</button>
      <button class="btn btn-outline">${icons.headphones} Contact Support</button>
    </div>
  `;
}

async function init() {
  try {
    const response = await fetch("fetch_pitch_status.php");
    const result = await response.json();

    if (result.status === "error") {
      document.body.innerHTML = `<div class="container"><p>${result.message}</p></div>`;
      return;
    }

    if (result.status === "no_pitch") {
      document.body.innerHTML = `<div class="container"><h3>No Pitch Found</h3><p>You haven't submitted a pitch yet.</p><a href="createPitch.php" class="btn btn-primary">Create Pitch</a></div>`;
      return;
    }

    const data = result;
    const currentStatus = data.pitch_status;

    updateStatusBadge(currentStatus);
    updateTimeline(currentStatus);
    document.getElementById("status-content").innerHTML = getContent(
      currentStatus,
      data,
    );

    // Update Summary
    document.getElementById("pitch-name").textContent =
      data.pitch_details.startup_name;
    document.getElementById("pitch-industry").textContent =
      data.pitch_details.industry;
    document.getElementById("pitch-stage").textContent =
      data.pitch_details.stage;
    document.getElementById("pitch-location").textContent =
      data.pitch_details.location;
    document.getElementById("pitch-goal").textContent =
      data.pitch_details.funding_goal;
    document.getElementById("pitch-valuation").textContent =
      data.pitch_details.valuation;
    document.getElementById("pitch-share-price").textContent =
      data.pitch_details.share_price;
    document.getElementById("pitch-shares").textContent =
      data.pitch_details.shares_issued;

    // Update Admin Notice Box
    const noticeBox = document.getElementById("admin-notice-box");
    if (currentStatus === "approved") {
      noticeBox.style.background = "rgba(16, 185, 129, 0.05)";
      noticeBox.style.border = "1px solid rgba(16, 185, 129, 0.3)";
      noticeBox.innerHTML = `
        <div style="display: flex; gap: 8px; align-items: flex-start;">
          <svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px; color: #10b981; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p style="font-size: 0.75rem; color: #059669; margin: 0; line-height: 1.4;">
            <strong>Verified:</strong> This share structure has been formally audited and approved by SmartPitchHub. These figures are now binding for your funding round.
          </p>
        </div>
      `;
    } else {
      noticeBox.style.background = "rgba(245, 158, 11, 0.05)";
      noticeBox.style.border = "1px dashed rgba(245, 158, 11, 0.3)";
      noticeBox.innerHTML = `
        <div style="display: flex; gap: 8px; align-items: flex-start;">
          <svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px; color: #f59e0b; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p style="font-size: 0.75rem; color: #d97706; margin: 0; line-height: 1.4;">
            <strong>Administrative Disclaimer:</strong> The share prices and quantities listed above are based on your submission. These figures are subject to verification and may be adjusted by the SmartPitchHub compliance team during review.
          </p>
        </div>
      `;
    }

    // Load Documents
    const docsList = document.getElementById("pitch-docs-list");
    if (data.documents.length === 0) {
      docsList.innerHTML = "<p>No documents uploaded.</p>";
    } else {
      docsList.innerHTML = data.documents
        .map(
          (doc) => `
            <div class="document-item">
              <div class="document-icon">${icons.fileText}</div>
              <div class="document-info">
                <p class="document-name">${doc.name}</p>
                <p class="document-meta">${doc.type}</p>
              </div>
              <a href="${doc.path}" target="_blank" class="btn-icon">
                ${icons.eye}
              </a>
            </div>
        `,
        )
        .join("");
    }

    document.getElementById("last-updated").textContent =
      `Last checked: ${new Date().toLocaleTimeString()}`;
  } catch (err) {
    console.error(err);
  }
}

document.addEventListener("DOMContentLoaded", init);
