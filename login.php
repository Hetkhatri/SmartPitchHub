<?php
session_start();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Simple demo authentication (in real app, validate against database)
    if (!empty($email) && !empty($password)) {
        $_SESSION['user_role'] = $role;
        $_SESSION['user_email'] = $email;
        
        // Set demo user data based on role
        if ($role === 'investor') {
            $_SESSION['user_name'] = 'John Investor';
            $_SESSION['user_id'] = 1;
            header('Location: dashboard-investor.php');
        } else {
            $_SESSION['user_name'] = 'Sarah Entrepreneur';
            $_SESSION['user_id'] = 2;
            $_SESSION['startup_name'] = 'InnovateTech';
            header('Location: dashboard-entrepreneur.php');
        }
        exit();
    }
}

// Get role from query parameter
$role = isset($_GET['role']) ? $_GET['role'] : 'investor';
$roleTitle = ucfirst($role);
?>
<?php include 'includes/header.php'; ?>

<div class="dashboard" style="padding-top: 8rem; min-height: 100vh;">
    <div class="content-section" style="max-width: 400px; margin: 0 auto;">
        <div class="text-center mb-4">
            <i class="fas fa-rocket" style="font-size: 3rem; color: #667eea; margin-bottom: 1rem;"></i>
            <h2><?php echo $roleTitle; ?> Login</h2>
            <p>Sign in to access your <?php echo $role; ?> dashboard</p>
        </div>

        <form method="POST" action="login.php">
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                Sign In as <?php echo $roleTitle; ?>
            </button>
            
            <div class="text-center">
                <p>Don't have an account? <a href="register.php?role=<?php echo $role; ?>" style="color: #667eea;">Sign up here</a></p>
                <p>
                    <a href="index.php" style="color: #6b7280; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </p>
            </div>
        </form>

        <!-- Demo Credentials Note -->
        <div style="margin-top: 2rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <h4 style="color: #f59e0b; margin-bottom: 0.5rem;">Demo Note</h4>
            <p style="font-size: 0.875rem; margin: 0; color: #6b7280;">
                This is a demo login. You can use any email and password to login. 
                The system will automatically create a demo <?php echo $role; ?> account.
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
