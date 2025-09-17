<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Get user statistics from existing tables
$total_investors = $conn->query("SELECT COUNT(*) as count FROM investors")->fetch_assoc()['count'];
$total_entrepreneurs = $conn->query("SELECT COUNT(*) as count FROM entrepreneurs")->fetch_assoc()['count'];
$total_users = $total_investors + $total_entrepreneurs;

// Check if pitches table exists and get count
$pitches_exists = $conn->query("SHOW TABLES LIKE 'pitches'");
$total_pitches = 0;
if ($pitches_exists->num_rows > 0) {
    $total_pitches = $conn->query("SELECT COUNT(*) as count FROM pitches")->fetch_assoc()['count'];
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Admin Dashboard</h1>
        <p>Welcome back, <?php echo $_SESSION['admin_name']; ?>! Here's your system overview.</p>
    </div>

    <!-- Statistics -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_users; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_investors; ?></div>
            <div class="stat-label">Investors</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_entrepreneurs; ?></div>
            <div class="stat-label">Entrepreneurs</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_pitches; ?></div>
            <div class="stat-label">Pitches</div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- Recent Users Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Recent Users</h3>
                <a href="users.php" class="btn btn-primary">Manage Users</a>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get recent investors
                        $recent_investors = $conn->query("
                            SELECT name, email, 'investor' as role, created_at, 'active' as status 
                            FROM investors 
                            ORDER BY created_at DESC 
                            LIMIT 3
                        ");
                        
                        // Get recent entrepreneurs  
                        $recent_entrepreneurs = $conn->query("
                            SELECT name, email, 'entrepreneur' as role, created_at, 'active' as status 
                            FROM entrepreneurs 
                            ORDER BY created_at DESC 
                            LIMIT 2
                        ");
                        
                        // Combine and display results
                        $recent_users = [];
                        while ($row = $recent_investors->fetch_assoc()) {
                            $recent_users[] = $row;
                        }
                        while ($row = $recent_entrepreneurs->fetch_assoc()) {
                            $recent_users[] = $row;
                        }
                        
                        // Sort by creation date
                        usort($recent_users, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        
                        // Display top 5
                        $count = 0;
                        foreach ($recent_users as $user):
                            if ($count >= 5) break;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst($user['role']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <span style="color: #10b981;">
                                    Active
                                </span>
                            </td>
                        </tr>
                        <?php 
                            $count++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Pitches Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Recent Pitches</h3>
                <a href="pitches.php" class="btn btn-primary">Manage Pitches</a>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Startup Name</th>
                            <th>Entrepreneur</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Check if pitches table exists
                        $pitches_exists = $conn->query("SHOW TABLES LIKE 'pitches'");
                        if ($pitches_exists->num_rows > 0) {
                            $recent_pitches = $conn->query("
                                SELECT p.startup_name, e.name as entrepreneur, p.funding_goal, p.status, p.created_at 
                                FROM pitches p 
                                JOIN entrepreneurs e ON p.entrepreneur_id = e.id 
                                WHERE p.status = 'active'
                                ORDER BY p.created_at DESC 
                                LIMIT 5
                            ");
                            
                            while ($pitch = $recent_pitches->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pitch['startup_name']); ?></td>
                                <td><?php echo htmlspecialchars($pitch['entrepreneur']); ?></td>
                                <td>$<?php echo number_format($pitch['funding_goal']); ?></td>
                                <td>
                                    <span style="color: <?php echo $pitch['status'] === 'active' ? '#10b981' : '#6b7280'; ?>;">
                                        <?php echo ucfirst($pitch['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($pitch['created_at'])); ?></td>
                            </tr>
                            <?php endwhile;
                        } else {
                            echo '<tr><td colspan="5" style="text-align: center; color: #6b7280;">No pitches found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-section">
            <div class="section-header">
                <h3>Quick Actions</h3>
            </div>
            
            <div class="quick-actions-grid">
                <a href="users.php?action=add" class="quick-action-card">
                    <i class="fas fa-user-plus"></i>
                    <span>Add User</span>
                </a>
                
                <a href="pitches.php?action=add" class="quick-action-card">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Pitch</span>
                </a>
                
                <a href="settings.php" class="quick-action-card">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                
                <a href="reports.php" class="quick-action-card">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.quick-action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem;
    background: #f8fafc;
    border-radius: 12px;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.quick-action-card:hover {
    background: #667eea;
    color: white;
    border-color: #5a67d8;
    transform: translateY(-2px);
}

.quick-action-card i {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.quick-action-card span {
    font-weight: 500;
}
</style>

<?php include 'includes/footer.php'; ?>
