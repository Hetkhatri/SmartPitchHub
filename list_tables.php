<?php
include 'db.php';

$query = "SHOW TABLES";
$result = mysqli_query($conn, $query);

if ($result) {
    echo "Tables in database 'smartpitchhub-1':\n";
    while ($row = mysqli_fetch_row($result)) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
