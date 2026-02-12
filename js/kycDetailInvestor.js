// =============================================
// SmartPitchHub Admin KYC Approval Page
// JavaScript Functions (Full Version with Backend)
// =============================================

// Helper: Get KYC ID from URL (New Requirement for Backend)
function getKycIdFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

// Initialize Lucide Icons
document.addEventListener("DOMContentLoaded", function () {
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
});

// Navigation (Kept your original logic)
function goBack() {
  // Tries to go back in history, fallback to list page if history is empty
  if (window.history.length > 1) {
    window.history.back();
  } else {
    window.location.href = "kyc-requests.php";
  }
}

// View Document (Enhanced to actually open files)
function viewDocument(documentNameOrPath) {
  if (
    documentNameOrPath &&
    (documentNameOrPath.includes("/") || documentNameOrPath.includes("."))
  ) {
    // If it looks like a file path (from PHP), open it
    window.open(documentNameOrPath, "_blank");
  } else {
    // Original toast behavior for text-only names
    showToast("Opening Document", `Viewing ${documentNameOrPath}...`);
  }
}

// Approve KYC
function approveKYC() {
  const aiRecommendation = "approve"; // Future AI Integration point

  if (aiRecommendation === "reject") {
    // Show override warning
    document.getElementById("mainActions").style.display = "none";
    document.getElementById("overrideWarning").style.display = "flex";
    if (typeof lucide !== "undefined") lucide.createIcons();
  } else {
    confirmApprove();
  }
}

// Confirm Approve - UPDATED WITH BACKEND
function confirmApprove() {
  const kycId = getKycIdFromUrl();

  // Call Backend
  submitKYCDecision(kycId, "approved", "", function () {
    updateStatus("approved");
    showToast(
      "KYC Approved",
      "The investor's KYC has been successfully approved."
    );
    hideActionPanel();
  });
}

// Confirm Override - UPDATED WITH BACKEND
function confirmOverride() {
  const justification = document.querySelector(
    "#overrideWarning textarea"
  ).value;
  const kycId = getKycIdFromUrl();

  if (!justification.trim()) {
    alert("Please enter a justification for overriding the AI recommendation.");
    return;
  }

  // Call Backend
  submitKYCDecision(kycId, "approved", justification, function () {
    updateStatus("approved");
    showToast(
      "KYC Approved (Override)",
      "The KYC has been approved with AI override. Marked for Super Admin review."
    );
    hideActionPanel();
  });
}

// Cancel Override
function cancelOverride() {
  document.getElementById("overrideWarning").style.display = "none";
  document.getElementById("mainActions").style.display = "flex";
}

// Reject KYC
function rejectKYC() {
  document.getElementById("mainActions").style.display = "none";
  document.getElementById("rejectForm").style.display = "block"; // Changed to block for layout safety
  if (typeof lucide !== "undefined") lucide.createIcons();
}

// Confirm Reject - UPDATED WITH BACKEND
function confirmReject() {
  const reason = document.getElementById("rejectReason").value;
  // Note: allowResubmission logic would be handled by status ('rejected' implies resubmission allowed usually)
  const allowResubmission = document.getElementById("allowResubmission")
    ? document.getElementById("allowResubmission").checked
    : true;
  const kycId = getKycIdFromUrl();

  if (!reason.trim()) {
    alert("Please enter a rejection reason.");
    return;
  }

  // Call Backend
  submitKYCDecision(kycId, "rejected", reason, function () {
    updateStatus("rejected");
    const message = allowResubmission
      ? "The investor's KYC has been rejected. Re-submission allowed."
      : "The investor's KYC has been rejected. Re-submission not allowed.";
    showToast("KYC Rejected", message, true);
    hideActionPanel();
  });
}

// Cancel Reject
function cancelReject() {
  document.getElementById("rejectForm").style.display = "none";
  document.getElementById("mainActions").style.display = "flex";
  document.getElementById("rejectReason").value = "";
}

// Update Status Badge
function updateStatus(status) {
  const badge = document.getElementById("statusBadge");

  // Remove all status classes
  badge.classList.remove(
    "status-pending",
    "status-approved",
    "status-rejected",
    "status-under-review" // Added for consistency with PHP
  );

  // Add new status class and update text
  if (status === "approved") {
    badge.classList.add("status-approved");
    badge.innerHTML = '<span class="status-dot"></span>Approved';
  } else if (status === "rejected") {
    badge.classList.add("status-rejected");
    badge.innerHTML = '<span class="status-dot"></span>Rejected';
  } else {
    badge.classList.add("status-pending");
    badge.innerHTML = '<span class="status-dot"></span>Pending Review';
  }
}

// Hide Action Panel
function hideActionPanel() {
  const panel = document.getElementById("actionPanel");
  if (panel) {
    panel.innerHTML =
      '<h2 class="section-title">Admin Action Panel</h2><p style="color: var(--muted-foreground); padding:10px;">Decision has been recorded. No further actions available.</p>';
  }
}

// Show Toast Notification
function showToast(title, message, isError = false) {
  const toast = document.getElementById("toast");
  const toastTitle = document.getElementById("toastTitle");
  const toastMessage = document.getElementById("toastMessage");

  if (!toast) return; // Safety check

  toastTitle.textContent = title;
  toastMessage.textContent = message;

  if (isError) {
    toast.classList.add("toast-error");
  } else {
    toast.classList.remove("toast-error");
  }

  toast.classList.add("show");

  // Hide after 4 seconds
  setTimeout(() => {
    toast.classList.remove("show");
  }, 4000);
}

// Form Validation Helper (Restored from your original code)
function validateForm(formData) {
  const errors = [];
  if (!formData.reason || formData.reason.trim() === "") {
    errors.push("Rejection reason is required");
  }
  return errors;
}

// API Call Helper - UPDATED TO REAL PHP
async function submitKYCDecision(id, status, reason, onSuccess) {
  try {
    const formData = new FormData();
    formData.append("id", id);
    formData.append("status", status);
    formData.append("reason", reason);

    // Call the PHP file created earlier
    const response = await fetch("update_investor_kyc_status.php", {
      method: "POST",
      body: formData,
    });

    // Check if response is valid JSON
    const text = await response.text();
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error("Server response was not JSON:", text);
      throw new Error("Server error. Check console.");
    }

    if (result.status === "success") {
      if (onSuccess) onSuccess();
    } else {
      throw new Error(result.message || "Failed to submit decision");
    }
  } catch (error) {
    console.error("Error submitting KYC decision:", error);
    showToast("Error", error.message, true);
  }
}

// Keyboard Shortcuts
document.addEventListener("keydown", function (event) {
  // Escape key to cancel forms
  if (event.key === "Escape") {
    const overrideWarning = document.getElementById("overrideWarning");
    const rejectForm = document.getElementById("rejectForm");

    if (overrideWarning && overrideWarning.style.display !== "none") {
      cancelOverride();
    }
    if (rejectForm && rejectForm.style.display !== "none") {
      cancelReject();
    }
  }
});
