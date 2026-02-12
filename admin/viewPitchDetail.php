<?php
session_start();
require_once '../db.php';

// 1. Get Pitch ID
$pitch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pitch_id <= 0) {
    die("Invalid Pitch ID.");
}

// 2. Fetch Pitch Details
$sql = "SELECT p.*, e.id as ent_id, e.name as entrepreneur_name, e.email as entrepreneur_email, e.total_shares
        FROM pitches p
        JOIN entrepreneurs e ON p.entrepreneur_id = e.id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pitch_id);
$stmt->execute();
$pitch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pitch) {
    die("Pitch not found.");
}

// Extraction logic for legacy pitches where problem/solution are only in description
if (empty($pitch['problem']) || empty($pitch['solution'])) {
    $desc = $pitch['description'];
    
    // Extract Problem
    if (empty($pitch['problem']) && preg_match('/<strong>Problem:<\/strong><br>(.*?)<br><br>/s', $desc, $matches)) {
        $extracted = $matches[1];
        $extracted = str_ireplace(['<br />', '<br>', '<br/>'], "\n", $extracted);
        $pitch['problem'] = html_entity_decode(htmlspecialchars_decode($extracted));
    }
    
    // Extract Solution
    if (empty($pitch['solution']) && preg_match('/<strong>Solution:<\/strong><br>(.*?)<br><br>/s', $desc, $matches)) {
        $extracted = $matches[1];
        $extracted = str_ireplace(['<br />', '<br>', '<br/>'], "\n", $extracted);
        $pitch['solution'] = html_entity_decode(htmlspecialchars_decode($extracted));
    }
}

// Fallback for Valuation if missing in p.valuation (older pitches or schema mismatch)
$displayValuation = $pitch['valuation'];
if ($displayValuation <= 0 && isset($pitch['share_price']) && $pitch['share_price'] > 0) {
    $displayValuation = $pitch['share_price'] * ($pitch['total_shares'] ?? 50000);
}

