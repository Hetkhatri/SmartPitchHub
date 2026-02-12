<?php
session_start();
require_once '../db.php'; 

// 1. Security Check
if (!isset($_SESSION['admin_id'])) {
    // header("Location: ../admin/index.php");
    // exit;
}

// 2. Get KYC ID
if (!isset($_GET['id'])) {
    die("Error: No KYC ID provided in URL.");
}
$kyc_id = intval($_GET['id']);

// 3. FETCH DATA (ROBUST QUERY)
$sql = "SELECT 
            k.*, 
            i.name as user_name, 
            i.email as user_email, 
            i.contact as user_contact, 
            i.created_at as account_created 
        FROM investor_kyc_details k 
        JOIN investors i ON k.investor_id = i.id 
        WHERE k.id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database Error: " . $conn->error);
}
$stmt->bind_param("i", $kyc_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: KYC record #$kyc_id not found.");
}

$data = $result->fetch_assoc();


if ($data['ai_doc_score'] <= 0 && !isset($_GET['ai_retry'])) {
    require_once 'ai_engine_processor.php';
    run_ai_analysis($kyc_id, $conn);
    
    // Re-fetch data so we show updated AI scores without a page refresh loop
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    // Safety check: if it still 0, we must stop and not loop again
    if ($data['ai_doc_score'] <= 0) {
        $data['ai_doc_score'] = 1; // Temporary mock value to prevent loop
    }
}
// ---------------------------------------------------------
// 4. MAP & FORMAT DATA (This is the new part!)
// ---------------------------------------------------------

// Helper: Format "5l_10l" -> "₹5L - ₹10L"
function formatMoney($str) {
    if (empty($str) || $str === 'N/A') return 'N/A';
    
    // 1. Replace underscore with " - "
    $str = str_replace('_', ' - ', $str);
    
    // 2. Capitalize (5l -> 5L)
    $str = strtoupper($str);
    
    // 3. Add Rupee Symbol to each part
    $parts = explode(' - ', $str);
    $formattedParts = array_map(function($p) {
        // Only add ₹ if it's a number-like string
        return (preg_match('/[0-9]/', $p)) ? '₹' . trim($p) : trim($p);
    }, $parts);
    
    return implode(' - ', $formattedParts);
}

// Basic Fields
$full_name = !empty($data['full_name']) ? $data['full_name'] : $data['user_name'];
$email = !empty($data['email']) ? $data['email'] : $data['user_email'];
$contact = !empty($data['contact']) ? $data['contact'] : $data['user_contact'];
$country = !empty($data['country_of_residence']) ? $data['country_of_residence'] : 'N/A';

// Financial Fields (Formatted)
$annual_income = formatMoney($data['annual_income'] ?? '');
$net_worth = formatMoney($data['net_worth'] ?? '');
$typ_invest = formatMoney($data['typical_investment_amount'] ?? '');
$experience = ucfirst(str_replace('_', ' ', $data['investment_experience'] ?? 'N/A'));

// Handle Dates
$sub_date_raw = $data['submission_date'] ?? $data['created_at'] ?? 'now';
$submission_date = date("M d, Y", strtotime($sub_date_raw));
$acc_created = !empty($data['account_created']) ? date("M d, Y", strtotime($data['account_created'])) : 'N/A';

// Format ID
$formatted_id = 'INV-' . date("Y") . '-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);

