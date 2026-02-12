<?php
require 'db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM valuation_requests");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>