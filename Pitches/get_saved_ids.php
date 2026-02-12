<?php
// File: Pitches/get_saved_ids.php

// 1. Disable HTML errors to ensure clean JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// 2. PATH FIX: Go up ONE level to find db.php
// Structure: SmartPitchHub/Pitches/get_saved_ids.php -> SmartPitchHub/db.php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
$saved_ids = [];

try {
    $stmt = $conn->prepare("SELECT pitch_id FROM saved_pitches WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $saved_ids[] = $row['pitch_id'];
    }
    
    echo json_encode($saved_ids);

} catch (Exception $e) {
    echo json_encode([]);
}
?>