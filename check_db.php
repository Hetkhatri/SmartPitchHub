<?php
require 'db.php';
$res = $conn->query('SELECT id, startup_name, funding_goal, amount_raised FROM pitches');
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | " . $row['startup_name'] . " | Goal: " . $row['funding_goal'] . " | Raised: " . $row['amount_raised'] . "\n";
}
?>