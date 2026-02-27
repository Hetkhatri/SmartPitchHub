<?php
// submit-pitch.php - SHARE-BASED LOGIC IMPLEMENTED
session_start();

// 1. DISABLE HTML ERRORS & SET JSON HEADER
error_reporting(E_ALL); 
ini_set('display_errors', 0); 
header('Content-Type: application/json');

// 2. RESPONSE HELPER
function sendJson($success, $message, $redirect = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit;
}

// 3. CONNECT TO DATABASE
if (file_exists('../db.php')) {
    require '../db.php';
} else {
    sendJson(false, "System Error: Could not find db.php in the root folder.");
}

// Check if $conn exists and is valid
if (!isset($conn) || !$conn) {
    sendJson(false, "Database connection failed. Check db.php.");
}

// 4. AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    sendJson(false, "Unauthorized. Please login first.");
}
$user_id = $_SESSION['user_id'];

// Check Role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'entrepreneur') {
    sendJson(false, "Access Denied. Only entrepreneurs can submit pitches.");
}

// 4.1 KYC Check (Production Standard)
$kycSql = "SELECT kyc_status, total_shares, available_shares FROM entrepreneurs WHERE id = ?";
$kycStmt = $conn->prepare($kycSql);
$kycStmt->bind_param("i", $user_id);
$kycStmt->execute();
$kycRes = $kycStmt->get_result()->fetch_assoc();
if (!$kycRes || $kycRes['kyc_status'] !== 'verified') {
    sendJson(false, "Identity Verification Required. Please complete your KYC in the dashboard before submitting a pitch.");
}
$totalShares = intval($kycRes['total_shares']);
$availableShares = intval($kycRes['available_shares']);

$mode = $_POST['mode'] ?? 'new';

// Check for existing pitch ONLY if not launching a next round
if ($mode !== 'next_round') {
    $checkSql = "SELECT id FROM pitches WHERE entrepreneur_id = ? LIMIT 1";
    if ($cStmt = $conn->prepare($checkSql)) {
        $cStmt->bind_param("i", $user_id);
        $cStmt->execute();
        if ($cStmt->get_result()->num_rows > 0) {
            sendJson(false, "You already have a pitch submitted.");
        }
        $cStmt->close();
    }
}

// 5. HELPER: UPLOAD FILE
function uploadFile($file, $subfolder, $allowedTypes) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    // Save to: root/uploads/subfolder/
    $baseDir = '../uploads/'; 
    $targetDir = $baseDir . $subfolder . '/';

    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) return false;

    $filename = uniqid() . '.' . $ext;
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return path relative to project root
        return 'uploads/' . $subfolder . '/' . $filename;
    }
    return false;
}

