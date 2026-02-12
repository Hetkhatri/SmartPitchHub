<?php
require_once 'db.php';
$sql = "ALTER TABLE entrepreneur_kyc_details ADD COLUMN rejection_reason TEXT AFTER status";
if ($conn->query($sql)) {
    echo "Column rejection_reason added successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>