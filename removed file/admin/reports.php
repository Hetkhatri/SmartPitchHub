<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
include '../db.php';
include 'includes/header.php';

// Get report data
$total_users = $conn->query("SELECT COUNT(*) as count FROM (SELECT id FROM entrepreneurs UNION ALL SELECT id FROM investors) AS users")->fetch_assoc()['count'];
$total_investors = $conn->query("SELECT COUNT(*) as count FROM investors")->fetch_assoc()['count'];
$total_entrepreneurs = $conn->query("SELECT COUNT(*) as count FROM entrepreneurs")->fetch_assoc()['count'];
$total_pitches = $conn->query("SELECT COUNT(*) as count FROM pitches WHERE status != 'deleted'")->fetch_assoc()['count'];
$total_investments = $conn->query("SELECT COUNT(*) as count FROM investments")->fetch_assoc()['count'];

// Get monthly user registrations
$monthly_users = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM (
        SELECT created_at FROM entrepreneurs
        UNION ALL
        SELECT created_at FROM investors
    ) AS users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");

// Get pitch categories
$pitch_categories = $conn->query("
    SELECT category, COUNT(*) as count
    FROM pitches
    WHERE status != 'deleted'
    GROUP BY category
    ORDER BY count DESC
");
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Reports & Analytics</h1>
        <p>System-wide statistics and insights</p>
    </div>

    <!-- Summary Cards -->
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
            <div class="stat-label">Active Pitches</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?php echo $total_investments; ?></div>
            <div class="stat-label">Total Investments</div>
        </div>
    </div>

    <div class="dashboard-content">
        <!-- Monthly Registrations Chart -->
        <div class="content-section">
            <div class="section-header">
                <h3>User Registrations (Last 12 Months)</h3>
            </div>

            <div class="chart-container">
                <canvas id="registrationChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Pitch Categories -->
        <div class="content-section">
            <div class="section-header">
                <h3>Pitch Categories Distribution</h3>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Number of Pitches</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_pitches_count = 0;
                        while ($cat = $pitch_categories->fetch_assoc()) {
                            $total_pitches_count += $cat['count'];
                        }
                        $pitch_categories->data_seek(0); // Reset pointer

                        while ($category = $pitch_categories->fetch_assoc()):
                            $percentage = $total_pitches_count > 0 ? round(($category['count'] / $total_pitches_count) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category']); ?></td>
                            <td><?php echo $category['count']; ?></td>
                            <td><?php echo $percentage; ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Performing Pitches -->
        <div class="content-section">
            <div class="section-header">
                <h3>Top Performing Pitches</h3>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Startup Name</th>
                            <th>Entrepreneur</th>
                            <th>Funding Goal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $top_pitches = $conn->query("
                            SELECT p.startup_name, e.name as entrepreneur, p.funding_goal, p.status
                            FROM pitches p
                            JOIN entrepreneurs e ON p.entrepreneur_id = e.id
                            WHERE p.status = 'active'
                            ORDER BY p.funding_goal DESC
                            LIMIT 10
                        ");

                        while ($pitch = $top_pitches->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pitch['startup_name']); ?></td>
                            <td><?php echo htmlspecialchars($pitch['entrepreneur']); ?></td>
                            <td>$<?php echo number_format($pitch['funding_goal']); ?></td>
                            <td>
                                <span style="color: #10b981;">
                                    <?php echo ucfirst($pitch['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Registration Chart
const ctx = document.getElementById('registrationChart').getContext('2d');
const registrationChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            <?php
            $monthly_users->data_seek(0);
            while ($month = $monthly_users->fetch_assoc()) {
                echo "'" . date('M Y', strtotime($month['month'] . '-01')) . "', ";
            }
            ?>
        ],
        datasets: [{
            label: 'New Users',
            data: [
                <?php
                $monthly_users->data_seek(0);
                while ($month = $monthly_users->fetch_assoc()) {
                    echo $month['count'] . ", ";
                }
                ?>
            ],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Monthly User Registrations'
            }
        }
    }
});
</script>

<style>
.chart-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 0;
}
</style>

<?php include 'includes/footer.php'; ?>
