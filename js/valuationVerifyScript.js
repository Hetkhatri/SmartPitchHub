// ============================================\
// VALUATION BUILDER - JAVASCRIPT
// ============================================

document.addEventListener("DOMContentLoaded", function () {
  initTooltips();
  initFileUploads();
  initFormSubmission();
  initAiAnalysis();
  initProgressTracking();
  initCurrencyFormatting();
  initJustificationCounter();
});

function initJustificationCounter() {
  const textarea = document.getElementById("justificationTextarea");
  const counterEl = document.getElementById("justificationCharCount");

  if (!textarea || !counterEl) return;

  textarea.addEventListener("input", function () {
    const len = this.value.trim().length;
    counterEl.textContent = `${len} / 50`;

    if (len < 50) {
      counterEl.style.color = "hsla(0, 100%, 70%, 0.8)";
    } else {
      counterEl.style.color = "hsla(187, 80%, 50%, 0.8)";
    }
  });
}

// ============================================\
// SMART CURRENCY FORMATTING
// ============================================

function initCurrencyFormatting() {
  console.log("Initializing Currency Formatting...");

  const financialSelectors = [
    'input[name="ttmRevenue"]',
    'input[name="projectedRevenue"]',
    'input[name="cashBurn"]',
    'input[name="valuationAsk"]',
    'input[name="fundraiseTarget"]',
    'input[name="previousRaised"]',
  ];

  financialSelectors.forEach((selector) => {
    const el = document.querySelector(selector);
    if (!el) {
      console.warn("Currency formatting target not found:", selector);
      return;
    }

    const formatAndUpdate = function () {
      let cursor = this.selectionStart;
      let originalValue = this.value;

      // Clean non-digits
      let digits = originalValue.replace(/[^\d]/g, "");
      if (digits === "") {
        this.value = "";
        return;
      }

      let formatted = formatIndianNumber(digits);

      if (this.value !== formatted) {
        this.value = formatted;

        // Advanced cursor management
        // Count characters before cursor in original vs formatted
        let originalBefore = originalValue.substring(0, cursor);
        let digitsBefore = originalBefore.replace(/[^\d]/g, "").length;

        // Find new cursor position by counting digits in formatted string
        let newCursor = 0;
        let digitsCount = 0;
        while (digitsCount < digitsBefore && newCursor < formatted.length) {
          if (/\d/.test(formatted[newCursor])) {
            digitsCount++;
          }
          newCursor++;
        }

        // If we just typed a digit and a comma was inserted before it, move past the comma
        while (
          newCursor < formatted.length &&
          !/\d/.test(formatted[newCursor])
        ) {
          newCursor++;
        }

        this.setSelectionRange(newCursor, newCursor);
      }
    };

    el.addEventListener("input", formatAndUpdate);
    el.addEventListener("blur", formatAndUpdate); // Cleanup on blur

    // Initial format if value exists
    if (el.value) {
      el.value = formatIndianNumber(el.value.replace(/[^\d]/g, ""));
    }
  });
}

function formatIndianNumber(val) {
  if (!val) return "";
  val = val.toString().replace(/,/g, "");
  if (isNaN(val)) return val;

  let x = val;
  let lastThree = x.substring(x.length - 3);
  let otherNumbers = x.substring(0, x.length - 3);
  if (otherNumbers != "") lastThree = "," + lastThree;
  let res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
  return res;
}

// ============================================\
// PROGRESS TRACKING
// ============================================

