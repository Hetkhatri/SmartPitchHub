<?php
header('Content-Type: application/json');
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login to use AI features.']);
    exit;
}

require_once '../db.php';
$user_id = $_SESSION['user_id'];

// Check KYC status for verified entrepreneurs only
$kyc_res = $conn->query("SELECT kyc_status FROM entrepreneurs WHERE id = $user_id");
$kyc = $kyc_res->fetch_assoc();
if (($kyc['kyc_status'] ?? '') !== 'verified') {
    echo json_encode(['success' => false, 'error' => 'Identity Verification Required: Please complete your KYC in the dashboard to use the AI Pitch Assistant.']);
    exit;
}

// 1. Configuration (Using the centralized config)
$config = require '../config.php';
$API_KEY = $config['groq_api_key']; 
$MODEL = $config['groq_model'] ?? 'llama-3.3-70b-versatile';

// 2. Get Input
$inputData = json_decode(file_get_contents('php://input'), true);

$field = $inputData['field'] ?? '';
$name = $inputData['name'] ?? 'Your Startup';
$category = $inputData['category'] ?? 'Technology';
$current_text = trim($inputData['current_text'] ?? '');

if (empty($field)) {
    echo json_encode(['success' => false, 'error' => 'Field type is missing.']);
    exit;
}

// 2.5 Logic: Only enhance if there's text, otherwise draft something fresh based on Name/Industry
$is_enhancing = !empty($current_text);

// 3. Construct the Professional Prompt
$field_names = [
    'shortPitch' => 'Elevator Pitch (150 chars max)',
    'problem' => 'Problem Statement',
    'solution' => 'Solution Description',
    'valueProposition' => 'Unique Value Proposition',
    'targetMarket' => 'Target Market Analysis',
    'revenueModel' => 'Business/Revenue Model',
    'traction' => 'Current Traction & Metrics'
];

$field_label = $field_names[$field] ?? $field;

$action_verb = $is_enhancing ? "ENHANCE and POLISH" : "DRAFT a high-quality professional";

$prompt = "Act as a professional Startup Pitch Architect. 
Your task is to $action_verb the following pitch section for a startup.

Startup Name: $name
Industry: $category
Section: $field_label

" . ($is_enhancing ? "USER'S DRAFT TO ENHANCE: \"$current_text\"" : "The user hasn't provided details yet. Generate a compelling placeholder based on the Industry.") . "

INSTRUCTIONS:
1. Preserve all factual data (numbers, metrics, specific names).
2. Use professional, VC-ready language.
3. Keep it concise but impactful.
4. Output ONLY the resulting paragraph. NO introductory text, NO quotes, NO 'Here is the version'.

Enhanced Text:";

// 4. Call Groq API
$url = "https://api.groq.com/openai/v1/chat/completions";
$headers = [
    'Authorization: Bearer ' . $API_KEY,
    'Content-Type: application/json'
];

$payload = [
    "model" => $MODEL, 
    "messages" => [
        ["role" => "system", "content" => "You are a professional pitch writer. Output raw text only."],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.5,
    "max_tokens" => 500
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $res = json_decode($response, true);
    $enhanced_text = trim($res['choices'][0]['message']['content'] ?? '');
    
    // Clean up any accidental quotes or "Enhanced Draft:" prefixes
    $enhanced_text = preg_replace('/^Enhanced Draft:\s*/i', '', $enhanced_text);
    $enhanced_text = trim($enhanced_text, '"\' ');

    // Safety Truncation for Short Pitch
    if ($field === 'shortPitch' && strlen($enhanced_text) > 150) {
        $enhanced_text = substr($enhanced_text, 0, 147) . '...';
    }

    echo json_encode([
        'success' => true,
        'draft' => $enhanced_text
    ]);
} else {
    $res = json_decode($response, true);
    $error_msg = $res['error']['message'] ?? 'Check console/debug for more info.';
    
    echo json_encode([
        'success' => false,
        'error' => "AI Provider error ($http_code): $error_msg",
        'debug' => $response
    ]);
}
