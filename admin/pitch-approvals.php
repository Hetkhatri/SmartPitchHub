<?php
session_start();
require_once '../db.php';

// 1. Security Check (Admin Only)
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// 2. Fetch Pitch Requests
$sql = "SELECT p.*, e.name as entrepreneur_name, e.email as entrepreneur_email,
               wz.survival_score
        FROM pitches p
        JOIN entrepreneurs e ON p.entrepreneur_id = e.id
        LEFT JOIN (
            SELECT pitch_id, MAX(survival_score) as survival_score 
            FROM warzone_sessions 
            GROUP BY pitch_id
        ) wz ON p.id = wz.pitch_id
        ORDER BY p.created_at DESC";

$pitches = [];
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $pitches[] = $row;
    }
}

// 3. Stats calculation
$pending = 0; $approved = 0; $rejected = 0;
foreach($pitches as $p) {
    if($p['is_approved'] == 0) $pending++;
    elseif($p['is_approved'] == 1) $approved++;
    elseif($p['is_approved'] == 2) $rejected++;
}

// Layout helper for status mapping
$status_map = [
    0 => ['label' => 'Pending', 'class' => 'pending'],
    1 => ['label' => 'Approved', 'class' => 'verified'],
    2 => ['label' => 'Rejected', 'class' => 'rejected']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pitch Approvals - SmartPitchHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background: #f8fafc;
            --foreground: #0f172a;
            --card: #ffffff;
            --card-foreground: #0f172a;
            --primary: #a78bfa;
            --primary-foreground: #ffffff;
            --secondary: #f1f5f9;
            --muted: #f1f5f9;
            --muted-foreground: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --destructive: #ef4444;
            --radius: 0.75rem;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background); color: var(--foreground); line-height: 1.5; }

        .header { position: sticky; top: 0; z-index: 40; background-color: var(--card); border-bottom: 1px solid var(--border); }
        .header-content { max-width: 1400px; margin: 0 auto; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        
        .logo-section { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .logo-icon { width: 40px; height: 40px; border-radius: 12px; background: rgba(167, 139, 250, 0.1); display: flex; align-items: center; justify-content: center; color: var(--primary); }
        .logo-text h1 { font-size: 1.125rem; font-weight: 700; color: var(--foreground); }
        .logo-text p { font-size: 0.75rem; color: var(--muted-foreground); }

        .back-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); background: var(--card); color: var(--muted-foreground); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .back-btn:hover { background: var(--muted); color: var(--foreground); }

        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }
        
        .page-header { margin-bottom: 2rem; }
        .page-header h2 { font-size: 1.875rem; font-weight: 700; }
        .page-header p { color: var(--muted-foreground); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); }
        .stat-label { font-size: 0.875rem; color: var(--muted-foreground); display: block; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-icon { float: right; padding: 0.5rem; border-radius: 8px; }

        .table-container { background: var(--card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: var(--muted); padding: 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--muted-foreground); }
        td { padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.875rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fdfdfd; }

        .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-verified { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        .action-btn { padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: #fff; color: var(--primary); cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .action-btn:hover { background: var(--muted); border-color: var(--primary); }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="admin-dashboard.php" class="logo-section">
                <div class="logo-icon">P</div>
                <div class="logo-text">
                    <h1>SmartPitchHub</h1>
                    <p>Pitch Approval Workflow</p>
                </div>
            </a>
            <a href="admin-dashboard.php" class="back-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Dashboard
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>Pitch Approval Requests</h2>
            <p>Review startup pitches and evaluate their AI Warzone audit results.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <span class="stat-label">Pending Approval</span>
                <span class="stat-value"><?php echo $pending; ?></span>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <span class="stat-label">Approved Pitches</span>
                <span class="stat-value"><?php echo $approved; ?></span>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--destructive);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <span class="stat-label">Rejected Pitches</span>
                <span class="stat-value"><?php echo $rejected; ?></span>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Founder</th>
                        <th>Startup Name</th>
                        <th>Industry</th>
                        <th>Funding Ask</th>
                        <th>Warzone Score</th>
                        <th>Date Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pitches)): ?>
                        <tr><td colspan="9" style="text-align:center; padding: 3rem; color: var(--muted-foreground);">No pitch requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach($pitches as $p): ?>
                        <tr>
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($p['entrepreneur_name']); ?></strong></div>
                                <div style="font-size: 0.75rem; color: var(--muted-foreground);"><?php echo htmlspecialchars($p['entrepreneur_email']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($p['startup_name']); ?></td>
                            <td><span class="badge" style="background: rgba(167, 139, 250, 0.1); color: var(--primary);"><?php echo htmlspecialchars($p['industry']); ?></span></td>
                            <td>₹<?php echo number_format($p['funding_goal']); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 40px; height: 6px; background: #eee; border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo $p['survival_score'] ?? 0; ?>%; height: 100%; background: <?php echo ($p['survival_score'] ?? 0) > 70 ? '#10b981' : (($p['survival_score'] ?? 0) > 40 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                                    </div>
                                    <span style="font-weight: 700; font-size: 0.7rem;">
                                        <?php echo isset($p['survival_score']) ? $p['survival_score'] . '%' : 'N/A'; ?>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo date("d M Y", strtotime($p['created_at'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $status_map[$p['is_approved']]['class']; ?>">
                                    <?php echo $status_map[$p['is_approved']]['label']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="viewPitchDetail.php?id=<?php echo $p['id']; ?>" class="action-btn" style="display: inline-flex;" title="Review & Take Action">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