// 3. Fetch Documents
$doc_sql = "SELECT * FROM pitch_documents WHERE pitch_id = ?";
$doc_stmt = $conn->prepare($doc_sql);
$doc_stmt->bind_param("i", $pitch_id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$doc_stmt->close();

// 4. Fetch Warzone Session
$wz_sql = "SELECT * FROM warzone_sessions WHERE pitch_id = ? ORDER BY created_at DESC LIMIT 1";
$wz_stmt = $conn->prepare($wz_sql);
$wz_stmt->bind_param("i", $pitch_id);
$wz_stmt->execute();
$warzone = $wz_stmt->get_result()->fetch_assoc();
$wz_stmt->close();

// 5. Fetch Linked Valuation Request
$v_sql = "SELECT id, status, valuation_ask FROM valuation_requests WHERE entrepreneur_id = ? ORDER BY created_at DESC LIMIT 1";
$v_stmt = $conn->prepare($v_sql);
$v_stmt->bind_param("i", $pitch['entrepreneur_id']);
$v_stmt->execute();
$valuation_req = $v_stmt->get_result()->fetch_assoc();
$v_stmt->close();

// 6. Handle Post Submission (Approval/Rejection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action']; // 'approve', 'reject', 'verify_valuation', 'reject_valuation'
    $reason = $_POST['admin_reason'] ?? '';

    // Handle Valuation actions first
    if ($action === 'verify_valuation' || $action === 'reject_valuation') {
        if ($valuation_req) {
            $v_status = ($action === 'verify_valuation') ? 'verified' : 'rejected';
            $v_up = $conn->prepare("UPDATE valuation_requests SET status = ?, admin_remarks = ? WHERE id = ?");
            $v_up->bind_param("ssi", $v_status, $reason, $valuation_req['id']);
            if ($v_up->execute()) {
                $v_up->close();
                header("Location: viewPitchDetail.php?id=$pitch_id&msg=Valuation updated");
                exit;
            }
        }
    }

    // Validation: Cannot approve/reject pitch if valuation is still pending
    if (($action === 'approve' || $action === 'reject') && $valuation_req && $valuation_req['status'] === 'pending') {
        die("Error: Please verify or reject the valuation first.");
    }
    
    $status = ($action === 'approve') ? 1 : 2;
    
    // Start transaction
    $conn->begin_transaction();

    try {
        // Update Pitch Status
        $up_sql = "UPDATE pitches SET is_approved = ?, admin_feedback = ? WHERE id = ?";
        $up_stmt = $conn->prepare($up_sql);
        $up_stmt->bind_param("isi", $status, $reason, $pitch_id);
        $up_stmt->execute();
        $up_stmt->close();

        $conn->commit();
        header("Location: pitch-approvals.php?msg=Status updated successfully.");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Pitch: <?php echo htmlspecialchars($pitch['startup_name']); ?> - SmartPitchHub Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background: #f8fafc;
            --foreground: #0f172a;
            --card: #ffffff;
            --card-foreground: #0f172a;
            --primary: #2563eb;
            --primary-foreground: #ffffff;
            --secondary: #f1f5f9;
            --muted: #f1f5f9;
            --muted-foreground: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --destructive: #ef4444;
            --radius: 0.75rem;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--background); color: var(--foreground); line-height: 1.5; }

        .header { background: var(--card); border-bottom: 1px solid var(--border); padding: 1rem 0; sticky; top: 0; z-index: 50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--muted-foreground); font-size: 0.875rem; }
        .breadcrumb a { color: inherit; text-decoration: none; }
        .breadcrumb a:hover { color: var(--primary); }

        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; }
        .page-title h1 { font-size: 1.875rem; font-weight: 700; }
        .badge { padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved, .badge-verified { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }

        .card { background: var(--card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 2rem; overflow: hidden; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1.125rem; font-weight: 600; color: #334155; }
        .card-body { padding: 1.5rem; }

        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .info-item label { display: block; font-size: 0.75rem; color: var(--muted-foreground); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem; }
        .info-item p { font-size: 0.9375rem; font-weight: 600; color: #1e293b; }

        .section-box { margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9; }
        .section-box label { font-size: 0.8125rem; font-weight: 700; display: block; margin-bottom: 0.5rem; }
        .section-box p { font-size: 0.875rem; color: #475569; line-height: 1.6; }

        .doc-list { list-style: none; display: flex; flex-direction: column; gap: 0.75rem; }
        .doc-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: #fdfdfd; }
        .doc-link { color: var(--primary); text-decoration: none; font-size: 0.875rem; font-weight: 600; border: 1px solid var(--primary); padding: 4px 12px; border-radius: 6px; transition: 0.2s; }
        .doc-link:hover { background: var(--primary); color: #fff; }

        .warzone-panel { background: #0f172a; color: #f8fafc; padding: 1.5rem; }
        .score-display { text-align: center; padding: 2rem 0; border-bottom: 1px solid #1e293b; margin-bottom: 1.5rem; }
        .score-value { font-size: 3.5rem; font-weight: 800; color: #8b5cf6; }
        .score-label { font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; }

        .log-container { background: #000; border: 1px solid #1e293b; border-radius: 8px; padding: 1rem; font-family: monospace; font-size: 0.75rem; max-height: 350px; overflow-y: auto; }
        .log-entry { margin-bottom: 0.75rem; }
        .log-ai { color: #a78bfa; }
        .log-user { color: #e2e8f0; border-left: 2px solid #334155; padding-left: 8px; }

        textarea { width: 100%; min-height: 120px; padding: 1rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 0.875rem; margin-top: 0.5rem; }
        .btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
        .btn { padding: 0.75rem; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; transition: 0.2s; }
        .btn-approve { background: var(--success); color: #fff; }
        .btn-reject { background: var(--destructive); color: #fff; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .back-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            padding: 0.5rem 0.875rem; 
            border-radius: 8px; 
            border: 1px solid var(--border); 
            background: var(--card); 
            color: var(--muted-foreground); 
            text-decoration: none; 
            font-size: 0.8125rem; 
            font-weight: 500;
            transition: all 0.2s; 
            margin-bottom: 1.5rem;
        }
        .back-btn:hover { 
            background: var(--muted); 
            color: var(--foreground); 
            border-color: #cbd5e1;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="breadcrumb">
                <a href="pitch-approvals.php">Pitch Approval</a> 
                <span>/</span>
                <span>Review Request</span>
            </div>
            
            <a href="pitch-approvals.php" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Requests
            </a>

            <div class="page-header">
                <div class="page-title">
                    <h1><?php echo htmlspecialchars($pitch['startup_name']); ?></h1>
                    <p>Submitted by <?php echo htmlspecialchars($pitch['entrepreneur_name']); ?> • <?php echo date("F j, Y, g:i a", strtotime($pitch['created_at'])); ?></p>
                </div>
                <?php 
                    $stat_map = [0 => 'pending', 1 => 'approved', 2 => 'rejected'];
                    $cur_stat = $stat_map[$pitch['is_approved']];
                ?>
                <span class="badge badge-<?php echo $cur_stat; ?>"><?php echo ucfirst($cur_stat); ?></span>
            </div>
        </div>
    </header>

    <main class="container" style="padding: 2rem 0;">
        <div class="grid">
            <div class="left-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Startup Profile</h2>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--primary)"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item"><label>Industry</label><p><?php echo htmlspecialchars($pitch['industry']); ?></p></div>
                            <div class="info-item"><label>Stage</label><p><?php echo htmlspecialchars($pitch['stage']); ?></p></div>
                            <div class="info-item"><label>Funding Goal</label><p>₹<?php echo number_format($pitch['funding_goal']); ?></p></div>
                            <div class="info-item"><label>Valuation Ask</label><p>₹<?php echo number_format($displayValuation); ?></p></div>
                            <div class="info-item"><label>Location</label><p><?php echo htmlspecialchars($pitch['location']); ?></p></div>
                            <div class="info-item"><label>Contact</label><p><?php echo htmlspecialchars($pitch['entrepreneur_email']); ?></p></div>
                        </div>

                        <div class="section-box">
                            <label>Problem Statement</label>
                            <p><?php echo nl2br(htmlspecialchars($pitch['problem'] ?? '')); ?></p>
                        </div>
                        <div class="section-box">
                            <label>Solution & Product</label>
                            <p><?php echo nl2br(htmlspecialchars($pitch['solution'] ?? '')); ?></p>
                        </div>
                        <div style="margin-top:20px;">
                            <label style="font-size:12px; font-weight:700; color:var(--muted-foreground); text-transform:uppercase;">Full Description Block</label>
                            <div style="font-size:14px; color:#444; border:1px dashed #ddd; padding:15px; border-radius:8px; margin-top:5px;">
                                <?php echo $pitch['description']; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Pitch Documents</h2></div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <p style="color:var(--muted-foreground); font-size:14px; text-align:center;">No documents uploaded for this pitch.</p>
                        <?php else: ?>
                            <div class="doc-list">
                                <?php foreach($documents as $doc): ?>
                                    <div class="doc-item">
                                        <span style="font-weight:500; font-size:14px;"><?php echo htmlspecialchars($doc['name']); ?></span>
                                        <a href="../<?php echo $doc['file_url']; ?>" target="_blank" class="doc-link">View File</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="right-content">
                <form method="POST">
                <!-- Valuation Status Card -->
                <?php if ($valuation_req): ?>
                <div class="card" style="border-left: 4px solid var(--primary);">
                    <div class="card-header">
                        <h2>Valuation Verification</h2>
                        <span class="badge badge-<?php echo $valuation_req['status']; ?>">
                            <?php echo $valuation_req['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="font-size: 0.875rem; color: var(--muted-foreground);">Requested Valuation</span>
                            <span style="font-weight: 700;">₹<?php echo number_format($valuation_req['valuation_ask']); ?></span>
                        </div>
                        
                        <a href="valuation/valuationRequestView.php?id=<?php echo $valuation_req['id']; ?>" style="font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 600;">View Full Valuation Audit →</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Warzone Score Card -->
                <div class="card" style="background: #0f172a; border: none;">
                    <div class="card-header" style="background:transparent; border-bottom:1px solid #1e293b;">
                        <h2 style="color:#fff;">🧠 Warzone Audit</h2>
                    </div>
                    <div class="card-body warzone-panel">
                        <?php if($warzone): ?>
                            <div class="score-display">
                                <div class="score-label">Survival Score</div>
                                <div class="score-value"><?php echo $warzone['survival_score']; ?>%</div>
                            </div>
                            <div class="log-container">
                                <?php 
                                    $logs = json_decode($warzone['chat_history'], true);
                                    foreach($logs as $log): 
                                ?>
                                    <div class="log-entry <?php echo ($log['role'] === 'ai') ? 'log-ai' : 'log-user'; ?>">
                                        <strong><?php echo ($log['role'] === 'ai') ? 'AUDITOR' : 'FOUNDER'; ?>:</strong> 
                                        <?php echo htmlspecialchars($log['content']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align:center; color:var(--muted-foreground); padding: 2rem;">Audit interrogation not started.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Card -->
                <?php if ($pitch['is_approved'] == 0): ?>
                <div class="card" style="border: 1px solid var(--primary); background: #fdfdff;">
                    <div class="card-header" style="background: rgba(37, 99, 235, 0.03);">
                        <h2>Smart Decision Console</h2>
                    </div>
                    <div class="card-body">
                            <div class="smart-checklist" style="margin-bottom: 20px; padding: 10px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 8px;">
                                <h4 style="font-size: 11px; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">Approval Readiness</h4>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <?php 
                                        $val_ok = ($valuation_req && $valuation_req['status'] === 'verified');
                                        $warzone_ok = ($warzone && $warzone['survival_score'] > 50);
                                    ?>
                                    <div style="font-size: 13px; display: flex; align-items: center; gap: 8px;">
                                        <span style="color: <?php echo $val_ok ? '#22c55e' : '#ef4444'; ?>;"><?php echo $val_ok ? '✅' : '❌'; ?></span> 
                                        Valuation Verified
                                        <?php if(!$val_ok && $valuation_req): ?>
                                            <a href="valuation/valuationRequestView.php?id=<?php echo $valuation_req['id']; ?>" style="font-size:11px; color:var(--primary); text-decoration:underline; margin-left: auto;">Review Now</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; display: flex; align-items: center; gap: 8px;">
                                        <span style="color: <?php echo $warzone_ok ? '#22c55e' : '#ef4444'; ?>;"><?php echo $warzone_ok ? '✅' : '❌'; ?></span> 
                                        AI Warzone Audit (>50%)
                                    </div>
                                </div>
                            </div>

                            <label style="font-size:12px; font-weight:700; color:var(--muted-foreground);">ADMIN FEEDBACK / REMARKS</label>
                            <textarea name="admin_reason" placeholder="Explain why this decision is being made..."><?php echo htmlspecialchars($pitch['admin_feedback'] ?? ''); ?></textarea>
                            
                            <div class="btn-group" style="margin-top: 15px;">
                                <?php 
                                    $can_action = ($valuation_req && $valuation_req['status'] !== 'pending');
                                    $tooltip = !$can_action ? "Decide on Valuation first (Verified/Rejected)" : "";
                                    $disabled_attr = !$can_action ? 'disabled style="opacity:0.5; cursor:not-allowed;" title="'.$tooltip.'"' : '';
                                    
                                    // Specifically for Approval, must be VERIFIED
                                    $can_approve = ($valuation_req && $valuation_req['status'] === 'verified');
                                    $approve_disabled = !$can_approve ? 'disabled style="opacity:0.5; cursor:not-allowed;" title="Valuation must be VERIFIED to approve."' : '';
                                ?>
                                <button type="submit" name="action" value="reject" class="btn btn-reject" <?php echo $disabled_attr; ?>>Reject Pitch</button>
                                <button type="submit" name="action" value="approve" class="btn btn-approve" <?php echo $approve_disabled; ?>>Approve Pitch</button>
                            </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card" style="border: 1px solid <?php echo ($pitch['is_approved'] == 1) ? 'var(--success)' : 'var(--destructive)'; ?>;">
                    <div class="card-header" style="background: <?php echo ($pitch['is_approved'] == 1) ? 'rgba(34, 197, 94, 0.05)' : 'rgba(239, 68, 68, 0.05)'; ?>;">
                        <h2>Conclusion Summary</h2>
                    </div>
                    <div class="card-body">
                         <div class="badge badge-<?php echo ($pitch['is_approved'] == 1) ? 'verified' : 'rejected'; ?>" style="margin-bottom:15px; font-size:14px; padding: 8px 15px; border-radius: 6px;">
                            Decision: <?php echo ($pitch['is_approved'] == 1) ? 'APPROVED' : 'REJECTED'; ?>
                         </div>
                         <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border);">
                            <label style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase;">Admin Feedback</label>
                            <p style="font-size:14px; color:var(--foreground); margin-top:5px; line-height:1.6;">
                                <?php echo !empty($pitch['admin_feedback']) ? nl2br(htmlspecialchars($pitch['admin_feedback'])) : '<i>No feedback provided.</i>'; ?>
                            </p>
                         </div>
                    </div>
                </div>
                <?php endif; ?>
                </form>
            </div>
        </div>
    </main>        
</body>
</html>
