<?php
session_start();
include 'db.php'; // your DB connection

$response = ['success' => false, 'message' => 'Something went wrong'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirmPassword']);
    $userType = $_POST['userType'];
    $startupName = isset($_POST['startup_name']) ? trim($_POST['startup_name']) : '';

    // Password match
    if ($password !== $confirmPassword) {
        $_SESSION['registration_error'] = "Passwords do not match.";
        header('Location: register.php');
        exit;
    }

    // Password strength
    if (strlen($password) < 8 || !preg_match('/[\W]/', $password)) {
        $_SESSION['registration_error'] = "Password must be at least 8 chars & include 1 special character.";
        header('Location: register.php');
        exit;
    }

    // Duplicate email check
    $query = "SELECT email FROM entrepreneurs WHERE email=? 
              UNION 
              SELECT email FROM investors WHERE email=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $_SESSION['registration_error'] = "This email is already registered.";
        header('Location: register.php');
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = ($userType === 'founder') ? 'entrepreneur' : 'investor';

    // Store pending registration in session
    $_SESSION['pending_registration'] = [
        'name' => $fullName,
        'email' => $email,
        'contact' => $contact,
        'password' => $hashedPassword,
        'role' => $role,
        'startup_name' => $startupName
    ];

    $_SESSION['verificationEmail'] = $email;

    header('Location: email-verification.php');
    exit;
}

