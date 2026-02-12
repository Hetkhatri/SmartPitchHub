<?php
header('Content-Type: application/json');

// 1. SET UP
ini_set('display_errors', 0);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../db.php';
$buyer_id = $_SESSION['user_id'];

// 3. INPUTS
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$qty_wanted = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

if ($order_id <= 0 || $qty_wanted <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid trade details.']);
    exit;
}

try {
    $conn->begin_transaction();

    // 4. FETCH AND LOCK LISTING
    $stmt_order = $conn->prepare("SELECT * FROM secondary_market_orders WHERE id = ? AND status = 'listing' FOR UPDATE");
    $stmt_order->bind_param("i", $order_id);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Listing is no longer available.");
    }

    if ($order['seller_id'] == $buyer_id) {
        throw new Exception("You cannot buy your own shares.");
    }

    if ($qty_wanted > $order['shares_quantity']) {
        throw new Exception("Insufficient shares available in this listing. Max: " . $order['shares_quantity']);
    }

    $seller_id = $order['seller_id'];
    $pitch_id = $order['pitch_id'];
    $price_per_share = floatval($order['price_per_share']);
    
    // 5. CALCULATE TOTALS
    $transaction_value = $qty_wanted * $price_per_share;
    $fee = $transaction_value * 0.005; // 0.5% platform fee
    $total_cost = $transaction_value + $fee;

    // 6. CHECK BUYER BALANCE
    $stmt_wallet = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt_wallet->bind_param("i", $buyer_id);
    $stmt_wallet->execute();
    $buyer_wallet = $stmt_wallet->get_result()->fetch_assoc();

    if (!$buyer_wallet || floatval($buyer_wallet['balance']) < $total_cost) {
        throw new Exception("Insufficient wallet balance. You need ₹" . number_format($total_cost, 2));
    }

    // 7. EXECUTE FUND TRANSFER
    // Deduct from Buyer
    $stmt_deduct = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
    $stmt_deduct->bind_param("di", $total_cost, $buyer_id);
    $stmt_deduct->execute();

    // Add to Seller (Seller gets the transaction value, platform keeps the fee)
    $stmt_add = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt_add->bind_param("di", $transaction_value, $seller_id);
    $stmt_add->execute();

    // 8. LOG WALLET TRANSACTIONS
    $stmt_log_buy = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
    $desc_buy = "Secondary Purchase: $qty_wanted shares of " . $order_id;
    $stmt_log_buy->bind_param("ids", $buyer_id, $total_cost, $desc_buy);
    $stmt_log_buy->execute();

    $stmt_log_sell = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
    $desc_sell = "Secondary Sale: $qty_wanted shares of " . $order_id;
    $stmt_log_sell->bind_param("ids", $seller_id, $transaction_value, $desc_sell);
    $stmt_log_sell->execute();

    // 9. TRANSFER SHARE OWNERSHIP (INVESTMENTS TABLE)
    // Add shares to Buyer
    $stmt_inv_buy = $conn->prepare("INSERT INTO investments (investor_id, pitch_id, amount, shares_bought, status) VALUES (?, ?, ?, ?, 'completed')");
    $stmt_inv_buy->bind_param("iidi", $buyer_id, $pitch_id, $transaction_value, $qty_wanted);
    $stmt_inv_buy->execute();

    // Deduct shares from Seller (Negative entry to balance the portfolio)
    $stmt_inv_sell = $conn->prepare("INSERT INTO investments (investor_id, pitch_id, amount, shares_bought, status) VALUES (?, ?, ?, ?, 'transfer_out')");
    $negative_val = -$transaction_value;
    $negative_qty = -$qty_wanted;
    $stmt_inv_sell->bind_param("iidi", $seller_id, $pitch_id, $negative_val, $negative_qty);
    $stmt_inv_sell->execute();

    // 10. UPDATE LISTING
    if ($qty_wanted == $order['shares_quantity']) {
        // Close listing
        $stmt_update_order = $conn->prepare("UPDATE secondary_market_orders SET status = 'completed', shares_quantity = 0 WHERE id = ?");
        $stmt_update_order->bind_param("i", $order_id);
        $stmt_update_order->execute();
    } else {
        // Partial fill
        $stmt_update_order = $conn->prepare("UPDATE secondary_market_orders SET shares_quantity = shares_quantity - ? WHERE id = ?");
        $stmt_update_order->bind_param("ii", $qty_wanted, $order_id);
        $stmt_update_order->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Trade successful. Portfolio updated.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>