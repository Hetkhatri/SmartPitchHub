<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password - Reset Your Account</title>
    <meta name="description" content="Reset your password securely" />
    <style>
      /* --- YOUR ORIGINAL CSS --- */
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        --bg-primary: #0a0f1e;
        --bg-secondary: #1a1f35;
        --text-primary: #ffffff;
        --text-secondary: #a0aec0;
        --border-color: #2d3748;
        --gradient-primary: linear-gradient(135deg, #a78bfa 0%, #ec4899 100%);
        --gradient-bg: #0a0f1e;
        --card-bg: #1a1f35;
        --input-bg: #0f1629;
        --shape-opacity: 0.05;
      }

      body.light-mode {
        --bg-primary: #f7fafc;
        --bg-secondary: #ffffff;
        --text-primary: #1a202c;
        --text-secondary: #718096;
        --border-color: #e2e8f0;
        --gradient-primary: linear-gradient(135deg, #a78bfa 0%, #ec4899 100%);
        --gradient-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-bg: #ffffff;
        --input-bg: #ffffff;
        --shape-opacity: 0.1;
      }

      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
          Oxygen, Ubuntu, Cantarell, sans-serif;
        background: var(--bg-primary);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: hidden;
        transition: background 0.3s ease, color 0.3s ease;
      }

      .theme-toggle {
        position: fixed;
        top: 24px;
        right: 24px;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: var(--card-bg);
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      }

      .theme-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
      }

      .theme-toggle svg {
        width: 24px;
        height: 24px;
        stroke: var(--text-primary);
        transition: all 0.3s ease;
      }

      .theme-toggle .sun-icon {
        display: none;
      }

      body.light-mode .theme-toggle .sun-icon {
        display: block;
      }

      body.light-mode .theme-toggle .moon-icon {
        display: none;
      }

      .background-shapes {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        overflow: hidden;
      }

      .shape {
        position: absolute;
        background: rgba(167, 139, 250, var(--shape-opacity));
        border-radius: 50%;
        animation: float 20s infinite ease-in-out;
      }

      .shape:nth-child(1) {
        width: 300px;
        height: 300px;
        top: -100px;
        left: -100px;
        animation-delay: 0s;
      }

      .shape:nth-child(2) {
        width: 200px;
        height: 200px;
        bottom: -50px;
        right: -50px;
        animation-delay: 5s;
      }

      .shape:nth-child(3) {
        width: 150px;
        height: 150px;
        top: 50%;
        right: 10%;
        animation-delay: 10s;
      }

      @keyframes float {
        0%,
        100% {
          transform: translate(0, 0) rotate(0deg);
        }
        33% {
          transform: translate(30px, -30px) rotate(120deg);
        }
        66% {
          transform: translate(-20px, 20px) rotate(240deg);
        }
      }

      .container {
        position: relative;
        z-index: 1;
        max-width: 450px;
        width: 100%;
        animation: slideUp 0.6s ease-out;
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

      .card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 48px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
      }

      .icon-wrapper {
        width: 64px;
        height: 64px;
        background: var(--gradient-primary);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        animation: scaleIn 0.5s ease-out 0.2s both;
      }

      @keyframes scaleIn {
        from {
          transform: scale(0);
          opacity: 0;
        }
        to {
          transform: scale(1);
          opacity: 1;
        }
      }

      .icon-wrapper svg {
        width: 32px;
        height: 32px;
        stroke: white;
        stroke-width: 2;
        fill: none;
      }

      h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        text-align: center;
        margin-bottom: 12px;
        animation: fadeIn 0.6s ease-out 0.3s both;
      }

      .subtitle {
        color: var(--text-secondary);
        text-align: center;
        font-size: 15px;
        line-height: 1.6;
        margin-bottom: 32px;
        animation: fadeIn 0.6s ease-out 0.4s both;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
        }
        to {
          opacity: 1;
        }
      }

      .form-group {
        position: relative;
        margin-bottom: 24px;
        animation: fadeIn 0.6s ease-out 0.5s both;
      }

      .input-wrapper {
        position: relative;
      }

      input {
        width: 100%;
        padding: 16px 16px 16px 48px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 16px;
        transition: all 0.3s ease;
        outline: none;
        background: var(--input-bg);
        color: var(--text-primary);
      }

      input:focus {
        border-color: #a78bfa;
        box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.1);
      }

      input.error {
        border-color: #f56565;
      }

      input.success {
        border-color: #48bb78;
      }

      input::placeholder {
        color: var(--text-secondary);
      }

      .input-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        stroke: var(--text-secondary);
        transition: stroke 0.3s ease;
      }

      input:focus + .input-icon {
        stroke: #a78bfa;
      }

      .error-message {
        color: #f56565;
        font-size: 13px;
        margin-top: 8px;
        display: none;
        animation: shake 0.4s ease;
      }

      @keyframes shake {
        0%,
        100% {
          transform: translateX(0);
        }
        25% {
          transform: translateX(-5px);
        }
        75% {
          transform: translateX(5px);
        }
      }

      .error-message.show {
        display: block;
      }

      button {
        width: 100%;
        padding: 16px;
        background: var(--gradient-primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        animation: fadeIn 0.6s ease-out 0.6s both;
      }

      button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(167, 139, 250, 0.4);
      }

      button:active {
        transform: translateY(0);
      }

      button.loading {
        pointer-events: none;
      }

      .button-content {
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.3s ease;
      }

      button.loading .button-content {
        opacity: 0;
      }

      .spinner {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        opacity: 0;
      }

      button.loading .spinner {
        opacity: 1;
      }

      @keyframes spin {
        to {
          transform: translate(-50%, -50%) rotate(360deg);
        }
      }

      .back-link {
        text-align: center;
        margin-top: 24px;
        animation: fadeIn 0.6s ease-out 0.7s both;
      }

      .back-link a {
        color: #a78bfa;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.3s ease;
      }

      .back-link a:hover {
        color: #ec4899;
      }

      /* ADDED: Styling for PHP messages */
      .php-message {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
        font-size: 14px;
      }
      .php-message.success {
        background: rgba(72, 187, 120, 0.2);
        color: #48bb78;
        border: 1px solid #48bb78;
      }
      .php-message.error {
        background: rgba(245, 101, 101, 0.2);
        color: #f56565;
        border: 1px solid #f56565;
      }

      @media (max-width: 480px) {
        .card {
          padding: 32px 24px;
        }

        h1 {
          font-size: 24px;
        }
      }
    </style>
  </head>
  <body>
    <button
      class="theme-toggle"
      onclick="toggleTheme()"
      aria-label="Toggle theme"
    >
      <svg
        class="moon-icon"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
        />
      </svg>
      <svg
        class="sun-icon"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
        />
      </svg>
    </button>

    <div class="background-shapes">
      <div class="shape"></div>
      <div class="shape"></div>
      <div class="shape"></div>
    </div>

    <div class="container">
      <div class="card">
        <div class="icon-wrapper">
          <svg viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
        </div>

        <h1>Forgot Password?</h1>
        <p class="subtitle">
          No worries! Enter your email address and we'll send you instructions
          to reset your password.
        </p>

        <!-- ADDED: PHP Message Display Area -->
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="php-message <?php echo isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : ''; ?>">
                <?php echo $_SESSION['msg']; ?>
            </div>
            <?php 
              // Clear the message
              unset($_SESSION['msg']);
              unset($_SESSION['msg_type']);
            ?>
        <?php endif; ?>

        <!-- UPDATED: Form points to the action file -->
        <form id="forgotPasswordForm" method="POST" action="forgotPasswordAction.php">
          <div class="form-group">
            <div class="input-wrapper">
              <!-- ADDED: name attribute -->
              <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email address"
                required
              />
              <svg class="input-icon" viewBox="0 0 24 24" fill="none">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                />
              </svg>
            </div>
            <div class="error-message" id="emailError">
              Please enter a valid email address
            </div>
          </div>

          <!-- ADDED: name attribute -->
          <button type="submit" name="submit_email">
            <span class="button-content">Send Reset Link</span>
            <div class="spinner"></div>
          </button>
        </form>

        <div class="back-link">
          <!-- Adjust this link to point to your login page -->
          <a href="../login.php">← Back to Login</a>
        </div>
      </div>
    </div>

    <script>
      // Theme Toggle
      function toggleTheme() {
        document.body.classList.toggle("light-mode");
        const isLight = document.body.classList.contains("light-mode");
        localStorage.setItem("theme", isLight ? "light" : "dark");
      }

      // Load saved theme
      window.addEventListener("DOMContentLoaded", () => {
        const savedTheme = localStorage.getItem("theme");
        if (savedTheme === "light") {
          document.body.classList.add("light-mode");
        }
      });

      const form = document.getElementById("forgotPasswordForm");
      const emailInput = document.getElementById("email");
      const emailError = document.getElementById("emailError");
      const submitButton = form.querySelector('button[type="submit"]');

      // Email validation
      function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
      }

      // Show error
      function showError() {
        emailInput.classList.add("error");
        emailInput.classList.remove("success");
        emailError.classList.add("show");
      }

      // Hide error
      function hideError() {
        emailInput.classList.remove("error");
        emailError.classList.remove("show");
      }

      // Real-time validation
      emailInput.addEventListener("input", function () {
        if (this.value.length > 0) {
          if (validateEmail(this.value)) {
            hideError();
            this.classList.add("success");
          } else {
            this.classList.remove("success");
          }
        } else {
          hideError();
          this.classList.remove("success");
        }
      });

      emailInput.addEventListener("blur", function () {
        if (this.value.length > 0 && !validateEmail(this.value)) {
          showError();
        }
      });

      // UPDATED: Form submission
      form.addEventListener("submit", function (e) {
        // 1. We still validate the email first
        const email = emailInput.value.trim();
        if (!validateEmail(email)) {
          e.preventDefault(); // Stop if invalid
          showError();
          return;
        }

        // 2. If valid, we show loading but ALLOW the form to submit to PHP
        submitButton.classList.add("loading");

        // 3. REMOVED: e.preventDefault() and setTimeout()
        // The form will now naturally submit to 'forgotPasswordAction.php'
      });

      // Remove loading state on page show (back button)
      window.addEventListener("pageshow", function () {
        submitButton.classList.remove("loading");
      });
    </script>
  </body>
</html>