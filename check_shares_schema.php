<?php
include 'db.php';
echo "--- entrepreneurs table ---\n";
$res = $conn->query("DESCRIBE entrepreneurs");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "--- pitches table ---\n";
$res = $conn->query("DESCRIBE pitches");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>