// 6. MAIN LOGIC
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // A. Collect Data
    $startupName = $_POST['startupName'] ?? 'Untitled';
    $category = $_POST['category'] ?? 'Others';
    $stage = $_POST['stage'] ?? 'Idea';
    $location = $_POST['location'] ?? 'India';
    $tagline = $_POST['tagline'] ?? '';
    $problem = $_POST['problem'] ?? '';
    $solution = $_POST['solution'] ?? '';
    $fundingGoal = floatval($_POST['fundingRequired'] ?? 0);
    $valuation = floatval($_POST['valuation'] ?? 0);
    
    // Auto-Calculate Round Name on Backend for Security/Integrity
    $roundName = 'Seed Round';
    $roundNumber = 1;
    $countSql = "SELECT COUNT(*) as round_count FROM pitches WHERE entrepreneur_id = ?";
    if ($countStmt = $conn->prepare($countSql)) {
        $countStmt->bind_param("i", $user_id);
        $countStmt->execute();
        $rcRes = $countStmt->get_result()->fetch_assoc();
        $r_count = $rcRes['round_count'] ?? 0;
        
        $roundNumber = $r_count + 1;
        $rounds_seq = ['Seed Round', 'Series A', 'Series B', 'Series C', 'Series D'];
        $roundName = ($r_count < count($rounds_seq)) ? $rounds_seq[$r_count] : "Series " . chr(65 + $r_count - 1);
    }

    // Validation
    if ($valuation <= 0 || $fundingGoal <= 0) {
        throw new Exception("Funding and Valuation must be greater than zero.");
    }

    // Combine Description Fields
    $descFields = ['problem'=>'Problem', 'solution'=>'Solution', 'valueProposition'=>'USP', 'targetMarket'=>'Target Market', 'revenueModel'=>'Revenue', 'competitors'=>'Competitors', 'traction'=>'Traction'];
    $description = "";
    foreach ($descFields as $key => $label) {
        if (!empty($_POST[$key])) {
            $description .= "<strong>$label:</strong><br>" . nl2br(htmlspecialchars($_POST[$key])) . "<br><br>";
        }
    }

    // Start Transaction
    $conn->begin_transaction();

    // B. SHARE LOGIC CALCULATION
    // 1. Fetch Entrepreneur's Available Shares
    // Using FOR UPDATE to lock the row and prevent race conditions
    $shareSql = "SELECT total_shares, available_shares FROM entrepreneurs WHERE id = ? FOR UPDATE";
    $stmtShare = $conn->prepare($shareSql);
    $stmtShare->bind_param("i", $user_id);
    $stmtShare->execute();
    $resultShare = $stmtShare->get_result();
    
    if ($resultShare->num_rows === 0) {
        throw new Exception("Entrepreneur record not found.");
    }

    $userShares = $resultShare->fetch_assoc();
    $totalShares = $userShares['total_shares'] ?? 50000; // Default if null
    $availableShares = $userShares['available_shares'] ?? 50000;

    // 2. Calculate Share Price & Issue Count
    $sharePrice = $valuation / $totalShares;
    
    // Safety check for division by zero or negative price
    if ($sharePrice <= 0) {
        throw new Exception("Invalid Share Price calculation. Check valuation.");
    }

    $sharesToIssue = floor($fundingGoal / $sharePrice);

    // 3. Validate Share Availability
    if ($sharesToIssue > $availableShares) {
        throw new Exception("Insufficient shares. You are trying to issue $sharesToIssue shares, but only have $availableShares available.");
    }

    // Capture Duration and Calculate Expiry
    $durationDays = isset($_POST['fundDuration']) ? (int)$_POST['fundDuration'] : 60;
    $expiryDate = date('Y-m-d H:i:s', strtotime("+$durationDays days"));

    // Capture Justification ID
    $valuationRequestId = isset($_POST['valuation_request_id']) ? (int)$_POST['valuation_request_id'] : null;
    
    // Capture Payment Info
    $paymentId = $_POST['payment_id'] ?? null;
    $platformFee = $fundingGoal * 0.02;

    if (!$paymentId) {
        throw new Exception("Platform fee payment is required to submit a pitch.");
    }

    if (!$valuationRequestId) {
        throw new Exception("Valuation Justification required. Please complete the Detailed Valuation Builder first.");
    }

    // Capture Problem/Solution for length check on backend too
    if (strlen($problem) < 50 || strlen($solution) < 50) {
        throw new Exception("Problem and Solution details are too short. Please provide more detail (min 50 chars).");
    }

    // C. Insert into Pitches Table (Updated Schema with Rounds Logic)
    $sql = "INSERT INTO pitches (
                entrepreneur_id, startup_name, tagline, industry, problem, solution, stage, 
                description, location, funding_goal, valuation, platform_fee, platform_fee_paid, razorpay_payment_id, min_investment, 
                round_name, share_price, shares_issued, 
                duration_days, expiry_date, round_status, round_number,
                is_approved, valuation_request_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, 'active', ?, 0, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    // Params: i=int, s=string, d=decimal
    $minInvestment = 10000; // Default
    
    // Bind: i(id), s(name), s(tag), s(ind), s(prob), s(sol), s(stg), s(desc), s(loc), d(goal), d(val), d(fee), s(pay_id), d(min), s(rnd), d(price), i(issued), i(duration), s(expiry), i(roundNumber), i(val_Req_id)
    $stmt->bind_param("issssssssdddssdiiisii", 
        $user_id, $startupName, $tagline, $category, $problem, $solution, $stage, 
        $description, $location, $fundingGoal, $valuation, $platformFee, $paymentId, $minInvestment, 
        $roundName, $sharePrice, $sharesToIssue,
        $durationDays, $expiryDate, $roundNumber, $valuationRequestId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $pitch_id = $conn->insert_id;
    $stmt->close();

    // D. Update Entrepreneur's Available Shares
    $newAvailable = $availableShares - $sharesToIssue;
    $updateShareSql = "UPDATE entrepreneurs SET available_shares = ? WHERE id = ?";
    $stmtUpdate = $conn->prepare($updateShareSql);
    $stmtUpdate->bind_param("ii", $newAvailable, $user_id);
    if (!$stmtUpdate->execute()) {
        throw new Exception("Failed to update available shares.");
    }
    $stmtUpdate->close();

    // E. Upload Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $logoPath = uploadFile($_FILES['logo'], 'logos', ['jpg', 'jpeg', 'png', 'webp']);
        if ($logoPath) {
            $updateSql = "UPDATE pitches SET pitch_logo = ? WHERE id = ?";
            $upStmt = $conn->prepare($updateSql);
            $upStmt->bind_param("si", $logoPath, $pitch_id);
            $upStmt->execute();
            $upStmt->close();
        }
    }

    // F. Upload Documents
    $docMap = [
        'pitchDeck' => ['Pitch Deck', ['pdf']],
        'businessPlan' => ['Business Plan', ['pdf', 'doc', 'docx']],
        'financials' => ['Financials', ['pdf', 'xls', 'xlsx']]
    ];

    $docSql = "INSERT INTO pitch_documents (pitch_id, name, type, file_url, is_locked) VALUES (?, ?, ?, ?, ?)";
    $docStmt = $conn->prepare($docSql);

    foreach ($docMap as $field => $info) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
            $uploadedPath = uploadFile($_FILES[$field], 'documents', $info[1]);
            if ($uploadedPath) {
                $ext = strtoupper(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                $size = round($_FILES[$field]['size'] / 1024 / 1024, 2) . ' MB';
                $displayType = "$ext • $size";
                $isLocked = 1;

                // Bind: i = int, s = string
                $docStmt->bind_param("isssi", $pitch_id, $info[0], $displayType, $uploadedPath, $isLocked);
                $docStmt->execute();
            }
        }
    }
    $docStmt->close();

    // Commit Transaction
    $conn->commit();
    
    // REDIRECT TO WARZONE INSTEAD OF SUCCESS PAGE
    sendJson(true, "Pitch Submitted! Entering AI Warzone for Combat Audit...", "warzone.php?pitch_id=" . $pitch_id);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    sendJson(false, "Server Error: " . $e->getMessage());
}
?>