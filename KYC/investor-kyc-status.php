<?php
session_start();
// Adjust this path to point to your actual db.php file
require_once '../db.php'; 

// 1. LOGIN CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); 
    exit;
}

$user_id = $_SESSION['user_id'];
$kyc_data = [];

// 2. FETCH DATA FROM DATABASE
$sql = "SELECT k.*, i.name, i.email, i.contact 
        FROM investor_kyc_details k 
        JOIN investors i ON k.investor_id = i.id 
        WHERE k.investor_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $kyc_data = $result->fetch_assoc();
    } else {
        // No KYC found? Redirect to Form
        header("Location: investor-kyc.php");
        exit;
    }
    $stmt->close();
}

// 3. TIME-BASED PROGRESS LOGIC
$db_status = $kyc_data['status']; // 'submitted', 'under_review', 'approved', 'rejected'

// Get submission time (Handle missing date safely)
$raw_date = $kyc_data['submitted_at'] ?? $kyc_data['created_at'] ?? date('Y-m-d H:i:s');
$submitted_timestamp = strtotime($raw_date);
$current_time = time();
$hours_passed = ($current_time - $submitted_timestamp) / 3600;

// Determine JS Status & Timeline Stage
$js_status = 'under_review';
$timeline_stage = 2; // Default to Step 2

if ($db_status == 'approved') {
    $js_status = 'approved';
    $timeline_stage = 4; // Step 4 (Final)
} elseif ($db_status == 'rejected') {
    $js_status = 'rejected';
    $timeline_stage = 4; // Step 4 (Final)
} else {
    // It is under review. Check time.
    $js_status = 'under_review';
    if ($hours_passed >= 24) {
        $timeline_stage = 3; // Move to Compliance Review
    } else {
        $timeline_stage = 2; // Stay at Document Verification
    }
}

// 4. FORMAT DATA FOR DISPLAY
$submitted_date = date("F j, Y", strtotime($raw_date));
$updated_date = !empty($kyc_data['updated_at']) ? date("F j, Y", strtotime($kyc_data['updated_at'])) : 'Pending';
$kyc_ref_id = "KYC-" . date("Y") . "-" . str_pad($kyc_data['id'] ?? 0, 5, '0', STR_PAD_LEFT);
$rejection_reason = !empty($kyc_data['rejection_reason']) ? $kyc_data['rejection_reason'] : "Verification failed. Please check your documents.";

