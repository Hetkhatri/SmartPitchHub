<?php
session_start();

// 1. REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['admin_id'])) {
    header("Location: admin-dashboard.php");
    exit;
}

// 2. HANDLE LOGIN REQUEST (Backend Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Read JSON input from JavaScript
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['email']) && isset($input['password'])) {
        
        // Connect to Database
        require_once '../db.php'; 

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(["success" => false, "message" => "Database connection failed"]);
            exit;
        }

        $email = $input['email'];
        $password = $input['password'];

        // Secure Query
        $stmt = $conn->prepare("SELECT admin_id, name, email, password, status FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Check if account is active
            if ($row['status'] !== 'active') {
                echo json_encode(["success" => false, "message" => "Account is inactive"]);
                exit;
            }

            // Verify Password
            if (password_verify($password, $row['password'])) {
                // Set Session
                $_SESSION['admin_id'] = $row['admin_id'];
                $_SESSION['admin_name'] = $row['name'];
                $_SESSION['admin_email'] = $row['email'];
                $_SESSION['role'] = 'admin';

                echo json_encode([
                    "success" => true, 
                    "message" => "Login Successful", 
                    "redirect" => "admin-dashboard.php" 
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid password"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Account not found"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
    }
    
    // Exit to prevent HTML from loading during API call
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Admin Portal - Secure login to access the dashboard">
  <title>Admin Portal | Login</title>
  
  <!-- Google Fonts - Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  
  <style>
    /* ========== CSS Variables / Design Tokens ========== */
    :root {
      /* Light Theme (Default) */
      --background: hsl(210, 40%, 98%);
      --foreground: hsl(222, 47%, 11%);
      --card: hsl(0, 0%, 100%);
      --card-foreground: hsl(222, 47%, 11%);
      --primary: hsl(199, 89%, 42%);
      --primary-foreground: hsl(0, 0%, 100%);
      --secondary: hsl(210, 40%, 94%);
      --secondary-foreground: hsl(222, 47%, 11%);
      --muted: hsl(210, 40%, 94%);
      --muted-foreground: hsl(215, 16%, 47%);
      --border: hsl(214, 32%, 85%);
      --input: hsl(214, 32%, 91%);
      --ring: hsl(199, 89%, 42%);
      --glow-primary: hsl(199, 89%, 42%);
      --orb-1: hsl(199, 89%, 60%);
      --orb-2: hsl(280, 80%, 65%);
      --grid-color: hsl(214, 32%, 80%);
      --radius: 0.75rem;
    }

    .dark {
      --background: hsl(222, 47%, 6%);
      --foreground: hsl(210, 40%, 98%);
      --card: hsl(222, 47%, 8%);
      --card-foreground: hsl(210, 40%, 98%);
      --primary: hsl(199, 89%, 48%);
      --primary-foreground: hsl(222, 47%, 6%);
      --secondary: hsl(217, 33%, 17%);
      --secondary-foreground: hsl(210, 40%, 98%);
      --muted: hsl(217, 33%, 17%);
      --muted-foreground: hsl(215, 20%, 55%);
      --border: hsl(217, 33%, 20%);
      --input: hsl(217, 33%, 17%);
      --ring: hsl(199, 89%, 48%);
      --glow-primary: hsl(199, 89%, 48%);
      --orb-1: hsl(199, 89%, 48%);
      --orb-2: hsl(280, 80%, 50%);
      --grid-color: hsl(217, 33%, 15%);
    }

    /* ========== Reset & Base Styles ========== */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Outfit', system-ui, -apple-system, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      min-height: 100vh;
      transition: background-color 0.3s ease, color 0.3s ease;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* ========== Animations ========== */
    @keyframes fade-in {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fade-in-up {
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
        transform: translateY(0) scale(1);
      }
      50% {
        transform: translateY(-20px) scale(1.05);
      }
    }

    @keyframes pulse-glow {
      0%, 100% {
        opacity: 0.4;
        transform: scale(1);
      }
      50% {
        opacity: 0.6;
        transform: scale(1.1);
      }
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    .animate-fade-in {
      animation: fade-in 0.6s ease-out forwards;
    }

    .animate-fade-in-up {
      animation: fade-in-up 0.8s ease-out forwards;
    }

    .animate-float {
      animation: float 6s ease-in-out infinite;
    }

    .animate-pulse-glow {
      animation: pulse-glow 4s ease-in-out infinite;
    }

    .animation-delay-200 { animation-delay: 0.2s; }
    .animation-delay-400 { animation-delay: 0.4s; }
    .animation-delay-600 { animation-delay: 0.6s; }

    .opacity-0 { opacity: 0; }

    /* ========== Layout ========== */
    .login-container {
      min-height: 100vh;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    /* ========== Background Effects ========== */
    .background {
      position: absolute;
      inset: 0;
      overflow: hidden;
    }

    .grid-pattern {
      position: absolute;
      inset: 0;
      opacity: 0.3;
      background-image: 
        linear-gradient(hsla(214, 32%, 80%, 0.5) 1px, transparent 1px),
        linear-gradient(90deg, hsla(214, 32%, 80%, 0.5) 1px, transparent 1px);
      background-size: 60px 60px;
    }

    .dark .grid-pattern {
      opacity: 0.2;
      background-image: 
        linear-gradient(hsla(217, 33%, 15%, 0.5) 1px, transparent 1px),
        linear-gradient(90deg, hsla(217, 33%, 15%, 0.5) 1px, transparent 1px);
    }

    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(60px);
    }

    .orb-1 {
      top: 25%;
      left: -8rem;
      width: 24rem;
      height: 24rem;
      background-color: var(--orb-1);
      opacity: 0.2;
    }

    .orb-2 {
      bottom: 25%;
      right: -8rem;
      width: 24rem;
      height: 24rem;
      background-color: var(--orb-2);
      opacity: 0.15;
    }

    .orb-center {
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 600px;
      height: 600px;
      background-color: var(--primary);
      opacity: 0.05;
    }

    .gradient-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, var(--background), transparent, var(--background));
    }

    /* ========== Theme Toggle ========== */
    .theme-toggle {
      position: absolute;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 20;
    }

    .theme-toggle-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: var(--radius);
      border: none;
      background-color: transparent;
      color: var(--foreground);
      cursor: pointer;
      transition: background-color 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .theme-toggle-btn:hover {
      background-color: var(--secondary);
    }

    .theme-toggle-btn svg {
      width: 1.25rem;
      height: 1.25rem;
      position: absolute;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }

    .theme-toggle-btn .sun-icon {
      transform: rotate(0deg) scale(1);
      opacity: 1;
    }

    .theme-toggle-btn .moon-icon {
      transform: rotate(90deg) scale(0);
      opacity: 0;
    }

    .dark .theme-toggle-btn .sun-icon {
      transform: rotate(-90deg) scale(0);
      opacity: 0;
    }

    .dark .theme-toggle-btn .moon-icon {
      transform: rotate(0deg) scale(1);
      opacity: 1;
    }

    /* ========== Card ========== */
    .login-card-wrapper {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 28rem;
      margin: 0 1rem;
    }

    .login-card {
      background-color: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid var(--border);
      border-radius: 1rem;
      padding: 2rem;
      box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.1),
        0 0 20px -5px var(--glow-primary);
    }

    .dark .login-card {
      background-color: rgba(15, 23, 42, 0.8);
      box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.25),
        0 0 20px -5px var(--glow-primary);
    }

    @media (min-width: 768px) {
      .login-card {
        padding: 2.5rem;
      }
    }

    /* ========== Header ========== */
    .card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo-container {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 4rem;
      height: 4rem;
      border-radius: 1rem;
      background-color: rgba(14, 165, 233, 0.1);
      border: 1px solid rgba(14, 165, 233, 0.2);
      margin-bottom: 1.5rem;
      box-shadow: 0 0 20px -5px var(--glow-primary);
    }

    .logo-container svg {
      width: 2rem;
      height: 2rem;
      color: var(--primary);
    }

    .card-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--foreground);
      margin-bottom: 0.5rem;
    }

    @media (min-width: 768px) {
      .card-title {
        font-size: 1.875rem;
      }
    }

    .card-subtitle {
      font-size: 0.875rem;
      color: var(--muted-foreground);
    }

    /* ========== Form ========== */
    .login-form {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .form-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground);
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      width: 1.25rem;
      height: 1.25rem;
      color: var(--muted-foreground);
      pointer-events: none;
    }

    .form-input {
      width: 100%;
      height: 3rem;
      padding: 0.75rem 1rem 0.75rem 3rem;
      font-size: 1rem;
      font-family: inherit;
      color: var(--foreground);
      background-color: rgba(148, 163, 184, 0.1);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      outline: none;
      transition: all 0.3s ease;
    }

    .form-input::placeholder {
      color: var(--muted-foreground);
    }

    .form-input:focus {
      border-color: rgba(14, 165, 233, 0.5);
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
      background-color: var(--secondary);
    }

    .form-input.has-suffix {
      padding-right: 3rem;
    }

    .password-toggle {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      color: var(--muted-foreground);
      transition: color 0.2s ease;
    }

    .password-toggle:hover {
      color: var(--foreground);
    }

    .password-toggle svg {
      width: 1.25rem;
      height: 1.25rem;
    }

    /* ========== Checkbox Row ========== */
    .checkbox-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .checkbox-wrapper {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .checkbox-input {
      width: 1rem;
      height: 1rem;
      accent-color: var(--primary);
      cursor: pointer;
    }

    .checkbox-label {
      font-size: 0.875rem;
      color: var(--muted-foreground);
      cursor: pointer;
      transition: color 0.2s ease;
    }

    .checkbox-label:hover {
      color: var(--foreground);
    }

    .forgot-link {
      font-size: 0.875rem;
      color: var(--primary);
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .forgot-link:hover {
      color: rgba(14, 165, 233, 0.8);
    }

    /* ========== Button ========== */
    .submit-btn {
      width: 100%;
      height: 3rem;
      margin-top: 0.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      font-size: 1rem;
      font-weight: 500;
      font-family: inherit;
      color: var(--primary-foreground);
      background-color: var(--primary);
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 0 20px -5px var(--glow-primary);
    }

    .submit-btn:hover:not(:disabled) {
      background-color: rgba(14, 165, 233, 0.9);
      box-shadow: 0 0 40px -10px var(--glow-primary);
      transform: scale(1.02);
    }

    .submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .submit-btn svg {
      width: 1.25rem;
      height: 1.25rem;
    }

    .submit-btn .spinner {
      animation: spin 1s linear infinite;
    }

    /* ========== Footer ========== */
    .card-footer {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(148, 163, 184, 0.2);
      text-align: center;
    }

    .footer-text {
      font-size: 0.875rem;
      color: var(--muted-foreground);
    }

    .security-badges {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      margin-top: 1rem;
    }

    .security-badge {
      display: flex;
      align-items: center;
      gap: 0.375rem;
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .security-badge .dot {
      width: 0.5rem;
      height: 0.5rem;
      border-radius: 50%;
      background-color: #22c55e;
      animation: pulse 2s ease-in-out infinite;
    }

    .security-badge svg {
      width: 0.75rem;
      height: 0.75rem;
    }

    /* ========== Help Text ========== */
    .help-text {
      text-align: center;
      font-size: 0.75rem;
      color: var(--muted-foreground);
      margin-top: 1.5rem;
    }

    .help-link {
      color: var(--primary);
      text-decoration: none;
    }

    .help-link:hover {
      text-decoration: underline;
    }

    /* ========== Toast Notification ========== */
    .toast {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem 1.5rem;
      box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.2);
      z-index: 100;
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.3s ease;
    }

    .toast.show {
      transform: translateY(0);
      opacity: 1;
    }

    .toast-title {
      font-weight: 600;
      color: var(--foreground);
      margin-bottom: 0.25rem;
    }

    .toast-description {
      font-size: 0.875rem;
      color: var(--muted-foreground);
    }
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Theme Toggle -->
    <div class="theme-toggle">
      <button class="theme-toggle-btn" id="themeToggle" aria-label="Toggle theme">
        <i data-lucide="sun" class="sun-icon"></i>
        <i data-lucide="moon" class="moon-icon"></i>
      </button>
    </div>

    <!-- Animated Background -->
    <div class="background">
      <div class="grid-pattern"></div>
      <div class="orb orb-1 animate-float animate-pulse-glow"></div>
      <div class="orb orb-2 animate-float animation-delay-400"></div>
      <div class="orb orb-center"></div>
      <div class="gradient-overlay"></div>
    </div>

    <!-- Login Card -->
    <div class="login-card-wrapper">
      <div class="login-card animate-fade-in-up">
        <!-- Header -->
        <div class="card-header animate-fade-in opacity-0 animation-delay-200">
          <div class="logo-container">
            <i data-lucide="shield" id="shield-icon"></i>
          </div>
          <h1 class="card-title">Admin Portal</h1>
          <p class="card-subtitle">Sign in to access the dashboard</p>
        </div>

        <!-- Form -->
        <form class="login-form" id="loginForm">
          <div class="form-group animate-fade-in opacity-0 animation-delay-400">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-wrapper">
              <i data-lucide="mail" class="input-icon"></i>
              <input 
                type="email" 
                id="email" 
                class="form-input" 
                placeholder="admin@company.com"
                required
              >
            </div>
          </div>

          <div class="form-group animate-fade-in opacity-0 animation-delay-600">
            <label for="password" class="form-label">Password</label>
            <div class="input-wrapper">
              <i data-lucide="lock" class="input-icon"></i>
              <input 
                type="password" 
                id="password" 
                class="form-input has-suffix" 
                placeholder="Enter your password"
                required
              >
              <button type="button" class="password-toggle" id="passwordToggle">
                <i data-lucide="eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <div class="checkbox-row animate-fade-in opacity-0 animation-delay-600">
            <div class="checkbox-wrapper">
              <input type="checkbox" id="remember" class="checkbox-input">
              <label for="remember" class="checkbox-label">Remember me</label>
            </div>
            <a href="#" class="forgot-link">Forgot password?</a>
          </div>

          <button type="submit" class="submit-btn" id="submitBtn">
            Sign In
          </button>
        </form>

        <!-- Footer -->
        <div class="card-footer animate-fade-in opacity-0 animation-delay-600">
          <p class="footer-text">Protected by enterprise-grade security</p>
          <div class="security-badges">
            <div class="security-badge">
              <span class="dot"></span>
              SSL Encrypted
            </div>
            <div class="security-badge">
              <i data-lucide="shield" class="shield-small"></i>
              2FA Ready
            </div>
          </div>
        </div>
      </div>

      <p class="help-text animate-fade-in opacity-0 animation-delay-600">
        Need help? Contact <a href="mailto:support@company.com" class="help-link">IT Support</a>
      </p>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
      <div class="toast-title" id="toastTitle">Login Successful</div>
      <div class="toast-description" id="toastDescription">Welcome back, Administrator!</div>
    </div>
  </div>

  <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // ========== Theme Toggle ==========
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    
    // Check for saved theme preference or default to dark
    const savedTheme = localStorage.getItem('admin-theme') || 'dark';
    if (savedTheme === 'dark') {
      body.classList.add('dark');
    }

    themeToggle.addEventListener('click', () => {
      body.classList.toggle('dark');
      const isDark = body.classList.contains('dark');
      localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
    });

    // ========== Password Toggle ==========
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    let passwordVisible = false;

    passwordToggle.addEventListener('click', () => {
      passwordVisible = !passwordVisible;
      passwordInput.type = passwordVisible ? 'text' : 'password';
      eyeIcon.setAttribute('data-lucide', passwordVisible ? 'eye-off' : 'eye');
      lucide.createIcons();
    });

    // ========== Toast Notification Function ==========
    const toast = document.getElementById('toast');
    
    function showToast(title, description) {
      const toastTitle = document.getElementById('toastTitle');
      const toastDescription = document.getElementById('toastDescription');
      
      toastTitle.textContent = title;
      toastDescription.textContent = description;
      
      toast.classList.add('show');
      
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }

    // ========== Form Submit (CONNECTED TO BACKEND) ==========
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    let isLoading = false;

    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      if (isLoading) return;
      isLoading = true;
      
      // 1. Get User Input
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;

      // 2. Show loading state (Your original spinner)
      submitBtn.innerHTML = `
        <svg class="spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        Signing in...
      `;
      submitBtn.disabled = true;

      try {
          // 3. ACTUAL API CALL to index.php
          const response = await fetch('index.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ email: email, password: password })
          });

          // 4. Handle Response
          // We get text first to catch any PHP fatal errors that aren't JSON
          const text = await response.text(); 
          let data;
          try {
             data = JSON.parse(text);
          } catch (err) {
             throw new Error("Server Error: " + text);
          }

          if (data.success) {
              // Success: Show toast and redirect
              showToast('Login Successful', data.message);
              
              setTimeout(() => {
                  window.location.href = data.redirect; // Redirect to dashboard
              }, 1000);
          } else {
              // Failed: Show error toast and reset button
              showToast('Login Failed', data.message);
              submitBtn.innerHTML = 'Sign In';
              submitBtn.disabled = false;
          }

      } catch (error) {
          console.error('Login Error:', error);
          showToast('System Error', 'Could not connect to server. Check console.');
          submitBtn.innerHTML = 'Sign In';
          submitBtn.disabled = false;
      }
      
      isLoading = false;
    });
</script>
</body>
</html>
