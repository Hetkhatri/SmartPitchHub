/* ==========================================================================
   SMART PITCH HUB - CREATE PITCH LOGIC
   ========================================================================== */

// ===== Theme Toggle =====
const themeToggle = document.getElementById("themeToggle");
const body = document.body;

// Check for saved theme preference
const savedTheme = localStorage.getItem("theme") || "dark";
body.classList.add(savedTheme);

if (themeToggle) {
  themeToggle.addEventListener("click", () => {
    if (body.classList.contains("dark")) {
      body.classList.remove("dark");
      body.classList.add("light");
      localStorage.setItem("theme", "light");
    } else {
      body.classList.remove("light");
      body.classList.add("dark");
      localStorage.setItem("theme", "dark");
    }
  });
}

// ===== Form Elements =====
const form = document.getElementById("pitchForm");
const startupName = document.getElementById("startupName");
const category = document.getElementById("category");
const stage = document.getElementById("stage");
const foundedYear = document.getElementById("foundedYear");
const location = document.getElementById("location");
const shortPitch = document.getElementById("shortPitch");
const shortPitchBar = document.getElementById("shortPitchBar");
const shortPitchCount = document.getElementById("shortPitchCount");

// Detailed Fields
const problem = document.getElementById("problem");
const solution = document.getElementById("solution");
const valueProposition = document.getElementById("valueProposition");
const targetMarket = document.getElementById("targetMarket");
const revenueModel = document.getElementById("revenueModel");
const competitors = document.getElementById("competitors");
const traction = document.getElementById("traction");

// Financials
const fundingRequired = document.getElementById("fundingRequired");
const valuation = document.getElementById("valuation");

// Display Elements
const equityDisplay = document.getElementById("equityDisplay");
const platformFeeDisplay = document.getElementById("platformFeeDisplay");
const btnFee = document.getElementById("btnFee");
const submitBtn = document.getElementById("submitBtn");

// Summary Elements
const summaryName = document.getElementById("summaryName");
const summaryCategory = document.getElementById("summaryCategory");
const summaryStage = document.getElementById("summaryStage");
const summaryLocation = document.getElementById("summaryLocation");
const summaryFunding = document.getElementById("summaryFunding");
const summaryValuation = document.getElementById("summaryValuation");
const summaryFee = document.getElementById("summaryFee");

// ===== Character Counter for Short Pitch =====
if (shortPitch) {
  shortPitch.addEventListener("input", () => {
    const length = shortPitch.value.length;
    const percentage = (length / 150) * 100;

    if (shortPitchCount) shortPitchCount.textContent = `${length}/150`;
    if (shortPitchBar) {
      shortPitchBar.style.width = `${percentage}%`;
      // Change color when approaching limit
      if (length > 120) {
        shortPitchBar.style.background = "var(--warning)";
      } else {
        shortPitchBar.style.background = "var(--gradient-primary)";
      }
    }
    updateSummary();
  });
}

// ===== Platform Fee Calculation =====
function calculatePlatformFee(fundingAmount) {
  // Formula: Funding Goal * 2%
  return fundingAmount * 0.02;
}

// ===== Format Currency =====
function formatCurrency(amount) {
  return "₹" + amount.toLocaleString("en-IN");
}

// ===== Update Calculated Values =====
function updateCalculations() {
  // console.log("Updating calculations...");

  const fundingNum = parseFloat(fundingRequired.value) || 0;
  const valuationNum = parseFloat(valuation.value) || 0;
  const totalShares = 50000; // Total fixed shares for entrepreneur

  // 1. Calculate Share Price: Valuation / Total Shares
  let sharePrice = 0;
  if (valuationNum > 0) {
    sharePrice = valuationNum / totalShares;
  }
  const sharePriceDisplay = document.getElementById("sharePriceDisplay");
  if (sharePriceDisplay)
    sharePriceDisplay.textContent = formatCurrency(sharePrice);

  // 2. Calculate Shares to Issue: Funding / Share Price
  let sharesToIssue = 0;
  if (sharePrice > 0 && fundingNum > 0) {
    sharesToIssue = Math.floor(fundingNum / sharePrice);
  }
  const sharesIssuedDisplay = document.getElementById("sharesIssuedDisplay");
  if (sharesIssuedDisplay)
    sharesIssuedDisplay.textContent = sharesToIssue.toLocaleString();

  // 3. Calculate Equity: (Funding / Valuation) * 100
  let equity = "0.00";
  if (valuationNum > 0 && fundingNum > 0) {
    equity = ((fundingNum / valuationNum) * 100).toFixed(2);
  }

  if (equityDisplay) equityDisplay.textContent = equity + "%";

  // 4. Calculate Platform Fee: Funding * 2%
  const fee = calculatePlatformFee(fundingNum);
  const formattedFee = formatCurrency(fee);

  // Update Fee Displays
  if (platformFeeDisplay) platformFeeDisplay.textContent = formattedFee;
  if (summaryFee) summaryFee.textContent = formattedFee;
  if (btnFee) btnFee.textContent = formattedFee;

  // Update Summary Numbers
  if (summaryFunding) summaryFunding.textContent = formatCurrency(fundingNum);
  if (summaryValuation)
    summaryValuation.textContent = formatCurrency(valuationNum);
}

