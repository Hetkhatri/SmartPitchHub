<?php
include 'db.php';
echo "--- investments table ---\n";
$res = $conn->query("DESCRIBE investments");
if($res){
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Table 'investments' does not exist or error: " . $conn->error;
}
?>