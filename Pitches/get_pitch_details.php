<?php
// =================================================================
// 1. PREVENT ERRORS FROM BREAKING JSON
// =================================================================
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// =================================================================
// 2. CONNECT TO DATABASE
// =================================================================
// Ensure the path to db.php is correct
if (file_exists('../db.php')) {
    require_once '../db.php';
} elseif (file_exists('../../db.php')) {
    require_once '../../db.php';
} else {
    echo json_encode(['status' => false, 'message' => 'Database file not found']);
    exit;
}

// =================================================================
// 3. VALIDATE INPUT
// =================================================================
if (!isset($_GET['id'])) {
    echo json_encode(['status' => false, 'message' => 'No ID provided']);
    exit;
}

$id = (int)$_GET['id'];

// =================================================================
// 4. FETCH DATA
// =================================================================
$sql = "
    SELECT 
        p.*,
        e.name AS founder_name,
        e.email AS founder_email,
        e.contact AS founder_contact,
        e.startup_name
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    WHERE p.id = ?
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        
        // SAFE FETCH: Use 'industry' if 'category' is missing (based on your dashboard code)
        $category = $row['industry'] ?? $row['category'] ?? 'General';
        
        // SAFE FETCH: Handle potentially missing image
        $logo = $row['pitch_logo'] ?? $row['logo'] ?? '';

        // Format data
        $pitch = [
            'status' => true,
            'id' => $row['id'],
            'name' => $row['startup_name'],
            'founder' => $row['founder_name'],
            'email' => $row['founder_email'],
            'category' => $category,
            'description' => $row['description'],
            'funding' => '₹' . number_format($row['funding_goal']),
            'equity' => ($row['equity_offer'] ?? 0), // Added equity since dashboard uses it
            'stage' => $row['stage'],
            'logo' => $logo,
            'likes' => $row['likes'] ?? 0,
            'views' => $row['views'] ?? 0
        ];
        echo json_encode($pitch);
    } else {
        echo json_encode(['status' => false, 'message' => 'Pitch not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => false, 'message' => 'Query preparation failed']);
}
?>