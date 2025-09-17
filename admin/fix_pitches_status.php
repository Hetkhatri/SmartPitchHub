<?php
include 'db.php'; // Corrected path to db.php

// Update pitches with empty or NULL status to 'pending'
$sql = "UPDATE pitches SET status = 'pending' WHERE status IS NULL OR status = ''";
if ($conn->query($sql) === TRUE) {
    echo "Pitches status updated successfully.";
} else {
    echo "Error updating pitches status: " . $conn->error;
}

$conn->close();
?>
