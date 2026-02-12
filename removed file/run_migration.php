<?php
// Database migration script to add views and likes columns
include '../db.php';

echo "<h2>Database Migration: Adding Views and Likes Columns</h2>";

// Check if columns already exist
$result = $conn->query("SHOW COLUMNS FROM pitches LIKE 'views'");
$viewsExists = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM pitches LIKE 'likes'");
$likesExists = $result->num_rows > 0;

if ($viewsExists && $likesExists) {
    echo "<p style='color: green;'>✓ Views and Likes columns already exist!</p>";
} else {
    // Add views column if it doesn't exist
    if (!$viewsExists) {
        $sql = "ALTER TABLE pitches ADD views INT DEFAULT 0";
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Views column added successfully!</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding views column: " . $conn->error . "</p>";
        }
    }

    // Add likes column if it doesn't exist
    if (!$likesExists) {
        $sql = "ALTER TABLE pitches ADD likes INT DEFAULT 0";
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Likes column added successfully!</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding likes column: " . $conn->error . "</p>";
        }
    }

    // Update existing records
    $sql = "UPDATE pitches SET views = 0, likes = 0 WHERE views IS NULL OR likes IS NULL";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Existing records updated with default values!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error updating existing records: " . $conn->error . "</p>";
    }
}

echo "<p><a href='../dashboard-entrepreneur.php'>← Back to Dashboard</a></p>";
?>