function initProgressTracking() {
  const form = document.getElementById("valuationForm");
  if (!form) return;

  const steps = {
    1: ["companyName", "industry", "stage", "incorporationDate"],
    2: [
      "ttmRevenue",
      "projectedRevenue",
      "cashBurn",
      "activeUsers",
      "growthRate",
    ],
    3: ["marketSize", "teamStrength"],
    4: ["valuationAsk", "fundraiseTarget", "valuationBasis[]", "justification"],
    5: ["pitchDeck", "financialDocs"],
  };

  const updateProgress = () => {
    let focusSet = false;

    for (let i = 1; i <= 6; i++) {
      const stepEl = document.querySelector(`.progress-step[data-step="${i}"]`);
      if (!stepEl) continue;

      const lineEl = stepEl.previousElementSibling?.classList.contains(
        "progress-line",
      )
        ? stepEl.previousElementSibling
        : null;

      let isCompleted = false;
      let isActive = false;

      if (i <= 5) {
        const fields = steps[i];
        isCompleted = fields.every((fieldName) => {
          if (fieldName === "valuationBasis[]") {
            return (
              form.querySelectorAll('input[name="valuationBasis[]"]:checked')
                .length > 0
            );
          }
          const field = form.querySelector(`[name="${fieldName}"]`);
          if (!field) return false;

          if (field.type === "file") {
            return field.closest(".file-upload").classList.contains("has-file");
          }
          return field.value.trim() !== "";
        });
      } else if (i === 6) {
        // Step 6 is completed if 1-5 are completed
        isCompleted = [1, 2, 3, 4, 5].every((s) => {
          const el = document.querySelector(`.progress-step[data-step="${s}"]`);
          return el && el.classList.contains("completed");
        });
      }

      // Logic for "Active" - first non-completed step
      if (!isCompleted && !focusSet) {
        isActive = true;
        focusSet = true;
      }

      // Update UI
      if (isCompleted) {
        stepEl.classList.add("completed");
        stepEl.classList.remove("active");
        stepEl.querySelector(".step-circle").innerHTML =
          `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>`;
        if (lineEl) lineEl.classList.add("completed");
      } else if (isActive) {
        stepEl.classList.add("active");
        stepEl.classList.remove("completed");
        stepEl.querySelector(".step-circle").textContent = i;
        if (lineEl) lineEl.classList.remove("completed");
      } else {
        stepEl.classList.remove("completed", "active");
        stepEl.querySelector(".step-circle").textContent = i;
        if (lineEl) lineEl.classList.remove("completed");
      }
    }
  };

  // Initial update
  updateProgress();

  // Listen for changes
  form.addEventListener("input", updateProgress);
  form.addEventListener("change", updateProgress);

  // File uploads need special handling because they don't trigger 'input' on the form always in the way we expect with the custom UI
  const fileInputs = form.querySelectorAll('input[type="file"]');
  fileInputs.forEach((input) => {
    input.addEventListener("change", () => setTimeout(updateProgress, 100));
  });

  // Checkboxes
  const checkboxes = form.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach((cb) => {
    cb.addEventListener("change", updateProgress);
  });
}

// ============================================\
// TOOLTIP FUNCTIONALITY
// ============================================

function initTooltips() {
  const tooltipEl = document.getElementById("tooltip");
  const triggers = document.querySelectorAll(".tooltip-trigger");

  triggers.forEach((trigger) => {
    trigger.addEventListener("mouseenter", (e) => {
      const text = trigger.dataset.tooltip;
      if (!text) return;

      tooltipEl.textContent = text;
      tooltipEl.classList.add("visible");

      const rect = trigger.getBoundingClientRect();
      const tooltipRect = tooltipEl.getBoundingClientRect();

      let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
      let top = rect.bottom + 8;

      // Keep tooltip within viewport
      if (left < 10) left = 10;
      if (left + tooltipRect.width > window.innerWidth - 10) {
        left = window.innerWidth - tooltipRect.width - 10;
      }

      tooltipEl.style.left = left + "px";
      tooltipEl.style.top = top + "px";
    });

    trigger.addEventListener("mouseleave", () => {
      tooltipEl.classList.remove("visible");
    });
  });
}

// ============================================\
// FILE UPLOAD FUNCTIONALITY
// ============================================

