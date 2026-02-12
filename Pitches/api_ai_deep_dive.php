<?php
header('Content-Type: application/json');
session_start();

require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Pitch ID']);
    exit;
}

// Fetch pitch data for analysis, including calculated amount raised
$stmt = $conn->prepare("
    SELECT p.*, COALESCE(SUM(i.amount), 0) as amount_raised 
    FROM pitches p 
    LEFT JOIN investments i ON p.id = i.pitch_id AND i.status = 'completed'
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$pitch = $stmt->get_result()->fetch_assoc();

if (!$pitch) {
    echo json_encode(['success' => false, 'error' => 'Pitch not found']);
    exit;
}

// Extract components from the HTML-tagged description for the AI engine
if (!empty($pitch['description'])) {
    if (preg_match('/<strong>Problem:?<\/strong><br>(.*?)<br><br>/is', $pitch['description'], $prob_match)) {
        $pitch['problem'] = strip_tags($prob_match[1]);
    }
    if (preg_match('/<strong>Solution:?<\/strong><br>(.*?)<br><br>/is', $pitch['description'], $sol_match)) {
        $pitch['solution'] = strip_tags($sol_match[1]);
    }
}

// Call Python Deep Dive using proc_open for reliability
$pythonData = json_encode($pitch);

$pythonPath = 'C:\\Users\\wishh\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
if (!file_exists($pythonPath)) {
    $pythonPath = 'python'; 
}

$scriptPath = dirname(__DIR__) . '/AI/deep_dive_analyst.py';

$descriptorspec = array(
   0 => array("pipe", "r"), // stdin
   1 => array("pipe", "w"), // stdout
   2 => array("pipe", "w")  // stderr
);

$process = proc_open("$pythonPath " . escapeshellarg($scriptPath), $descriptorspec, $pipes);

if (is_resource($process)) {
    fwrite($pipes[0], $pythonData);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    proc_close($process);

    $cleanOutput = trim($stdout);
    if (strpos($cleanOutput, '{') !== false) {
        $jsonPart = substr($cleanOutput, strpos($cleanOutput, '{'));
        $decoded = json_decode($jsonPart, true);
        if ($decoded) {
            echo json_encode($decoded);
            exit;
        }
    }

    echo json_encode([
        'success' => false, 
        'error' => 'AI script error: ' . ($stderr ?: 'Invalid output format'),
        'debug' => $stdout
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to start AI process.']);
}
