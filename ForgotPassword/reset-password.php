<?php
session_start();
// 1. Connect to Database (Adjust path: '../db.php' if one folder up, 'db.php' if same folder)
require_once '../db.php';

$error = "";
$valid_token = false;
$show_success = false;
$token_value = "";

// 2. Check for Success Flag (Redirected from Action File)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $show_success = true;
} 
// 3. Check for Token in URL
elseif (isset($_GET['token'])) {
    $token_value = $_GET['token'];
    $currentDate = date("Y-m-d H:i:s");
    
    // Verify token in database
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expiry > ?");
    $stmt->bind_param("ss", $token_value, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $valid_token = true;
    } else {
        $error = "This password reset link is invalid or has expired.";
    }
} else {
    $error = "No token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - Create New Password</title>
    <meta
      name="description"
      content="Create a new secure password for your account"
    />
    <style>
      /* --- START OF YOUR ORIGINAL CSS --- */
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
        transition: all 0.4s ease;
      }

      .card.success-state {
        transform: scale(1.02);
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
        transition: all 0.4s ease;
      }

      .icon-wrapper.success {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
      }

      @keyframes scaleIn {
        from {
          transform: scale(0) rotate(-180deg);
          opacity: 0;
        }
        to {
          transform: scale(1) rotate(0deg);
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

      .checkmark {
        display: none;
      }

      .icon-wrapper.success .lock {
        display: none;
      }

      .icon-wrapper.success .checkmark {
        display: block;
        animation: drawCheck 0.6s ease-out forwards;
      }

      @keyframes drawCheck {
        0% {
          stroke-dasharray: 0, 100;
        }
        100% {
          stroke-dasharray: 100, 100;
        }
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

      #resetForm {
        transition: opacity 0.3s ease, transform 0.3s ease;
      }

      #resetForm.hide {
        opacity: 0;
        transform: scale(0.95);
        pointer-events: none;
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
        padding: 16px 48px 16px 48px;
        border: 2px solid #e2e8f0;
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

      .toggle-password {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        cursor: pointer;
        stroke: var(--text-secondary);
        transition: stroke 0.3s ease;
      }

      .toggle-password:hover {
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

      .password-strength {
        margin-top: 12px;
        display: none;
      }

      .password-strength.show {
        display: block;
        animation: fadeIn 0.3s ease;
      }

      .strength-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .strength-text {
        font-weight: 600;
      }

      .strength-text.weak {
        color: #f56565;
      }
      .strength-text.medium {
        color: #ed8936;
      }
      .strength-text.strong {
        color: #48bb78;
      }

      .strength-bars {
        display: flex;
        gap: 4px;
        height: 4px;
      }

      .strength-bar {
        flex: 1;
        background: var(--border-color);
        border-radius: 2px;
        transition: background 0.3s ease;
      }

      .strength-bar.active.weak {
        background: #f56565;
      }
      .strength-bar.active.medium {
        background: #ed8936;
      }
      .strength-bar.active.strong {
        background: #48bb78;
      }

      .requirements {
        margin-top: 12px;
        font-size: 13px;
        color: var(--text-secondary);
      }

      .requirement {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        transition: color 0.3s ease;
      }

      .requirement.met {
        color: #48bb78;
      }

      .requirement-icon {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #cbd5e0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
      }

      .requirement.met .requirement-icon {
        background: #48bb78;
        border-color: #48bb78;
      }

      .requirement-icon::after {
        content: "";
        width: 6px;
        height: 6px;
        background: white;
        border-radius: 50%;
        opacity: 0;
        transform: scale(0);
        transition: all 0.3s ease;
      }

      .requirement.met .requirement-icon::after {
        opacity: 1;
        transform: scale(1);
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

      button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(167, 139, 250, 0.4);
      }

      button:active:not(:disabled) {
        transform: translateY(0);
      }

      button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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

      .success-message {
        display: none;
        text-align: center;
        animation: fadeIn 0.4s ease;
        color: var(--text-primary);
      }

      .success-message.show {
        display: block;
      }

      .success-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 12px;
      }

      .success-text {
        color: var(--text-secondary);
        font-size: 15px;
        line-height: 1.6;
        margin-bottom: 24px;
      }

      .login-button {
        display: inline-block;
        padding: 12px 32px;
        background: var(--gradient-primary);
        color: white;
        text-decoration: none;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
      }

      .login-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(167, 139, 250, 0.4);
      }
      
      /* Extra Styles for PHP Alerts */
      .alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
        font-size: 14px;
        width: 100%;
      }
      .alert-danger {
        background: rgba(245, 101, 101, 0.15);
        color: #f56565;
        border: 1px solid #f56565;
      }
      .alert-success {
        background: rgba(72, 187, 120, 0.15);
        color: #48bb78;
        border: 1px solid #48bb78;
      }

      @media (max-width: 480px) {
        .card {
          padding: 32px 24px;
        }

        h1 {
          font-size: 24px;
        }
      }
      /* --- END OF YOUR ORIGINAL CSS --- */
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
          <svg class="lock" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0110 0v4"></path>
          </svg>
          <svg class="checkmark" viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </div>
        
        <!-- PHP MESSAGES AND SUCCESS HANDLING -->
        <?php if ($show_success): ?>
            <script>
                // If PHP says success, trigger your JS success animations
                window.addEventListener('DOMContentLoaded', () => {
                    document.querySelector(".icon-wrapper").classList.add("success");
                    document.querySelector(".card").classList.add("success-state");
                    document.getElementById("successMessage").classList.add("show");
                    document.getElementById("resetForm").style.display = "none";
                    document.getElementById("pageTitle").textContent = "Success!";
                    document.getElementById("pageSubtitle").style.display = "none";
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if ($error && !$show_success): ?>
             <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <h1 id="pageTitle">Create New Password</h1>
        <p class="subtitle" id="pageSubtitle">
          Please enter your new password below. Make sure it's strong and
          secure.
        </p>

        <!-- FORM: Only show if token is valid and not already success -->
        <?php if ($valid_token && !$show_success): ?>
        <form id="resetForm" method="POST" action="resetPasswordAction.php">
          <!-- HIDDEN TOKEN -->
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_value); ?>">

          <div class="form-group">
            <div class="input-wrapper">
              <!-- ADDED NAME ATTRIBUTE -->
              <input
                type="password"
                id="newPassword"
                name="new_password"
                placeholder="Enter new password"
                required
              />
              <svg class="input-icon" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0110 0v4"></path>
              </svg>
              <svg
                class="toggle-password"
                id="toggleNew"
                viewBox="0 0 24 24"
                fill="none"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                ></path>
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                ></path>
              </svg>
            </div>
            <div class="password-strength" id="strengthIndicator">
              <div class="strength-label">
                <span>Password Strength:</span>
                <span class="strength-text" id="strengthText">Weak</span>
              </div>
              <div class="strength-bars">
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
              </div>
            </div>
            <div class="requirements">
              <div class="requirement" id="req-length">
                <div class="requirement-icon"></div>
                <span>At least 8 characters</span>
              </div>
              <div class="requirement" id="req-uppercase">
                <div class="requirement-icon"></div>
                <span>One uppercase letter</span>
              </div>
              <div class="requirement" id="req-lowercase">
                <div class="requirement-icon"></div>
                <span>One lowercase letter</span>
              </div>
              <div class="requirement" id="req-number">
                <div class="requirement-icon"></div>
                <span>One number</span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <div class="input-wrapper">
              <!-- ADDED NAME ATTRIBUTE -->
              <input
                type="password"
                id="confirmPassword"
                name="confirm_password"
                placeholder="Confirm new password"
                required
              />
              <svg class="input-icon" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0110 0v4"></path>
              </svg>
              <svg
                class="toggle-password"
                id="toggleConfirm"
                viewBox="0 0 24 24"
                fill="none"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                ></path>
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                ></path>
              </svg>
            </div>
            <div class="error-message" id="confirmError">
              Passwords do not match
            </div>
          </div>
          
          <!-- ADDED NAME ATTRIBUTE -->
          <button type="submit" id="submitButton" name="reset_password" disabled>
            <span class="button-content">Reset Password</span>
            <div class="spinner"></div>
          </button>
        </form>
        <?php elseif (!$show_success): ?>
            <div style="text-align: center;">
                <a href="../login.php" class="login-button">Return to Login</a>
            </div>
        <?php endif; ?>

        <div class="success-message" id="successMessage">
          <h2 class="success-title">Password Changed Successfully!</h2>
          <p class="success-text">
            Your password has been reset successfully. You can now log in with
            your new password.
          </p>
          <a
            href="../login.php"
            class="login-button"
            onclick="window.location.href='../login.php'"
            >Go to Login</a
          >
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

      const form = document.getElementById("resetForm");
      
      // Only run this logic if form is present (meaning token was valid)
      if (form) {
          const newPasswordInput = document.getElementById("newPassword");
          const confirmPasswordInput = document.getElementById("confirmPassword");
          const confirmError = document.getElementById("confirmError");
          const submitButton = document.getElementById("submitButton");
          const strengthIndicator = document.getElementById("strengthIndicator");
          const strengthText = document.getElementById("strengthText");
          const strengthBars = document.querySelectorAll(".strength-bar");
          const toggleNew = document.getElementById("toggleNew");
          const toggleConfirm = document.getElementById("toggleConfirm");
    
          // Password requirements
          const requirements = {
            length: {
              regex: /.{8,}/,
              element: document.getElementById("req-length"),
            },
            uppercase: {
              regex: /[A-Z]/,
              element: document.getElementById("req-uppercase"),
            },
            lowercase: {
              regex: /[a-z]/,
              element: document.getElementById("req-lowercase"),
            },
            number: {
              regex: /[0-9]/,
              element: document.getElementById("req-number"),
            },
          };
    
          // Toggle password visibility
          toggleNew.addEventListener("click", function () {
            const type = newPasswordInput.type === "password" ? "text" : "password";
            newPasswordInput.type = type;
          });
    
          toggleConfirm.addEventListener("click", function () {
            const type =
              confirmPasswordInput.type === "password" ? "text" : "password";
            confirmPasswordInput.type = type;
          });
    
          // Check password strength
          function checkPasswordStrength(password) {
            let strength = 0;
            let metRequirements = 0;
    
            Object.keys(requirements).forEach((key) => {
              if (requirements[key].regex.test(password)) {
                requirements[key].element.classList.add("met");
                metRequirements++;
              } else {
                requirements[key].element.classList.remove("met");
              }
            });
    
            if (metRequirements === 4) strength = 3; // Strong
            else if (metRequirements >= 2) strength = 2; // Medium
            else if (metRequirements >= 1) strength = 1; // Weak
    
            return { strength, metRequirements };
          }
    
          // Update strength indicator
          function updateStrengthIndicator(strength) {
            const strengthLabels = ["Weak", "Weak", "Medium", "Strong"];
            const strengthClasses = ["weak", "weak", "medium", "strong"];
    
            strengthText.textContent = strengthLabels[strength];
            strengthText.className = "strength-text " + strengthClasses[strength];
    
            strengthBars.forEach((bar, index) => {
              bar.classList.remove("active", "weak", "medium", "strong");
              if (index < strength) {
                bar.classList.add("active", strengthClasses[strength]);
              }
            });
          }
    
          // Validate passwords match
          function validatePasswordsMatch() {
            if (confirmPasswordInput.value.length === 0) {
              confirmPasswordInput.classList.remove("error", "success");
              confirmError.classList.remove("show");
              return false;
            }
    
            if (newPasswordInput.value !== confirmPasswordInput.value) {
              confirmPasswordInput.classList.add("error");
              confirmPasswordInput.classList.remove("success");
              confirmError.classList.add("show");
              return false;
            } else {
              confirmPasswordInput.classList.remove("error");
              confirmPasswordInput.classList.add("success");
              confirmError.classList.remove("show");
              return true;
            }
          }
    
          // Update submit button state
          function updateSubmitButton() {
            const { metRequirements } = checkPasswordStrength(
              newPasswordInput.value
            );
            const passwordsMatch =
              newPasswordInput.value === confirmPasswordInput.value;
            const bothFilled =
              newPasswordInput.value.length > 0 &&
              confirmPasswordInput.value.length > 0;
    
            if (metRequirements === 4 && passwordsMatch && bothFilled) {
              submitButton.disabled = false;
            } else {
              submitButton.disabled = true;
            }
          }
    
          // New password input handler
          newPasswordInput.addEventListener("input", function () {
            const password = this.value;
    
            if (password.length > 0) {
              strengthIndicator.classList.add("show");
              const { strength } = checkPasswordStrength(password);
              updateStrengthIndicator(strength);
            } else {
              strengthIndicator.classList.remove("show");
            }
    
            validatePasswordsMatch();
            updateSubmitButton();
          });
    
          // Confirm password input handler
          confirmPasswordInput.addEventListener("input", function () {
            validatePasswordsMatch();
            updateSubmitButton();
          });
    
          confirmPasswordInput.addEventListener("blur", function () {
            if (this.value.length > 0) {
              validatePasswordsMatch();
            }
          });
    
          // --- MODIFIED FORM SUBMISSION ---
          form.addEventListener("submit", function (e) {
            // We do NOT preventDefault() anymore, because we want to submit to PHP
            // But we still check validation first
    
            const { metRequirements } = checkPasswordStrength(
              newPasswordInput.value
            );
    
            if (metRequirements !== 4) {
              e.preventDefault();
              return;
            }
    
            if (!validatePasswordsMatch()) {
               e.preventDefault();
              return;
            }
    
            // Show loading state
            submitButton.classList.add("loading");
            
            // REMOVED: The 'setTimeout' and 'simulate API call' code
            // The form will now submit naturally to resetPasswordAction.php
          });
      }

      // Check if email exists in session (Optional, kept from old code)
      /*
      window.addEventListener("load", function () {
        const email = sessionStorage.getItem("resetEmail");
        if (!email) {
          // Redirect logic if needed
        }
      });
      */
    </script>
  </body>
</html>