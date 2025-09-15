<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

$error = $_GET['error'] ?? '';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Add New User</h1>
        <p>Fill in the details below to add a new user</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="users.php" class="form-container">
        <input type="hidden" name="add_user" value="1" />
        <div class="form-group">
            <label for="name">Name<span style="color:red;">*</span></label>
            <input type="text" id="name" name="name" required />
        </div>
        <div class="form-group">
            <label for="email">Email<span style="color:red;">*</span></label>
            <input type="email" id="email" name="email" required />
        </div>
        <div class="form-group">
            <label for="contact">Contact<span style="color:red;">*</span></label>
            <input type="text" id="contact" name="contact" required />
        </div>
        <div class="form-group">
            <label for="password">Password<span style="color:red;">*</span></label>
            <input type="password" id="password" name="password" required />
        </div>
        <div class="form-group">
            <label for="role">Role<span style="color:red;">*</span></label>
            <select id="role" name="role" required>
                <option value="">Select Role</option>
                <option value="entrepreneur">Entrepreneur</option>
                <option value="investor">Investor</option>
            </select>
        </div>
        <div class="form-group" id="startup_name_group" style="display:none;">
            <label for="startup_name">Startup Name</label>
            <input type="text" id="startup_name" name="startup_name" />
        </div>
        <button type="submit" class="btn btn-primary">Add User</button>
        <a href="users.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
    </form>
</div>

<style>
.form-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
.form-group {
    margin-bottom: 1rem;
}
label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}
input[type="text"],
input[type="email"],
input[type="password"],
select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 1rem;
}
.btn {
    padding: 0.5rem 1rem;
    font-size: 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.btn-primary {
    background-color: #3b82f6;
    color: white;
}
.btn-secondary {
    background-color: #6b7280;
    color: white;
    text-decoration: none;
}
</style>

<script>
document.getElementById('role').addEventListener('change', function() {
    var startupGroup = document.getElementById('startup_name_group');
    if (this.value === 'entrepreneur') {
        startupGroup.style.display = 'block';
    } else {
        startupGroup.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