function initFileUploads() {
  const fileUploads = document.querySelectorAll(".file-upload");

  fileUploads.forEach((upload) => {
    const input = upload.querySelector(".file-input");
    const content = upload.querySelector(".file-upload-content");
    const preview = upload.querySelector(".file-upload-preview");
    const fileName = upload.querySelector(".file-name");
    const fileSize = upload.querySelector(".file-size");
    const removeBtn = upload.querySelector(".file-remove");

    // Drag and drop
    upload.addEventListener("dragover", (e) => {
      e.preventDefault();
      upload.classList.add("dragover");
    });

    upload.addEventListener("dragleave", () => {
      upload.classList.remove("dragover");
    });

    upload.addEventListener("drop", (e) => {
      e.preventDefault();
      upload.classList.remove("dragover");

      const file = e.dataTransfer.files[0];
      if (file) {
        handleFileSelect(file, upload, content, preview, fileName, fileSize);
      }
    });

    // File input change
    input.addEventListener("change", () => {
      const file = input.files[0];
      if (file) {
        handleFileSelect(file, upload, content, preview, fileName, fileSize);
      }
    });

    // Remove file
    removeBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();

      input.value = "";
      upload.classList.remove("has-file");
      content.style.display = "flex";
      preview.style.display = "none";
    });
  });
}

function handleFileSelect(
  file,
  upload,
  content,
  preview,
  fileNameEl,
  fileSizeEl,
) {
  upload.classList.add("has-file");
  content.style.display = "none";
  preview.style.display = "flex";

  fileNameEl.textContent = file.name;
  fileSizeEl.textContent = formatFileSize(file.size);
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / (1024 * 1024)).toFixed(1) + " MB";
}

// ============================================\
// FORM SUBMISSION
// ============================================

// ============================================
// FORM SUBMISSION (CONNECTED TO BACKEND)
// ============================================

function initFormSubmission() {
  const form = document.getElementById("valuationForm");
  const submitBtn = form.querySelector('button[type="submit"]');

  // Inject CSS for the spinner animation
  if (!document.getElementById("spin-style")) {
    const style = document.createElement("style");
    style.id = "spin-style";
    style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } } .animate-spin { animation: spin 1s linear infinite; }`;
    document.head.appendChild(style);
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    // 0. FIELD VALIDATION
    if (!validateForm(form)) {
      return;
    }

    // 1. UI Feedback (Loading State)
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
            <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/>
        </svg>
        Verifying & Uploading...`;

    try {
      // 2. Prepare Data
      const formData = new FormData(form);

      // 3. Send to Backend
      const response = await fetch("submit_valuation.php", {
        method: "POST",
        body: formData,
      });

      const responseText = await response.text();
      let result;

      try {
        result = JSON.parse(responseText.trim());
      } catch (e) {
        console.error("Server Raw Response:", responseText);
        // If it's a 500 error, the body often contains the real PHP error message
        let bodySnippet = responseText
          .replace(/<[^>]*>?/gm, "")
          .substring(0, 300); // Strip HTML and take snippet
        throw new Error(
          `Server Error ${response.status}: ${bodySnippet || response.statusText}`,
        );
      }

      if (result.status === "success") {
        // 4. Show Success Modal with the New ID
        showSuccessMessage(result.id);
        form.reset();
      } else {
        // Handle PHP Error professionally
        const errorArea = document.createElement("div");
        errorArea.className = "error-message pulse-error";
        errorArea.style.cssText =
          "justify-content: center; padding: 15px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; margin-bottom: 10px;";
        errorArea.textContent = "Submission Failed: " + result.message;
        form.prepend(errorArea);

        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    } catch (error) {
      console.error("Full Error Info:", error);
      const errorArea = document.createElement("div");
      errorArea.className = "error-message pulse-error";
      errorArea.style.cssText =
        "justify-content: center; padding: 15px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; margin-bottom: 10px;";
      errorArea.textContent = "System Error: " + error.message;
      form.prepend(errorArea);

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  });
}

// ============================================
// SUCCESS MODAL (HANDLES POPUP CLOSING)
// ============================================

