<?php
include 'db.php';
echo "--- wallets table ---\n";
$res = $conn->query("DESCRIBE wallets");
if($res){
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Table 'wallets' does not exist or error: " . $conn->error;
}
?>