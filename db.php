<?php
$servername = "localhost";   // usually 'localhost'
$username   = "root";        // default XAMPP user
$password   = "";            // default XAMPP password is empty
$dbname     = "smartpitchhub"; // your database name (you can change this)

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
