<?php
session_start();
require_once 'db.php';

// Initialize variables
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Check if user is already logged in, redirect to dashboard
if(isset($_SESSION["user_id"]) && isset($_SESSION["user_role"])){
    if($_SESSION["user_role"] === "entrepreneur"){
        // Use forward slash /
        header("Location: dashboards/Entrepreneur-dashboard.php");
    } else {
        header("Location: dashboards/investor-dashboard.php");
    }
    exit;
}

// Process form data when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Check if email is empty
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter your email.";
    } else{
        $email = trim($_POST["email"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($email_err) && empty($password_err)){
        // Check if user is an entrepreneur
        $sql = "SELECT id, name, email, password, startup_name FROM entrepreneurs WHERE email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = $email;
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    mysqli_stmt_bind_result($stmt, $id, $name, $email, $hashed_password, $startup_name);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables (matching dashboard expectations)
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["user_email"] = $email;
                            $_SESSION["username"] = $name;
                            $_SESSION["user_role"] = "entrepreneur";
                            $_SESSION["startup_name"] = $startup_name;
                            
                            // Redirect user to entrepreneur dashboard
                            header("location: dashboards/Entrepreneur-dashboard.php");
                        } else{
                            // Password is not valid
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else{
                    // Check if user is an investor
                    $sql = "SELECT id, name, email, password FROM investors WHERE email = ?";
                    
                    if($stmt = mysqli_prepare($conn, $sql)){
                        mysqli_stmt_bind_param($stmt, "s", $param_email);
                        $param_email = $email;
                        
                        if(mysqli_stmt_execute($stmt)){
                            mysqli_stmt_store_result($stmt);
                            
                            if(mysqli_stmt_num_rows($stmt) == 1){
                                mysqli_stmt_bind_result($stmt, $id, $name, $email, $hashed_password);
                                if(mysqli_stmt_fetch($stmt)){
                                    if(password_verify($password, $hashed_password)){
                                        // Password is correct, start a new session
                                        session_start();
                                        
                                        // Store data in session variables (matching dashboard expectations)
                                        $_SESSION["loggedin"] = true;
                                        $_SESSION["user_id"] = $id;
                                        $_SESSION["user_email"] = $email;
                                        $_SESSION["username"] = $name;
                                        $_SESSION["user_role"] = "investor";
                                        
                                        // Redirect user to investor dashboard
                                        header("location: dashboards/investor-dashboard.php");
                                    } else{
                                        // Password is not valid
                                        $login_err = "Invalid email or password.";
                                    }
                                }
                            } else{
                                // Email doesn't exist
                                $login_err = "Invalid email or password.";
                            }
                        } else{
                            $login_err = "Oops! Something went wrong. Please try again later.";
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
            } else{
                $login_err = "Oops! Something went wrong. Please try again later.";
            }
        }
        // mysqli_stmt_close($stmt);
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - SmartPitchHub</title>
    <meta name="description" content="Sign in to SmartPitchHub to access your dashboard and connect with investors or explore startup opportunities.">
    
    <style>
        /* Your existing CSS remains exactly the same */
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
            text-align: center;
        }

        .subtitle {
            color: var(--text-secondary);
            text-align: center;
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

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox {
            width: 1rem;
            height: 1rem;
            accent-color: var(--primary);
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            position: relative;
        }

        .forgot-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1px;
            bottom: -2px;
            left: 0;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .forgot-link:hover::after {
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

        .signup-link {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .signup-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            position: relative;
        }

        .signup-link a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1px;
            bottom: -2px;
            left: 0;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .signup-link a:hover::after {
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
        
        /* PHP error styling */
        .php-error {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: none;
        }
        
        .php-error.show {
            display: block;
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
            <h1 class="welcome-text">Welcome Back</h1>
            <p class="subtitle">Sign in to your account to continue</p>
        </div>

        <div class="form-container">
            <?php 
            if(!empty($login_err)){
                echo '<div class="php-error show">' . $login_err . '</div>';
            }
            ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input <?php echo (!empty($email_err)) ? 'error' : ''; ?>" placeholder="Enter your email address" value="<?php echo $email; ?>" required>
                    <div class="error-message <?php echo (!empty($email_err)) ? 'show' : ''; ?>" id="emailError"><?php echo $email_err; ?></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-input <?php echo (!empty($password_err)) ? 'error' : ''; ?>" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="passwordToggle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="error-message <?php echo (!empty($password_err)) ? 'show' : ''; ?>" id="passwordError"><?php echo $password_err; ?></div>
                </div>

                <div class="form-row">
                    <div class="checkbox-container">
                        <input type="checkbox" id="remember" name="remember" class="checkbox">
                        <label class="checkbox-label" for="remember">Remember me</label>
                    </div>
                    <a href="ForgotPassword/forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <div class="loading-spinner"></div>
                    <span class="btn-text">Sign In</span>
                </button>

                <div class="divider">
                    <span>Or continue with</span>
                </div>

                <div class="social-buttons">
                    <a href="google-login/login.php" class="social-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" style="margin-right: 8px;">
                        <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google
                </a>
                    <button type="button" class="social-btn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        Facebook
                    </button>
                </div>
            </form>

            <div class="signup-link">
                Don't have an account? <a href="register.php">Sign up here</a>
            </div>
        </div>
    </div>

    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        // Check for saved theme or default to dark
        const savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'light') {
            body.classList.add('light');
        }

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('light');
            const theme = body.classList.contains('light') ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            
            // Add button animation
            themeToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                themeToggle.style.transform = 'scale(1)';
            }, 150);
        });

        // Password toggle functionality
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');

        passwordToggle.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            
            // Change icon based on visibility
            const svg = passwordToggle.querySelector('svg');
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
        });

        // Form validation
        function showError(inputId, message) {
            const input = document.getElementById(inputId);
            const errorDiv = document.getElementById(inputId + 'Error');
            
            input.classList.add('error');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
        }

        function clearError(inputId) {
            const input = document.getElementById(inputId);
            const errorDiv = document.getElementById(inputId + 'Error');
            
            input.classList.remove('error');
            errorDiv.classList.remove('show');
        }

        function clearAllErrors() {
            clearError('email');
            clearError('password');
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Hide and remove notification
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 5000);
        }

        // Form submission
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            clearAllErrors();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const remember = document.getElementById('remember').checked;
            
            let isValid = true;
            
            // Validate email
            if (!email) {
                showError('email', 'Email is required');
                isValid = false;
            } else if (!validateEmail(email)) {
                showError('email', 'Please enter a valid email address');
                isValid = false;
            }
            
            // Validate password
            if (!password) {
                showError('password', 'Password is required');
                isValid = false;
            } else if (password.length < 6) {
                showError('password', 'Password must be at least 6 characters');
                isValid = false;
            }
            
            if (!isValid) return;
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            try {
                // Submit the form programmatically since we're using PHP processing
                loginForm.submit();
            } catch (error) {
                showNotification('Login failed. Please try again.', 'error');
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });

        // // Social login buttons
        // document.querySelectorAll('.social-btn').forEach(btn => {
        //     btn.addEventListener('click', (e) => {
        //         const provider = e.currentTarget.textContent.trim();
        //         showNotification(`${provider} login coming soon!`, 'success');
        //     });
        // });

        // Add input animations
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>