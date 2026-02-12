<?php
include 'db.php';
echo "--- wallet_transactions Table ---\n";
$res = mysqli_query($conn, "DESCRIBE wallet_transactions");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>