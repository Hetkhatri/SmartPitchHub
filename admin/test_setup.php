<?php
// Test script to verify admin panel setup
echo "<h1>Admin Panel Setup Test</h1>";

// Test database connection
include '../db.php';
if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit();
}

// Check if tables exist
$tables = ['users', 'pitches', 'investments'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Table '$table' does not exist (run setup_database.php)</p>";
    }
}

// Check if admin user exists
$admin_check = $conn->query("SELECT * FROM users WHERE email = 'admin@smartpitchhub.com'");
if ($admin_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Admin user exists</p>";
    $admin = $admin_check->fetch_assoc();
    echo "<p>Admin Email: " . $admin['email'] . "</p>";
    echo "<p>Admin Role: " . $admin['role'] . "</p>";
} else {
    echo "<p style='color: orange;'>⚠ Admin user not found (run setup_database.php)</p>";
}

// Check file structure
$files_to_check = [
    'login.php',
    'dashboard.php',
    'users.php',
    'pitches.php',
    'logout.php',
    'includes/header.php',
    'includes/footer.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ File '$file' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ File '$file' missing</p>";
    }
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Run <a href='setup_database.php'>setup_database.php</a> to create tables and admin user</li>";
echo "<li>Access <a href='login.php'>login.php</a> to test admin login</li>";
echo "<li>Use credentials: admin@smartpitchhub.com / admin123</li>";
echo "<li>Explore the admin dashboard and features</li>";
echo "</ol>";

echo "<h2>Security Note:</h2>";
echo "<p style='color: orange;'>⚠ Remember to change the default admin password after first login!</p>";
?>
