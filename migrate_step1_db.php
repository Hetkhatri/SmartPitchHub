<?php
// MIGRATION SCRIPT for Step 1 - Database Foundation
require 'db.php';

echo "Updating investments table for Share-Based Logic...\n";

$sql = "ALTER TABLE investments 
        ADD COLUMN shares_bought INT DEFAULT 0 AFTER amount,
        ADD COLUMN purchase_price DECIMAL(15,2) DEFAULT 0.00 AFTER shares_bought,
        ADD COLUMN investment_type ENUM('primary', 'secondary') DEFAULT 'primary' AFTER purchase_price,
        ADD COLUMN payout_status ENUM('escrow', 'released', 'refunded') DEFAULT 'escrow' AFTER investment_type";

if ($conn->query($sql)) {
    echo "SUCCESS: investments table updated with share tracking columns.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>