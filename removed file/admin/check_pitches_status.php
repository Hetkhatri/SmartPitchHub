<?php
include '../db.php';

$result = $conn->query("SELECT id, startup_name, IFNULL(status, '') as status FROM pitches");

echo "<h2>Pitches Status</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Startup Name</th><th>Status</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['startup_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?>