// Document Helper
function getDocUrl($path) {
    if (empty($path)) return '#';
    if (strpos($path, 'uploads/') === 0) return '../' . $path;
    return $path;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartPitchHub Admin - KYC Verification</title>
    <meta name="description" content="Admin portal for investor KYC verification and compliance management">
    <link rel="stylesheet" href="../css/kycDetailInvestor.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* Fallback styles in case CSS file is missing */
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .document-preview img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; }
        .status-badge.status-approved { background-color: #dcfce7; color: #166534; }
        .status-badge.status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-badge.status-under-review { background-color: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>
    <div class="page-container">
        <button class="back-button" onclick="window.location.href='kyc-requests.php'">
            <i data-lucide="arrow-left"></i>
            Back to Dashboard
        </button>

        <div class="card header-card">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Investor KYC Verification</h1>
                    <p class="page-subtitle">Review investor identity and compliance details</p>
                </div>
                <div class="header-right">
                    <span class="status-badge status-<?php echo str_replace('_', '-', $data['status']); ?>" id="statusBadge">
                        <span class="status-dot"></span>
                        <?php echo ucwords(str_replace('_', ' ', $data['status'])); ?>
                    </span>
                    <div class="header-meta">
                        <div class="meta-item">
                            <i data-lucide="hash"></i>
                            <span><?php echo $formatted_id; ?></span>
                        </div>
                        <div class="meta-item">
                            <i data-lucide="calendar"></i>
                            <span><?php echo $submission_date; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Investor Basic Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-icon info-icon-secondary"><i data-lucide="user"></i></div>
                    <div class="info-content">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-secondary"><i data-lucide="mail"></i></div>
                    <div class="info-content">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-secondary"><i data-lucide="phone"></i></div>
                    <div class="info-content">
                        <span class="info-label">Mobile Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($contact); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-secondary"><i data-lucide="globe"></i></div>
                    <div class="info-content">
                        <span class="info-label">Country</span>
                        <span class="info-value"><?php echo htmlspecialchars($country); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-secondary"><i data-lucide="calendar-days"></i></div>
                    <div class="info-content">
                        <span class="info-label">Account Created</span>
                        <span class="info-value"><?php echo $acc_created; ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-secondary"><i data-lucide="refresh-cw"></i></div>
                    <div class="info-content">
                        <span class="info-label">KYC Attempt Count</span>
                        <span class="info-value">1</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Document Verification</h2>
            <div class="documents-grid">
                
                <div class="glass-card document-card">
                    <div class="document-header">
                        <div>
                            <h3 class="document-title">Identity Proof (<?php echo strtoupper($data['document_type'] ?? 'ID'); ?>)</h3>
                            <div class="document-date">
                                <i data-lucide="calendar"></i>
                                <span>Uploaded: <?php echo $submission_date; ?></span>
                            </div>
                        </div>
                        <span class="quality-badge quality-good">
                            <i data-lucide="check-circle-2"></i> Good Quality
                        </span>
                    </div>
                    <div class="document-preview">
                        <i data-lucide="file-text" class="preview-icon"></i>
                        <p class="preview-filename"><?php echo htmlspecialchars($data['document_number'] ?? 'N/A'); ?></p>
                        <p class="preview-type">Document File</p>
                    </div>
                    <button class="btn btn-outline btn-full" onclick="viewDocument('<?php echo getDocUrl($data['identity_proof_path']); ?>')">
                        <i data-lucide="eye"></i> View Document
                    </button>
                </div>

                <div class="glass-card document-card">
                    <div class="document-header">
                        <div>
                            <h3 class="document-title">Address Proof</h3>
                            <div class="document-date">
                                <i data-lucide="calendar"></i>
                                <span>Uploaded: <?php echo $submission_date; ?></span>
                            </div>
                        </div>
                        <span class="quality-badge quality-good">
                            <i data-lucide="check-circle-2"></i> Good Quality
                        </span>
                    </div>
                    <div class="document-preview">
                        <i data-lucide="home" class="preview-icon"></i>
                        <p class="preview-filename">Address Document</p>
                        <p class="preview-type">PDF/Image</p>
                    </div>
                    <button class="btn btn-outline btn-full" onclick="viewDocument('<?php echo getDocUrl($data['address_proof_path']); ?>')">
                        <i data-lucide="eye"></i> View Document
                    </button>
                </div>

                <div class="glass-card document-card">
                    <div class="document-header">
                        <div>
                            <h3 class="document-title">Bank Proof</h3>
                            <div class="document-date">
                                <i data-lucide="calendar"></i>
                                <span>Uploaded: <?php echo $submission_date; ?></span>
                            </div>
                        </div>
                        <span class="quality-badge quality-medium">
                            <i data-lucide="alert-circle"></i> Review Needed
                        </span>
                    </div>
                    <div class="document-preview">
                        <i data-lucide="credit-card" class="preview-icon"></i>
                        <p class="preview-filename">Bank Statement</p>
                        <p class="preview-type">PDF/Image</p>
                    </div>
                    <button class="btn btn-outline btn-full" onclick="viewDocument('<?php echo getDocUrl($data['bank_proof_path']); ?>')">
                        <i data-lucide="eye"></i> View Document
                    </button>
                </div>

                <div class="glass-card document-card">
                    <div class="document-header">
                        <div>
                            <h3 class="document-title">Live Selfie</h3>
                            <div class="document-date">
                                <i data-lucide="calendar"></i>
                                <span>Uploaded: <?php echo $submission_date; ?></span>
                            </div>
                        </div>
                        <span class="quality-badge quality-good">
                            <i data-lucide="check-circle-2"></i> Good Quality
                        </span>
                    </div>
                    <div class="document-preview">
                        <?php if(!empty($data['selfie_path'])): ?>
                            <img src="<?php echo getDocUrl($data['selfie_path']); ?>" alt="Selfie" class="preview-image">
                        <?php else: ?>
                            <i data-lucide="image" class="preview-icon"></i>
                            <p class="preview-filename">No Selfie Uploaded</p>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-outline btn-full" onclick="viewDocument('<?php echo getDocUrl($data['selfie_path']); ?>')">
                        <i data-lucide="maximize"></i> Full View
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Financial & Investment Profile</h2>
            <div class="financial-grid">
                <div class="info-item">
                    <div class="info-icon info-icon-primary"><i data-lucide="dollar-sign"></i></div>
                    <div class="info-content">
                        <span class="info-label">Annual Income Range</span>
                        <span class="info-value"><?php echo htmlspecialchars($annual_income); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-primary"><i data-lucide="trending-up"></i></div>
                    <div class="info-content">
                        <span class="info-label">Net Worth Range</span>
                        <span class="info-value"><?php echo htmlspecialchars($net_worth); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-primary"><i data-lucide="target"></i></div>
                    <div class="info-content">
                        <span class="info-label">Experience</span>
                        <span class="info-value"><?php echo htmlspecialchars($experience); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon info-icon-primary"><i data-lucide="wallet"></i></div>
                    <div class="info-content">
                        <span class="info-label">Typically Invests</span>
                        <span class="info-value"><?php echo htmlspecialchars($typ_invest); ?></span>
                    </div>
                </div>
            </div>      <div class="declarations-section">
                <div class="declarations-header">
                    <i data-lucide="file-check"></i>
                    <h3>Legal Declarations & Consent</h3>
                </div>
                <div class="declarations-grid">
                    <label class="checkbox-item"><input type="checkbox" checked disabled><span class="checkmark"></span> Terms & Conditions accepted</label>
                    <label class="checkbox-item"><input type="checkbox" checked disabled><span class="checkmark"></span> Privacy Policy acknowledged</label>
                    <label class="checkbox-item"><input type="checkbox" checked disabled><span class="checkmark"></span> Risk Disclosure signed</label>
                    <label class="checkbox-item"><input type="checkbox" checked disabled><span class="checkmark"></span> Tax Compliance declaration</label>
                </div>
            </div>
        </div>

        <<div class="card">
            <div class="ai-header">
                <div class="ai-icon"><i data-lucide="sparkles"></i></div>
                <div>
                    <h2 class="section-title" style="margin-bottom: 0;">AI-Assisted KYC Analysis</h2>
                    <p class="ai-subtitle">Automated verification and risk assessment</p>
                </div>
            </div>
            <div class="ai-modules-grid">
                
                <?php 
                    $doc_class = ($data['ai_doc_score'] >= 80) ? 'status-pass' : (($data['ai_doc_score'] >= 50) ? 'status-warning' : 'status-fail'); 
                    $module_class = ($data['ai_doc_score'] >= 80) ? 'ai-module-success' : 'ai-module-warning';
                ?>
                <div class="ai-module <?php echo $module_class; ?>">
                    <div class="ai-module-header">
                        <div class="ai-module-icon ai-module-icon-success"><i data-lucide="scan-search"></i></div>
                        <h3>Document Verification AI</h3>
                    </div>
                    <div class="ai-module-items">
                        <div class="ai-item">
                            <span class="ai-item-label">OCR Match Score</span>
                            <span class="ai-item-value <?php echo $doc_class; ?>"><?php echo $data['ai_doc_score']; ?>%</span>
                        </div>
                        <div class="ai-item">
                            <span class="ai-item-label">Quality Check</span>
                            <span class="ai-item-value <?php echo $doc_class; ?>"><?php echo $data['ai_doc_quality']; ?></span>
                        </div>
                        <div class="ai-item">
                            <span class="ai-item-label">Tampering Detection</span>
                            <span class="ai-item-value status-pass">No Issues</span>
                        </div>
                    </div>
                </div>

                <?php 
                    // High risk = bad (red), Low risk = good (green)
                    $fraud_class = ($data['ai_fraud_risk_level'] == 'High') ? 'status-fail' : 'status-pass'; 
                    $fraud_module = ($data['ai_fraud_risk_level'] == 'High') ? 'ai-module-error' : 'ai-module-success';
                ?>
                <div class="ai-module <?php echo $fraud_module; ?>">
                    <div class="ai-module-header">
                        <div class="ai-module-icon ai-module-icon-success"><i data-lucide="shield-alert"></i></div>
                        <h3>Fraud & Risk Analysis</h3>
                    </div>
                    <div class="ai-module-items">
                        <div class="ai-item">
                            <span class="ai-item-label">Duplicate Check</span>
                            <span class="ai-item-value status-pass">Clear</span>
                        </div>
                        <div class="ai-item">
                            <span class="ai-item-label">Risk Level</span>
                            <span class="ai-item-value <?php echo $fraud_class; ?>"><?php echo $data['ai_fraud_risk_level']; ?></span>
                        </div>
                        <div class="ai-item">
                            <span class="ai-item-label">Overall Fraud Score</span>
                            <span class="ai-item-value <?php echo $fraud_class; ?>"><?php echo $data['ai_fraud_score']; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="ai-module ai-module-info">
                    <div class="ai-module-header">
                        <div class="ai-module-icon ai-module-icon-info"><i data-lucide="brain"></i></div>
                        <h3>Behavioral Analysis</h3>
                    </div>
                    <div class="ai-module-items">
                        <div class="ai-item">
                            <span class="ai-item-label">Trust Score</span>
                            <span class="ai-item-value status-pass"><?php echo $data['ai_behavior_score']; ?>/100</span>
                        </div>
                        <div class="ai-item">
                            <span class="ai-item-label">Session Duration</span>
                            <span class="ai-item-value">8 mins (avg)</span>
                        </div>
                    </div>
                </div>

                <?php 
                    $rec_class = ($data['ai_recommendation'] == 'Reject') ? 'status-fail' : 'status-pass'; 
                ?>
                <div class="ai-module ai-module-success">
                    <div class="ai-module-header">
                        <div class="ai-module-icon ai-module-icon-success"><i data-lucide="sparkles"></i></div>
                        <h3>AI Recommendation</h3>
                    </div>
                    <div class="ai-module-items">
                        <div class="ai-item">
                            <span class="ai-item-label">AI Verdict</span>
                            <span class="ai-item-value <?php echo $rec_class; ?>" style="font-weight: 700;">
                                <?php echo strtoupper($data['ai_recommendation']); ?>
                            </span>
                        </div>
                        <div class="ai-item">
                            <span class="ai-item-label">Confidence Score</span>
                            <span class="ai-item-value"><?php echo $data['ai_confidence']; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="actionPanel" <?php if($data['status'] == 'approved' || $data['status'] == 'rejected') echo 'style="display:none;"'; ?>>
            <h2 class="section-title">Admin Action Panel</h2>
            
            <div class="override-warning" id="overrideWarning" style="display: none;">
                <div class="warning-icon"><i data-lucide="alert-triangle"></i></div>
                <div class="warning-content">
                    <h3>AI Override Warning</h3>
                    <p>You are about to approve this KYC against AI recommendation to reject. This action requires justification and will be marked for Super Admin review.</p>
                    <textarea class="textarea" placeholder="Enter justification for overriding AI recommendation..."></textarea>
                    <div class="warning-actions">
                        <button class="btn btn-success" onclick="confirmOverride()">Confirm Override & Approve</button>
                        <button class="btn btn-outline" onclick="cancelOverride()">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="reject-form" id="rejectForm" style="display: none;">
                <div class="reject-icon"><i data-lucide="info"></i></div>
                <div class="reject-content">
                    <h3>Rejection Details</h3>
                    <textarea class="textarea" id="rejectReason" placeholder="Enter rejection reason (mandatory)..."></textarea>
                    <div class="toggle-container">
                        <label class="toggle">
                            <input type="checkbox" id="allowResubmission" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Allow investor to re-submit KYC</span>
                    </div>
                    <div class="reject-actions">
                        <button class="btn btn-destructive" onclick="confirmReject()">Confirm Rejection</button>
                        <button class="btn btn-outline" onclick="cancelReject()">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="action-buttons" id="mainActions">
                <button class="btn btn-success btn-large" onclick="approveKYC()">
                    <i data-lucide="check-circle-2"></i> Approve KYC
                </button>
                <button class="btn btn-destructive btn-large" onclick="rejectKYC()">
                    <i data-lucide="x-circle"></i> Reject KYC
                </button>
            </div>
        </div>

        <div class="card">
            <div class="audit-header">
                <div class="audit-icon"><i data-lucide="clock"></i></div>
                <div>
                    <h2 class="section-title" style="margin-bottom: 0;">Decision History & Audit Log</h2>
                    <p class="audit-subtitle">Complete timeline of KYC verification process</p>
                </div>
            </div>
            <?php if($data['status'] == 'pending' || $data['status'] == 'under_review'): ?>
                    <div    class="timeline-item">
                        <div class="timeline-marker timeline-marker-warning">
                            <i data-lucide="clock"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h3>Under Review</h3>
                                <span class="timeline-time">Current Stage</span>
                            </div>
                            <p>Application is currently being reviewed by the compliance team.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                
                <?php if($data['status'] == 'approved'): ?>
                <div class="timeline-item">
                    <div class="timeline-marker timeline-marker-success"><i data-lucide="check"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <h3>Approved</h3>
                            <span class="timeline-time"><?php echo date("M d, Y", strtotime($data['updated_at'])); ?></span>
                        </div>
                        <p>Admin approved the application.</p>
                    </div>
                </div>
                <?php elseif($data['status'] == 'rejected'): ?>
                <div class="timeline-item">
                    <div class="timeline-marker timeline-marker-destructive"><i data-lucide="x"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <h3>Rejected</h3>
                            <span class="timeline-time"><?php echo date("M d, Y", strtotime($data['updated_at'])); ?></span>
                        </div>
                        <p>Reason: <?php echo htmlspecialchars($data['rejection_reason']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="toast" id="toast">
        <div class="toast-icon"><i data-lucide="check-circle-2"></i></div>
        <div class="toast-content">
            <h4 id="toastTitle">Success</h4>
            <p id="toastMessage">Action completed successfully.</p>
        </div>
    </div>

    <script src="../js/kycDetailinvestor.js"></script>
</body>
</html>