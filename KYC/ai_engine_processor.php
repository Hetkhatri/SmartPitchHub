<?php
// KYC/ai_engine_processor.php

function run_ai_analysis($kyc_id, $conn) {
    // 1. Fetch Data
    $sql = "SELECT k.*, i.email, i.contact FROM investor_kyc_details k JOIN investors i ON k.investor_id = i.id WHERE k.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $kyc_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    // ==========================================
    // STEP 1: RUN DOCUMENT AI (OCR)
    // ==========================================
    $image_relative_path = $data['identity_proof_path']; 
    $full_image_path = __DIR__ . '/../' . $image_relative_path; 
    $python_script = __DIR__ . '/../Ai/analyze_doc.py';

    // Call Python in "ocr" mode
    $cmd_ocr = "python " . escapeshellarg($python_script) . " ocr " . escapeshellarg($full_image_path) . " 2>&1";
    $output_ocr = shell_exec($cmd_ocr);
    $ai_doc_result = json_decode($output_ocr, true);

    // Set Defaults
    $doc_score = 1.0; // Minimal default to prevent infinite refresh loop (when it was 0)
    if ($ai_doc_result && isset($ai_doc_result['status']) && $ai_doc_result['status'] == 'success') {
        $doc_score = $ai_doc_result['doc_score'];
    }

    // ==========================================
    // STEP 2: RUN FRAUD AI (Data Pattern)
    // ==========================================
    
    // Prepare data to send to Python
    $user_data = json_encode([
        "email" => $data['email'],
        "phone" => $data['contact'],
        "ip" => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    // Call Python in "fraud" mode
    // Note: We wrap $user_data in double quotes for command line safety
    $cmd_fraud = "python " . escapeshellarg($python_script) . " fraud " . escapeshellarg($user_data) . " 2>&1";
    $output_fraud = shell_exec($cmd_fraud);
    $ai_fraud_result = json_decode($output_fraud, true);

    // Set Defaults
    $fraud_score = 5.0; // Start low risk
    $fraud_risk_level = 'Low';
    
    if ($ai_fraud_result && isset($ai_fraud_result['fraud_score'])) {
        $fraud_score = $ai_fraud_result['fraud_score'];
        $fraud_risk_level = $ai_fraud_result['risk_level'];
    }

    // ==========================================
    // STEP 3: DATABASE DUPLICATE CHECK (PHP Only)
    // ==========================================
    // Check if this email exists in OTHER kyc records
    $dup_sql = "SELECT COUNT(*) as count FROM investors WHERE email = ? AND id != ?";
    $stmt_dup = $conn->prepare($dup_sql);
    $stmt_dup->bind_param("si", $data['email'], $data['investor_id']);
    $stmt_dup->execute();
    $dup_count = $stmt_dup->get_result()->fetch_assoc()['count'];

    if ($dup_count > 0) {
        $fraud_score = 100; // Maximum Risk
        $fraud_risk_level = 'Critical';
    }

    // ==========================================
    // STEP 4: FINAL DECISION
    // ==========================================
    // Behavior Score (Mock for now, will do next)
    $behavior_score = rand(88, 98); 

    // Recommendation Logic
    $recommendation = 'Approve';
    if ($doc_score < 60 || $fraud_risk_level == 'High' || $fraud_risk_level == 'Critical') {
        $recommendation = 'Reject';
    }

    $confidence = ($doc_score + (100 - $fraud_score)) / 2;

    // UPDATE DATABASE
    $update_sql = "UPDATE investor_kyc_details SET 
        ai_doc_score = ?, 
        ai_fraud_risk_level = ?, 
        ai_fraud_score = ?,
        ai_behavior_score = ?, 
        ai_recommendation = ?, 
        ai_confidence = ? 
        WHERE id = ?";

    $stmt2 = $conn->prepare($update_sql);
    $stmt2->bind_param("dsddsdi", $doc_score, $fraud_risk_level, $fraud_score, $behavior_score, $recommendation, $confidence, $kyc_id);
    $stmt2->execute();
    $stmt2->close();
}
?>