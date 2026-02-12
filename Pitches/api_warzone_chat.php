<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$pitch_id = $data['pitch_id'] ?? null;
$user_msg = $data['message'] ?? '';

if (!$pitch_id) {
    echo json_encode(['success' => false, 'error' => 'Missing Pitch ID']);
    exit;
}

// 1. Fetch or Create Session
$session_sql = "SELECT * FROM warzone_sessions WHERE pitch_id = ? AND entrepreneur_id = ? AND status = 'ongoing' ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($session_sql);
$stmt->bind_param("ii", $pitch_id, $user_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    // Start New Session
    $chat_init = [
        ['role' => 'ai', 'content' => 'System online. Subject identified. Commencing Audit. Prepare for interrogation.']
    ];
    $history_json = json_encode($chat_init);
    $ins_sql = "INSERT INTO warzone_sessions (entrepreneur_id, pitch_id, chat_history, status) VALUES (?, ?, ?, 'ongoing')";
    $ins_stmt = $conn->prepare($ins_sql);
    $ins_stmt->bind_param("iis", $user_id, $pitch_id, $history_json);
    $ins_stmt->execute();
    $session_id = $ins_stmt->insert_id;
    $history = $chat_init;
    $survival_score = 100;
} else {
    $session_id = $session['id'];
    $history = json_decode($session['chat_history'], true);
    $survival_score = $session['survival_score'];
}

// 2. Fetch Pitch Details for AI context
$pitch_sql = "SELECT * FROM pitches WHERE id = ?";
$p_stmt = $conn->prepare($pitch_sql);
$p_stmt->bind_param("i", $pitch_id);
$p_stmt->execute();
$pitch = $p_stmt->get_result()->fetch_assoc();

// 3. AI Helper Function
function callGroqWarzone($messages) {
    $api_key = 'YOUR_GROQ_API_KEY';
    $url = "https://api.groq.com/openai/v1/chat/completions";
    
    $payload = [
        "model" => "llama-3.3-70b-versatile",
        "messages" => $messages,
        "temperature" => 0.5,
        "response_format" => ["type" => "json_object"]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Count user messages to track progress
$user_count = 0;
foreach($history as $msg) {
    if ($msg['role'] === 'user') $user_count++;
}

$next_reply = "";
$redirect = null;
$finished = false;

if ($user_msg !== '') {
    $history[] = ['role' => 'user', 'content' => $user_msg];
}

// Prepare the Prompt for the Auditor
$system_prompt = "You are the 'Audit Commander' in a high-stakes Startup Warzone. 
Your goal is to interrogate the entrepreneur about their pitch:
Startup: {$pitch['startup_name']}
Industry: {$pitch['industry']}
Funding Goal: ₹" . number_format($pitch['funding_goal']) . "

RULES:
1. Be direct, tough, and slightly robotic/cyberpunk.
2. Ask exactly 3 questions, one by one.
3. After the user answers the 3rd question, give a final verdict.
4. On every response, you must return JSON with:
   'reply': Your next question or final verdict.
   'survival_score': current survival probability (0-100) based on their defense.
   'finished': boolean true if the warzone is over.

Current history length: " . count($history);

$api_messages = [
    ['role' => 'system', 'content' => $system_prompt]
];
// Append history for context
foreach($history as $h) {
    $api_messages[] = ['role' => $h['role'] === 'ai' ? 'assistant' : 'user', 'content' => $h['content']];
}

$ai_response = callGroqWarzone($api_messages);
$ai_data = json_decode($ai_response['choices'][0]['message']['content'] ?? '{}', true);

$next_reply = $ai_data['reply'] ?? "Interference detected. Signal lost.";
$survival_score = $ai_data['survival_score'] ?? $survival_score;
$finished = $ai_data['finished'] ?? false;

if ($finished) {
    $redirect = "../pages/createPitchSucccess.php";
}

$history[] = ['role' => 'ai', 'content' => $next_reply];
$history_json = json_encode($history);

// Update Session
$status_update = $finished ? 'completed' : 'ongoing';
$up_sql = "UPDATE warzone_sessions SET chat_history = ?, survival_score = ?, status = ? WHERE id = ?";
$up_stmt = $conn->prepare($up_sql);
$up_stmt->bind_param("sisi", $history_json, $survival_score, $status_update, $session_id);
$up_stmt->execute();

echo json_encode([
    'success' => true,
    'reply' => $next_reply,
    'score' => max(0, min(100, (int)$survival_score)),
    'redirect' => $redirect
]);
?>
