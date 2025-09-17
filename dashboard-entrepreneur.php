<?php
// Start session and check if user is logged in as entrepreneur
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'entrepreneur') {
    // Redirect to login if not authenticated as entrepreneur
    header('Location: login.php?role=entrepreneur');
    exit();
}

include 'includes/header.php';
include 'db.php';

$userId = $_SESSION['user_id']; // Assuming user_id is stored in session

// Fetch entrepreneur's pitches
$pitchesQuery = $conn->prepare("SELECT * FROM pitches WHERE entrepreneur_id = ? AND status != 'deleted' ORDER BY created_at DESC");
$pitchesQuery->bind_param("i", $userId);
$pitchesQuery->execute();
$pitchesResult = $pitchesQuery->get_result();
$statsQuery = $conn->prepare("
    SELECT
        COUNT(*) as total_pitches,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_pitches,
        IFNULL(SUM(views), 0) as total_views,
        IFNULL(SUM(likes), 0) as investor_likes,
        (SELECT COUNT(*) FROM investments i JOIN pitches p ON i.pitch_id = p.id WHERE p.entrepreneur_id = ?) as expressions_of_interest
    FROM pitches
    WHERE entrepreneur_id = ? AND status != 'deleted'
");
$statsQuery->bind_param("ii", $userId, $userId);
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();

// Fetch investor interest expressions
$interestQuery = $conn->prepare("
    SELECT i.id, u.name as investor_name, p.startup_name as pitch_title, i.created_at as interest_date, i.status
    FROM investments i
    JOIN users u ON i.investor_id = u.id
    JOIN pitches p ON i.pitch_id = p.id
    WHERE p.entrepreneur_id = ?
    ORDER BY i.created_at DESC
    LIMIT 10
");
$interestQuery->bind_param("i", $userId);
$interestQuery->execute();
$interestResult = $interestQuery->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pitch'])) {
    $startup_name = $_POST['startup_name'];
    $category = $_POST['category'];
    $funding_goal = $_POST['funding_goal'];
    $short_description = $_POST['short_description'];
    $detailed_pitch = $_POST['detailed_pitch'];
    // When publish_now is clicked, set status to 'pending' else 'draft'
    $status = isset($_POST['publish_now']) && $_POST['publish_now'] === 'on' ? 'pending' : 'draft';

    // Use only pitches table for all pitch operations
    $stmt = $conn->prepare("INSERT INTO pitches (startup_name, description, category, funding_goal, entrepreneur_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("sssdis", $startup_name, $short_description, $category, $funding_goal, $userId, $status);

    if ($stmt->execute()) {
        $message = "Pitch created successfully";
        header("Location: dashboard-entrepreneur.php");
        exit();
    } else {
        $error = "Failed to create pitch";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pitch'])) {
    $pitch_id = $_POST['pitch_id'];
    $startup_name = $_POST['startup_name'];
    $category = $_POST['category'];
    $funding_goal = $_POST['funding_goal'];
    $short_description = $_POST['short_description'];
    $detailed_pitch = $_POST['detailed_pitch'];
    // Fix: check if publish_now is set and true, else draft
    // When publish_now is clicked, set status to 'pending' for admin approval
    $status = isset($_POST['publish_now']) && $_POST['publish_now'] === 'on' ? 'pending' : 'draft';

    $stmt = $conn->prepare("UPDATE pitches SET startup_name = ?, description = ?, category = ?, funding_goal = ?, status = ?, updated_at = NOW() WHERE id = ? AND entrepreneur_id = ?");
    $stmt->bind_param("sssdsii", $startup_name, $short_description, $category, $funding_goal, $status, $pitch_id, $userId);

    if ($stmt->execute()) {
        $message = "Pitch updated successfully";
        header("Location: dashboard-entrepreneur.php");
        exit();
    } else {
        $error = "Failed to update pitch";
    }
}

// Handle pitch actions
if (isset($_GET['action']) && isset($_GET['pitch_id'])) {
    $action = $_GET['action'];
    $pitchId = $_GET['pitch_id'];

    if ($action === 'delete') {
        $stmt = $conn->prepare("UPDATE pitches SET status = 'deleted' WHERE id = ? AND entrepreneur_id = ?");
        $stmt->bind_param("ii", $pitchId, $userId);
        $stmt->execute();
        $message = "Pitch deleted successfully";
    } elseif ($action === 'publish') {
        $stmt = $conn->prepare("UPDATE pitches SET status = 'pending' WHERE id = ? AND entrepreneur_id = ?");
        $stmt->bind_param("ii", $pitchId, $userId);
        $stmt->execute();
        $message = "Pitch submitted for approval";
    }
}

// Fetch recent activity (simplified example)
$activityQuery = $conn->prepare("
    SELECT 'investment' as type, COUNT(*) as count, MAX(i.created_at) as last_date 
    FROM investments i 
    JOIN pitches p ON i.pitch_id = p.id 
    WHERE p.entrepreneur_id = ?
    UNION ALL
    SELECT 'pitch_created' as type, COUNT(*) as count, MAX(created_at) as last_date 
    FROM pitches 
    WHERE entrepreneur_id = ?
    ORDER BY last_date DESC
    LIMIT 5
");
$activityQuery->bind_param("ii", $userId, $userId);
$activityQuery->execute();
$activityResult = $activityQuery->get_result();
?>

<div class="dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">Entrepreneur Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! Manage your startup <?php echo htmlspecialchars($_SESSION['startup_name']); ?> and track investor interest.</p>
    </div>

    <!-- Statistics -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number" data-target="<?php echo $stats['active_pitches'] ?? 0; ?>"><?php echo $stats['active_pitches'] ?? 0; ?></div>
            <div class="stat-label">Active Pitches</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="<?php echo $stats['total_views'] ?? 0; ?>"><?php echo $stats['total_views'] ?? 0; ?></div>
            <div class="stat-label">Total Views</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="<?php echo $stats['investor_likes'] ?? 0; ?>"><?php echo $stats['investor_likes'] ?? 0; ?></div>
            <div class="stat-label">Investor Likes</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" data-target="<?php echo $stats['expressions_of_interest'] ?? 0; ?>"><?php echo $stats['expressions_of_interest'] ?? 0; ?></div>
            <div class="stat-label">Expressions of Interest</div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- My Pitches Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>My Pitches</h3>
                <button class="btn btn-primary" onclick="openModal('createPitchModal')">Create New Pitch</button>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pitch Title</th>
                            <th>Category</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Likes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pitch = $pitchesResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pitch['startup_name']); ?></td>
                            <td><?php echo htmlspecialchars($pitch['category']); ?></td>
                            <td>₹<?php echo number_format($pitch['funding_goal']); ?></td>
                            <td>
                                <?php if ($pitch['status'] === 'active'): ?>
                                    <span style="color: #10b981;">Active</span>
                                <?php elseif ($pitch['status'] === 'pending'): ?>
                                    <span style="color: #f59e0b;">Pending Approval</span>
                                <?php elseif ($pitch['status'] === 'draft'): ?>
                                    <span style="color: #f59e0b;">Draft</span>
                                <?php elseif ($pitch['status'] === 'rejected'): ?>
                                    <span style="color: #dc2626;">Rejected</span>
                                <?php else: ?>
                                    <span style="color: #6b7280;"><?php echo ucfirst($pitch['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $pitch['views'] ?? 0; ?></td>
                            <td><?php echo $pitch['likes'] ?? 0; ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewPitch(<?php echo $pitch['id']; ?>)">View</button>
                                <button class="btn btn-warning btn-sm" onclick='openEditPitchModal(<?php echo json_encode($pitch); ?>)'>Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deletePitch(<?php echo $pitch['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Investor Interest Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Investor Interest</h3>
                <span class="badge"><?php echo $stats['expressions_of_interest'] ?? 0; ?> expressions of interest</span>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Investor</th>
                            <th>Pitch</th>
                            <th>Interest Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($interest = $interestResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($interest['investor_name']); ?></td>
                            <td><?php echo htmlspecialchars($interest['pitch_title']); ?></td>
                            <td><?php echo htmlspecialchars($interest['interest_date']); ?></td>
                            <td>
                                <?php if ($interest['status'] === 'new'): ?>
                                    <span style="color: #10b981;">New</span>
                                <?php elseif ($interest['status'] === 'contacted'): ?>
                                    <span style="color: #3b82f6;">Contacted</span>
                                <?php elseif ($interest['status'] === 'scheduled'): ?>
                                    <span style="color: #f59e0b;">Scheduled Meeting</span>
                                <?php else: ?>
                                    <span><?php echo ucfirst($interest['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm">Contact</button>
                                <button class="btn btn-info btn-sm">View Profile</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analytics Overview -->
        <div class="content-section">
            <div class="section-header">
                <h3>Pitch Performance Analytics</h3>
                <select class="form-control" style="width: auto;">
                    <option>Last 7 days</option>
                    <option selected>Last 30 days</option>
                    <option>Last 90 days</option>
                </select>
            </div>
            
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h4>Total Views</h4>
                    <div class="stat-number"><?php echo $stats['total_views'] ?? 0; ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 75%; background: #3b82f6;"></div>
                    </div>
                    <small>+15% from last month</small>
                </div>
                
                <div class="analytics-card">
                    <h4>Engagement Rate</h4>
                    <div class="stat-number">18.5%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 65%; background: #10b981;"></div>
                    </div>
                    <small>+8% from last month</small>
                </div>
                
                <div class="analytics-card">
                    <h4>Conversion Rate</h4>
                    <div class="stat-number">6.2%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 40%; background: #f59e0b;"></div>
                    </div>
                    <small>+3% from last month</small>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-section">
            <div class="section-header">
                <h3>Recent Activity</h3>
            </div>
            
            <div class="activity-feed">
                <?php while ($activity = $activityResult->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: <?php 
                        echo $activity['type'] === 'view' ? '#10b981' : 
                             ($activity['type'] === 'like' ? '#3b82f6' : 
                             ($activity['type'] === 'message' ? '#f59e0b' : '#6b7280')); 
                    ?>;">
                        <i class="fas fa-<?php 
                            echo $activity['type'] === 'view' ? 'eye' : 
                                 ($activity['type'] === 'like' ? 'heart' : 
                                 ($activity['type'] === 'message' ? 'comment' : 'bell')); 
                        ?>"></i>
                    </div>
                    <div class="activity-content">
                        <p>
                            <?php echo $activity['count']; ?> 
                            <?php 
                                echo $activity['type'] === 'view' ? 'investors viewed your pitches' : 
                                     ($activity['type'] === 'like' ? 'likes received' : 
                                     ($activity['type'] === 'message' ? 'new messages' : 'notifications')); 
                            ?>
                        </p>
                        <small><?php echo date('M j, Y', strtotime($activity['last_date'])); ?></small>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Pitch Modal -->
<div id="createPitchModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal('createPitchModal')">&times;</span>
        <h2>Create New Pitch</h2>
        <form method="POST" action="">
            <input type="hidden" name="create_pitch" value="1">
            <div class="form-group">
                <label>Pitch Title *</label>
                <input type="text" name="startup_name" class="form-control" placeholder="Enter your pitch title" required>
            </div>

            <div class="form-group">
                <label>Category *</label>
                <select name="category" class="form-control" required>
                    <option value="">Select category</option>
                    <option>SaaS</option>
                    <option>Healthcare</option>
                    <option>FinTech</option>
                    <option>E-commerce</option>
                    <option>Sustainability</option>
                    <option>Education</option>
                    <option>Manufacturing</option>
                </select>
            </div>

            <div class="form-group">
                <label>Funding Goal ($) *</label>
                <input type="number" name="funding_goal" class="form-control" placeholder="Enter funding amount" required>
            </div>

            <div class="form-group">
                <label>Short Description *</label>
                <textarea name="short_description" class="form-control" rows="3" placeholder="Brief description of your startup" required></textarea>
            </div>

            <div class="form-group">
                <label>Detailed Pitch</label>
                <textarea name="detailed_pitch" class="form-control" rows="6" placeholder="Detailed business plan, market analysis, team information..."></textarea>
            </div>

            <div class="form-group">
                <label>Upload Images (Max 5)</label>
                <input type="file" class="form-control" multiple accept="image/*">
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPitchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save as Draft</button>
                <button type="submit" name="publish_now" value="on" class="btn btn-success">Publish Now</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Pitch Modal -->
<div id="editPitchModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editPitchModal')">&times;</span>
        <h2>Edit Pitch</h2>
        <form method="POST" action="">
            <input type="hidden" name="edit_pitch" value="1">
            <input type="hidden" name="pitch_id" id="edit_pitch_id">
            <div class="form-group">
                <label>Pitch Title *</label>
                <input type="text" name="startup_name" id="edit_startup_name" class="form-control" placeholder="Enter your pitch title" required>
            </div>

            <div class="form-group">
                <label>Category *</label>
                <select name="category" id="edit_category" class="form-control" required>
                    <option value="">Select category</option>
                    <option>SaaS</option>
                    <option>Healthcare</option>
                    <option>FinTech</option>
                    <option>E-commerce</option>
                    <option>Sustainability</option>
                    <option>Education</option>
                    <option>Manufacturing</option>
                </select>
            </div>

            <div class="form-group">
                <label>Funding Goal ($) *</label>
                <input type="number" name="funding_goal" id="edit_funding_goal" class="form-control" placeholder="Enter funding amount" required>
            </div>

            <div class="form-group">
                <label>Short Description *</label>
                <textarea name="short_description" id="edit_short_description" class="form-control" rows="3" placeholder="Brief description of your startup" required></textarea>
            </div>

            <div class="form-group">
                <label>Detailed Pitch</label>
                <textarea name="detailed_pitch" id="edit_detailed_pitch" class="form-control" rows="6" placeholder="Detailed business plan, market analysis, team information..."></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPitchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save as Draft</button>
                <button type="submit" name="publish_now" value="on" class="btn btn-success">Publish Now</button>
            </div>
        </form>
    </div>
</div>

<style>
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.analytics-card {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    border-left: 4px solid #667eea;
}

.analytics-card h4 {
    margin-bottom: 1rem;
    color: #374151;
    font-size: 0.9rem;
    font-weight: 600;
}

.analytics-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 0.5rem;
}

.analytics-card .progress-bar {
    margin: 0.5rem auto;
    max-width: 100px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.badge {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-content p {
    margin: 0;
    font-weight: 500;
}

.activity-content small {
    color: #6b7280;
    font-size: 0.875rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.close {
    float: right;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
}
</style>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openEditPitchModal(pitchData) {
    document.getElementById('edit_pitch_id').value = pitchData.id;
    document.getElementById('edit_startup_name').value = pitchData.startup_name;
    document.getElementById('edit_category').value = pitchData.category;
    document.getElementById('edit_funding_goal').value = pitchData.funding_goal;
    document.getElementById('edit_short_description').value = pitchData.description;
    document.getElementById('edit_detailed_pitch').value = pitchData.detailed_pitch || '';
    openModal('editPitchModal');
}

function viewPitch(pitchId) {
    // Redirect to pitch view page
    window.location.href = 'pitch_view.php?id=' + pitchId;
}

function deletePitch(pitchId) {
    if (confirm('Are you sure you want to delete this pitch?')) {
        window.location.href = 'dashboard-entrepreneur.php?action=delete&pitch_id=' + pitchId;
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
