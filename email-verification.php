<?php
  session_start();
  $emailValue = isset($_SESSION['verificationEmail']) ? $_SESSION['verificationEmail'] : '';

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Verification - SmartPitchHub</title>
    <meta
      name="description"
      content="Verify your email address to ensure account security and access all features of SmartPitchHub."
    />

    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        /* Color System - HSL Format */
        --primary: 262 83% 58%;
        --primary-light: 266 85% 78%;
        --primary-glow: 262 83% 68%;
        --secondary: 220 14% 96%;
        --accent: 262 83% 58%;

        /* Background Colors */
        --bg-primary: 222 47% 11%;
        --bg-secondary: 217 33% 17%;
        --bg-surface: 215 28% 17%;

        /* Text Colors */
        --text-primary: 210 40% 98%;
        --text-secondary: 215 20% 65%;
        --text-muted: 215 16% 47%;

        /* Border Colors */
        --border-primary: 215 28% 17%;
        --border-error: 0 84% 60%;

        /* Semantic Colors */
        --error: 0 84% 60%;
        --success: 142 76% 36%;

        /* Gradients */
        --gradient-brand: linear-gradient(
          135deg,
          hsl(var(--primary)),
          hsl(316 73% 52%)
        );
        --gradient-glow: radial-gradient(
          600px circle at 50% 300px,
          hsl(var(--primary) / 0.15),
          transparent 40%
        );

        /* Shadows */
        --shadow-glow: 0 0 40px hsl(var(--primary-glow) / 0.4);
        --shadow-elevated: 0 20px 25px -5px hsl(0 0% 0% / 0.3),
          0 10px 10px -5px hsl(0 0% 0% / 0.1);

        /* Transitions */
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
          sans-serif;
        background: hsl(var(--bg-primary));
        color: hsl(var(--text-primary));
        min-height: 100vh;
        overflow-x: hidden;
      }

      .floating-element {
        position: absolute;
        border-radius: 50%;
        background: hsl(var(--primary) / 0.2);
        filter: blur(40px);
        animation: float 6s ease-in-out infinite;
        pointer-events: none;
      }

      .floating-1 {
        width: 128px;
        height: 128px;
        top: 40px;
        left: 40px;
      }

      .floating-2 {
        width: 192px;
        height: 192px;
        bottom: 80px;
        right: 80px;
        animation-delay: 2s;
      }

      .floating-3 {
        width: 96px;
        height: 96px;
        top: 50%;
        left: 25%;
        animation-delay: 4s;
      }

      .gradient-overlay {
        position: absolute;
        inset: 0;
        background: var(--gradient-glow);
        opacity: 0.3;
        pointer-events: none;
      }

      .header {
        position: relative;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px;
      }

      .back-button {
        display: flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        border: none;
        color: hsl(var(--text-secondary));
        cursor: pointer;
        transition: var(--transition-smooth);
        text-decoration: none;
        font-size: 14px;
      }

      .back-button:hover {
        color: hsl(var(--text-primary));
      }

      .brand {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .brand-icon {
        width: 32px;
        height: 32px;
        background: var(--gradient-brand);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-glow);
      }

      .brand-name {
        font-size: 20px;
        font-weight: bold;
        background: var(--gradient-brand);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .main-content {
        position: relative;
        z-index: 10;
        max-width: 1200px;
        margin: 0 auto;
        padding: 48px 24px;
      }

      .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 48px;
        align-items: center;
      }

      .form-section {
        animation: fadeIn 0.6s ease-out;
      }

      .hero-text {
        margin-bottom: 32px;
      }

      .hero-title {
        font-size: 48px;
        font-weight: bold;
        color: hsl(var(--text-primary));
        line-height: 1.2;
        margin-bottom: 16px;
      }

      .hero-gradient {
        display: block;
        background: var(--gradient-brand);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .hero-subtitle {
        font-size: 18px;
        color: hsl(var(--text-secondary));
        max-width: 400px;
      }

      .form-card {
        background: hsl(var(--bg-secondary) / 0.5);
        backdrop-filter: blur(12px);
        border: 1px solid hsl(var(--border-primary));
        border-radius: 16px;
        padding: 32px;
        box-shadow: var(--shadow-elevated);
        transition: var(--transition-smooth);
        animation: slideUp 0.6s ease-out;
      }

      .form-card:hover {
        box-shadow: var(--shadow-glow);
      }

      .form-group {
        margin-bottom: 24px;
      }

      .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: hsl(var(--text-primary));
        margin-bottom: 8px;
      }

      .input-wrapper {
        position: relative;
      }

      .input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: hsl(var(--text-muted));
        pointer-events: none;
      }

      .form-input {
        width: 100%;
        height: 48px;
        padding: 0 16px 0 48px;
        background: hsl(var(--bg-primary));
        border: 1px solid hsl(var(--border-primary));
        border-radius: 8px;
        color: hsl(var(--text-primary));
        font-size: 14px;
        transition: var(--transition-smooth);
      }

      .form-input:focus {
        outline: none;
        border-color: hsl(var(--primary));
        box-shadow: 0 0 0 3px hsl(var(--primary) / 0.2);
      }

      .form-input.error {
        border-color: hsl(var(--border-error));
      }

      .form-input::placeholder {
        color: hsl(var(--text-muted));
      }

      .error-message {
        color: hsl(var(--error));
        font-size: 12px;
        margin-top: 4px;
        display: none;
      }

      .error-message.show {
        display: block;
      }

      .submit-button {
        width: 100%;
        height: 48px;
        background: var(--gradient-brand);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        box-shadow: var(--shadow-glow);
        position: relative;
        overflow: hidden;
      }

      .submit-button:hover {
        opacity: 0.9;
        transform: scale(1.02);
        box-shadow: var(--shadow-elevated);
      }

      .submit-button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
      }

      .loading-spinner {
        display: none;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 8px;
      }

      .submit-button.loading .loading-spinner {
        display: inline-block;
      }

      .form-footer {
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid hsl(var(--border-primary));
        text-align: center;
      }

      .legal-text {
        font-size: 12px;
        color: hsl(var(--text-muted));
      }

      .legal-link {
        color: hsl(var(--primary));
        text-decoration: none;
        transition: var(--transition-smooth);
      }

      .legal-link:hover {
        color: hsl(var(--primary-light));
      }

      .features-section {
        animation: fadeIn 0.6s ease-out 0.2s both;
      }

      .features-title {
        font-size: 24px;
        font-weight: bold;
        color: hsl(var(--text-primary));
        margin-bottom: 16px;
      }

      .features-subtitle {
        color: hsl(var(--text-secondary));
        margin-bottom: 32px;
      }

      .feature-list {
        display: flex;
        flex-direction: column;
        gap: 24px;
        margin-bottom: 32px;
      }

      .feature-item {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 16px;
        background: hsl(var(--bg-secondary) / 0.3);
        backdrop-filter: blur(12px);
        border: 1px solid hsl(var(--border-primary) / 0.5);
        border-radius: 12px;
        transition: var(--transition-smooth);
        animation: scaleIn 0.4s ease-out;
      }

      .feature-item:hover {
        background: hsl(var(--bg-secondary) / 0.5);
      }

      .feature-icon {
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        background: var(--gradient-brand);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-glow);
      }

      .feature-content h3 {
        font-size: 16px;
        font-weight: 600;
        color: hsl(var(--text-primary));
        margin-bottom: 4px;
      }

      .feature-content p {
        font-size: 14px;
        color: hsl(var(--text-secondary));
      }

      .security-tip {
        padding: 24px;
        background: hsl(var(--primary) / 0.1);
        border: 1px solid hsl(var(--primary) / 0.2);
        border-radius: 12px;
        backdrop-filter: blur(12px);
      }

      .security-tip-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
      }

      .security-tip-title {
        font-size: 16px;
        font-weight: 600;
        color: hsl(var(--text-primary));
      }

      .security-tip-text {
        font-size: 14px;
        color: hsl(var(--text-secondary));
      }

      .toast {
        position: fixed;
        top: 24px;
        right: 24px;
        background: hsl(var(--bg-secondary));
        border: 1px solid hsl(var(--border-primary));
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: var(--shadow-elevated);
        color: hsl(var(--text-primary));
        font-size: 14px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 1000;
        max-width: 400px;
      }

      .toast.show {
        transform: translateX(0);
      }

      .toast.success {
        border-color: hsl(var(--success));
        background: hsl(var(--success) / 0.1);
      }

      .toast.error {
        border-color: hsl(var(--error));
        background: hsl(var(--error) / 0.1);
      }

      /* Animations */
      @keyframes float {
        0%,
        100% {
          transform: translateY(0px);
        }
        50% {
          transform: translateY(-10px);
        }
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      @keyframes scaleIn {
        from {
          opacity: 0;
          transform: scale(0.95);
        }
        to {
          opacity: 1;
          transform: scale(1);
        }
      }

      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }

      /* Responsive */
      @media (max-width: 1024px) {
        .content-grid {
          grid-template-columns: 1fr;
          gap: 32px;
        }

        .hero-title {
          font-size: 40px;
          text-align: center;
        }

        .hero-subtitle {
          text-align: center;
          margin: 0 auto;
        }
      }

      @media (max-width: 640px) {
        .main-content {
          padding: 24px 16px;
        }

        .form-card {
          padding: 24px;
        }

        .hero-title {
          font-size: 32px;
        }

        .floating-2,
        .floating-3 {
          display: none;
        }
      }
    </style>
  </head>
  <body>
    <!-- Animated background elements -->
    <div class="floating-element floating-1"></div>
    <div class="floating-element floating-2"></div>
    <div class="floating-element floating-3"></div>

    <!-- Background gradient overlay -->
    <div class="gradient-overlay"></div>

    <!-- Header -->
    <header class="header">
      <a href="register.php" class="back-button">
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="m12 19-7-7 7-7"></path>
          <path d="M19 12H5"></path>
        </svg>
        Back
      </a>

      <div class="brand">
        <div class="brand-icon">
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="white"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M9 12l2 2 4-4"></path>
            <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
            <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
          </svg>
        </div>
        <span class="brand-name">SmartPitchHub</span>
      </div>
    </header>

    <!-- Main content -->
    <main class="main-content">
      <div class="content-grid">
        <!-- Left side - Form -->
        <div class="form-section">
          <div class="hero-text">
            <h1 class="hero-title">
              Verify Your
              <span class="hero-gradient">Email Address</span>
            </h1>
            <p class="hero-subtitle">
              We'll send you a verification code to ensure the security of your
              account.
            </p>
          </div>

          <div class="form-card">
            <form id="emailForm" method="POST">
  <div class="form-group">
    <label class="form-label" for="email">Email Address</label>
    <div class="input-wrapper">
      <svg
        class="input-icon"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
        <polyline points="22,6 12,13 2,6"></polyline>
      </svg>
      <input
    type="email"
    id="email"
    class="form-input"
    placeholder="Enter your email address"
    required
    value="<?php echo htmlspecialchars($emailValue); ?>"
    <?php if(!empty($emailValue)) echo 'readonly'; ?>