// ===== Update Summary Text =====
function updateSummary() {
  if (summaryName) summaryName.textContent = startupName.value || "—";
  if (summaryCategory) summaryCategory.textContent = category.value || "—";
  if (summaryStage) summaryStage.textContent = stage.value || "—";
  if (summaryLocation) summaryLocation.textContent = location.value || "—";
}

// ===== Event Listeners for Calculations =====
if (fundingRequired) {
  fundingRequired.addEventListener("input", updateCalculations);
  fundingRequired.addEventListener("change", updateCalculations);
  fundingRequired.addEventListener("keyup", updateCalculations);
}

if (valuation) {
  valuation.addEventListener("input", updateCalculations);
  valuation.addEventListener("change", updateCalculations);
  valuation.addEventListener("keyup", updateCalculations);
}

// ===== Event Listeners for Summary =====
if (startupName) startupName.addEventListener("input", updateSummary);
if (category) category.addEventListener("change", updateSummary);
if (stage) stage.addEventListener("change", updateSummary);
if (location) location.addEventListener("input", updateSummary);

// ===== Payment Method Selection =====
const paymentOptions = document.querySelectorAll(".payment-option input");
let selectedPaymentMethod = null;

paymentOptions.forEach((option) => {
  option.addEventListener("change", (e) => {
    selectedPaymentMethod = e.target.value;
    validateForm();
  });
});

// ===== File Upload Handling =====
const uploadCards = document.querySelectorAll(".upload-card");
const uploadedFiles = {};

