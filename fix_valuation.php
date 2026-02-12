<?php
require 'db.php';

// Fetch all pitches where valuation is 0 or NULL
$sql = "SELECT p.id, p.share_price, e.total_shares 
        FROM pitches p 
        JOIN entrepreneurs e ON p.entrepreneur_id = e.id 
        WHERE (p.valuation = 0 OR p.valuation IS NULL) AND p.share_price > 0";

$res = $conn->query($sql);
$count = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $valuation = $row['share_price'] * $row['total_shares'];
        $up = $conn->prepare("UPDATE pitches SET valuation = ? WHERE id = ?");
        $up->bind_param("di", $valuation, $row['id']);
        $up->execute();
        $count++;
    }
}

echo "Fixed $count pitches valuation.";
?>