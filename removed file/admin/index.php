<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}
// Redirect to the main dashboard
header('Location: dashboard.php');
exit();
?>
