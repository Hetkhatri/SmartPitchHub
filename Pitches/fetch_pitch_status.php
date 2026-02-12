<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'entrepreneur') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Pitch Data
    $sql = "SELECT id, startup_name, industry, stage, funding_goal, is_approved, created_at, tagline, description, location, share_price, shares_issued 
            FROM pitches 
            WHERE entrepreneur_id = ? 
            ORDER BY created_at DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pitch = $result->fetch_assoc();

    if (!$pitch) {
        echo json_encode(['status' => 'no_pitch', 'message' => 'No pitch found']);
        exit;
    }

    // 2. Map Status
    $status_map = [
        0 => 'under_review',
        1 => 'approved',
        2 => 'rejected'
    ];
    $ui_status = $status_map[$pitch['is_approved']] ?? 'under_review';

    // 3. Fetch Documents
    $doc_stmt = $conn->prepare("SELECT name, type, file_url FROM pitch_documents WHERE pitch_id = ?");
    $doc_stmt->bind_param("i", $pitch['id']);
    $doc_stmt->execute();
    $doc_res = $doc_stmt->get_result();
    $docs = [];
    while ($doc = $doc_res->fetch_assoc()) {
        $docs[] = [
            'name' => $doc['name'],
            'type' => $doc['type'],
            'path' => '../' . $doc['file_url']
        ];
    }

    // 4. Response
    $response = [
        'status' => 'success',
        'pitch_status' => $ui_status,
        'dates' => [
            'submitted' => date("F j, Y", strtotime($pitch['created_at'])),
            'updated' => date("F j, Y") 
        ],
        'pitch_details' => [
            'startup_name' => $pitch['startup_name'],
            'industry' => $pitch['industry'],
            'stage' => $pitch['stage'],
            'tagline' => $pitch['tagline'],
            'location' => $pitch['location'],
            'funding_goal' => '₹' . number_format($pitch['funding_goal']),
            'valuation' => '₹' . number_format($pitch['share_price'] * $pitch['shares_issued'] * (100/ ($pitch['shares_issued'] > 0 ? ($pitch['funding_goal'] / ($pitch['share_price'] * $pitch['shares_issued'])) : 1))), // This calculation might be complex based on how they store valuation. Let's just use funding goal if valuation isn't stored directly.
            'share_price' => '₹' . number_format($pitch['share_price'], 2),
            'shares_issued' => number_format($pitch['shares_issued'])
        ],
        'documents' => $docs,
        'rejection' => [
            'reason' => 'Admin has not provided specific feedback yet. Please contact support if you have questions.'
        ]
    ];

    // Recalculate valuation more simply if I can find the original valuation.
    // In submit-pitch.php: $sharePrice = $valuation / $totalShares; $sharesToIssue = floor($fundingGoal / $sharePrice);
    // So valuation = sharePrice * totalShares. But we don't store totalShares in pitches.
    // However, the entrepreneur table has total_shares.
    
    $ent_stmt = $conn->prepare("SELECT total_shares FROM entrepreneurs WHERE id = ?");
    $ent_stmt->bind_param("i", $user_id);
    $ent_stmt->execute();
    $ent = $ent_stmt->get_result()->fetch_assoc();
    if ($ent) {
        $response['pitch_details']['valuation'] = '₹' . number_format($pitch['share_price'] * $ent['total_shares']);
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
