<?php
require_once 'db.php';

// Step 4: Secondary Market (P2P Trading) Migration
// This table tracks sell orders from investors.

$sqls = [
    "CREATE TABLE IF NOT EXISTS secondary_market_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        pitch_id INT NOT NULL,
        shares_quantity INT NOT NULL,
        price_per_share DECIMAL(15,2) NOT NULL,
        original_investment_id INT NOT NULL,
        status ENUM('available', 'pending', 'filled', 'cancelled') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES investors(id),
        FOREIGN KEY (pitch_id) REFERENCES pitches(id),
        INDEX (status),
        INDEX (pitch_id)
    ) ENGINE=InnoDB;"
];

echo "<h2>Step 4: Database Migration (Secondary Market)</h2>";

foreach ($sqls as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>[SUCCESS] Table 'secondary_market_orders' created/verified.</p>";
    } else {
        echo "<p style='color:red;'>[ERROR] " . $conn->error . "</p>";
    }
}

// Add a helper column to investments if needed to track "listed" status
// In a high-end system, we'd lock shares. For now, we'll check availability during listing.

echo "<p>Migration Complete. <a href='dashboards/investor-dashboard.php'>Go to Dashboard</a></p>";
?>