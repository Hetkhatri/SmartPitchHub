<?php
$servername = "localhost";   // usually 'localhost'
$username   = "root";        // default XAMPP user
$password   = "";            // default XAMPP password is empty
$dbname     = "smartpitchhub-1"; // updated database name



// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// =================================================================
// BACKGROUND TASKS (MOCKED CRON)
// =================================================================
// 1. Auto-expire rounds that passed their deadline
$conn->query("UPDATE pitches SET round_status = 'expired' WHERE round_status = 'active' AND expiry_date <= NOW()");

// 2. Auto-complete rounds that reached their funding goal
$conn->query("UPDATE pitches SET round_status = 'completed' WHERE round_status = 'active' AND amount_raised >= funding_goal");
?>
