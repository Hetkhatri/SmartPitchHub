<?php
session_start();
include '../db.php'; // Include the main database connection

// Create admin table if it doesn't exist
$create_admin_table = "
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_admin_table);

// Create default admin if it doesn't exist
$check_admin = $conn->query("SELECT id FROM admins WHERE email = 'admin@smartpitchhub.com'");
if ($check_admin->num_rows === 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (name, email, password) VALUES ('Admin User', 'admin@smartpitchhub.com', '$hashed_password')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Check if the user is an admin
        $stmt = $conn->prepare("SELECT id, name, password FROM admins WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_id'] = $user['id'];
                header('Location: index.php');
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Please fill in all fields";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Smart Pitch Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard" style="padding-top: 8rem; min-height: 100vh;">
        <div class="content-section" style="max-width: 400px; margin: 0 auto;">
            <div class="text-center mb-4">
                <i class="fas fa-lock" style="font-size: 3rem; color: #667eea; margin-bottom: 1rem;"></i>
                <h2>Admin Login</h2>
                <p>Access the admin panel</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                    Sign In
                </button>
                
                <div class="text-center">
                    <p>
                        <a href="../index.php" style="color: #6b7280; text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
