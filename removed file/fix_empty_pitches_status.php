<?php
include '../db.php';

// Update pitches with empty or NULL status to 'draft'
$sql = "UPDATE pitches SET status = 'draft' WHERE status IS NULL OR status = ''";
if ($conn->query($sql) === TRUE) {
    echo "Empty pitches status updated to 'draft' successfully.";
} else {
    echo "Error updating pitches status: " . $conn->error;
}

$conn->close();
?>
