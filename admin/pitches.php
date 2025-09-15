<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Handle pitch actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $pitchId = $_GET['id'] ?? null;

    if ($action === 'delete' && $pitchId) {
        // Delete pitch logic
        $stmt = $conn->prepare("UPDATE pitches SET status = 'deleted' WHERE id = ?");
        $stmt->bind_param("i", $pitchId);
        $stmt->execute();
        $message = "Pitch deleted successfully";
    } elseif ($action === 'reject' && $pitchId) {
        $stmt = $conn->prepare("UPDATE pitches SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $pitchId);
        $stmt->execute();
        $message = "Pitch rejected successfully";
    }
}

// Handle add pitch form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pitch'])) {
    $startup_name = $_POST['startup_name'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $funding_goal = $_POST['funding_goal'];
    $entrepreneur_id = $_POST['entrepreneur_id'];

    $stmt = $conn->prepare("INSERT INTO pitches (startup_name, description, category, funding_goal, entrepreneur_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sssdi", $startup_name, $description, $category, $funding_goal, $entrepreneur_id);

    if ($stmt->execute()) {
        $message = "Pitch added successfully";
    } else {
        $error = "Failed to add pitch";
    }
}

// Get entrepreneurs for dropdown
$entrepreneurs = $conn->query("SELECT id, name FROM entrepreneurs");

// Get all pitches with entrepreneur information
$pitches = $conn->query("
    SELECT p.*, e.name as entrepreneur_name, e.email as entrepreneur_email,
           COUNT(i.id) as investment_count,
           0 as total_investment
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    LEFT JOIN investments i ON p.id = i.pitch_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Pitch Management</h1>
        <p>Manage all startup pitches in the system</p>
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
                <h3>All Pitches</h3>
                <a href="pitches.php?action=add" class="btn btn-primary">Add New Pitch</a>
            </div>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'add'): ?>
            <!-- Add Pitch Form -->
            <div class="content-section" style="margin-top: 2rem; border: 1px solid #e5e7eb; border-radius: 8px; padding: 2rem;">
                <h4>Add New Pitch</h4>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="pitches.php?action=add">
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="startup_name">Startup Name</label>
                            <input type="text" id="startup_name" name="startup_name" class="form-control" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="entrepreneur_id">Entrepreneur</label>
                            <select id="entrepreneur_id" name="entrepreneur_id" class="form-control" required>
                                <option value="">Select Entrepreneur</option>
                                <?php while ($entrepreneur = $entrepreneurs->fetch_assoc()): ?>
                                    <option value="<?php echo $entrepreneur['id']; ?>"><?php echo htmlspecialchars($entrepreneur['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Technology">Technology</option>
                            <option value="Healthcare">Healthcare</option>
                            <option value="Finance">Finance</option>
                            <option value="Education">Education</option>
                            <option value="E-commerce">E-commerce</option>
                            <option value="Food & Beverage">Food & Beverage</option>
                            <option value="Real Estate">Real Estate</option>
                            <option value="Entertainment">Entertainment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="funding_goal">Funding Goal ($)</label>
                        <input type="number" id="funding_goal" name="funding_goal" class="form-control" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="pitches.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="add_pitch" class="btn btn-primary">Add Pitch</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Startup Name</th>
                            <th>Entrepreneur</th>
                            <th>Funding Goal</th>
                            <th>Raised</th>
                            <th>Investors</th>
                            <th>Status</th>
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
                                <br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($pitch['category']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($pitch['entrepreneur_name']); ?>
                                <br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($pitch['entrepreneur_email']); ?></small>
                            </td>
                            <td>$<?php echo number_format($pitch['funding_goal']); ?></td>
                            <td>
                                $<?php echo number_format($pitch['total_investment'] ?? 0); ?>
                                <?php if ($pitch['funding_goal'] > 0): ?>
                                    <br>
                                    <small style="color: #6b7280;">
                                        <?php echo round(($pitch['total_investment'] / $pitch['funding_goal']) * 100, 1); ?>%
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $pitch['investment_count']; ?></td>
                            <td>
                                <span style="color: <?php 
                                    echo $pitch['status'] === 'active' ? '#10b981' : 
                                         ($pitch['status'] === 'pending' ? '#f59e0b' : 
                                         ($pitch['status'] === 'rejected' ? '#dc2626' : '#6b7280')); 
                                ?>;">
                                    <?php echo ucfirst($pitch['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($pitch['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="pitch_view.php?id=<?php echo $pitch['id']; ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="pitch_edit.php?id=<?php echo $pitch['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <?php if ($pitch['status'] === 'pending'): ?>
                                        <a href="pitches.php?action=approve&id=<?php echo $pitch['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                        <a href="pitches.php?action=reject&id=<?php echo $pitch['id']; ?>" class="btn btn-danger btn-sm">Reject</a>
                                    <?php elseif ($pitch['status'] === 'active'): ?>
                                        <a href="pitches.php?action=reject&id=<?php echo $pitch['id']; ?>" class="btn btn-danger btn-sm">Reject</a>
                                    <?php elseif ($pitch['status'] === 'rejected'): ?>
                                        <a href="pitches.php?action=approve&id=<?php echo $pitch['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                    <?php endif; ?>
                                    <a href="pitches.php?action=delete&id=<?php echo $pitch['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this pitch?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pitch Statistics -->
        <div class="content-section">
            <div class="section-header">
                <h3>Pitch Statistics</h3>
            </div>
            
            <div class="dashboard-stats">
                <?php
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_pitches,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_pitches,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_pitches,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_pitches,
        SUM(funding_goal) as total_funding_goal,
        0 as total_raised
    FROM pitches
    WHERE status != 'deleted'
")->fetch_assoc();
                ?>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_pitches']; ?></div>
                    <div class="stat-label">Total Pitches</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_pitches']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_pitches']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['rejected_pitches']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_raised']); ?></div>
                    <div class="stat-label">Total Raised</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_funding_goal']); ?></div>
                    <div class="stat-label">Funding Goal</div>
                </div>
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
