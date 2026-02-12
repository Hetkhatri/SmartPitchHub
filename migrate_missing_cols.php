<?php
include 'db.php';
$conn->query("ALTER TABLE pitches ADD COLUMN problem TEXT AFTER industry");
$conn->query("ALTER TABLE pitches ADD COLUMN solution TEXT AFTER problem");
echo "Columns added successfully";
?>