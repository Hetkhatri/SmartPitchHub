<?php
require_once 'db.php';

echo "<h2>🔧 Fixing Missing Database Columns (amount_raised, shares_sold)</h2>";

$queries = [
    // 1. Add amount_raised to pitches
    "ALTER TABLE pitches ADD COLUMN amount_raised DECIMAL(15,2) DEFAULT 0.00 AFTER funding_goal",
    
    // 2. Add shares_sold to pitches
    "ALTER TABLE pitches ADD COLUMN shares_sold INT DEFAULT 0 AFTER shares_issued",
    
    // 3. Sync existing data for amount_raised
    "UPDATE pitches p 
     SET p.amount_raised = (
        SELECT COALESCE(SUM(amount), 0) 
        FROM investments i 
        WHERE i.pitch_id = p.id AND i.status = 'completed'
     )",
     
    // 4. Sync existing data for shares_sold
    "UPDATE pitches p 
     SET p.shares_sold = (
        SELECT COALESCE(SUM(shares_bought), 0) 
        FROM investments i 
        WHERE i.pitch_id = p.id AND i.status = 'completed'
     )"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✅ Success: " . substr($sql, 0, 50) . "...</p>";
    } else {
        echo "<p style='color: red;'>❌ Error: " . $conn->error . " | Query: $sql</p>";
    }
}

echo "<p>Done! The 'db.php' error should be resolved now as the column 'amount_raised' exists.</p>";
?>