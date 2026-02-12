<?php
require 'db.php';

// 1. Add columns to 'entrepreneurs' table
$sql1 = "ALTER TABLE entrepreneurs 
ADD COLUMN total_shares INT(11) DEFAULT 50000,
ADD COLUMN available_shares INT(11) DEFAULT 50000;";

// 2. Add columns to 'pitches' table and drop equity_offer
$sql2 = "ALTER TABLE pitches 
ADD COLUMN round_name VARCHAR(50) DEFAULT 'Seed',
ADD COLUMN share_price DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN shares_issued INT(11) DEFAULT 0,
DROP COLUMN equity_offer;";

// Execute
try {
    if ($conn->query($sql1) === TRUE) {
        echo "Entrepreneurs table updated successfully.<br>";
    } else {
        echo "Error updating entrepreneurs table: " . $conn->error . "<br>";
    }

    if ($conn->query($sql2) === TRUE) {
        echo "Pitches table updated successfully.<br>";
    } else {
        echo "Error updating pitches table: " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>