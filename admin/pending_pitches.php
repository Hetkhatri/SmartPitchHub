<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Handle pitch approval actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $pitchId = $_GET['id'] ?? null;

    if ($pitchId) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE pitches SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $pitchId);
            $stmt->execute();
            $message = "Pitch approved successfully";
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE pitches SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $pitchId);
            $stmt->execute();
            $message = "Pitch rejected successfully";
        }
    }
}

// Fetch pitches pending approval (draft or inactive)
$pitches = $conn->query("
    SELECT p.*, e.name as entrepreneur_name, e.email as entrepreneur_email
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    WHERE p.status IN ('draft', 'inactive')
    ORDER BY p.created_at DESC
");
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Pending Pitch Approvals</h1>
        <p>Review and approve or reject pending pitches</p>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-content">
        <?php if ($pitches->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Startup Name</th>
                            <th>Entrepreneur</th>
                            <th>Category</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pitch = $pitches->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $pitch['id']; ?></td>
                            <td><?php echo htmlspecialchars($pitch['startup_name']); ?></td>
                            <td><?php echo htmlspecialchars($pitch['entrepreneur_name']); ?><br><small><?php echo htmlspecialchars($pitch['entrepreneur_email']); ?></small></td>
                            <td><?php echo htmlspecialchars($pitch['category']); ?></td>
                            <td>$<?php echo number_format($pitch['funding_goal']); ?></td>
                            <td><?php echo ucfirst($pitch['status']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($pitch['created_at'])); ?></td>
                                <td>
                                    <a href="pitch_view.php?id=<?php echo $pitch['id']; ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="pending_pitches.php?action=approve&id=<?php echo $pitch['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                    <a href="pending_pitches.php?action=reject&id=<?php echo $pitch['id']; ?>" class="btn btn-danger btn-sm">Reject</a>
                                </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No pitches pending approval.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include 'includes/footer.php'; ?>
