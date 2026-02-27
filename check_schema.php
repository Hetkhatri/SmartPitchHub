<?php
require 'db.php';
echo "--- TABLE: valuation_requests ---\n";
$result = $conn->query("DESCRIBE valuation_requests");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
echo "\n--- TABLE: pitches ---\n";
$result = $conn->query("DESCRIBE pitches");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>