function showSuccessMessage(newId) {
  // Create success modal
  const modal = document.createElement("div");
  modal.style.cssText = `
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(10, 14, 26, 0.95);
    backdrop-filter: blur(10px);
    z-index: 1000;
    animation: fadeIn 0.3s ease-out;
  `;

  modal.innerHTML = `
    <div style="
      background: linear-gradient(135deg, hsl(224, 40%, 10%) 0%, hsl(224, 40%, 8%) 100%);
      border: 1px solid hsl(263, 70%, 58%);
      border-radius: 1.5rem;
      padding: 2.5rem;
      text-align: center;
      max-width: 400px;
      width: 90%;
      margin: 1rem;
      box-shadow: 0 0 40px hsla(263, 70%, 58%, 0.2);
    ">
      <div style="
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        background: linear-gradient(135deg, hsl(187, 80%, 50%), hsl(217, 91%, 60%));
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        box-shadow: 0 0 20px hsla(187, 80%, 50%, 0.4);
      ">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      
      <h3 style="
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: white;
        font-family: 'Inter', sans-serif;
      ">Valuation Verified</h3>
      
      <p style="
        color: hsl(220, 15%, 55%);
        font-size: 0.9375rem;
        margin-bottom: 0.5rem;
        line-height: 1.6;
      ">Request ID: <strong style="color:hsl(217, 91%, 60%)">#${newId}</strong></p>
      
      <p style="
        color: hsl(220, 15%, 50%);
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
      ">Your data has been securely locked and sent to the admin for final approval.</p>

      <button id="closeModal" style="
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 3rem;
        border-radius: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        background: linear-gradient(135deg, hsl(263, 70%, 58%), hsl(217, 91%, 60%));
        color: white;
        box-shadow: 0 4px 15px hsla(263, 70%, 58%, 0.4);
        transition: transform 0.2s ease;
      ">Return to Pitch Dashboard</button>
    </div>
  `;

  document.body.appendChild(modal);

  // LOGIC: Close this Popup and Notify Parent
  modal.querySelector("#closeModal").addEventListener("click", () => {
    // 1. Send Message to the Main Window (createPitch.php)
    if (window.opener && !window.opener.closed) {
      window.opener.postMessage(
        { type: "VALUATION_COMPLETED", id: newId },
        "*",
      );
    }

    // 2. Close this Popup Window
    window.close();
  });
}

/**
 * Professional Field Error UI
 */
function showFieldError(inputName, message) {
  const input = document.querySelector(`[name="${inputName}"]`);
  if (!input) return;

  // Add error classes
  input.classList.add("error-state");
  const parent = input.closest(".form-group") || input.parentElement;

  // Remove existing error if any
  const existingError = parent.querySelector(".error-message");
  if (existingError) existingError.remove();

  // Create error message element
  const errorEl = document.createElement("div");
  errorEl.className = "error-message";
  errorEl.innerHTML = `
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
    </svg>
    ${message}
  `;

  // Shake the section/group
  const section = input.closest(".glass-card");
  if (section) {
    section.classList.remove("shake");
    void section.offsetWidth; // trigger reflow
    section.classList.add("shake");
  }

  parent.appendChild(errorEl);

  // Auto-clear error when user starts typing
  const clearOnInput = () => {
    input.classList.remove("error-state");
    errorEl.remove();
    input.removeEventListener("input", clearOnInput);
  };
  input.addEventListener("input", clearOnInput);
}

/**
 * Validate essential fields before submission
 */
