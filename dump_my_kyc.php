<?php
session_start();
require 'db.php';
$user_id = $_SESSION['user_id'] ?? 0;
echo "User ID: $user_id\n";
$res = $conn->query("SELECT * FROM entrepreneur_kyc_details WHERE entrepreneur_id = $user_id");
if ($row = $res->fetch_assoc()) {
    print_r($row);
} else {
    echo "No KYC record found for this user.\n";
}
?>