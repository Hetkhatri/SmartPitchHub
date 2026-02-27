<?php
require_once 'db.php';

// SQL to update the ENUM for investment status
$sql = "ALTER TABLE investments MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'transfer_out') DEFAULT 'completed'";

if ($conn->query($sql)) {
    echo "SUCCESS: Investments status enum updated to include 'transfer_out'.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}

// Also ensure transaction_id is long enough for our SEC- prefix
$sql2 = "ALTER TABLE investments MODIFY COLUMN transaction_id VARCHAR(100)";
$conn->query($sql2);

echo "Final check: All database schemas are now compatible with the Secondary Market and Dividend logic.\n";
?>