function validateForm(form) {
  // Clear all existing errors first
  document.querySelectorAll(".error-message").forEach((el) => el.remove());
  document
    .querySelectorAll(".error-state")
    .forEach((el) => el.classList.remove("error-state"));

  const requiredFields = [
    { name: "companyName", label: "Registered Entity Name" },
    { name: "industry", label: "Industry Sector" },
    { name: "stage", label: "Business Stage" },
    { name: "incorporationDate", label: "Date of Incorporation" },
    { name: "ttmRevenue", label: "Trailing 12-Month Revenue" },
    { name: "projectedRevenue", label: "Projected Revenue (Next 12 Months)" },
    { name: "cashBurn", label: "Monthly Cash Burn" },
    { name: "activeUsers", label: "Total Active Users / Customers" },
    { name: "growthRate", label: "Growth Rate" },
    { name: "marketSize", label: "Market Size Indicator" },
    { name: "teamStrength", label: "Founder / Team Strength" },
    { name: "valuationAsk", label: "Pre-Money Valuation Ask" },
    { name: "fundraiseTarget", label: "Target Fundraise Amount" },
    { name: "justification", label: "Valuation Justification" },
  ];

  let isValid = true;
  let firstErrorField = null;

  for (const field of requiredFields) {
    const input = form.querySelector(`[name="${field.name}"]`);
    if (!input || !input.value.trim()) {
      showFieldError(field.name, `${field.label} is required.`);
      if (!firstErrorField) firstErrorField = input;
      isValid = false;
      continue; // Check remaining fields
    }

    // Minimum Justification Length (Check this even if field is "filled")
    if (field.name === "justification" && input.value.trim().length < 50) {
      showFieldError(
        "justification",
        "Please provide a more detailed justification (min 50 chars for security).",
      );
      if (!firstErrorField) firstErrorField = input;
      isValid = false;
    }
  }

  // Checkbox Validation (Valuation Basis)
  const basisChecked = form.querySelectorAll(
    'input[name="valuationBasis[]"]:checked',
  );
  if (basisChecked.length === 0) {
    const basisGrid = form.querySelector(".multi-select-grid");
    const errorMsg = document.createElement("div");
    errorMsg.className = "error-message";
    errorMsg.textContent = "Please select at least one valuation basis.";
    basisGrid.parentElement.appendChild(errorMsg);
    if (!firstErrorField) firstErrorField = basisGrid;
    isValid = false;
  }

  // File Upload Validations
  const pitchDeck = form.querySelector('[name="pitchDeck"]');
  const financialDocs = form.querySelector('[name="financialDocs"]');

  if (!pitchDeck.files || pitchDeck.files.length === 0) {
    showFieldError("pitchDeck", "Pitch Deck is mandatory for verification.");
    if (!firstErrorField) firstErrorField = pitchDeck.closest(".file-upload");
    isValid = false;
  }

  if (!financialDocs.files || financialDocs.files.length === 0) {
    showFieldError("financialDocs", "Financial Statements are required.");
    if (!firstErrorField)
      firstErrorField = financialDocs.closest(".file-upload");
    isValid = false;
  }

  // Scroll to first error
  if (!isValid && firstErrorField) {
    firstErrorField.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  return isValid;
}

// ============================================
// AI VALUATION ADVISOR
// ============================================
// AI VALUATION ADVISOR (REAL-TIME)
// ============================================

function initAiAnalysis() {
  const output = document.getElementById("aiAdviceOutput");
  const form = document.getElementById("valuationForm");

  if (!output || !form) return;

  const triggers = [
    'input[name="ttmRevenue"]',
    'input[name="projectedRevenue"]',
    'input[name="cashBurn"]',
    'input[name="growthRate"]',
    'input[name="valuationAsk"]',
    'select[name="industry"]',
    'textarea[name="justification"]',
  ];

  let debounceTimer;

  const runAnalysis = async () => {
    // Get values directly
    const valInput = document.getElementById("valuationAsk");
    const ttmInput = form.querySelector('input[name="ttmRevenue"]');
    const projInput = form.querySelector('input[name="projectedRevenue"]');
    const growthInput = form.querySelector('input[name="growthRate"]');
    const indInput = form.querySelector('select[name="industry"]');
    const stageInput = form.querySelector('input[name="stage"]');
    const justInput = form.querySelector('textarea[name="justification"]');

    const valuationAsk = valInput
      ? valInput.value.replace(/,/g, "").trim()
      : "";

    // Auto-analysis only triggers if a valuation is entered
    if (!valuationAsk || valuationAsk === "0" || valuationAsk.length < 5) {
      const label = document.getElementById("aiIndicatorLabel");
      const bar = document.getElementById("aiConfidenceBar");
      if (label) label.textContent = "Standby (Awaiting Valuation)...";
      if (bar) bar.style.width = "0%";
      output.innerHTML = `<p class="awareness-description">Enter your valuation and growth metrics to see real-time AI advisory.</p>`;
      return;
    }

    const ttmRevenue = ttmInput ? ttmInput.value.replace(/,/g, "").trim() : "";
    const projectedRevenue = projInput
      ? projInput.value.replace(/,/g, "").trim()
      : "";
    const growthRate = growthInput ? growthInput.value.trim() : "";
    const industry = indInput ? indInput.value : "";
    const stage = stageInput ? stageInput.value : "";
    const justification = justInput ? justInput.value.trim() : "";

    // Show a subtle "Analyzing" indicator with Skeleton Loader
    const label = document.getElementById("aiIndicatorLabel");
    if (label) {
      label.innerHTML = `<span class="spinner-small"></span> AI is thinking...`;
    }

    // Inject Skeleton Loader into the output area
    output.innerHTML = `
      <div class="skeleton-loader" style="height: 100px; width: 100%; border-radius: 0.5rem; opacity: 0.5;"></div>
    `;

    try {
      // Use relative path correctly
      const response = await fetch("api_valuation_advice.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ttmRevenue,
          projectedRevenue,
          growthRate,
          valuationAsk,
          industry,
          stage,
          justification,
        }),
      });

      if (!response.ok) {
        throw new Error(`Server responded with ${response.status}`);
      }

      const responseText = await response.text();

      let data;
      try {
        data = JSON.parse(responseText.trim());
      } catch (e) {
        console.error("AI Response Error:", responseText);
        if (label) label.textContent = "AI Status: Offline";
        return;
      }

      if (data.status === "success" && data.data) {
        const aiInfo = data.data;

        // 1. Update text output
        // Use advice field from Python
        output.innerHTML = `
            <div style="background: rgba(13, 17, 23, 0.4); border-radius: 0.5rem; padding: 1rem; border-left: 3px solid var(--glow-purple)">
                <p>${(aiInfo.advice || "No advice generated.").replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")}</p>
            </div>
        `;

        // 2. Update Confidence Bar
        const bar = document.getElementById("aiConfidenceBar");
        const indicatorLabel = document.getElementById("aiIndicatorLabel");

        if (bar && indicatorLabel) {
          const score = aiInfo.confidence || 0;
          bar.style.width = score + "%";
          indicatorLabel.textContent = `Verdict: ${aiInfo.status || "Analyzed"} (${score}% Score)`;

          // Color mapping
          if (aiInfo.status === "Fair") {
            bar.style.background = "linear-gradient(90deg, #10b981, #34d399)";
          } else if (aiInfo.status === "Aggressive") {
            bar.style.background = "linear-gradient(90deg, #f59e0b, #fbbf24)";
          } else {
            bar.style.background = "linear-gradient(90deg, #06b6d4, #8b5cf6)";
          }
        }
      } else {
        // Updated: Show actual error from backend instead of generic message
        const label = document.getElementById("aiIndicatorLabel");
        if (label)
          label.textContent = data.message || "AI: Waiting for more data...";
        output.innerHTML = `<p class="awareness-description">${data.message || "Fill more fields to get AI context."}</p>`;
      }
    } catch (err) {
      console.error("AI Auto-Update Error:", err);
      const label = document.getElementById("aiIndicatorLabel");
      if (label) label.textContent = "AI Status: Offline";
      output.innerHTML = `<p class="awareness-description" style="color: #ef4444;">AI Engine is currently unavailable. Please check if Python is installed and configured.</p>`;
    }
  };

  // Attach Listeners for real-time update
  triggers.forEach((selector) => {
    const el = form.querySelector(selector);
    if (!el) return;

    if (el.tagName === "INPUT" || el.tagName === "TEXTAREA") {
      el.addEventListener("input", () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runAnalysis, 1000); // 1s debounce to avoid flickering
      });
    } else {
      el.addEventListener("change", () => {
        clearTimeout(debounceTimer);
        runAnalysis();
      });
    }
  });
}
