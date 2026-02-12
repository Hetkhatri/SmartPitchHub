<?php
include 'db.php';
$res = $conn->query("DESCRIBE pitches");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>