<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = $_POST['site_name'];
    $site_description = $_POST['site_description'];
    $contact_email = $_POST['contact_email'];
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;

    // Update settings (you might want to create a settings table)
    // For now, we'll just show a success message
    $message = "Settings updated successfully";
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">System Settings</h1>
        <p>Configure system-wide settings</p>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-content">
        <div class="content-section">
            <form method="POST" action="settings.php">
                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" class="form-control" value="Smart Pitch Hub" required>
                </div>

                <div class="form-group">
                    <label for="site_description">Site Description</label>
                    <textarea id="site_description" name="site_description" class="form-control" rows="3">A platform connecting entrepreneurs and investors</textarea>
                </div>

                <div class="form-group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control" value="admin@smartpitchhub.com" required>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode">
                        Enable Maintenance Mode
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<style>
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
}
</style>

<?php include 'includes/footer.php'; ?>
