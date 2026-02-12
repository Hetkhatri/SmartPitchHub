<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Handle user actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $userId = $_GET['id'] ?? null;
    $role = $_GET['role'] ?? null;

    if ($action === 'add') {
        // Redirect to add user form
        header('Location: users_add_form.php');
        exit();
    } elseif ($action === 'delete' && $userId && $role) {
        // Delete user logic based on role
        if ($role === 'entrepreneur') {
            $stmt = $conn->prepare("DELETE FROM entrepreneurs WHERE id = ?");
        } elseif ($role === 'investor') {
            $stmt = $conn->prepare("DELETE FROM investors WHERE id = ?");
        }
        if (isset($stmt)) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $message = "User deleted successfully";
        }
    } elseif ($action === 'activate' && $userId && $role) {
        // Note: Current schema doesn't have status field in entrepreneurs/investors tables
        // We'll need to add status fields to these tables or handle differently
        $message = "User activation not implemented yet";
    } elseif ($action === 'deactivate' && $userId && $role) {
        // Note: Current schema doesn't have status field in entrepreneurs/investors tables
        // We'll need to add status fields to these tables or handle differently
        $message = "User deactivation not implemented yet";
    }
}

// Handle form submission for adding new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $startup_name = trim($_POST['startup_name'] ?? '');

    if (!empty($name) && !empty($email) && !empty($contact) && !empty($_POST['password']) && !empty($role)) {
        if ($role === 'entrepreneur') {
            $stmt = $conn->prepare("INSERT INTO entrepreneurs (name, email, contact, password, startup_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $contact, $password, $startup_name);
        } elseif ($role === 'investor') {
            $stmt = $conn->prepare("INSERT INTO investors (name, email, contact, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $contact, $password);
        }

        if ($stmt->execute()) {
            $message = "User added successfully";
            header("Location: users.php");
            exit();
        } else {
            $error = "Error adding user: " . $conn->error;
        }
    } else {
        $error = "Please fill in all required fields";
    }
}

// Get all users from entrepreneurs and investors tables
$users = $conn->query("
    SELECT 'entrepreneur' as role, e.id, e.name, e.email, 'active' as status, e.created_at,
           0 as investment_count,
           COALESCE(p.pitch_count, 0) as pitch_count
    FROM entrepreneurs e
    LEFT JOIN (
        SELECT entrepreneur_id, COUNT(*) as pitch_count
        FROM pitches
        GROUP BY entrepreneur_id
    ) p ON e.id = p.entrepreneur_id

    UNION ALL

    SELECT 'investor' as role, i.id, i.name, i.email, 'active' as status, i.created_at,
           COALESCE(inv.investment_count, 0) as investment_count,
           0 as pitch_count
    FROM investors i
    LEFT JOIN (
        SELECT investor_id, COUNT(*) as investment_count
        FROM investments
        GROUP BY investor_id
    ) inv ON i.id = inv.investor_id

    ORDER BY created_at DESC
");
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">User Management</h1>
        <p>Manage all users in the system</p>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-content">
        <div class="content-section">
            <div class="section-header">
                <h3>All Users</h3>
                <a href="users.php?action=add" class="btn btn-primary">Add New User</a>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge" style="background: <?php 
                                    echo $user['role'] === 'admin' ? '#667eea' : 
                                         ($user['role'] === 'investor' ? '#10b981' : '#f59e0b'); 
                                ?>;">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'investor'): ?>
                                    <?php echo $user['investment_count']; ?> investments
                                <?php elseif ($user['role'] === 'entrepreneur'): ?>
                                    <?php echo $user['pitch_count']; ?> pitches
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php 
                                    echo $user['status'] === 'active' ? '#10b981' : 
                                         ($user['status'] === 'inactive' ? '#6b7280' : '#dc2626'); 
                                ?>;">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">Edit</a>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <a href="users.php?action=deactivate&id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">Deactivate</a>
                                    <?php elseif ($user['status'] === 'inactive'): ?>
                                        <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">Activate</a>
                                    <?php endif; ?>
                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    color: white;
    font-size: 0.75rem;
    font-weight: 500;
}
</style>

<?php include 'includes/footer.php'; ?>
