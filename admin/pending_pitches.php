<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Handle pending pitch actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $pitchId = $_GET['id'] ?? null;

    if ($action === 'approve' && $pitchId) {
        // Move pitch from pending_pitches to pitches with status 'active'
        $stmt = $conn->prepare("SELECT * FROM pending_pitches WHERE id = ?");
        $stmt->bind_param("i", $pitchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pitch = $result->fetch_assoc();

        if ($pitch) {
            // Insert into pitches table
            $insertStmt = $conn->prepare("INSERT INTO pitches (startup_name, description, category, funding_goal, entrepreneur_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
            $insertStmt->bind_param("sssdis", $pitch['startup_name'], $pitch['description'], $pitch['category'], $pitch['funding_goal'], $pitch['entrepreneur_id']);
            if ($insertStmt->execute()) {
                // Delete from pending_pitches
                $deleteStmt = $conn->prepare("DELETE FROM pending_pitches WHERE id = ?");
                $deleteStmt->bind_param("i", $pitchId);
                $deleteStmt->execute();
                $message = "Pitch approved and moved to active pitches.";
            } else {
                $error = "Failed to approve pitch.";
            }
        } else {
            $error = "Pitch not found.";
        }
    } elseif ($action === 'reject' && $pitchId) {
        // Delete from pending_pitches (reject)
        $stmt = $conn->prepare("DELETE FROM pending_pitches WHERE id = ?");
        $stmt->bind_param("i", $pitchId);
        if ($stmt->execute()) {
            $message = "Pitch rejected and removed from pending.";
        } else {
            $error = "Failed to reject pitch.";
        }
    }
}

// Fetch all pending pitches with entrepreneur info
$pitches = $conn->query("
    SELECT pp.*, e.name as entrepreneur_name, e.email as entrepreneur_email
    FROM pending_pitches pp
    JOIN entrepreneurs e ON pp.entrepreneur_id = e.id
    ORDER BY pp.created_at DESC
");
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Pending Pitches</h1>
        <p>Review and approve or reject pending startup pitches.</p>
        <a href="pitches.php" class="btn btn-secondary" style="margin-top: 1rem;">Back to All Pitches</a>
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
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Startup Name</th>
                            <th>Entrepreneur</th>
                            <th>Category</th>
                            <th>Funding Goal</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pitch = $pitches->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $pitch['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($pitch['startup_name']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($pitch['entrepreneur_name']); ?>
                                <br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($pitch['entrepreneur_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($pitch['category']); ?></td>
                            <td>$<?php echo number_format($pitch['funding_goal']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($pitch['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="pending_pitches.php?action=approve&id=<?php echo $pitch['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                    <a href="pending_pitches.php?action=reject&id=<?php echo $pitch['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this pitch?')">Reject</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($pitches->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No pending pitches found.</td>
                        </tr>
                        <?php endif; ?>
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
</style>

<?php include 'includes/footer.php'; ?>
