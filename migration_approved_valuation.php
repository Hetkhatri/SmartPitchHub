<?php
require 'db.php';
$sql = "ALTER TABLE valuation_requests ADD COLUMN approved_valuation DECIMAL(15,2) DEFAULT NULL AFTER valuation_ask";
if ($conn->query($sql)) {
    echo "Column approved_valuation added successfully.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>