uploadCards.forEach((card) => {
  const input = card.querySelector('input[type="file"]');
  const field = card.dataset.field;

  if (input) {
    input.addEventListener("change", (e) => {
      const file = e.target.files[0];
      if (file) {
        uploadedFiles[field] = file;
        card.classList.add("uploaded");

        // Update UI
        const title = card.querySelector(".upload-title");
        const hint = card.querySelector(".upload-hint");
        const browse = card.querySelector(".upload-browse");
        const icon = card.querySelector(".upload-icon");

        if (title) title.textContent = file.name;
        if (hint) hint.textContent = `${(file.size / 1024).toFixed(1)} KB`;
        if (browse) browse.style.display = "none";

        // Change icon to checkmark
        if (icon) {
          icon.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    `;
          icon.style.background = "rgba(34, 197, 94, 0.2)";
          icon.style.color = "var(--success)";
        }

        // Dynamic Update
        validateForm(false);
      }
    });
  }
});

// ===== Form Validation =====
function validateForm(showErrors = false) {
  let errors = [];
  const fields = [
    { el: startupName, name: "Startup Name" },
    { el: category, name: "Category" },
    { el: stage, name: "Startup Stage" },
    { el: foundedYear, name: "Founded Year" },
    { el: location, name: "Location" },
    { el: shortPitch, name: "Short Pitch" },
    { el: problem, name: "Problem Statement" },
    { el: solution, name: "Solution Description" },
    { el: valueProposition, name: "Unique Value Proposition" },
    { el: targetMarket, name: "Target Market" },
    { el: revenueModel, name: "Revenue Model" },
    { el: traction, name: "Current Traction" },
    { el: fundingRequired, name: "Funding Required" },
    { el: valuation, name: "Startup Valuation" },
  ];

  // Reset visual errors
  document
    .querySelectorAll(".form-field")
    .forEach((f) => f.classList.remove("has-error"));

  // Check required text/select fields
  fields.forEach((field) => {
    if (!field.el || field.el.value.trim() === "") {
      errors.push(`${field.name} is required.`);
      if (showErrors && field.el) {
        field.el.closest(".form-field")?.classList.add("has-error");
      }
    }
  });

  // Check Numbers
  if (foundedYear && (foundedYear.value < 1900 || foundedYear.value > 2026)) {
    errors.push("Please enter a valid Founded Year (1900-2026).");
  }
  if (fundingRequired && parseFloat(fundingRequired.value) <= 0) {
    errors.push("Funding Required must be greater than 0.");
  }
  if (valuation && parseFloat(valuation.value) <= 0) {
    errors.push("Startup Valuation must be greater than 0.");
  }

  // Check for Valuation Verification Requirement
  const valuationRequestId = document.getElementById("valuation_request_id");
  if (!valuationRequestId || !valuationRequestId.value) {
    errors.push(
      "Please verify your valuation by clicking the verification widget.",
    );
    if (showErrors) {
      document
        .getElementById("valuation_status_area")
        ?.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  // Check for Required Files (Professional Standard: All 4 are mandatory)
  if (!uploadedFiles["logo"]) errors.push("Startup Logo is required.");
  if (!uploadedFiles["pitchDeck"]) errors.push("Pitch Deck (PDF) is required.");
  if (!uploadedFiles["businessPlan"]) errors.push("Business Plan is required.");
  if (!uploadedFiles["financials"])
    errors.push("Financial Projections are required.");

  // Check Payment Method
  if (!selectedPaymentMethod) {
    errors.push("Please select a payment method.");
  }

  const isValid = errors.length === 0;

  // Update stepper dynamically
  updateStepper();

  if (showErrors && !isValid) {
    alert("Validation Errors:\n• " + errors.join("\n• "));
    // Scroll to first error
    const firstError = document.querySelector(".has-error");
    if (firstError) {
      firstError.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  return isValid;
}

// ===== Dynamic Stepper Update =====
function updateStepper() {
  const steps = document.querySelectorAll(".progress-steps .step");
  if (steps.length < 6) return;

  // Step 1: Details
  const step1Valid =
    startupName.value &&
    category.value &&
    stage.value &&
    foundedYear.value &&
    location.value;
  steps[0].className = step1Valid ? "step completed" : "step active";

  // Step 2: Pitch
  const step2Valid =
    shortPitch.value &&
    problem.value &&
    solution.value &&
    valueProposition.value;
  steps[1].className = step2Valid
    ? "step completed"
    : step1Valid
      ? "step active"
      : "step";

  // Step 3: Business
  const step3Valid = targetMarket.value && revenueModel.value && traction.value;
  steps[2].className = step3Valid
    ? "step completed"
    : step2Valid
      ? "step active"
      : "step";

  // Step 4: Funding
  const valuationRequestId = document.getElementById("valuation_request_id");
  const step4Valid =
    fundingRequired.value &&
    valuation.value &&
    valuationRequestId &&
    valuationRequestId.value;
  steps[3].className = step4Valid
    ? "step completed"
    : step3Valid
      ? "step active"
      : "step";

  // Step 5: Documents
  const step5Valid =
    uploadedFiles["logo"] &&
    uploadedFiles["pitchDeck"] &&
    uploadedFiles["businessPlan"] &&
    uploadedFiles["financials"];
  steps[4].className = step5Valid
    ? "step completed"
    : step4Valid
      ? "step active"
      : "step";

  // Step 6: Review
  const step6Valid = selectedPaymentMethod !== null;
  steps[5].className = step6Valid && step5Valid ? "step active" : "step";
}

// Add validation listeners to all required fields
const fieldsToWatch = [
  startupName,
  category,
  stage,
  foundedYear,
  location,
  shortPitch,
  problem,
  solution,
  valueProposition,
  targetMarket,
  revenueModel,
  traction,
  fundingRequired,
  valuation,
];

fieldsToWatch.forEach((field) => {
  if (field) {
    field.addEventListener("input", () => validateForm(false));
    field.addEventListener("change", () => validateForm(false));
  }
});

// ===== Form Submission =====
if (form) {
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validateForm(true)) {
      return;
    }

    // Disable button
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
            <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <span>Processing...</span>
        `;

    // Create FormData
    const formData = new FormData();

    // Text Fields
    formData.append("startupName", startupName.value);
    formData.append("category", category.value);
    formData.append("stage", stage.value);
    formData.append("foundedYear", foundedYear.value);
    formData.append("location", location.value);
    formData.append("shortPitch", shortPitch.value);
    formData.append("problem", problem.value);
    formData.append("solution", solution.value);
    formData.append("valueProposition", valueProposition.value);
    formData.append("targetMarket", targetMarket.value);
    formData.append("revenueModel", revenueModel.value);
    formData.append("competitors", competitors.value);
    formData.append("traction", traction.value);
    formData.append("fundingRequired", fundingRequired.value);
    formData.append("valuation", valuation.value);
    formData.append("paymentMethod", selectedPaymentMethod);
    formData.append(
      "valuation_request_id",
      document.getElementById("valuation_request_id")?.value || "",
    );

    // Files
    for (const [key, file] of Object.entries(uploadedFiles)) {
      formData.append(key, file);
    }

    try {
      const response = await fetch("submit-pitch.php", {
        method: "POST",
        body: formData,
      });

      const responseText = await response.text();
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (e) {
        console.error("Server Error:", responseText);
        alert("Server Error: Unable to process response.");
        resetButton();
        return;
      }

      if (result.success) {
        alert("Success: " + result.message);
        // Redirect after short delay
        setTimeout(() => {
          window.location.href =
            result.redirect || "../dashboards/Entrepreneur-dashboard.php";
        }, 1500);
      } else {
        alert("Error: " + result.message);
        resetButton();
      }
    } catch (error) {
      console.error("Error:", error);
      alert("Network error. Please try again.");
      resetButton();
    }

    function resetButton() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
    }
  });
}

// ===== Initialize =====
updateCalculations();
updateSummary();
validateForm();

// ===== Console Welcome =====
console.log(
  "%c SmartPitchHub Loaded ",
  "background: #a78bfa; color: white; padding: 4px; border-radius: 4px;",
);

/* ==========================================================================
   VALUATION VERIFICATION LOGIC (Fixed Global Scope for onclick)
   ========================================================================== */

// 1. MAKE FUNCTIONS GLOBAL (Fixes "ReferenceError" in HTML onclick)

window.confirmValuation = function () {
  // Get Elements
  var valInput = document.getElementById("valuation");
  var confirmBtn = document.getElementById("btn-confirm-val");
  var verifyArea = document.getElementById("valuation_status_area");
  var inputGroup = document.getElementById("valuation-group");

  // Validation
  if (!valInput.value || valInput.value <= 0) {
    alert("Please enter a valuation amount.");
    valInput.focus();
    return;
  }

  // 1. Lock the Input (Visual feedback)
  valInput.readOnly = true;
  inputGroup.style.background = "#f1f5f9"; // Entire group turns grey
  inputGroup.style.borderColor = "#e2e8f0";

  // 2. Change Button to "Locked" state
  confirmBtn.innerHTML = "✓";
  confirmBtn.style.background = "#10B981"; // Green background
  confirmBtn.style.color = "white";
  confirmBtn.style.cursor = "default";
  confirmBtn.disabled = true; // Prevent clicking again

  // 3. Reveal the Verify Widget
  verifyArea.style.display = "block";
};

window.openValuationWindow = function () {
  const width = 1000;
  const height = 900;
  const left = (window.screen.width - width) / 2;
  const top = (window.screen.height - height) / 2;

  const popup = window.open(
    "valuationVerify.php",
    "ValuationWindow",
    `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes,toolbar=no,menubar=no`,
  );

  if (!popup || popup.closed || typeof popup.closed == "undefined") {
    alert("⚠️ Pop-up Blocked! Please allow pop-ups to verify valuation.");
  }
};

// 2. LISTEN FOR SUCCESS MESSAGE (From Popup)
window.addEventListener(
  "message",
  (event) => {
    if (event.data.type === "VALUATION_COMPLETED") {
      console.log("Valuation Verified! ID:", event.data.id);

      const statusArea = document.getElementById("valuation_status_area");
      const hiddenInput = document.getElementById("valuation_request_id");

      // Update hidden input if it exists
      if (hiddenInput) {
        hiddenInput.value = event.data.id;
      }

      if (statusArea) {
        statusArea.innerHTML = `
                <div class="verify-widget success-state">
                    <div class="widget-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <div class="widget-text">
                        <h4 style="color: #065f46;">Valuation Verified</h4>
                        <p style="color: #059669;">Request ID: <strong>#${event.data.id}</strong> • Pending Approval</p>
                    </div>
                    <input type="hidden" name="valuation_request_id" value="${event.data.id}">
                </div>
            `;
      }
    }
  },
  false,
);

// ===== AI Pitch Architect Logic =====
window.generateAIDraft = async function (fieldId) {
  const btn =
    event.currentTarget ||
    document.querySelector(`button[onclick*="${fieldId}"]`);
  const inputField = document.getElementById(fieldId);
  if (!inputField) return;

  // Get current context
  const sName =
    document.getElementById("startupName")?.value.trim() || "Your Startup";
  const sCat = document.getElementById("category")?.value || "Technology";

  // Show loading state
  const originalHTML = btn.innerHTML;
  btn.classList.add("loading");
  btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path></svg> <i>Drafting...</i>`;

  try {
    const response = await fetch("api_ai_draft.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        field: fieldId,
        name: sName,
        category: sCat,
      }),
    });

    const data = await response.json();

    if (data.success) {
      // Apply the draft
      inputField.value = data.draft;

      // Trigger events for character counters and validation
      inputField.dispatchEvent(new Event("input"));
      inputField.dispatchEvent(new Event("change"));

      // Add a visual "glow" effect to the field
      inputField.classList.add("ai-pulse-glow");
      setTimeout(() => inputField.classList.remove("ai-pulse-glow"), 2000);

      // Scroll to the field if needed
      inputField.focus();
    } else {
      console.error("AI Error:", data.error);
      // Fallback for demo if Python fails
      if (
        data.error.includes("execute AI script") ||
        data.error.includes("offline")
      ) {
        const fallbackTemplates = {
          shortPitch: `Building the future of ${sCat} with ${sName}.`,
          problem: `Traditional ${sCat} methods are inefficient and costly.`,
          solution: `${sName} provides a streamlined ${sCat} workflow.`,
          valueProposition: `We offer 10x better ${sCat} results at lower cost.`,
          targetMarket: `Enterprises and professionals in the ${sCat} space.`,
          revenueModel: `SaaS subscription with tiered pricing.`,
          traction: `Over 1,000 users active in the last 30 days.`,
        };
        inputField.value =
          fallbackTemplates[fieldId] || "Amazing draft pending...";
        inputField.dispatchEvent(new Event("input"));
      }
    }
  } catch (err) {
    console.error("Fetch Error:", err);
  } finally {
    btn.classList.remove("loading");
    btn.innerHTML = originalHTML;
  }
};

// ===== AI Valuation Advisor Logic =====
window.reviewValuation = async function () {
  const btn = event.currentTarget;
  const valInput = document.getElementById("valuation");
  const stageInput = document.getElementById("stage");
  const categoryInput = document.getElementById("category");
  const feedbackArea = document.getElementById("ai-valuation-feedback");
  const valMessage = document.getElementById("ai-val-message");
  const valScore = document.getElementById("ai-val-score");

  if (!valInput.value || valInput.value <= 0) {
    alert("Please enter a valuation first.");
    return;
  }

  // Show loading state
  const originalHTML = btn.innerHTML;
  btn.classList.add("loading");
  btn.innerHTML = "Analyzing...";

  try {
    const response = await fetch("api_valuation_advice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        industry: categoryInput.value || "Technology",
        stage: stageInput.value || "Seed",
        valuationAsk: parseFloat(valInput.value),
        ttmRevenue: 0, // Placeholder
        growthRate: 20, // Placeholder
      }),
    });

    const result = await response.json();

    if (result.status === "success" && result.data) {
      const advice = result.data;
      feedbackArea.style.display = "block";
      valScore.innerText = advice.score + "% Confidence";
      valMessage.innerText = advice.message;

      // Color based on confidence/status
      if (
        advice.message.includes("Fair") ||
        advice.message.includes("aligns")
      ) {
        valScore.style.color = "var(--success)";
      } else if (advice.message.includes("Aggressive")) {
        valScore.style.color = "var(--warning)";
      } else {
        valScore.style.color = "var(--primary)";
      }

      feedbackArea.scrollIntoView({ behavior: "smooth", block: "nearest" });
    } else {
      alert(result.message || "AI Advisor is currently unavailable.");
    }
  } catch (err) {
    console.error("Advisor Error:", err);
    alert("Connection to AI Advisor failed.");
  } finally {
    btn.classList.remove("loading");
    btn.innerHTML = originalHTML;
  }
};
