<?php
// submit_investor_kyc.php
header('Content-Type: application/json');
session_start();
require_once '../db.php'; 

// 1. Auth Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// --- NEW LOGIC: Fetch Name for Folder ---
$query = $conn->prepare("SELECT name FROM investors WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$query->bind_result($investor_name);
$query->fetch();
$query->close();

// Fallback if name not found
if (empty($investor_name)) { $investor_name = "User_" . $user_id; }

// Sanitize Name (Replace spaces with _, remove special chars) to be folder-safe
$folder_name = preg_replace('/[^a-zA-Z0-9]/', '_', $investor_name); 

// New Upload Path: uploads/kyc/investor_Het_Patel/
$upload_dir = "../uploads/kyc/investor_" . $folder_name . "/"; 
// ----------------------------------------

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
// 2. Helper Function for File Uploads
function uploadFile($fileInputName, $targetDir, $prefix) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] != 0) {
        return null;
    }
    $fileVal = $_FILES[$fileInputName];
    $ext = pathinfo($fileVal['name'], PATHINFO_EXTENSION);
    $newName = $prefix . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . $newName;
    
    if (move_uploaded_file($fileVal['tmp_name'], $targetPath)) {
        return $targetPath; // Return the path to save in DB
    }
    return null;
}

// 3. Handle POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- File Uploads ---
        $path_identity = uploadFile('identityProof', $upload_dir, 'id');
        $path_selfie   = uploadFile('selfie', $upload_dir, 'selfie');
        $path_address  = uploadFile('addressProof', $upload_dir, 'addr');
        $path_bank     = uploadFile('bankProof', $upload_dir, 'bank');

        // Check if mandatory files failed (optional strict check)
        if (!$path_identity || !$path_selfie) {
            throw new Exception("Identity proof and Selfie are required.");
        }

        // --- Prepare SQL ---
        $sql = "INSERT INTO investor_kyc_details (
            investor_id, 
            full_name, dob, nationality, country_residence,
            doc_type, doc_number, issuing_country, expiry_date, identity_proof_path, selfie_path,
            address_line, city, state, country, pincode, address_proof_path,
            account_holder, bank_name, account_number, ifsc_code, account_type, bank_proof_path,
            investor_type, annual_income, net_worth, experience, typical_amount, investment_reason,
            pan_number, tax_residency, fatca_status, pep_status,
            status, submission_date
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, 
            'under_review', NOW()
        ) ON DUPLICATE KEY UPDATE 
            full_name=VALUES(full_name), status='under_review', submission_date=NOW()";

        $stmt = $conn->prepare($sql);

        // --- Bind Parameters ---
        // Note: We use $_POST['field_name'] from the JS FormData
        // Handle optional expiry date
        $expiry = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;

        $stmt->bind_param("issssssssssssssssssssssssssssssss", 
            $user_id,
            $_POST['fullName'], $_POST['dateOfBirth'], $_POST['nationality'], $_POST['countryOfResidence'],
            $_POST['documentType'], $_POST['documentNumber'], $_POST['issuingCountry'], $expiry, $path_identity, $path_selfie,
            $_POST['addressLine'], $_POST['city'], $_POST['state'], $_POST['addressCountry'], $_POST['pincode'], $path_address,
            $_POST['accountHolderName'], $_POST['bankName'], $_POST['accountNumber'], $_POST['ifscCode'], $_POST['accountType'], $path_bank,
            $_POST['investorType'], $_POST['annualIncome'], $_POST['netWorth'], $_POST['investmentExperience'], $_POST['typicalInvestmentAmount'], $_POST['investmentReason'],
            $_POST['panNumber'], $_POST['taxResidencyCountry'], $_POST['fatca'], $_POST['pep']
        );

        if ($stmt->execute()) {
            // Update the main investors table status too
            $updateMain = $conn->prepare("UPDATE investors SET kyc_status = 'pending' WHERE id = ?");
            $updateMain->bind_param("i", $user_id);
            $updateMain->execute();

            echo json_encode(['status' => 'success', 'message' => 'KYC Submitted Successfully']);
        } else {
            throw new Exception("Database Error: " . $stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>