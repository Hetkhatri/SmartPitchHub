<?php
// UNDO SCRIPT for Step 1 - Database Foundation
require 'db.php';

echo "Cleaning up investments table...\n";

$sql = "ALTER TABLE investments 
        DROP COLUMN IF EXISTS shares_bought,
        DROP COLUMN IF EXISTS purchase_price,
        DROP COLUMN IF EXISTS investment_type,
        DROP COLUMN IF EXISTS payout_status";

if ($conn->query($sql)) {
    echo "SUCCESS: New columns removed. Table restored to original state.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>