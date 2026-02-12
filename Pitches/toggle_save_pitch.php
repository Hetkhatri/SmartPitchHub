<?php
session_start();
require_once '../db.php'; // Adjust this path to point to your db.php file

header('Content-Type: application/json');

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

// 2. Get Input Data
$data = json_decode(file_get_contents("php://input"), true);
$pitch_id = isset($data['pitch_id']) ? intval($data['pitch_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($pitch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Pitch ID']);
    exit;
}

// 3. Check if already saved
$check = $conn->prepare("SELECT id FROM saved_pitches WHERE user_id = ? AND pitch_id = ?");
$check->bind_param("ii", $user_id, $pitch_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Already saved -> DELETE (Unsave)
    $stmt = $conn->prepare("DELETE FROM saved_pitches WHERE user_id = ? AND pitch_id = ?");
    $stmt->bind_param("ii", $user_id, $pitch_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'action' => 'unsaved']);
} else {
    // Not saved -> INSERT (Save)
    $stmt = $conn->prepare("INSERT INTO saved_pitches (user_id, pitch_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $pitch_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'action' => 'saved']);
}
?>