/>
    </div>
    <div class="error-message" id="emailError"></div>
  </div>

  <button type="submit" class="submit-button" id="submitButton" name="verify_email">
    <div class="loading-spinner"></div>
    <span id="buttonText">Send Verification Code</span>
  </button>
</form>


            <div class="form-footer">
              <p class="legal-text">
                By continuing, you agree to our
                <a href="#" class="legal-link">Terms of Service</a>
                and
                <a href="#" class="legal-link">Privacy Policy</a>
              </p>
            </div>
          </div>
        </div>

        <!-- Right side - Features -->
        <div class="features-section">
          <div>
            <h2 class="features-title">Why Verify Your Email?</h2>
            <p class="features-subtitle">
              Email verification ensures the security and reliability of your
              SmartPitchHub account.
            </p>
          </div>

          <div class="feature-list">
            <div class="feature-item" style="animation-delay: 0.4s">
              <div class="feature-icon">
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="white"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M9 12l2 2 4-4"></path>
                  <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                  <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                </svg>
              </div>
              <div class="feature-content">
                <h3>Secure Verification</h3>
                <p>Your account security is our top priority</p>
              </div>
            </div>

            <div class="feature-item" style="animation-delay: 0.5s">
              <div class="feature-icon">
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="white"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path
                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                  ></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
              </div>
              <div class="feature-content">
                <h3>Email Verification</h3>
                <p>Verify your email to access all features</p>
              </div>
            </div>

            <div class="feature-item" style="animation-delay: 0.6s">
              <div class="feature-icon">
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="white"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22,4 12,14.01 9,11.01"></polyline>
                </svg>
              </div>
              <div class="feature-content">
                <h3>Quick Process</h3>
                <p>Complete verification in under 2 minutes</p>
              </div>
            </div>
          </div>

          <div class="security-tip">
            <div class="security-tip-header">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="hsl(var(--primary))"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22,4 12,14.01 9,11.01"></polyline>
              </svg>
              <span class="security-tip-title">Security Tip</span>
            </div>
            <p class="security-tip-text">
              We'll never ask for your verification code via phone or other
              channels. Only enter it on this website.
            </p>
          </div>
        </div>
      </div>
    </main>

