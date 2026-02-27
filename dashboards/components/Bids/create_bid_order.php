<?php
session_start();
header('Content-Type: application/json');

// 1. Check login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'investor') {
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized access"
    ]);
    exit;
}

// 2. Include Razorpay & DB
require_once 'razorpay_config.php';
require_once '../../../vendor/autoload.php'; // Adjust path if needed
use Razorpay\Api\Api;
require_once '../../../db.php'; // Adjust path if needed

// 3. Validate request
if (!isset($_POST['pack_id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request"
    ]);
    exit;
}

$investor_id = $_SESSION['user_id'];
$pack_id     = (int) $_POST['pack_id'];

// 4. Fetch Pack Details (Corrected Table Name)
// Table matches SQL: `bid_packs`
$stmt = $conn->prepare("SELECT * FROM bid_packs WHERE id = ?");
$stmt->bind_param("i", $pack_id);
$stmt->execute();
$pack = $stmt->get_result()->fetch_assoc();

if (!$pack) {
    echo json_encode([
        "status" => false,
        "message" => "Bid package not found"
    ]);
    exit;
}

// Amount in paise (INR)
$amount = $pack['price'] * 100;

try {
    // 5. Create Razorpay Order
    if (!class_exists('Razorpay\Api\Api')) {
        throw new Exception("Razorpay SDK not found. Please ensure you have run 'composer install' in the project root.");
    }
    
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $orderData = [
        'receipt'         => 'bid_' . time(),
        'amount'          => intval($amount),
        'currency'        => RAZORPAY_CURRENCY,
        'payment_capture' => 1
    ];
    
    $order = $api->order->create($orderData);

    // ✅ FIX FOR LINE 74: Assign to variable first
    // bind_param requires a variable reference, it cannot reference an object property directly
    $razorpay_order_id = $order['id'];
    $pack_price = $pack['price'];

    // 6. Store in DB (Corrected Column Names)
    // Table matches SQL: `bid_purchase_orders` with column `pack_id`
    $stmt = $conn->prepare("
        INSERT INTO bid_purchase_orders 
        (investor_id, pack_id, razorpay_order_id, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'created', NOW())
    ");

    $stmt->bind_param(
        "iisd",
        $investor_id,
        $pack_id,
        $razorpay_order_id, // Using the variable we created above
        $pack_price
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Database Error: " . $stmt->error);
    }

    // 7. Return Response (Corrected Array Keys)
    // Matches SQL columns: `pack_name`, `bid_count`
    echo json_encode([
        "status"       => true,
        "order_id"     => $razorpay_order_id,
        "amount"       => $amount,
        "currency"     => RAZORPAY_CURRENCY,
        "razorpay_key" => RAZORPAY_KEY_ID,
        "pack_name"    => $pack['pack_name'], // ✅ Fixed: 'name' -> 'pack_name'
        "package"      => [
            "name"  => $pack['pack_name'],    // ✅ Fixed
            "bids"  => $pack['bid_count'],    // ✅ Fixed: 'bids' -> 'bid_count'
            "bonus" => $pack['bonus_bids']
        ]
    ]);
    exit;

} catch (Exception $e) {
    // Log error for debugging (optional)
    // error_log($e->getMessage());
    
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
    exit;
}
?>