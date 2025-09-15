<?php
include '../db.php';

echo "<h2>Existing Database Tables</h2>";

// Get all tables in the database
$result = $conn->query("SHOW TABLES");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Table Name</th><th>Structure</th></tr>";
    
    while ($row = $result->fetch_array()) {
        $tableName = $row[0];
        echo "<tr>";
        echo "<td><strong>" . $tableName . "</strong></td>";
        echo "<td>";
        
        // Show table structure
        $structure = $conn->query("DESCRIBE $tableName");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($field = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $field['Field'] . "</td>";
            echo "<td>" . $field['Type'] . "</td>";
            echo "<td>" . $field['Null'] . "</td>";
            echo "<td>" . $field['Key'] . "</td>";
            echo "<td>" . $field['Default'] . "</td>";
            echo "<td>" . $field['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No tables found in the database.";
}

// Close connection
$conn->close();
?>