<script>
const emailForm = document.getElementById("emailForm");
const emailInput = document.getElementById("email");
const emailError = document.getElementById("emailError");
const submitButton = document.getElementById("submitButton");
const buttonText = document.getElementById("buttonText");

function validateEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

function showError(message) {
  emailInput.classList.add("error");
  emailError.textContent = message;
  emailError.classList.add("show");
}

function clearError() {
  emailInput.classList.remove("error");
  emailError.classList.remove("show");
}

function showToast(message, type = "success") {
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.classList.add("show"), 100);
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => document.body.removeChild(toast), 300);
  }, 5000);
}

function setLoading(loading) {
  submitButton.disabled = loading;
  submitButton.classList.toggle("loading", loading);
  buttonText.textContent = loading ? "Sending Code..." : "Send Verification Code";
}

emailForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearError();

  const email = emailInput.value.trim();

  if (!email) {
    showError("Email address is required");
    return;
  }
  if (!validateEmail(email)) {
    showError("Please enter a valid email address");
    return;
  }

  setLoading(true);

  try {
    const formData = new FormData();
    formData.append("email", email);

    const res = await fetch("send-otp.php", {
      method: "POST",
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      localStorage.setItem("verificationEmail", email);
      emailInput.readOnly = true; // Lock input after sending OTP
      showToast(data.message);
      setTimeout(() => {
        window.location.href = "otp-verification1.php";
      }, 1000);
    } else {
      showError(data.message);
    }
  } catch (err) {
    showError("Something went wrong. Try again.");
  } finally {
    setLoading(false);
  }
});

emailInput.addEventListener("focus", clearError);

// Set localStorage if session has email
document.addEventListener("DOMContentLoaded", function() {
    const emailValue = "<?php echo addslashes($emailValue); ?>";
    if (emailValue) {
        localStorage.setItem("verificationEmail", emailValue);
    }
});

</script>

  </body>
</html>
