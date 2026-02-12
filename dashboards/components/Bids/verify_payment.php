<?php
session_start();
header('Content-Type: application/json');

// 1. Check Login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'investor') {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

require_once 'razorpay_config.php';
require_once '../../../db.php';
require_once '../../../vendor/autoload.php'; // Adjust path if needed

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// 2. Get POST Data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['razorpay_order_id']) || !isset($data['razorpay_payment_id']) || !isset($data['razorpay_signature'])) {
    echo json_encode(["status" => false, "message" => "Missing payment details"]);
    exit;
}

$order_id = $data['razorpay_order_id'];
$payment_id = $data['razorpay_payment_id'];
$signature = $data['razorpay_signature'];

try {
    // 3. Verify Signature
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    
    $attributes = [
        'razorpay_order_id' => $order_id,
        'razorpay_payment_id' => $payment_id,
        'razorpay_signature' => $signature
    ];

    $api->utility->verifyPaymentSignature($attributes);

    // --- SIGNATURE VERIFIED: START DB UPDATES ---
    $conn->begin_transaction();

    // 4. Update Order Status
    $stmt = $conn->prepare("UPDATE bid_purchase_orders SET status = 'paid', razorpay_payment_id = ?, razorpay_signature = ?, updated_at = NOW() WHERE razorpay_order_id = ?");
    $stmt->bind_param("sss", $payment_id, $signature, $order_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Order not found or already processed");
    }

    // 5. Get Pack Details (to know how many bids to add)
    // We join tables to get the pack info associated with this specific order
    $query = "
        SELECT p.bid_count, p.bonus_bids 
        FROM bid_purchase_orders o
        JOIN bid_packs p ON o.pack_id = p.id
        WHERE o.razorpay_order_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $packData = $result->fetch_assoc();

    if (!$packData) {
        throw new Exception("Pack details not found");
    }

    $total_bids_to_add = $packData['bid_count'] + $packData['bonus_bids'];
    $investor_id = $_SESSION['user_id'];

    // 6. Add Bids to Investor Account
    // We use ON DUPLICATE KEY UPDATE to handle both new and existing users
    $stmt = $conn->prepare("
        INSERT INTO investor_bids (investor_id, total_bids, used_bids) 
        VALUES (?, ?, 0) 
        ON DUPLICATE KEY UPDATE total_bids = total_bids + ?
    ");
    $stmt->bind_param("iii", $investor_id, $total_bids_to_add, $total_bids_to_add);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "status" => true, 
        "message" => "Payment verified and bids added!",
        "new_bids" => $total_bids_to_add
    ]);

} catch (SignatureVerificationError $e) {
    // Verification failed
    $conn->rollback();
    
    // Mark as failed in DB
    $stmt = $conn->prepare("UPDATE bid_purchase_orders SET status = 'failed' WHERE razorpay_order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();

    echo json_encode(["status" => false, "message" => "Payment verification failed"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>