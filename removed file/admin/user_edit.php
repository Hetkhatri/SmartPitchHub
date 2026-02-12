<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Get user ID from URL
$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: users.php');
    exit();
}

// Get user data
$user = $conn->query("SELECT * FROM users WHERE id = $userId")->fetch_assoc();
if (!$user) {
    header('Location: users.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Update user
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $role, $status, $userId);

    if ($stmt->execute()) {
        $message = "User updated successfully";
        // Refresh user data
        $user = $conn->query("SELECT * FROM users WHERE id = $userId")->fetch_assoc();
    } else {
        $error = "Failed to update user";
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Edit User</h1>
        <p>Update user information</p>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-content">
        <div class="content-section">
            <form method="POST" action="user_edit.php?id=<?php echo $userId; ?>">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="investor" <?php echo $user['role'] === 'investor' ? 'selected' : ''; ?>>Investor</option>
                        <option value="entrepreneur" <?php echo $user['role'] === 'entrepreneur' ? 'selected' : ''; ?>>Entrepreneur</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-actions">
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
}
</style>

<?php include 'includes/footer.php'; ?>
