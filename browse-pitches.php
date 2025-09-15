or <?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'investor') {
    header('Location: login.php?role=investor');
    exit();
}

include 'includes/header.php';
include 'db.php';

// Fetch only pitches approved by admin (status = 'active')
$pitches = $conn->query("
    SELECT p.*, u.name as entrepreneur_name
    FROM pitches p
    JOIN users u ON p.entrepreneur_id = u.id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
");
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Browse Pitches</h1>
        <p>Explore startup pitches approved by the admin.</p>
    </div>

    <div class="dashboard-content">
        <div class="content-section">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Startup Name</th>
                            <th>Entrepreneur</th>
                            <th>Category</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pitch = $pitches->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pitch['startup_name']); ?></td>
                            <td><?php echo htmlspecialchars($pitch['entrepreneur_name']); ?></td>
                            <td><?php echo htmlspecialchars($pitch['category']); ?></td>
                            <td>$<?php echo number_format($pitch['funding_goal']); ?></td>
                            <td><span style="color: #10b981;"><?php echo ucfirst($pitch['status']); ?></span></td>
                            <td>
                                <a href="pitch_view.php?id=<?php echo $pitch['id']; ?>" class="btn btn-info btn-sm">View</a>
                                <a href="pitch_like.php?id=<?php echo $pitch['id']; ?>" class="btn btn-success btn-sm">Like</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
