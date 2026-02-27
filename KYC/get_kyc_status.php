<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);
ini_set('display_errors', 0);

// 1. Connect to Database
if (file_exists('../db.php')) { require_once '../db.php'; } 
elseif (file_exists('../../db.php')) { require_once '../../db.php'; } 
else { echo json_encode(['status' => 'error', 'message' => 'Database file not found']); exit; }

// 2. Auth Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 3. Fetch Data (Added k.id to check existence)
    $sql = "SELECT 
                e.id, e.name as user_name, e.email, e.contact, e.startup_name as basic_startup_name, 
                e.kyc_status as main_status, e.created_at,
                k.id as kyc_detail_id,
                k.full_name, k.dob, k.nationality, k.country,
                k.legal_name, k.brand_name, k.business_type, k.industry, k.business_address, k.cin,
                k.bank_name, k.account_holder_name, k.account_number, k.ifsc_code,
                k.gov_id_path, k.coi_path, k.gst_certificate_path, k.bank_proof_path,
                k.submission_date, k.status as detail_status, k.rejection_reason
            FROM entrepreneurs e
            LEFT JOIN entrepreneur_kyc_details k ON e.id = k.entrepreneur_id
            WHERE e.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) { echo json_encode(['status' => 'error', 'message' => 'User not found']); exit; }

    // IF no KYC detail record exists, we should probably tell the frontend
    $has_submitted = !empty($user['kyc_detail_id']);

    // 4. Map Documents (Dynamic List)
    $docs = [];
    $base_path = "../uploads/kyc/"; // Adjust if your uploads folder is different

    if (!empty($user['gov_id_path'])) {
        $docs[] = ['name' => 'Government ID', 'type' => 'Uploaded', 'path' => $base_path . $user['gov_id_path']];
    }
    if (!empty($user['coi_path'])) {
        $docs[] = ['name' => 'Certificate of Incorporation', 'type' => 'Uploaded', 'path' => $base_path . $user['coi_path']];
    }
    if (!empty($user['gst_certificate_path'])) {
        $docs[] = ['name' => 'GST Certificate', 'type' => 'Uploaded', 'path' => $base_path . $user['gst_certificate_path']];
    }
    if (!empty($user['bank_proof_path'])) {
        $docs[] = ['name' => 'Bank Proof', 'type' => 'Uploaded', 'path' => $base_path . $user['bank_proof_path']];
    }

    // 5. Map Status
    $ui_status = 'under_review'; 
    $db_status = strtolower($user['main_status'] ?? 'pending');
    if ($db_status === 'verified' || $db_status === 'approved') { $ui_status = 'approved'; } 
    elseif ($db_status === 'rejected') { $ui_status = 'rejected'; }

    // 6. Response
    $response = [
        'status' => 'success',
        'has_submitted' => $has_submitted,
        'kyc_status' => $has_submitted ? $ui_status : 'not_submitted',
        'dates' => [
            'submitted' => !empty($user['submission_date']) ? date("F j, Y", strtotime($user['submission_date'])) : 'Not Submitted',
            'updated'   => date("F j, Y") 
        ],
        'personal' => [
            'full_name' => !empty($user['full_name']) ? $user['full_name'] : $user['user_name'],
            'email' => $user['email'],
            'phone' => $user['contact'] ?? 'N/A',
            'dob' => $user['dob'] ?? 'N/A',
            'nationality' => $user['nationality'] ?? ($user['country'] ?? 'N/A')
        ],
        'business' => [
            'name' => !empty($user['brand_name']) ? $user['brand_name'] : ($user['basic_startup_name'] ?? 'N/A'),
            'type' => $user['business_type'] ?? 'N/A',
            'reg_number' => $user['cin'] ?? 'N/A',
            'industry' => $user['industry'] ?? 'N/A',
            'address' => $user['business_address'] ?? 'N/A'
        ],
        'bank' => [
            'name' => $user['bank_name'] ?? 'Not Provided',
            'holder' => $user['account_holder_name'] ?? 'N/A',
            'account' => !empty($user['account_number']) ? '••••' . substr($user['account_number'], -4) : '••••',
            'ifsc' => $user['ifsc_code'] ?? 'N/A'
        ],
        'documents' => $docs,
        'rejection' => [
            'reason' => !empty($user['rejection_reason']) ? $user['rejection_reason'] : 'Verification failed. Please check your document clarity.',
            'date' => $user['submission_date'] ?? date('Y-m-d'),
            'fix_link' => 'Enterpreneur-kyc.php'
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
?>