// If GET request, render your registration HTML form below
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - SmartPitchHub</title>
  <meta name="description" content="Join SmartPitchHub to connect with investors, submit your startup pitches, and explore investment opportunities.">
  <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --primary-light: #a78bfa;
            --background: #0f172a;
            --surface: #1e293b;
            --surface-light: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --border: #334155;
            --error: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            
            --gradient: linear-gradient(135deg, var(--primary), #ec4899);
            --shadow-glow: 0 0 40px rgba(139, 92, 246, 0.3);
            --shadow-subtle: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(236, 72, 153, 0.1) 0%, transparent 50%);
        }

        .container {
            width: 100%;
            max-width: 400px;
            position: relative;
        }

        .floating-bg {
            position: absolute;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.2);
            filter: blur(40px);
            animation: float 6s ease-in-out infinite;
        }

        .floating-bg:nth-child(1) {
            width: 80px;
            height: 80px;
            top: -40px;
            left: -40px;
        }

        .floating-bg:nth-child(2) {
            width: 120px;
            height: 120px;
            bottom: -60px;
            right: -60px;
            animation-delay: -3s;
        }

        .theme-toggle {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            z-index: 100;
        }

        .theme-toggle:hover {
            background: var(--surface-light);
            transform: scale(1.1);
        }

        .back-link {
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            z-index: 100;
        }

        .back-link:hover {
            color: var(--text-primary);
            transform: translateX(-4px);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        .logo {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--gradient);
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-glow);
        }

        .logo svg {
            width: 1.5rem;
            height: 1.5rem;
            color: white;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: bold;
            background: var(--gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        .welcome-text {
            font-size: 1.875rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--text-secondary);
        }

        .form-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow-subtle);
            animation: slideUp 0.6s ease-out 0.2s both;
            backdrop-filter: blur(10px);
        }

        .form-container:hover {
            box-shadow: var(--shadow-glow);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .form-input.error {
            border-color: var(--error);
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
        }

        .password-toggle:hover {
            color: var(--text-secondary);
        }

        .error-message {
            color: var(--error);
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .user-type-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .user-type-option {
            position: relative;
        }

        .user-type-radio {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .user-type-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .user-type-radio:checked + .user-type-label {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
        }

        .user-type-label:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bars {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .strength-bar {
            height: 0.25rem;
            flex: 1;
            background: var(--border);
            border-radius: 0.125rem;
            transition: var(--transition);
        }

        .strength-bar.active {
            background: var(--success);
        }

        .strength-bar.active.weak {
            background: var(--error);
        }

        .strength-bar.active.fair {
            background: var(--warning);
        }

        .strength-bar.active.good {
            background: var(--success);
        }

        .strength-bar.active.strong {
            background: var(--primary);
        }

        .strength-text {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .checkbox {
            width: 1rem;
            height: 1rem;
            margin-top: 0.125rem;
            accent-color: var(--primary);
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .checkbox-label a {
            color: var(--primary);
            text-decoration: none;
            position: relative;
        }

        .checkbox-label a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1px;
            bottom: -1px;
            left: 0;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .checkbox-label a:hover::after {
            width: 100%;
        }

        .submit-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .loading-spinner {
            display: none;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        .submit-btn.loading .loading-spinner {
            display: inline-block;
        }

        .submit-btn.loading .btn-text {
            opacity: 0.7;
        }

        .divider {
            margin: 1.5rem 0;
            position: relative;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            background: var(--surface);
            padding: 0 1rem;
        }

        .social-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .social-btn {
            padding: 0.75rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .social-btn:hover {
            background: var(--surface-light);
            transform: translateY(-1px);
        }

        .signin-link {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .signin-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            position: relative;
        }

        .signin-link a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1px;
            bottom: -2px;
            left: 0;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .signin-link a:hover::after {
            width: 100%;
        }

        .notification {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-size: 0.875rem;
            box-shadow: var(--shadow-subtle);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: var(--success);
        }

        .notification.error {
            background: var(--error);
        }

        /* Animations */
        @keyframes fadeInUp {
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

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Light theme */
        body.light {
            --background: #ffffff;
            --surface: #f8fafc;
            --surface-light: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .theme-toggle,
            .back-link {
                top: 1rem;
            }
            
            .theme-toggle {
                right: 1rem;
            }
            
            .back-link {
                left: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
  <div class="floating-bg"></div>
  <div class="floating-bg"></div>

  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="4"></circle>
      <path d="M12 2v2"></path>
      <path d="M12 20v2"></path>
      <path d="m4.93 4.93 1.41 1.41"></path>
      <path d="m17.66 17.66 1.41 1.41"></path>
      <path d="M2 12h2"></path>
      <path d="M20 12h2"></path>
      <path d="m6.34 17.66-1.41 1.41"></path>
      <path d="m19.07 4.93-1.41 1.41"></path>
    </svg>
  </button>

    <a href="index.php" class="back-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m12 19-7-7 7-7"></path>
            <path d="M19 12H5"></path>
        </svg>
        Back to Home
    </a>

    <div class="container">
        <div class="logo-section">
            <div class="logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/>
                    <path d="M9 18h6"/>
                    <path d="M10 22h4"/>
                </svg>
            </div>
            <div class="brand-name">SmartPitchHub</div>
            <h1 class="welcome-text">Create Account</h1>
            <p class="subtitle">Join our platform to start your journey</p>
        </div>


    <div class="form-container">
      <?php
      if (isset($_SESSION['registration_error'])) {
          echo '<div class="notification error show">' . htmlspecialchars($_SESSION['registration_error']) . '</div>';
          unset($_SESSION['registration_error']);
      }
      ?>

      <form id="registerForm" method="POST">
        <!-- User Type Selection -->
        <div class="form-group">
          <label class="form-label">I am a:</label>
          <div class="user-type-selection">
            <div class="user-type-option">
              <input type="radio" id="founder" name="userType" value="founder" class="user-type-radio" required>
              <label for="founder" class="user-type-label">Founder</label>
            </div>
            <div class="user-type-option">
              <input type="radio" id="investor" name="userType" value="investor" class="user-type-radio" required>
              <label for="investor" class="user-type-label">Investor</label>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="fullName">Full Name</label>
          <input type="text" id="fullName" name="fullName" class="form-input" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-input" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="contact">Contact Number</label>
          <input type="text" id="contact" name="contact" class="form-input" required>
        </div>

        <div class="form-group" id="startupNameGroup" style="display:none;">
          <label class="form-label" for="startup_name">Startup Name</label>
          <input type="text" id="startup_name" name="startup_name" class="form-input">
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="password-container">
            <input type="password" id="password" name="password" class="form-input" required>
            <button type="button" class="password-toggle" id="passwordToggle">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <div class="password-strength">
            <div class="strength-bars">
              <div class="strength-bar"></div>
              <div class="strength-bar"></div>
              <div class="strength-bar"></div>
              <div class="strength-bar"></div>
            </div>
            <div class="strength-text">Password strength: <span id="strengthLevel">Weak</span></div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirmPassword">Confirm Password</label>
          <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" required>
        </div>

        <!-- reCAPTCHA -->
        <div class="form-group">
          <div class="g-recaptcha" data-sitekey="6LfX_o4rAAAAAPwV_1z9ud29yb2LkywVZsvI9Z9D"></div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
          <div class="loading-spinner"></div>
          <span class="btn-text">Create Account</span>
        </button>
      </form>

      <div class="signin-link">
        Already have an account? <a href="login.php">Sign in here</a>
      </div>
    </div>
  </div>

  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
// =======================
// Theme toggle functionality
// =======================
const themeToggle = document.getElementById('themeToggle');
const body = document.body;

// Load saved theme or default to dark
const savedTheme = localStorage.getItem('theme') || 'dark';
if (savedTheme === 'light') {
    body.classList.add('light');
} else {
    body.classList.remove('light');
}

themeToggle.addEventListener('click', () => {
    body.classList.toggle('light');
    const theme = body.classList.contains('light') ? 'light' : 'dark';
    localStorage.setItem('theme', theme);
    themeToggle.style.transform = 'scale(0.9)';
    setTimeout(() => themeToggle.style.transform = 'scale(1)', 150);
});

// =======================
// Password toggle functionality
// =======================
const passwordToggle = document.getElementById('passwordToggle');
const passwordInput = document.getElementById('password');

passwordToggle.addEventListener('click', () => {
    const type = passwordInput.type === 'password' ? 'text' : 'password';
    passwordInput.type = type;

    const svg = passwordToggle.querySelector('svg');
    if (svg) {
        if (type === 'text') {
            svg.innerHTML = `
                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                <path d="m10.73 5.08-1.2-.96C7.07 2.78 4.5 6.07 2 12c1.73 4.39 4 6.5 6.5 8.42l1.2-.96"/>
                <path d="M15.27 18.92c2.46-1.34 4.73-3.45 6.73-6.92-1.73-4.39-4-6.5-6.5-8.42"/>
            `;
        } else {
            svg.innerHTML = `
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
            `;
        }
    }
});

// =======================
// Password strength checker
// =======================
const strengthBars = document.querySelectorAll('.strength-bar');
const strengthLevel = document.getElementById('strengthLevel');

function checkPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;
    return score;
}

function updatePasswordStrength(password) {
    const score = checkPasswordStrength(password);
    const levels = ['Weak', 'Fair', 'Good', 'Strong'];
    const classes = ['weak', 'fair', 'good', 'strong'];

    strengthBars.forEach(bar => bar.classList.remove('active', 'weak', 'fair', 'good', 'strong'));
    for (let i = 0; i < score; i++) {
        strengthBars[i].classList.add('active', classes[i]);
    }
    strengthLevel.textContent = levels[score - 1] || 'Weak';
}

passwordInput.addEventListener('input', e => updatePasswordStrength(e.target.value));

// =======================
// Form validation helpers
// =======================
function showError(inputId, message) {
    const input = document.getElementById(inputId);
    const errorDiv = document.getElementById(inputId + 'Error');
    if (input) input.classList.add('error');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.classList.add('show');
    }
}

function clearError(inputId) {
    const input = document.getElementById(inputId);
    const errorDiv = document.getElementById(inputId + 'Error');
    if (input) input.classList.remove('error');
    if (errorDiv) errorDiv.classList.remove('show');
}

function clearAllErrors() {
    ['userType','fullName','email','password','confirmPassword'].forEach(clearError);
}

// =======================
// Email validation
// =======================
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// =======================
// Notifications
// =======================
function showNotification(message, type='success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 5000);
}

// =======================
// Founder/Investor toggle
// =======================
const founderRadio = document.getElementById('founder');
const investorRadio = document.getElementById('investor');
const startupGroup = document.getElementById('startupNameGroup');

founderRadio.addEventListener('change', () => { if (founderRadio.checked) startupGroup.style.display = 'block'; });
investorRadio.addEventListener('change', () => { if (investorRadio.checked) startupGroup.style.display = 'none'; });

// Form submission is handled by PHP directly
</script>

</body>
</html>
