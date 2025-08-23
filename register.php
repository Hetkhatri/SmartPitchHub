<?php
session_start();
include 'db.php';
include 'includes/mailer.php';

// Get role from query parameter (before HTML is output)
$role = isset($_GET['role']) ? $_GET['role'] : 'investor';
$roleTitle = ucfirst($role);

// Clear any previous email errors
if (isset($_SESSION['email_error'])) {
    unset($_SESSION['email_error']);
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $startup_name = $_POST['startup_name'] ?? '';
    $contact = $_POST['contact'] ?? ''; // Fixed: Added contact field
    
    // Simple validation
    if (!empty($name) && !empty($email) && !empty($password) && $password === $confirm_password && !empty($contact)) {
        // Generate OTP
        $otp = rand(100000, 999999);

        // Save OTP in DB
        $stmt = $conn->prepare("INSERT INTO otp_verifications (name, email, contact, password, role, startup_name, otp, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bind_param("sssssss", $name, $email, $contact, $hashedPassword, $role, $startup_name, $otp);
        
        if ($stmt->execute()) {
            // Send OTP via PHPMailer
            $emailSent = sendOTP($email, $otp);
            
            if ($emailSent) {
                // Store email in session for verification
                $_SESSION['email'] = $email;
                
                // Redirect to OTP page
                header("Location: verify_otp.php");
                exit();
            } else {
                // Email sending failed - show error message
                $errorMessage = isset($_SESSION['email_error']) ? $_SESSION['email_error'] : "Failed to send OTP email. Please try again.";
                $_SESSION['registration_error'] = $errorMessage;
                
                // Clean up the failed registration from database
                $cleanupStmt = $conn->prepare("DELETE FROM otp_verifications WHERE email = ? AND status = 'pending'");
                $cleanupStmt->bind_param("s", $email);
                $cleanupStmt->execute();
            }
        } else {
            $_SESSION['registration_error'] = "Database error: Could not save registration data.";
        }
    } else {
        $_SESSION['registration_error'] = "Please fill all required fields correctly and ensure passwords match.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard" style="padding-top: 8rem; min-height: 100vh;">
    <div class="content-section" style="max-width: 500px; margin: 0 auto;">
        <div class="text-center mb-4">
            <i class="fas fa-user-plus" style="font-size: 3rem; color: #667eea; margin-bottom: 1rem;"></i>
            <h2>Create <?php echo $roleTitle; ?> Account</h2>
            <p>Join Startup Pitch Hub as a <?php echo $role; ?></p>
        </div>

        <?php if (isset($_SESSION['registration_error'])): ?>
            <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['registration_error']; 
                    unset($_SESSION['registration_error']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
             <div class="form-group">
                <label for="contact">Contact Number *</label>
                <input type="text" id="contact" name="contact" class="form-control" placeholder="Enter Your contact Number" required>
            </div>
            
            <?php if ($role === 'entrepreneur'): ?>
            <div class="form-group">
                <label for="startup_name">Startup/Company Name</label>
                <input type="text" id="startup_name" name="startup_name" class="form-control" placeholder="Enter your startup name">
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Create a password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
            </div>
            <div class="g-recaptcha" data-sitekey="6LdC6K4rAAAAAE-DiP0ybiOtP_Q3JxG1UGgM-Cf_"></div>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                Create <?php echo $roleTitle; ?> Account
            </button>
            <?php
              if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // ✅ reCAPTCHA check
                $recaptchaResponse = $_POST['g-recaptcha-response'];
                $secret = "6LdC6K4rAAAAADq1rBwyrnX5l7irl0FzW7g0Ol2V";
                $verifyResponse = file_get_contents(
                    "https://www.google.com/recaptcha/api/siteverify?secret=" . $secret . "&response=" . $recaptchaResponse
                );
                $responseData = json_decode($verifyResponse, true);
                if (!$responseData["success"]) {
                    die("❌ Captcha verification failed!");
                }
            }
                ?>
            <div class="text-center">
                <p>Already have an account? <a href="login.php?role=<?php echo $role; ?>" style="color: #667eea;">Sign in here</a></p>
                <p>
                    <a href="index.php" style="color: #6b7280; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </p>
            </div>
        </form>

        <!-- Demo Note -->
        <div style="margin-top: 2rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <h4 style="color: #f59e0b; margin-bottom: 0.5rem;">Demo Note</h4>
            <p style="font-size: 0.875rem; margin: 0; color: #6b7280;">
                This is a <?php echo $role;?> registration. You can use any personal information to create an account. 
                The system will automatically create a  <?php echo $role; ?> profile.
            </p>
        </div>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a67d8;
}

.text-center {
    text-align: center;
}

.mb-4 {
    margin-bottom: 2rem;
}
</style>

<?php include 'includes/footer.php'; ?>
