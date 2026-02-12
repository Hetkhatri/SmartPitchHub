<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p>Invalid pitch ID.</p>";
    include 'includes/footer.php';
    exit();
}

$pitchId = intval($_GET['id']);

// Handle approve/reject actions from this page
if (isset($_GET['action']) && in_array($_GET['action'], ['approve', 'reject'])) {
    $action = $_GET['action'];
    $newStatus = $action === 'approve' ? 'active' : 'inactive';
    $stmtUpdate = $conn->prepare("UPDATE pitches SET status = ? WHERE id = ?");
    $stmtUpdate->bind_param("si", $newStatus, $pitchId);
    $stmtUpdate->execute();
    $message = "Pitch " . ($action === 'approve' ? "approved" : "rejected") . " successfully.";
}

$stmt = $conn->prepare("
    SELECT p.*, e.name as entrepreneur_name, e.email as entrepreneur_email
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $pitchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p>Pitch not found.</p>";
    include 'includes/footer.php';
    exit();
}

$pitch = $result->fetch_assoc();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Pitch Details</h1>
        <a href="pitches.php" class="btn btn-secondary">Back to Pitches</a>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="content-section" style="margin-top: 1rem;">
        <h2><?php echo htmlspecialchars($pitch['startup_name']); ?></h2>
        <p><strong>Entrepreneur:</strong> <?php echo htmlspecialchars($pitch['entrepreneur_name']); ?> (<?php echo htmlspecialchars($pitch['entrepreneur_email']); ?>)</p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($pitch['category']); ?></p>
        <p><strong>Funding Goal:</strong> $<?php echo number_format($pitch['funding_goal']); ?></p>
        <p><strong>Status:</strong> <?php echo ucfirst($pitch['status']); ?></p>
        <p><strong>Created At:</strong> <?php echo date('M j, Y', strtotime($pitch['created_at'])); ?></p>
        <hr>
        <h3>Description</h3>
        <p><?php echo nl2br(htmlspecialchars($pitch['description'])); ?></p>

        <?php if (in_array($pitch['status'], ['draft', 'inactive'])): ?>
            <div style="margin-top: 2rem;">
                <a href="pitch_view.php?id=<?php echo $pitch['id']; ?>&action=approve" class="btn btn-success" style="margin-right: 1rem;">Approve</a>
                <a href="pitch_view.php?id=<?php echo $pitch['id']; ?>&action=reject" class="btn btn-danger">Reject</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.btn-secondary {
    display: inline-block;
    padding: 0.5rem 1rem;
    background-color: #6b7280;
    color: white;
    border-radius: 4px;
    text-decoration: none;
    margin-bottom: 1rem;
}
.btn-secondary:hover {
    background-color: #4b5563;
}
.btn-success {
    background-color: #10b981;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    text-decoration: none;
}
.btn-success:hover {
    background-color: #059669;
}
.btn-danger {
    background-color: #ef4444;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    text-decoration: none;
}
.btn-danger:hover {
    background-color: #dc2626;
}
</style>

<?php include 'includes/footer.php'; ?>
