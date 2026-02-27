<?php
header('Content-Type: application/json');

// 1. SET UP ERROR LOGGING & SESSION
ini_set('display_errors', 0);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

require_once '../db.php';
$investor_id = $_SESSION['user_id'];

// 3. INPUT VALIDATION
$pitch_id = isset($_POST['pitch_id']) ? intval($_POST['pitch_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$price_per_share = isset($_POST['price_per_share']) ? floatval($_POST['price_per_share']) : 0;

if ($pitch_id <= 0 || $quantity <= 0 || $price_per_share <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing details.']);
    exit;
}

try {
    // 4. VERIFY OWNERSHIP & QUANTITY
    // We check how many shares they actually have vs how many they are trying to list
    // Note: They might already have some shares listed for sale elsewhere, so we should subtract those.
    
    // Total Shares Held (Only released/finalized shares can be traded secondarily)
    $stmt_held = $conn->prepare("SELECT SUM(shares_bought) as total_held FROM investments WHERE investor_id = ? AND pitch_id = ? AND payout_status = 'released'");
    $stmt_held->bind_param("ii", $investor_id, $pitch_id);
    $stmt_held->execute();
    $res_held = $stmt_held->get_result();
    $row_held = $res_held->fetch_assoc();
    $total_held = $row_held ? intval($row_held['total_held']) : 0;
    
    // Total Shares already listed (Active status)
    $stmt_listed = $conn->prepare("SELECT SUM(shares_quantity) as total_listed FROM secondary_market_orders WHERE seller_id = ? AND pitch_id = ? AND status = 'listing'");
    $stmt_listed->bind_param("ii", $investor_id, $pitch_id);
    $stmt_listed->execute();
    $res_listed = $stmt_listed->get_result();
    $row_listed = $res_listed->fetch_assoc();
    $total_listed = $row_listed ? intval($row_listed['total_listed']) : 0;
    
    $available_to_sell = $total_held - $total_listed;
    
    if ($quantity > $available_to_sell) {
        echo json_encode(['success' => false, 'message' => "Insufficient shares. You only have $available_to_sell shares available for new listings."]);
        exit;
    }

    // 5. INSERT LISTING
    $stmt_insert = $conn->prepare("INSERT INTO secondary_market_orders (seller_id, pitch_id, shares_quantity, price_per_share, status) VALUES (?, ?, ?, ?, 'listing')");
    $stmt_insert->bind_param("iiid", $investor_id, $pitch_id, $quantity, $price_per_share);
    
    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Shares listed successfully on the Secondary Market.']);
    } else {
        throw new Exception("Database error: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Technical Error: ' . $e->getMessage()]);
}
?>