// 5. FILE PATH HELPER
function getDocUrl($path) {
    if (empty($path)) return '#';
    if (strpos($path, '../') === 0) return $path;
    return '../' . $path; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification Status | SmartPitchHub</title>
    <style>
        /* ===== CSS RESET & BASE ===== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0D0F1A;
            --bg-card: #13162A;
            --bg-card-hover: #1a1e35;
            --accent-purple: #A78BFA;
            --accent-purple-glow: rgba(167, 139, 250, 0.3);
            --accent-green: #22C55E;
            --accent-green-glow: rgba(34, 197, 94, 0.3);
            --accent-amber: #F59E0B;
            --accent-amber-glow: rgba(245, 158, 11, 0.3);
            --accent-red: #EF4444;
            --accent-red-glow: rgba(239, 68, 68, 0.3);
            --text-primary: #FFFFFF;
            --text-secondary: #9CA3AF;
            --text-muted: #6B7280;
            --border-color: #2D3154;
            --radius: 14px;
            --radius-sm: 8px;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ===== CONTAINER ===== */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .header-content h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header-content p {
            font-size: 15px;
            color: var(--text-secondary);
            max-width: 480px;
        }

        /* ===== STATUS BADGE ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .status-badge.under-review {
            background: rgba(245, 158, 11, 0.15);
            color: var(--accent-amber);
            border: 1px solid rgba(245, 158, 11, 0.3);
            box-shadow: 0 0 30px var(--accent-amber-glow);
        }

        .status-badge.approved {
            background: rgba(34, 197, 94, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(34, 197, 94, 0.3);
            box-shadow: 0 0 30px var(--accent-green-glow);
        }

        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 30px var(--accent-red-glow);
        }

        .status-badge .pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .under-review .pulse { background: var(--accent-amber); }
        .approved .pulse { background: var(--accent-green); }
        .rejected .pulse { background: var(--accent-red); }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        /* ===== CARD STYLES ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .card:hover {
            border-color: var(--accent-purple);
            box-shadow: 0 0 40px var(--accent-purple-glow);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title svg {
            width: 20px;
            height: 20px;
            color: var(--accent-purple);
        }

        /* ===== PROGRESS TIMELINE ===== */
        .timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 16px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: var(--border-color);
            z-index: 0;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: 3px solid var(--border-color);
            margin-bottom: 12px;
            transition: var(--transition);
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
        }

        .timeline-step.completed .step-icon {
            background: var(--accent-green);
            border-color: var(--accent-green);
            color: white;
        }

        .timeline-step.current .step-icon {
            background: var(--accent-purple);
            border-color: var(--accent-purple);
            color: white;
            box-shadow: 0 0 20px var(--accent-purple-glow);
            animation: currentPulse 2s infinite;
        }

        @keyframes currentPulse {
            0%, 100% { box-shadow: 0 0 20px var(--accent-purple-glow); }
            50% { box-shadow: 0 0 35px var(--accent-purple-glow); }
        }

        .timeline-step.disabled .step-icon {
            opacity: 0.4;
        }

        .step-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            max-width: 100px;
        }

        .timeline-step.completed .step-label,
        .timeline-step.current .step-label {
            color: var(--text-primary);
        }

        .timeline-helper {
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .timeline-helper svg {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }

        /* ===== STATUS MESSAGE ===== */
        .status-message {
            padding: 20px 24px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .status-message.info {
            background: rgba(167, 139, 250, 0.1);
            border: 1px solid rgba(167, 139, 250, 0.2);
        }

        .status-message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .status-message.warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-message svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .status-message.info svg { color: var(--accent-purple); }
        .status-message.success svg { color: var(--accent-green); }
        .status-message.warning svg { color: var(--accent-amber); }
        .status-message.error svg { color: var(--accent-red); }

        .status-message p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        /* ===== DETAILS GRID ===== */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .detail-item {
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* ===== VERIFICATION BADGES ===== */
        .badges-container {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            color: var(--accent-green);
        }

        .verification-badge svg {
            width: 16px;
            height: 16px;
        }

        /* ===== REJECTION REASONS ===== */
        .rejection-list {
            list-style: none;
            margin-bottom: 20px;
        }

        .rejection-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-secondary);
        }

        .rejection-list li:last-child {
            border-bottom: none;
        }

        .rejection-list svg {
            width: 18px;
            height: 18px;
            color: var(--accent-red);
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ===== BUTTONS ===== */
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent-purple);
            color: white;
        }

        .btn-primary:hover {
            background: #9171f0;
            box-shadow: 0 0 30px var(--accent-purple-glow);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--accent-purple);
            color: var(--accent-purple);
        }

        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        /* ===== KYC SUMMARY ===== */
        .summary-section {
            margin-top: 40px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            padding: 24px;
            transition: var(--transition);
        }

        .summary-card:hover {
            border-color: var(--accent-purple);
        }

        .summary-card h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-card h4 svg {
            width: 18px;
            height: 18px;
            color: var(--accent-purple);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row .label {
            font-size: 13px;
            color: var(--text-muted);
        }

        .summary-row .value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* ===== DOCUMENT CARDS ===== */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .document-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: block; /* Added for anchor tags */
        }

        .document-card:hover {
            border-color: var(--accent-purple);
            background: var(--bg-card-hover);
        }

        .document-icon {
            width: 48px;
            height: 48px;
            background: rgba(167, 139, 250, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }

        .document-icon svg {
            width: 24px;
            height: 24px;
            color: var(--accent-purple);
        }

        .document-card span {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .document-card small {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ===== SECURITY FOOTER ===== */
        .security-footer {
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(167, 139, 250, 0.08);
            border-radius: 50px;
            margin-bottom: 16px;
        }

        .security-badge svg {
            width: 18px;
            height: 18px;
            color: var(--accent-purple);
        }

        .security-badge span {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .compliance-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .last-updated {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* ===== ANIMATIONS ===== */
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .slide-up {
            animation: slideUp 0.5s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        /* ===== INFO BOX ===== */
        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 22px;
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: var(--radius-sm);
            margin-top: 20px;
        }

        .info-box svg {
            width: 20px;
            height: 20px;
            color: var(--accent-amber);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .info-box p {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container {
                padding: 24px 16px 40px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-content h1 {
                font-size: 26px;
            }

            .timeline {
                flex-direction: column;
                gap: 20px;
            }

            .timeline::before {
                top: 22px;
                bottom: 22px;
                left: 21px;
                right: auto;
                width: 3px;
                height: auto;
            }

            .timeline-step {
                flex-direction: row;
                text-align: left;
                gap: 16px;
            }

            .step-label {
                max-width: none;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .documents-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Hidden sections */
        .hidden {
            display: none !important;
        }




        /* ===== BACK LINK STYLE ===== */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 30px; /* Space below the button */
    transition: all 0.3s ease;
    opacity: 0.8;
}

.back-link:hover {
    color: var(--accent-purple); /* Glows purple on hover */
    opacity: 1;
    transform: translateX(-5px); /* Slides left slightly */
}

.back-link svg {
    width: 20px;
    height: 20px;
    transition: transform 0.3s ease;
}
    </style>
</head>
<body>
    <div class="container">
        <a href="../dashboards/investor-dashboard.php" class="back-link fade-in">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
    </svg>
    Back to Dashboard
</a>
        <header class="page-header fade-in">
            <div class="header-content">
                <h1>KYC Verification Status</h1>
                <p>We verify all entrepreneurs to ensure a secure and compliant investment ecosystem.</p>
            </div>
            <div class="status-badge" id="statusBadge">
                <span class="pulse"></span>
                <span id="statusText">Loading...</span>
            </div>
        </header>

        <div class="card slide-up delay-1">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 11 12 14 22 4"></polyline>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
                Verification Progress
            </h3>
            <div class="timeline" id="timeline">
                <div class="timeline-step" id="step1">
                    <div class="step-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <span class="step-label">KYC Submitted</span>
                </div>
                <div class="timeline-step" id="step2">
                    <div class="step-icon">2</div>
                    <span class="step-label">Document Verification</span>
                </div>
                <div class="timeline-step" id="step3">
                    <div class="step-icon">3</div>
                    <span class="step-label">Compliance Review</span>
                </div>
                <div class="timeline-step" id="step4">
                    <div class="step-icon">4</div>
                    <span class="step-label">Final Decision</span>
                </div>
            </div>
            <p class="timeline-helper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Typical verification time: 24–48 business hours
            </p>
        </div>

        <div class="card slide-up delay-2 hidden" id="underReviewContent">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                Review In Progress
            </h3>
            <div class="status-message warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <p>Your KYC submission is currently under review by our compliance team. We're carefully verifying all your submitted documents and information to ensure compliance with regulatory standards.</p>
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Submission Date</div>
                    <div class="detail-value"><?php echo $submitted_date; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">KYC Reference ID</div>
                    <div class="detail-value"><?php echo $kyc_ref_id; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Estimated Completion</div>
                    <div class="detail-value">Within 48 Hours</div>
                </div>
            </div>
            <div class="info-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <p><strong>Platform actions temporarily restricted:</strong> Certain features like raising investments and creating pitch decks are unavailable until your verification is complete.</p>
            </div>
            <div class="button-group">
                <button class="btn btn-primary" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                    Go to Dashboard
                </button>
            </div>
        </div>

        <div class="card slide-up delay-2 hidden" id="approvedContent">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Verification Complete
            </h3>
            <div class="status-message success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <p>Congratulations! Your identity has been successfully verified. You now have full access to all platform features including creating pitch decks and raising investments.</p>
            </div>
            <div class="badges-container">
                <span class="verification-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Identity Verified
                </span>
                <span class="verification-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    Bank Verified
                </span>
                <span class="verification-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Business Verified
                </span>
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Approval Date</div>
                    <div class="detail-value"><?php echo $updated_date; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">KYC ID</div>
                    <div class="detail-value"><?php echo $kyc_ref_id; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Verification Level</div>
                    <div class="detail-value">Enhanced</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Valid Until</div>
                    <div class="detail-value">January 16, 2028</div>
                </div>
            </div>
            <div class="button-group">
                <a href="../dashboards/investor-dashboard.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                    Go to Dashboard
                </a>
                <button class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    View KYC Summary
                </button>
            </div>
        </div>

        <div class="card slide-up delay-2 hidden" id="rejectedContent">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                Action Required
            </h3>
            <div class="status-message error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <p>Unfortunately, your KYC submission could not be approved. Please review the issues below and resubmit.</p>
            </div>
            
            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">Rejection Reason:</h4>
            
            <div style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($rejection_reason); ?></p>
            </div>

            <div class="status-message info" style="margin-top: 16px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <p>Please correct the issues mentioned above and resubmit your KYC application. Ensure all documents are clear, legible, and match your registered details.</p>
            </div>
            <div class="button-group">
                <a href="investor-kyc.php?retry=true" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    Resubmit KYC
                </a>
                <button class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    View Submitted Documents
                </button>
            </div>
        </div>

        <section class="summary-section slide-up delay-3">
            <h3 class="card-title" style="margin-bottom: 24px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-purple);">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Submitted KYC Information
            </h3>
            <div class="summary-grid">
                <div class="summary-card">
                    <h4>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Personal Information
                    </h4>
                    <div class="summary-row">
                        <span class="label">Legal Name</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['name']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['email']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Phone</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['contact']); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <h4>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        Business Information
                    </h4>
                    <div class="summary-row">
                        <span class="label">Investor Type</span>
                        <span class="value" style="text-transform: capitalize;"><?php echo htmlspecialchars($kyc_data['investor_type']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Business Type</span>
                        <span class="value">Individual</span>
                    </div>
                    <div class="summary-row">
                        <span class="label">PAN Number</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['pan_number']); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <h4>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                            <line x1="1" y1="10" x2="23" y2="10"></line>
                        </svg>
                        Bank Details
                    </h4>
                    <div class="summary-row">
                        <span class="label">Bank Name</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['bank_name']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">Account Number</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['account_number']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="label">IFSC Code</span>
                        <span class="value"><?php echo htmlspecialchars($kyc_data['ifsc_code']); ?></span>
                    </div>
                </div>
            </div>

            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-top: 32px; margin-bottom: 8px;">Uploaded Documents</h4>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">Click on any document to view in a new tab.</p>
            
            <div class="documents-grid">
                <?php if(!empty($kyc_data['identity_proof_path'])): ?>
                <a href="<?php echo getDocUrl($kyc_data['identity_proof_path']); ?>" class="document-card" target="_blank">
                    <div class="document-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    </div>
                    <span>Identity Proof</span>
                    <small>Click to View</small>
                </a>
                <?php endif; ?>

                <?php if(!empty($kyc_data['address_proof_path'])): ?>
                <a href="<?php echo getDocUrl($kyc_data['address_proof_path']); ?>" class="document-card" target="_blank">
                    <div class="document-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <span>Address Proof</span>
                    <small>Click to View</small>
                </a>
                <?php endif; ?>

                <?php if(!empty($kyc_data['bank_proof_path'])): ?>
                <a href="<?php echo getDocUrl($kyc_data['bank_proof_path']); ?>" class="document-card" target="_blank">
                    <div class="document-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    </div>
                    <span>Bank Proof</span>
                    <small>Click to View</small>
                </a>
                <?php endif; ?>
                
                 <?php if(!empty($kyc_data['selfie_path'])): ?>
                <a href="<?php echo getDocUrl($kyc_data['selfie_path']); ?>" class="document-card" target="_blank">
                    <div class="document-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    </div>
                    <span>Live Photo</span>
                    <small>Click to View</small>
                </a>
                <?php endif; ?>
            </div>
        </section>

        <footer class="security-footer slide-up delay-4">
            <div class="security-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Your information is encrypted and securely stored</span>
            </div>
            <p class="compliance-text">KYC is conducted as per regulatory and platform compliance standards.</p>
            <p class="last-updated">Last status update: <?php echo $updated_date; ?></p>
        </footer>
    </div>

    <div style="display: none; position: fixed; bottom: 20px; right: 20px; background: var(--bg-card); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); z-index: 1000;">
        <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Demo: Change Status</p>
        <select id="statusSelector" style="background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 6px; font-size: 13px; cursor: pointer;">
            <option value="under_review">Under Review</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    <script>
        // ===== KYC STATUS CONTROLLER =====
        // PHP INJECTION: This line pulls the status from your Database!
        let currentStatus = "<?php echo $js_status; ?>"; 
        let currentStage = <?php echo $timeline_stage; ?>; // Dynamic Stage (2, 3, or 4)

        // DOM Elements
        const statusBadge = document.getElementById('statusBadge');
        const statusText = document.getElementById('statusText');
        const underReviewContent = document.getElementById('underReviewContent');
        const approvedContent = document.getElementById('approvedContent');
        const rejectedContent = document.getElementById('rejectedContent');
        const timeline = document.getElementById('timeline');
        const statusSelector = document.getElementById('statusSelector');

        // Timeline step configurations based on status
        const timelineConfigs = {
            under_review: { 
                // Dynamically check the stage. If stage is 3 (Compliance), mark 1 & 2 as done.
                completed: currentStage >= 3 ? [1, 2] : [1], 
                current: currentStage, 
                disabled: [4] 
            },
            approved: { completed: [1, 2, 3, 4], current: null, disabled: [] },
            rejected: { completed: [1, 2, 3], current: 4, disabled: [] }
        };

        // Update timeline based on configuration
        function updateTimeline(config) {
            for (let i = 1; i <= 4; i++) {
                const step = document.getElementById(`step${i}`);
                if(!step) continue; 
                const icon = step.querySelector('.step-icon');
                
                // Reset classes
                step.classList.remove('completed', 'current', 'disabled');
                
                if (config.completed.includes(i)) {
                    step.classList.add('completed');
                    icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
                } else if (config.current === i) {
                    step.classList.add('current');
                    icon.innerHTML = i;
                } else if (config.disabled && config.disabled.includes(i)) {
                    step.classList.add('disabled');
                    icon.innerHTML = i;
                } else {
                    icon.innerHTML = i;
                }
            }
        }

        // Update status badge
        function updateStatusBadge(status) {
            statusBadge.classList.remove('under-review', 'approved', 'rejected');
            
            switch(status) {
                case 'under_review':
                    statusBadge.classList.add('under-review');
                    statusText.textContent = 'Under Review';
                    break;
                case 'approved':
                    statusBadge.classList.add('approved');
                    statusText.textContent = 'Approved';
                    break;
                case 'rejected':
                    statusBadge.classList.add('rejected');
                    statusText.textContent = 'Rejected';
                    break;
            }
        }

        // Show appropriate content section
        function updateContentSections(status) {
            // Hide all sections first
            underReviewContent.classList.add('hidden');
            approvedContent.classList.add('hidden');
            rejectedContent.classList.add('hidden');
            
            // Show the appropriate section
            switch(status) {
                case 'under_review':
                    underReviewContent.classList.remove('hidden');
                    break;
                case 'approved':
                    approvedContent.classList.remove('hidden');
                    break;
                case 'rejected':
                    rejectedContent.classList.remove('hidden');
                    break;
            }
        }

        // Main update function
        function updateStatus(status) {
            currentStatus = status;
            updateStatusBadge(status);
            if(timelineConfigs[status]) {
                updateTimeline(timelineConfigs[status]);
            }
            updateContentSections(status);
        }

        // Event listener for demo switcher (If enabled)
        statusSelector.addEventListener('change', (e) => {
            updateStatus(e.target.value);
        });

        // Initialize the page with the default status
        document.addEventListener('DOMContentLoaded', () => {
            console.log("Status loaded from DB:", currentStatus, "Stage:", currentStage);
            updateStatus(currentStatus);
        });
    </script>
</body>
</html>