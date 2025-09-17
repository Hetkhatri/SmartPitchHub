<?php
// pitch_view.php - Simple data fetching without security
session_start();

// Database connection
function getDBConnection() {
    $host = 'localhost';
    $dbname = 'smartpitchhub-1';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Get pitch ID from URL
$pitch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pitch_id <= 0) {
    die("Invalid pitch ID. Please provide a valid pitch ID in the URL.");
}

try {
    $pdo = getDBConnection();
    
    // Fetch pitch data
    $stmt = $pdo->prepare("
        SELECT p.*, e.name as entrepreneur_name, e.email as entrepreneur_email, 
               e.contact as entrepreneur_contact
        FROM pitches p 
        JOIN entrepreneurs e ON p.entrepreneur_id = e.id 
        WHERE p.id = :id
    ");
    $stmt->bindParam(':id', $pitch_id, PDO::PARAM_INT);
    $stmt->execute();
    $pitch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pitch) {
        die("Pitch not found with ID: $pitch_id");
    }
    
    // Fetch investor interest data
    $stmt = $pdo->prepare("
        SELECT i.name, i.email, i.contact, inv.created_at, inv.status 
        FROM investments inv
        JOIN investors i ON inv.investor_id = i.id
        WHERE inv.pitch_id = :pitch_id
        ORDER BY inv.created_at DESC
    ");
    $stmt->bindParam(':pitch_id', $pitch_id, PDO::PARAM_INT);
    $stmt->execute();
    $investors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update view count
    $stmt = $pdo->prepare("UPDATE pitches SET views = views + 1 WHERE id = :id");
    $stmt->bindParam(':id', $pitch_id, PDO::PARAM_INT);
    $stmt->execute();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pitch['startup_name']); ?> - Pitch Detail</title>
    <link rel="stylesheet" href="css/pitch_view.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <!-- Header -->
        <header class="header">
            <div class="container">
                <div class="header-content">
                    <div class="header-left">
                        <a href="dashboard-entrepreneur.php" class="back-button">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m12 19-7-7 7-7"/>
                                <path d="M19 12H5"/>
                            </svg>
                            Back to Dashboard
                        </a>
                        <div class="title-section">
                            <h1 class="page-title"><?php echo htmlspecialchars($pitch['startup_name']); ?></h1>
                            <div class="badges">
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($pitch['category']); ?></span>
                                <span class="badge badge-<?php echo $pitch['status'] === 'active' ? 'approved' : 'outline'; ?>">
                                    <?php echo ucfirst($pitch['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                <path d="m16 6-4-2-4 2"/>
                            </svg>
                            Share
                        </button>
                        <button class="btn btn-primary" onclick="window.location.href='edit_pitch.php?id=<?php echo $pitch_id; ?>'">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                            </svg>
                            Edit Pitch
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="container">
                <div class="content-grid">
                    <!-- Main Content -->
                    <div class="main-column">
                        <!-- Analytics Cards -->
                        <div class="analytics-grid">
                            <div class="analytics-card">
                                <div class="analytics-content">
                                    <div class="analytics-text">
                                        <p class="analytics-label">Total Views</p>
                                        <p class="analytics-value"><?php echo $pitch['views']; ?></p>
                                        <p class="analytics-change">+23%</p>
                                    </div>
                                    <svg class="analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-content">
                                    <div class="analytics-text">
                                        <p class="analytics-label">Likes</p>
                                        <p class="analytics-value"><?php echo $pitch['likes']; ?></p>
                                        <p class="analytics-change">+15%</p>
                                    </div>
                                    <svg class="analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7 7-7Z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-content">
                                    <div class="analytics-text">
                                        <p class="analytics-label">Investor Interest</p>
                                        <p class="analytics-value"><?php echo count($investors); ?></p>
                                        <p class="analytics-change">+8%</p>
                                    </div>
                                    <svg class="analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="m22 21-3-3"/>
                                        <path d="m17 17-5 5"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-content">
                                    <div class="analytics-text">
                                        <p class="analytics-label">Conversion Rate</p>
                                        <p class="analytics-value">3.5%</p>
                                        <p class="analytics-change">+0.8%</p>
                                    </div>
                                    <svg class="analytics-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/>
                                        <polyline points="16,7 22,7 22,13"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="tabs-container">
                            <div class="tabs-list">
                                <button class="tab-trigger active" data-tab="overview">Overview</button>
                                <button class="tab-trigger" data-tab="interest">Investor Interest</button>
                                <button class="tab-trigger" data-tab="analytics">Analytics</button>
                            </div>

                            <!-- Overview Tab -->
                            <div class="tab-content active" id="overview">
                                <div class="content-section">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">
                                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="12" x2="12" y1="2" y2="22"/>
                                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                                </svg>
                                                Funding Requirements
                                            </h3>
                                        </div>
                                        <div class="card-content">
                                            <p class="funding-amount">₹<?php echo number_format($pitch['funding_goal'], 2); ?></p>
                                            <p class="funding-label">Seeking investment</p>
                                        </div>
                                    </div>

                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">Pitch Description</h3>
                                        </div>
                                        <div class="card-content">
                                            <p class="description"><?php echo nl2br(htmlspecialchars($pitch['description'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Investor Interest Tab -->
                            <div class="tab-content" id="interest">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Investor Interest (<?php echo count($investors); ?>)</h3>
                                    </div>
                                    <div class="card-content">
                                        <div class="table-container">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th>Investor</th>
                                                        <th>Contact</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($investors) > 0): ?>
                                                        <?php foreach ($investors as $investor): ?>
                                                            <tr>
                                                                <td class="font-medium"><?php echo htmlspecialchars($investor['name']); ?></td>
                                                                <td><?php echo htmlspecialchars($investor['email']); ?></td>
                                                                <td><?php echo date('Y-m-d', strtotime($investor['created_at'])); ?></td>
                                                                <td>
                                                                    <span class="badge badge-<?php 
                                                                        echo $investor['status'] === 'contacted' ? 'approved' : 'outline';
                                                                    ?>">
                                                                        <?php echo ucfirst($investor['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No investor interest yet</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Analytics Tab -->
                            <div class="tab-content" id="analytics">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Performance Analytics</h3>
                                    </div>
                                    <div class="card-content">
                                        <div class="analytics-detail-grid">
                                            <div class="analytics-detail-card">
                                                <h4 class="analytics-detail-title">Views This Week</h4>
                                                <p class="analytics-detail-value">89</p>
                                                <p class="analytics-detail-change">+12% from last week</p>
                                            </div>
                                            <div class="analytics-detail-card">
                                                <h4 class="analytics-detail-title">Interest Rate</h4>
                                                <p class="analytics-detail-value">3.5%</p>
                                                <p class="analytics-detail-change">Above average</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="sidebar">
                        <!-- Pitch Image -->
                        <div class="card">
                            <div class="pitch-image-container">
                                <img src="/placeholder.svg" alt="<?php echo htmlspecialchars($pitch['startup_name']); ?>" class="pitch-image">
                                <div class="pitch-image-content">
                                    <h3 class="pitch-image-title">Pitch Image</h3>
                                    <p class="pitch-image-description">Visual representation of your startup</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pitch Info -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M8 2v4"/>
                                        <path d="M16 2v4"/>
                                        <rect width="18" height="18" x="3" y="4" rx="2"/>
                                        <path d="M3 10h18"/>
                                    </svg>
                                    Pitch Information
                                </h3>
                            </div>
                            <div class="card-content">
                                <div class="info-section">
                                    <div class="info-item">
                                        <p class="info-label">Submitted Date</p>
                                        <p class="info-value"><?php echo date('Y-m-d', strtotime($pitch['created_at'])); ?></p>
                                    </div>
                                    <div class="separator"></div>
                                    <div class="info-item">
                                        <p class="info-label">Category</p>
                                        <p class="info-value"><?php echo htmlspecialchars($pitch['category']); ?></p>
                                    </div>
                                    <div class="separator"></div>
                                    <div class="info-item">
                                        <p class="info-label">Status</p>
                                        <span class="badge badge-<?php echo $pitch['status'] === 'active' ? 'approved' : 'outline'; ?>">
                                            <?php echo ucfirst($pitch['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Quick Actions</h3>
                            </div>
                            <div class="card-content">
                                <div class="actions-section">
                                    <button class="btn btn-primary btn-full">Contact Interested Investors</button>
                                    <button class="btn btn-outline btn-full">Download Analytics Report</button>
                                    <button class="btn btn-outline btn-full">Promote This Pitch</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Simple tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            const tabContents = document.querySelectorAll('.tab-content');

            tabTriggers.forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const targetTab = trigger.getAttribute('data-tab');
                    
                    // Remove active class from all triggers and contents
                    tabTriggers.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked trigger and corresponding content
                    trigger.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>