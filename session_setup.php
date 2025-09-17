<?php
// session_setup.php
session_start();

// Database connection
function getDBConnection() {
    $host = 'localhost';
    $dbname = 'smartpitchhub-1';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get user data from session
function getUserData() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['name'] ?? 'User',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? 'guest',
            'entrepreneur_id' => $_SESSION['entrepreneur_id'] ?? null,
            'investor_id' => $_SESSION['investor_id'] ?? null
        ];
    }
    return null;
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Check if user can view pitch (owner, admin, or investor with interest)
function canViewPitch($pitch_owner_id, $userData) {
    if ($userData['role'] === 'admin') {
        return true;
    }
    
    if ($userData['role'] === 'entrepreneur' && $userData['id'] == $pitch_owner_id) {
        return true;
    }
    
    if ($userData['role'] === 'investor') {
        // Check if investor has shown interest in this pitch
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM investments 
            WHERE investor_id = :investor_id AND pitch_id = :pitch_id
        ");
        $stmt->bindParam(':investor_id', $userData['id'], PDO::PARAM_INT);
        $stmt->bindParam(':pitch_id', $_GET['id'], PDO::PARAM_INT);
        $stmt->execute();
        $hasInterest = $stmt->fetchColumn();
        
        return $hasInterest > 0;
    }
    
    return false;
}
?>