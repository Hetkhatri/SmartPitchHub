<?php
session_start();
include 'db.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Validate against database
    if (!empty($email) && !empty($password)) {
        // Query database to get user information
        if ($role === 'investor') {
            $stmt = $conn->prepare("SELECT id, name, password FROM investors WHERE email = ?");
        } else {
            $stmt = $conn->prepare("SELECT id, name, password, startup_name FROM entrepreneurs WHERE email = ?");
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password (assuming passwords are hashed)
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['user_id'] = $user['id'];
                
                if ($role === 'investor') {
                    header('Location: dashboard-investor.php');
                } else {
                    $_SESSION['startup_name'] = $user['startup_name'] ?? 'My Startup';
                    header('Location: dashboard-entrepreneur.php');
                }
                exit();
            } else {
                $_SESSION['login_error'] = "Invalid email or password";
            }
        } else {
            $_SESSION['login_error'] = "Invalid email or password";
        }
    } else {
        $_SESSION['login_error'] = "Please fill in all fields";
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

        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['login_error']; 
                    unset($_SESSION['login_error']);
                ?>
            </div>
        <?php endif; ?>

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
