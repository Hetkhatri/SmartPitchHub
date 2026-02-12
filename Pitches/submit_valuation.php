<?php
// submit_valuation.php
header('Content-Type: application/json');

// 1. ENABLE ERROR REPORTING TEMPORARILY
ini_set('display_errors', 1);
error_reporting(E_ALL);

function sendResponse($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// 2. CHECK DATABASE CONNECTION INDEPENDENTLY
$host = "localhost";
$user = "root";
$pass = "";
$db   = "smartpitchhub-1";

$conn = @mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    sendResponse('error', 'DB Connection Error: ' . mysqli_connect_error());
}

// 3. CHECK SESSION
session_start();
if (!isset($_SESSION['user_id'])) {
    sendResponse('error', 'Login Required (Session User ID Missing)');
}
$user_id = $_SESSION['user_id'];

// Check Role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'entrepreneur') {
    sendResponse('error', 'Access Denied. Only entrepreneurs can perform valuations.');
}

// 2. File Upload Helper Function
function cleanNumber($val) {
    if (is_array($val)) return 0;
    return str_replace(',', '', $val);
}

function uploadFile($file, $user_id, $type) {
    if (!isset($file['name']) || empty($file['name'])) return null;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_codes = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
        ];
        return 'Upload Error: ' . ($error_codes[$file['error']] ?? 'Unknown upload error');
    }

    // Create directory if not exists
    $target_dir = "../uploads/valuations/user_" . $user_id . "/";
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            return 'Permission Error: Could not create upload directory.';
        }
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'xlsx', 'xls', 'doc', 'docx', 'jpg', 'jpeg', 'png']; // Added images for testing
    
    if (!in_array($ext, $allowed)) {
        return 'Format Error: .' . $ext . ' files are not allowed.';
    }
    
    $new_name = $type . "_" . time() . "." . $ext;
    $target_file = $target_dir . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $target_file;
    }
    return 'System Error: move_uploaded_file failed. Check folder permissions.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if POST data is actually received (handle post_max_size overflow)
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        sendResponse('error', 'The uploaded file is too large for the server. Try a smaller file.');
    }
    
    try {
        // 3. Handle Files
        $deckPath = uploadFile($_FILES['pitchDeck'] ?? [], $user_id, 'deck');
        $finPath = uploadFile($_FILES['financialDocs'] ?? [], $user_id, 'financials');
        
        // If uploadFile returns a string starting with 'Error', 'Permission', or 'System', it's an error
        if (is_string($deckPath) && (strpos($deckPath, 'Error') !== false || strpos($deckPath, 'Format') !== false)) {
            throw new Exception("Pitch Deck: " . $deckPath);
        }
        if (is_string($finPath) && (strpos($finPath, 'Error') !== false || strpos($finPath, 'Format') !== false)) {
            throw new Exception("Financials: " . $finPath);
        }

        // 4. Capture Form Data
        $entityName = $_POST['companyName'] ?? '';
        $industry = $_POST['industry'] ?? '';
        $stage = $_POST['stage'] ?? '';
        $incDate = $_POST['incorporationDate'] ?? '';
        
        $ttm = cleanNumber($_POST['ttmRevenue'] ?? 0);
        $proj = cleanNumber($_POST['projectedRevenue'] ?? 0);
        $burn = cleanNumber($_POST['cashBurn'] ?? 0);
        $users = cleanNumber($_POST['activeUsers'] ?? 0);
        $growth = cleanNumber($_POST['growthRate'] ?? 0);
        
        $market = $_POST['marketSize'] ?? '';
        $team = $_POST['teamStrength'] ?? '';
        
        $valAsk = cleanNumber($_POST['valuationAsk'] ?? 0);
        $fundAsk = cleanNumber($_POST['fundraiseTarget'] ?? 0);
        $prevRaised = cleanNumber($_POST['previousRaised'] ?? 0);
        
        $justification = $_POST['justification'] ?? '';
        
        // Handle checkboxes for Valuation Basis (Must be an array)
        $basis_raw = $_POST['valuationBasis'] ?? [];
        $basis = is_array($basis_raw) ? implode(',', $basis_raw) : $basis_raw;

        // 5. Insert Query
        $sql = "INSERT INTO valuation_requests 
        (entrepreneur_id, entity_name, industry, stage, incorporation_date, 
        ttm_revenue, projected_revenue, monthly_burn, active_users, growth_rate,
        market_size, team_strength, valuation_ask, fundraise_amount, previous_capital,
        valuation_basis, justification, deck_path, financials_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database preparation failed: " . $conn->error);

        $stmt->bind_param("issssdddidssdddssss", 
            $user_id, $entityName, $industry, $stage, $incDate,
            $ttm, $proj, $burn, $users, $growth,
            $market, $team, $valAsk, $fundAsk, $prevRaised,
            $basis, $justification, $deckPath, $finPath
        );

        if ($stmt->execute()) {
            sendResponse('success', 'Valuation request submitted.', ['id' => $stmt->insert_id]);
        } else {
            throw new Exception("Database Execution Error: " . $stmt->error);
        }
        
        $stmt->close();

    } catch (Exception $e) {
        sendResponse('error', $e->getMessage());
    }
} else {
    sendResponse('error', 'Invalid Request Method');
}
?>