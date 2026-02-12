<?php
// 1. BACKEND CONFIGURATION
ini_set('display_errors', 0); 
error_reporting(E_ALL); 

session_start();
require_once '../db.php'; 

// --- HELPERS ---
function sendResponse($success, $message = '') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => (bool)$success, 'message' => (string)$message]);
    exit;
}

function getPost($key, $default = '') {
    return $_POST[$key] ?? $default;
}

function handleUpload($key, $dir, $uid, $folder, &$cleanup, $existing_path = null) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing_path;
    }
    
    if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        // If it's a real error (not just "no file"), we should probably know
        if ($existing_path) return $existing_path;
        throw new Exception("Upload error for $key (Code: " . $_FILES[$key]['error'] . ")");
    }

    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    $filename = $uid . '_' . $key . '_' . time() . '.' . $ext;
    $full_path = $dir . $filename;
    
    if (move_uploaded_file($_FILES[$key]['tmp_name'], $full_path)) {
        $cleanup[] = $full_path;
        return $folder . '/' . $filename;
    }
    
    throw new Exception("Failed to save uploaded file: $key to $full_path");
}

// Start output buffering
ob_start();

// 2. Security Check
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') sendResponse(false, "User not logged in.");
    else { header("Location: ../login.php"); exit; }
}

$user_id = $_SESSION['user_id'];
$upload_base_dir = '../uploads/kyc/';

// --- GET REQUEST: Fetch Pre-filled Data & Status ---
$user_email = '';
$user_phone = '';
$kyc_status = '';
$kyc_rejection_reason = '';
$existing_kyc = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 1. Fetch Entrepreneur Account Data
    if ($stmt = $conn->prepare("SELECT email, contact, kyc_status FROM entrepreneurs WHERE id = ?")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($user_email, $user_phone, $kyc_status);
        $stmt->fetch();
        $stmt->close();
    }

    // 2. Fetch Detailed KYC data if exists
    $stmt = $conn->prepare("SELECT * FROM entrepreneur_kyc_details WHERE entrepreneur_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $existing_kyc = $stmt->get_result()->fetch_assoc();
    if ($existing_kyc) {
        $kyc_rejection_reason = $existing_kyc['rejection_reason'] ?? '';
    }
}

// --- POST REQUEST: Process Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Handle large file uploads exceeding post_max_size
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        sendResponse(false, "Total upload size too large. Please upload smaller documents.");
    }

    $files_to_cleanup = [];
    $conn->begin_transaction();

    try {
        // Fetch existing record to check if we update or insert
        $stmt_check = $conn->prepare("SELECT id, gov_id_path, selfie_path, coi_path, gst_certificate_path, bank_proof_path FROM entrepreneur_kyc_details WHERE entrepreneur_id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $existing = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        // A. Create Folders
        if (!file_exists($upload_base_dir) && !mkdir($upload_base_dir, 0777, true)) {
            // throw new Exception("Server Error: Main upload directory could not be created.");
        }

        $full_name = getPost('fullName', 'User_' . $user_id);
        $safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', $full_name);
        $target_dir = $upload_base_dir . $safe_name . '/';
        
        if (!file_exists($target_dir) && !mkdir($target_dir, 0777, true)) {
            throw new Exception("Server Error: Folder creation failed for $safe_name.");
        }

        // B. Process Files
        $gov_id = handleUpload('govId', $target_dir, $user_id, $safe_name, $files_to_cleanup, !empty($existing['gov_id_path']) ? $existing['gov_id_path'] : null);
        $selfie = handleUpload('selfie', $target_dir, $user_id, $safe_name, $files_to_cleanup, !empty($existing['selfie_path']) ? $existing['selfie_path'] : null);
        $coi = handleUpload('coi', $target_dir, $user_id, $safe_name, $files_to_cleanup, !empty($existing['coi_path']) ? $existing['coi_path'] : null);
        $gst = handleUpload('gst', $target_dir, $user_id, $safe_name, $files_to_cleanup, !empty($existing['gst_certificate_path']) ? $existing['gst_certificate_path'] : null);
        $bank = handleUpload('bankProof', $target_dir, $user_id, $safe_name, $files_to_cleanup, !empty($existing['bank_proof_path']) ? $existing['bank_proof_path'] : null);

        // More robust checking 
        $missing = [];
        if (!$gov_id) $missing[] = "Government ID (None in DB, none in upload)";
        if (!$selfie) $missing[] = "Selfie (None in DB, none in upload)";
        if (!$bank) $missing[] = "Bank Proof (None in DB, none in upload)";

        if (!empty($missing)) {
            throw new Exception("Missing required documents: " . implode(", ", $missing));
        }

        $coi = $coi ?? '';
        $gst = $gst ?? '';

        // D. Prepare Data
        $inc_date = !empty($_POST['incorporationDate']) ? $_POST['incorporationDate'] : NULL;
        $own = floatval(getPost('ownershipPercentage', 0));
        $c1 = (getPost('accuracyDeclaration') === 'true') ? 1 : 0;
        $c2 = (getPost('eligibilityDeclaration') === 'true') ? 1 : 0;
        $c3 = (getPost('commissionAgreement') === 'true') ? 1 : 0;
        $c4 = (getPost('refundAgreement') === 'true') ? 1 : 0;
        $c5 = (getPost('backgroundCheckConsent') === 'true') ? 1 : 0;

        if ($existing) {
            // Update Query
            $sql = "UPDATE entrepreneur_kyc_details SET 
                full_name=?, dob=?, nationality=?, residential_address=?, city=?, state=?, country=?, postal_code=?, 
                gov_id_path=?, selfie_path=?, legal_name=?, brand_name=?, business_type=?, industry=?, incorporation_date=?, 
                startup_stage=?, business_address=?, coi_path=?, gst_certificate_path=?, role=?, ownership_percentage=?, 
                is_ubo=?, has_other_controllers=?, is_pep=?, account_holder_name=?, bank_name=?, account_number=?, 
                ifsc_code=?, account_type=?, bank_proof_path=?, pan_number=?, tax_residency=?, gst_number=?, cin=?, 
                agree_accuracy=?, agree_eligibility=?, agree_commission=?, agree_refund=?, agree_background_check=?,
                status='pending', rejection_reason=NULL
                WHERE entrepreneur_id=?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

            $types = "ssssssssssssssssssssdsssssssssssssiiiiii";
            $stmt->bind_param($types,
                getPost('fullName'), getPost('dateOfBirth'), getPost('nationality'), getPost('address'), getPost('city'), getPost('state'), getPost('country'), getPost('postalCode'), $gov_id, $selfie,
                getPost('legalName'), getPost('brandName'), getPost('businessType'), getPost('industry'), $inc_date, getPost('stage'), getPost('businessAddress'), $coi, $gst,
                getPost('role'), $own, getPost('isUBO'), getPost('hasOtherControllers'), getPost('isPEP'),
                getPost('accountHolderName'), getPost('bankName'), getPost('accountNumber'), getPost('ifscCode'), getPost('accountType'), $bank,
                getPost('panNumber'), getPost('taxResidency'), getPost('gstNumber'), getPost('cin'),
                $c1, $c2, $c3, $c4, $c5,
                $user_id
            );
        } else {
            // Insert Query
            $sql = "INSERT INTO entrepreneur_kyc_details (
                entrepreneur_id, full_name, dob, nationality, residential_address, city, state, country, postal_code, gov_id_path, selfie_path,
                legal_name, brand_name, business_type, industry, incorporation_date, startup_stage, business_address, coi_path, gst_certificate_path,
                role, ownership_percentage, is_ubo, has_other_controllers, is_pep,
                account_holder_name, bank_name, account_number, ifsc_code, account_type, bank_proof_path,
                pan_number, tax_residency, gst_number, cin, 
                agree_accuracy, agree_eligibility, agree_commission, agree_refund, agree_background_check,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

            $types = "issssssssssssssssssssdsssssssssssssiiiii";
            $stmt->bind_param($types,
                $user_id, 
                getPost('fullName'), getPost('dateOfBirth'), getPost('nationality'), getPost('address'), getPost('city'), getPost('state'), getPost('country'), getPost('postalCode'), $gov_id, $selfie,
                getPost('legalName'), getPost('brandName'), getPost('businessType'), getPost('industry'), $inc_date, getPost('stage'), getPost('businessAddress'), $coi, $gst,
                getPost('role'), $own, getPost('isUBO'), getPost('hasOtherControllers'), getPost('isPEP'),
                getPost('accountHolderName'), getPost('bankName'), getPost('accountNumber'), getPost('ifscCode'), getPost('accountType'), $bank,
                getPost('panNumber'), getPost('taxResidency'), getPost('gstNumber'), getPost('cin'),
                $c1, $c2, $c3, $c4, $c5
            );
        }

        if (!$stmt->execute()) throw new Exception("Execute Failed: " . $stmt->error);
        $stmt->close();

        // Update Main Table
        file_put_contents('debug_kyc.log', "Updating entrepreneurs table\n", FILE_APPEND);
        $conn->query("UPDATE entrepreneurs SET kyc_status = 'pending' WHERE id = $user_id");
        $conn->commit();
        file_put_contents('debug_kyc.log', "SUCCESS\n", FILE_APPEND);
        sendResponse(true);

    } catch (Exception $e) {
        if ($conn) $conn->rollback(); 
        foreach ($files_to_cleanup as $f) { if (file_exists($f)) unlink($f); } 
        sendResponse(false, $e->getMessage());
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartPitchHub - KYC Verification</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ============= CSS Variables ============= */
    :root {
      --background: hsl(230, 30%, 6%);
      --foreground: hsl(0, 0%, 100%);
      --card: hsl(230, 25%, 10%);
      --card-foreground: hsl(0, 0%, 100%);
      --card-elevated: hsl(230, 25%, 12%);
      --primary: hsl(262, 83%, 76%);
      --primary-foreground: hsl(230, 30%, 6%);
      --secondary: hsl(230, 20%, 18%);
      --secondary-foreground: hsl(0, 0%, 100%);
      --muted: hsl(230, 15%, 20%);
      --muted-foreground: hsl(230, 10%, 55%);
      --success: hsl(142, 76%, 36%);
      --success-foreground: hsl(0, 0%, 100%);
      --warning: hsl(38, 92%, 50%);
      --warning-foreground: hsl(230, 30%, 6%);
      --destructive: hsl(0, 72%, 51%);
      --border: hsl(230, 20%, 18%);
      --input: hsl(230, 20%, 15%);
      --radius: 1.125rem;
    }

    .light {
      --background: hsl(0, 0%, 98%);
      --foreground: hsl(230, 30%, 10%);
      --card: hsl(0, 0%, 100%);
      --card-foreground: hsl(230, 30%, 10%);
      --card-elevated: hsl(0, 0%, 100%);
      --primary: hsl(262, 83%, 58%);
      --primary-foreground: hsl(0, 0%, 100%);
      --secondary: hsl(230, 15%, 94%);
      --secondary-foreground: hsl(230, 30%, 10%);
      --muted: hsl(230, 15%, 92%);
      --muted-foreground: hsl(230, 10%, 45%);
      --success: hsl(142, 76%, 36%);
      --success-foreground: hsl(0, 0%, 100%);
      --warning: hsl(38, 92%, 50%);
      --warning-foreground: hsl(230, 30%, 6%);
      --destructive: hsl(0, 72%, 51%);
      --border: hsl(230, 15%, 88%);
      --input: hsl(230, 15%, 95%);
    }

    /* ============= Reset & Base ============= */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      line-height: 1.6;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3, h4, h5, h6 {
      font-weight: 600;
      letter-spacing: -0.02em;
    }

    /* ============= Utilities ============= */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1rem;
    }

    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-lg { font-size: 1.125rem; }
    .text-xl { font-size: 1.25rem; }
    .text-2xl { font-size: 1.5rem; }
    .text-3xl { font-size: 1.875rem; }
    .text-4xl { font-size: 2.25rem; }

    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }

    .text-muted { color: var(--muted-foreground); }
    .text-primary { color: var(--primary); }
    .text-success { color: var(--success); }

    .hidden { display: none; }

    @media (min-width: 768px) {
      .md-flex { display: flex; }
      .md-hidden { display: none; }
    }

    /* ============= Components ============= */
    .card-premium {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      box-shadow: 
        0 0 0 1px var(--border),
        0 4px 6px -1px rgba(0, 0, 0, 0.3),
        0 2px 4px -2px rgba(0, 0, 0, 0.2);
    }

    .card-premium-glow {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      box-shadow: 
        0 0 0 1px var(--border),
        0 4px 6px -1px rgba(0, 0, 0, 0.3),
        0 2px 4px -2px rgba(0, 0, 0, 0.2),
        0 0 40px -10px rgba(167, 139, 250, 0.15);
    }

    @media (min-width: 768px) {
      .card-premium-glow {
        padding: 2rem;
      }
    }

    /* Buttons */
    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: var(--primary);
      color: var(--primary-foreground);
      font-weight: 600;
      border-radius: 0.5rem;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 0.875rem;
    }

    .btn-primary:hover:not(:disabled) {
      filter: brightness(0.9);
      box-shadow: 0 4px 15px rgba(167, 139, 250, 0.25);
    }

    .btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: var(--secondary);
      color: var(--secondary-foreground);
      font-weight: 500;
      border-radius: 0.5rem;
      border: 1px solid var(--border);
      cursor: pointer;
      transition: all 0.2s;
      font-size: 0.875rem;
    }

    .btn-secondary:hover:not(:disabled) {
      background: var(--muted);
      border-color: var(--primary);
    }

    .btn-secondary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .btn-ghost {
      background: transparent;
      border: none;
      padding: 0.5rem;
      border-radius: 0.5rem;
      cursor: pointer;
      color: var(--foreground);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
    }

    .btn-ghost:hover {
      background: var(--secondary);
    }

    /* Inputs */
    .input-premium {
      width: 100%;
      padding: 0.75rem 1rem;
      background: var(--input);
      border: 1px solid var(--border);
      border-radius: 0.5rem;
      color: var(--foreground);
      font-family: inherit;
      font-size: 0.875rem;
      transition: all 0.2s;
    }

    .input-premium::placeholder {
      color: var(--muted-foreground);
    }

    .input-premium:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.15);
    }

    .input-premium:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Readonly inputs (Locked Fields) */
    .input-premium[readonly] {
      background: var(--secondary); /* Darker background */
      cursor: not-allowed;
      border-color: var(--border);
      color: var(--muted-foreground);
      opacity: 0.8;
    }

    .select-premium {
      width: 100%;
      padding: 0.75rem 2.5rem 0.75rem 1rem;
      background: var(--input);
      border: 1px solid var(--border);
      border-radius: 0.5rem;
      color: var(--foreground);
      font-family: inherit;
      font-size: 0.875rem;
      appearance: none;
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
      background-position: right 0.75rem center;
      background-repeat: no-repeat;
      background-size: 1.5em 1.5em;
      transition: all 0.2s;
    }

    .select-premium:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.15);
    }

    /* Labels */
    .label-text {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--foreground);
      margin-bottom: 0.5rem;
    }

    .helper-text {
      font-size: 0.75rem;
      color: var(--muted-foreground);
      margin-top: 0.375rem;
    }

    /* Badges */
    .badge-verified {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.25rem 0.625rem;
      font-size: 0.75rem;
      font-weight: 500;
      border-radius: 9999px;
      background: rgba(34, 197, 94, 0.2);
      color: hsl(142, 76%, 50%);
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .badge-pending {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.25rem 0.625rem;
      font-size: 0.75rem;
      font-weight: 500;
      border-radius: 9999px;
      background: rgba(245, 158, 11, 0.2);
      color: hsl(38, 92%, 50%);
      border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .badge-neutral {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.25rem 0.625rem;
      font-size: 0.75rem;
      font-weight: 500;
      border-radius: 9999px;
      background: var(--muted);
      color: var(--muted-foreground);
      border: 1px solid var(--border);
    }

    /* Step Indicators */
    .step-indicator-active {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      background: var(--primary);
      color: var(--primary-foreground);
      font-weight: 600;
      box-shadow: 0 0 20px rgba(167, 139, 250, 0.4);
    }

    .step-indicator-completed {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      background: var(--success);
      color: var(--success-foreground);
    }

    .step-indicator-pending {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      background: var(--muted);
      color: var(--muted-foreground);
      border: 1px solid var(--border);
    }

    /* Upload Zone */
    .upload-zone {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      border: 2px dashed var(--border);
      border-radius: 0.75rem;
      transition: all 0.2s;
      cursor: pointer;
    }

    .upload-zone:hover {
      border-color: var(--primary);
      background: rgba(167, 139, 250, 0.05);
    }

    .upload-zone.active {
      border-color: var(--primary);
      background: rgba(167, 139, 250, 0.1);
    }

    /* Checkbox */
    .checkbox-container {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      cursor: pointer;
    }

    .checkbox-container input[type="checkbox"] {
      width: 1.25rem;
      height: 1.25rem;
      margin-top: 0.125rem;
      accent-color: var(--primary);
      cursor: pointer;
      flex-shrink: 0;
    }

    .checkbox-container label {
      cursor: pointer;
      font-size: 0.875rem;
      color: var(--foreground);
    }

    /* Progress Bar */
    .progress-bar {
      width: 100%;
      height: 0.5rem;
      background: var(--muted);
      border-radius: 9999px;
      overflow: hidden;
    }

    .progress-bar-fill {
      height: 100%;
      background: var(--primary);
      border-radius: 9999px;
      transition: width 0.5s ease;
    }

    /* Divider */
    .divider {
      height: 1px;
      background: linear-gradient(to right, transparent, var(--border), transparent);
      margin: 1.5rem 0;
    }

    /* ============= Layout ============= */
    .header {
      border-bottom: 1px solid var(--border);
      padding: 1rem 0;
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .logo-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 0.5rem;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-foreground);
      font-weight: 700;
      font-size: 1.125rem;
    }

    .logo-text h1 {
      font-size: 1.125rem;
      font-weight: 700;
    }

    .logo-text p {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .ssl-badge {
      display: none;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    @media (min-width: 768px) {
      .ssl-badge {
        display: flex;
      }
    }

    .main-content {
      padding: 2rem 0 3rem;
    }

    @media (min-width: 768px) {
      .main-content {
        padding: 3rem 0 4rem;
      }
    }

    .max-w-4xl {
      max-width: 56rem;
      margin: 0 auto;
    }

    /* Page Header */
    .page-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .page-header-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      background: rgba(167, 139, 250, 0.1);
      border: 1px solid rgba(167, 139, 250, 0.2);
      color: var(--primary);
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 1rem;
    }

    .page-header h1 {
      font-size: 1.875rem;
      margin-bottom: 0.75rem;
    }

    @media (min-width: 768px) {
      .page-header h1 {
        font-size: 2.25rem;
      }
    }

    .page-header p {
      color: var(--muted-foreground);
      max-width: 42rem;
      margin: 0 auto;
    }

    /* Trust Badges Grid */
    .trust-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
      margin-bottom: 2.5rem;
    }

    @media (min-width: 768px) {
      .trust-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .trust-badge {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .trust-badge-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .trust-badge-icon.success { background: rgba(34, 197, 94, 0.2); color: var(--success); }
    .trust-badge-icon.primary { background: rgba(167, 139, 250, 0.2); color: var(--primary); }
    .trust-badge-icon.warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }

    .trust-badge-text h3 {
      font-size: 0.875rem;
      font-weight: 500;
    }

    .trust-badge-text p {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    /* Form Grid */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.5rem;
    }

    @media (min-width: 768px) {
      .form-grid-2 {
        grid-template-columns: repeat(2, 1fr);
      }
      .form-grid-3 {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    /* Step Content */
    .step-content {
      animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .step-header {
      margin-bottom: 1.5rem;
    }

    .step-header h2 {
      font-size: 1.25rem;
      margin-bottom: 0.5rem;
    }

    .step-header p {
      font-size: 0.875rem;
      color: var(--muted-foreground);
    }

    /* Step Indicator Desktop */
    .step-indicator-desktop {
      display: none;
    }

    @media (min-width: 1024px) {
      .step-indicator-desktop {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }
    }

    .step-item {
      display: flex;
      align-items: center;
      flex: 1;
    }

    .step-item-content {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .step-item-title {
      margin-top: 0.75rem;
      font-size: 0.75rem;
      font-weight: 500;
      text-align: center;
    }

    .step-line {
      flex: 1;
      height: 2px;
      margin: 0 1rem;
      background: var(--border);
      border-radius: 9999px;
      transition: background 0.3s;
    }

    .step-line.completed {
      background: var(--success);
    }

    /* Step Indicator Mobile */
    .step-indicator-mobile {
      display: block;
    }

    @media (min-width: 1024px) {
      .step-indicator-mobile {
        display: none;
      }
    }

    .step-mobile-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }

    .step-mobile-title {
      margin-top: 0.75rem;
      font-size: 0.875rem;
      font-weight: 500;
    }

    /* Navigation */
    .form-navigation {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 2rem;
    }

    /* Footer */
    .footer-note {
      margin-top: 2.5rem;
      text-align: center;
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .footer-note a {
      color: var(--primary);
      text-decoration: none;
    }

    .footer-note a:hover {
      text-decoration: underline;
    }

    /* Upload Preview */
    .upload-preview {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      background: var(--secondary);
      border-radius: 0.5rem;
      margin-top: 0.5rem;
    }

    .upload-preview-icon {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 0.375rem;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-foreground);
    }

    .upload-preview-info {
      flex: 1;
    }

    .upload-preview-name {
      font-size: 0.875rem;
      font-weight: 500;
    }

    .upload-preview-size {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .upload-preview-remove {
      color: var(--destructive);
      background: none;
      border: none;
      cursor: pointer;
      padding: 0.25rem;
    }

    /* Icons */
    .icon {
      width: 1.25rem;
      height: 1.25rem;
    }

    .icon-sm {
      width: 1rem;
      height: 1rem;
    }

    .icon-lg {
      width: 1.5rem;
      height: 1.5rem;
    }

    /* Space utilities */
    .space-y-6 > * + * { margin-top: 1.5rem; }
    .space-y-4 > * + * { margin-top: 1rem; }
    .space-y-3 > * + * { margin-top: 0.75rem; }
    .space-y-2 > * + * { margin-top: 0.5rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .gap-6 { gap: 1.5rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .mb-4 { margin-bottom: 1rem; }
    .mt-4 { margin-top: 1rem; }
    .mt-6 { margin-top: 1.5rem; }

    /* Flex utilities */
    .flex { display: flex; }
    .flex-col { flex-direction: column; }
    .items-center { align-items: center; }
    .items-start { align-items: flex-start; }
    .justify-between { justify-content: space-between; }
    .justify-center { justify-content: center; }
    .flex-1 { flex: 1; }
    .flex-shrink-0 { flex-shrink: 0; }

    /* Grid utilities */
    .grid { display: grid; }

    /* Text utilities */
    .text-center { text-align: center; }

    /* Status Card */
    .status-card {
      text-align: center;
      padding: 2rem;
    }

    .status-icon {
      width: 4rem;
      height: 4rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
    }

    .status-icon.pending { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    .status-icon.success { background: rgba(34, 197, 94, 0.2); color: var(--success); }
    .status-icon.error { background: rgba(239, 68, 68, 0.2); color: var(--destructive); }

    /* Theme Toggle */
    .theme-toggle {
      background: var(--secondary);
      border: 1px solid var(--border);
    }

    .theme-toggle svg {
      width: 1.25rem;
      height: 1.25rem;
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="container">
      <div class="header-content">
        <div class="logo-container">
          <div class="logo-icon">S</div>
          <div class="logo-text">
            <h1>SmartPitchHub</h1>
            <p>Startup Fundraising Platform</p>
          </div>
        </div>
        <div class="header-right">
          <div class="ssl-badge">
            <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            256-bit SSL Encrypted
          </div>
          <button class="btn-ghost theme-toggle" id="themeToggle" aria-label="Toggle theme">
            <svg id="sunIcon" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            <svg id="moonIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          </button>
        </div>
      </div>
    </div>
  </header>

  <main class="main-content">
    <div class="container">
      <div class="max-w-4xl">
        <div class="page-header">
          <div class="page-header-badge">
            <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Entrepreneur Verification
          </div>
          <h1>Complete Your KYC Verification</h1>
          <p>Verify your identity and business details to start raising funds from accredited investors. This process typically takes 5-10 minutes.</p>
        </div>

        <div class="trust-grid">
          <div class="card-premium">
            <div class="trust-badge">
              <div class="trust-badge-icon success">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
              </div>
              <div class="trust-badge-text">
                <h3>SEBI Compliant</h3>
                <p>Regulatory guidelines followed</p>
              </div>
            </div>
          </div>
          <div class="card-premium">
            <div class="trust-badge">
              <div class="trust-badge-icon primary">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
              </div>
              <div class="trust-badge-text">
                <h3>Bank-Grade Security</h3>
                <p>Data encrypted at rest & transit</p>
              </div>
            </div>
          </div>
          <div class="card-premium">
            <div class="trust-badge">
              <div class="trust-badge-icon warning">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              </div>
              <div class="trust-badge-text">
                <h3>Quick Review</h3>
                <p>2-3 business days verification</p>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6" id="kycFormContainer">
          <div id="rejectionBanner" class="hidden"></div>
          <div class="card-premium-glow" id="stepIndicatorCard">
            <div class="step-indicator-desktop" id="stepIndicatorDesktop"></div>
            <div class="step-indicator-mobile" id="stepIndicatorMobile"></div>
          </div>

          <div class="card-premium-glow" id="formContent"></div>

          <div class="form-navigation" id="formNavigation">
            <button class="btn-secondary" id="prevBtn" disabled>
              <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
              Previous
            </button>
            <button class="btn-primary" id="nextBtn">
              Continue
              <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>

        <div class="card-premium-glow hidden" id="statusCard">
          <div class="status-card">
            <div class="status-icon pending">
              <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h2 class="text-xl font-semibold mb-2">KYC Under Review</h2>
            <p class="text-muted mb-4">Your verification is being processed. We'll notify you within 2-3 business days.</p>
            <span class="badge-pending">Under Review</span>
            <p class="text-xs text-muted mt-4" id="submissionTime"></p>
            <br>
            <button class="btn-primary" onclick="window.location.href='../dashboards/Entrepreneur-dashboard.php'">Return to Dashboard</button>
          </div>
        </div>

        <div class="footer-note">
          <p>By proceeding, you agree to SmartPitchHub's <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.<br>
          Need help? Contact <a href="mailto:support@smartpitchhub.com">support@smartpitchhub.com</a></p>
        </div>
      </div>
    </div>
  </main>

  <script>
    // ============= Theme Toggle =============
    const themeToggle = document.getElementById('themeToggle');
    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');
    let isDark = true;

    // Check saved preference
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light');
      isDark = false;
      sunIcon.classList.remove('hidden');
      moonIcon.classList.add('hidden');
    }

    themeToggle.addEventListener('click', () => {
      isDark = !isDark;
      if (isDark) {
        document.documentElement.classList.remove('light');
        localStorage.setItem('theme', 'dark');
        sunIcon.classList.add('hidden');
        moonIcon.classList.remove('hidden');
      } else {
        document.documentElement.classList.add('light');
        localStorage.setItem('theme', 'light');
        sunIcon.classList.remove('hidden');
        moonIcon.classList.add('hidden');
      }
    });

    // ============= Form State =============
    const STEPS = [
      { id: 1, title: "Individual Identity Verification", shortTitle: "Identity" },
      { id: 2, title: "Startup / Business Details", shortTitle: "Business" },
      { id: 3, title: "Ownership & Control Disclosure", shortTitle: "Ownership" },
      { id: 4, title: "Bank Account Verification", shortTitle: "Bank" },
      { id: 5, title: "Tax & Regulatory Information", shortTitle: "Tax" },
      { id: 6, title: "Declarations & Consent", shortTitle: "Consent" },
    ];

    let currentStep = 1;
    let completedSteps = [];
    let formData = {
      step1: { emailVerified: true, mobileVerified: true },
      step2: {},
      step3: {},
      step4: {},
      step5: {},
      step6: {},
    };
    
    // --- PERSISTENCE: Store files globally ---
    let fileStorage = {}; 

    // --- KYC STATUS & DATA FROM PHP ---
    const KYC_DATA = {
        status: "<?php echo $kyc_status; ?>",
        rejectionReason: <?php echo json_encode($kyc_rejection_reason); ?>,
        existing: <?php echo json_encode($existing_kyc); ?>
    };

    const PREFILLED_DATA = {
        email: "<?php echo htmlspecialchars($user_email); ?>",
        phone: "<?php echo htmlspecialchars($user_phone); ?>"
    };

    // Initialize form with existing data if available
    if (KYC_DATA.existing) {
        formData.step1 = {
            fullName: KYC_DATA.existing.full_name,
            dateOfBirth: KYC_DATA.existing.dob,
            nationality: KYC_DATA.existing.nationality,
            address: KYC_DATA.existing.residential_address,
            city: KYC_DATA.existing.city,
            state: KYC_DATA.existing.state,
            country: KYC_DATA.existing.country,
            postalCode: KYC_DATA.existing.postal_code,
            emailVerified: true, mobileVerified: true
        };
        formData.step2 = {
            legalName: KYC_DATA.existing.legal_name,
            brandName: KYC_DATA.existing.brand_name,
            businessType: KYC_DATA.existing.business_type,
            industry: KYC_DATA.existing.industry,
            incorporationDate: KYC_DATA.existing.incorporation_date,
            stage: KYC_DATA.existing.startup_stage,
            businessAddress: KYC_DATA.existing.business_address
        };
        formData.step3 = {
            role: KYC_DATA.existing.role,
            ownershipPercentage: KYC_DATA.existing.ownership_percentage,
            isUBO: KYC_DATA.existing.is_ubo,
            hasOtherControllers: KYC_DATA.existing.has_other_controllers,
            isPEP: KYC_DATA.existing.is_pep
        };
        formData.step4 = {
            accountHolderName: KYC_DATA.existing.account_holder_name,
            bankName: KYC_DATA.existing.bank_name,
            accountNumber: KYC_DATA.existing.account_number,
            ifscCode: KYC_DATA.existing.ifsc_code,
            accountType: KYC_DATA.existing.account_type
        };
        formData.step5 = {
            panNumber: KYC_DATA.existing.pan_number,
            taxResidency: KYC_DATA.existing.tax_residency,
            gstNumber: KYC_DATA.existing.gst_number,
            cin: KYC_DATA.existing.cin
        };
    }

    // ============= Render Functions =============
    function renderStepIndicator() {
      // Desktop
      const desktopHtml = STEPS.map((step, index) => {
        const isCompleted = completedSteps.includes(step.id);
        const isActive = currentStep === step.id;
        
        let indicatorClass = 'step-indicator-pending';
        let indicatorContent = step.id;
        let titleColor = 'text-muted';
        
        if (isCompleted) {
          indicatorClass = 'step-indicator-completed';
          indicatorContent = '<svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
          titleColor = 'text-success';
        } else if (isActive) {
          indicatorClass = 'step-indicator-active';
          titleColor = '';
        }
        
        const lineClass = isCompleted ? 'step-line completed' : 'step-line';
        const line = index < STEPS.length - 1 ? `<div class="${lineClass}"></div>` : '';
        
        return `
          <div class="step-item">
            <div class="step-item-content">
              <div class="${indicatorClass}">${indicatorContent}</div>
              <div class="step-item-title ${titleColor}">${step.shortTitle}</div>
            </div>
            ${line}
          </div>
        `;
      }).join('');
      
      document.getElementById('stepIndicatorDesktop').innerHTML = desktopHtml;
      
      // Mobile
      const progress = Math.round((completedSteps.length / STEPS.length) * 100);
      const currentStepData = STEPS.find(s => s.id === currentStep);
      
      const mobileHtml = `
        <div class="step-mobile-header">
          <span class="text-sm font-medium text-muted">Step ${currentStep} of ${STEPS.length}</span>
          <span class="badge-neutral">${progress}% Complete</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width: ${progress}%"></div>
        </div>
        <p class="step-mobile-title">${currentStepData.title}</p>
      `;
      
      document.getElementById('stepIndicatorMobile').innerHTML = mobileHtml;
    }

    function renderFormContent() {
      const formContent = document.getElementById('formContent');
      let html = '';
      
      switch (currentStep) {
        case 1: html = renderStep1(); break;
        case 2: html = renderStep2(); break;
        case 3: html = renderStep3(); break;
        case 4: html = renderStep4(); break;
        case 5: html = renderStep5(); break;
        case 6: html = renderStep6(); break;
      }
      
      formContent.innerHTML = `<div class="step-content">${html}</div>`;
      attachFormListeners();
      
      // Re-display uploaded file names
      restoreFilePreviews();
    }

    // --- Helper to show file name if exists ---
    function getFilePreviewHTML(key) {
        if (fileStorage[key]) {
            return `<div class="upload-preview" id="${key}PreviewBox">
                      <div class="upload-preview-icon"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                      <div class="upload-preview-info"><div class="upload-preview-name">${fileStorage[key].name}</div></div>
                    </div>`;
        }
        return '';
    }

    function restoreFilePreviews() {
        // Step 1
        if(document.getElementById('govIdPreview')) document.getElementById('govIdPreview').innerHTML = getFilePreviewHTML('govId');
        if(document.getElementById('selfiePreview')) document.getElementById('selfiePreview').innerHTML = getFilePreviewHTML('selfie');
        // Step 2
        if(document.getElementById('coiPreview')) document.getElementById('coiPreview').innerHTML = getFilePreviewHTML('coi');
        if(document.getElementById('gstPreview')) document.getElementById('gstPreview').innerHTML = getFilePreviewHTML('gst');
        // Step 4
        if(document.getElementById('bankProofPreview')) document.getElementById('bankProofPreview').innerHTML = getFilePreviewHTML('bankProof');
    }

    function renderStep1() {
      const data = formData.step1;
      
      // LOGIC: If database phone is empty, allow user to type. If it exists, lock it.
      const hasPhone = PREFILLED_DATA.phone && PREFILLED_DATA.phone.trim() !== '';
      const phoneReadonly = hasPhone ? 'readonly' : '';
      const phoneBadge = hasPhone 
          ? '<span class="badge-verified"><svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Verified</span>' 
          : '<span class="text-xs text-muted">(Please Enter)</span>';

      return `
        <div class="step-header">
          <h2>Individual Identity Verification</h2>
          <p>Please provide your personal details as they appear on your government-issued ID.</p>
        </div>
        
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group full-width">
            <label class="label-text">Legal Full Name (as per Government ID) *</label>
            <input type="text" class="input-premium" name="fullName" value="${data.fullName || ''}" placeholder="Enter your full legal name">
          </div>
          
          <div class="form-group">
            <label class="label-text">Date of Birth *</label>
            <input type="date" class="input-premium" name="dateOfBirth" value="${data.dateOfBirth || ''}">
          </div>
          
          <div class="form-group">
            <label class="label-text">Nationality *</label>
            <select class="select-premium" name="nationality">
              <option value="">Select nationality</option>
              <option value="indian" ${data.nationality === 'indian' ? 'selected' : ''}>Indian</option>
              <option value="us" ${data.nationality === 'us' ? 'selected' : ''}>US Citizen</option>
              <option value="uk" ${data.nationality === 'uk' ? 'selected' : ''}>UK Citizen</option>
              <option value="other" ${data.nationality === 'other' ? 'selected' : ''}>Other</option>
            </select>
          </div>
          
          <div class="form-group full-width">
            <label class="label-text">Residential Address *</label>
            <input type="text" class="input-premium" name="address" value="${data.address || ''}" placeholder="Street address, building, apartment">
          </div>
          
          <div class="form-group">
            <label class="label-text">City *</label>
            <input type="text" class="input-premium" name="city" value="${data.city || ''}" placeholder="City">
          </div>
          
          <div class="form-group">
            <label class="label-text">State *</label>
            <input type="text" class="input-premium" name="state" value="${data.state || ''}" placeholder="State">
          </div>
          
          <div class="form-group">
            <label class="label-text">Country *</label>
            <select class="select-premium" name="country">
              <option value="">Select country</option>
              <option value="india" ${data.country === 'india' ? 'selected' : ''}>India</option>
              <option value="usa" ${data.country === 'usa' ? 'selected' : ''}>United States</option>
              <option value="uk" ${data.country === 'uk' ? 'selected' : ''}>United Kingdom</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="label-text">Postal Code *</label>
            <input type="text" class="input-premium" name="postalCode" value="${data.postalCode || ''}" placeholder="Postal code">
          </div>
          
          <div class="form-group">
            <label class="label-text flex items-center gap-2">
              Email Address <span class="text-xs text-muted">(Linked)</span>
              <span class="badge-verified">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Verified
              </span>
            </label>
            <input type="email" class="input-premium" name="email" value="${PREFILLED_DATA.email}" readonly>
          </div>
          
          <div class="form-group">
            <label class="label-text flex items-center gap-2">
              Mobile Number ${phoneBadge}
            </label>
            <input type="tel" class="input-premium" name="mobile" value="${PREFILLED_DATA.phone}" ${phoneReadonly} placeholder="+91 XXXXX XXXXX">
          </div>
        </div>
        
        <div class="divider"></div>
        
        <h3 class="font-semibold mb-4">Document Upload</h3>
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group">
            <label class="label-text">Government ID (PAN / Passport / Adhar Card) *</label>
            <div class="upload-zone" onclick="triggerUpload('govId')">
              <input type="file" id="govIdInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
              <svg class="icon-lg text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
              <p class="text-sm text-muted">Click to upload or drag and drop</p>
              <p class="text-xs text-muted mt-1">PDF, JPG or PNG (max. 5MB)</p>
            </div>
            <div id="govIdPreview"></div>
          </div>
          
          <div class="form-group">
            <label class="label-text">Live Selfie / Photo *</label>
            <div class="upload-zone" onclick="triggerUpload('selfie')">
              <input type="file" id="selfieInput" class="hidden" accept=".jpg,.jpeg,.png">
              <svg class="icon-lg text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
              <p class="text-sm text-muted">Click to upload your photo</p>
              <p class="text-xs text-muted mt-1">JPG or PNG (max. 5MB)</p>
            </div>
            <div id="selfiePreview"></div>
          </div>
        </div>
      `;
    }

    function renderStep2() {
      const data = formData.step2;
      return `
        <div class="step-header">
          <h2>Startup / Business Details</h2>
          <p>Provide information about your startup or business entity.</p>
        </div>
        
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group">
            <label class="label-text">Startup Legal Name *</label>
            <input type="text" class="input-premium" name="legalName" value="${data.legalName || ''}" placeholder="Legal registered name">
          </div>
          
          <div class="form-group">
            <label class="label-text">Brand / Trade Name</label>
            <input type="text" class="input-premium" name="brandName" value="${data.brandName || ''}" placeholder="Brand name (if different)">
          </div>
          
          <div class="form-group">
            <label class="label-text">Business Type *</label>
            <select class="select-premium" name="businessType">
              <option value="">Select type</option>
              <option value="individual" ${data.businessType === 'individual' ? 'selected' : ''}>Individual / Sole Proprietor</option>
              <option value="pvt_ltd" ${data.businessType === 'pvt_ltd' ? 'selected' : ''}>Private Limited</option>
              <option value="llp" ${data.businessType === 'llp' ? 'selected' : ''}>LLP</option>
              <option value="partnership" ${data.businessType === 'partnership' ? 'selected' : ''}>Partnership</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="label-text">Industry Category *</label>
            <select class="select-premium" name="industry">
              <option value="">Select industry</option>
              <option value="fintech" ${data.industry === 'fintech' ? 'selected' : ''}>Fintech</option>
              <option value="healthtech" ${data.industry === 'healthtech' ? 'selected' : ''}>Healthtech</option>
              <option value="edtech" ${data.industry === 'edtech' ? 'selected' : ''}>Edtech</option>
              <option value="ecommerce" ${data.industry === 'ecommerce' ? 'selected' : ''}>E-commerce</option>
              <option value="saas" ${data.industry === 'saas' ? 'selected' : ''}>SaaS</option>
              <option value="other" ${data.industry === 'other' ? 'selected' : ''}>Other</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="label-text">Date of Incorporation</label>
            <input type="date" class="input-premium" name="incorporationDate" value="${data.incorporationDate || ''}">
          </div>
          
          <div class="form-group">
            <label class="label-text">Startup Stage *</label>
            <select class="select-premium" name="stage">
              <option value="">Select stage</option>
              <option value="idea" ${data.stage === 'idea' ? 'selected' : ''}>Idea Stage</option>
              <option value="mvp" ${data.stage === 'mvp' ? 'selected' : ''}>MVP</option>
              <option value="revenue" ${data.stage === 'revenue' ? 'selected' : ''}>Revenue Generating</option>
              <option value="growth" ${data.stage === 'growth' ? 'selected' : ''}>Growth Stage</option>
            </select>
          </div>
          
          <div class="form-group full-width">
            <label class="label-text">Registered Business Address</label>
            <input type="text" class="input-premium" name="businessAddress" value="${data.businessAddress || ''}" placeholder="Complete registered address">
          </div>
        </div>
        
        <div class="divider"></div>
        
        <h3 class="font-semibold mb-4">Business Documents</h3>
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group">
            <label class="label-text">Certificate of Incorporation</label>
            <div class="upload-zone" onclick="triggerUpload('coi')">
              <input type="file" id="coiInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
              <svg class="icon-lg text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
              <p class="text-sm text-muted">Upload document</p>
            </div>
            <div id="coiPreview"></div>
          </div>
          
          <div class="form-group">
            <label class="label-text">GST Certificate (Optional)</label>
            <div class="upload-zone" onclick="triggerUpload('gst')">
              <input type="file" id="gstInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
              <svg class="icon-lg text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
              <p class="text-sm text-muted">Upload document</p>
            </div>
            <div id="gstPreview"></div>
          </div>
        </div>
      `;
    }

    function renderStep3() {
      const data = formData.step3;
      return `
        <div class="step-header">
          <h2>Ownership & Control Disclosure</h2>
          <p>Provide information about your role and ownership in the company.</p>
        </div>
        
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group">
            <label class="label-text">Your Role *</label>
            <select class="select-premium" name="role">
              <option value="">Select role</option>
              <option value="founder" ${data.role === 'founder' ? 'selected' : ''}>Founder</option>
              <option value="cofounder" ${data.role === 'cofounder' ? 'selected' : ''}>Co-Founder</option>
              <option value="director" ${data.role === 'director' ? 'selected' : ''}>Director</option>
              <option value="ceo" ${data.role === 'ceo' ? 'selected' : ''}>CEO</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="label-text">Ownership Percentage *</label>
            <input type="number" class="input-premium" name="ownershipPercentage" value="${data.ownershipPercentage || ''}" placeholder="e.g., 51" min="0" max="100">
            <p class="helper-text">Your stake in the company</p>
          </div>
          
          <div class="form-group">
            <label class="label-text">Are you the Ultimate Beneficial Owner (UBO)? *</label>
            <select class="select-premium" name="isUBO">
              <option value="">Select</option>
              <option value="yes" ${data.isUBO === 'yes' ? 'selected' : ''}>Yes</option>
              <option value="no" ${data.isUBO === 'no' ? 'selected' : ''}>No</option>
            </select>
            <p class="helper-text">A UBO owns 25% or more of the company</p>
          </div>
          
          <div class="form-group">
            <label class="label-text">Are there other controlling persons?</label>
            <select class="select-premium" name="hasOtherControllers">
              <option value="">Select</option>
              <option value="yes" ${data.hasOtherControllers === 'yes' ? 'selected' : ''}>Yes</option>
              <option value="no" ${data.hasOtherControllers === 'no' ? 'selected' : ''}>No</option>
            </select>
          </div>
          
          <div class="form-group full-width">
            <label class="label-text">Are you a Politically Exposed Person (PEP)?</label>
            <select class="select-premium" name="isPEP">
              <option value="">Select</option>
              <option value="yes" ${data.isPEP === 'yes' ? 'selected' : ''}>Yes</option>
              <option value="no" ${data.isPEP === 'no' ? 'selected' : ''}>No</option>
            </select>
            <p class="helper-text">A PEP is someone who holds or has held a prominent public position</p>
          </div>
        </div>
      `;
    }

    function renderStep4() {
      const data = formData.step4;
      return `
        <div class="step-header">
          <h2>Bank Account Verification</h2>
          <p>Provide your bank details for receiving investments.</p>
        </div>
        
        <div class="card-premium mb-6" style="background: rgba(167, 139, 250, 0.1); border-color: rgba(167, 139, 250, 0.3);">
          <div class="flex items-start gap-3">
            <svg class="icon text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <div>
              <p class="text-sm font-medium">Secure Fund Transfer</p>
              <p class="text-xs text-muted">All investment funds will be transferred directly to this verified bank account. You can only add one bank account.</p>
            </div>
          </div>
        </div>
        
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group">
            <label class="label-text">Account Holder Name *</label>
            <input type="text" class="input-premium" name="accountHolderName" value="${data.accountHolderName || ''}" placeholder="As per bank records">
          </div>
          
          <div class="form-group">
            <label class="label-text">Bank Name *</label>
            <input type="text" class="input-premium" name="bankName" value="${data.bankName || ''}" placeholder="e.g., HDFC Bank">
          </div>
          
          <div class="form-group">
            <label class="label-text">Account Number *</label>
            <input type="text" class="input-premium" name="accountNumber" value="${data.accountNumber || ''}" placeholder="Enter account number">
          </div>
          
          <div class="form-group">
            <label class="label-text">IFSC / SWIFT Code *</label>
            <input type="text" class="input-premium" name="ifscCode" value="${data.ifscCode || ''}" placeholder="e.g., HDFC0001234">
          </div>
          
          <div class="form-group">
            <label class="label-text">Account Type *</label>
            <select class="select-premium" name="accountType">
              <option value="">Select type</option>
              <option value="savings" ${data.accountType === 'savings' ? 'selected' : ''}>Savings</option>
              <option value="current" ${data.accountType === 'current' ? 'selected' : ''}>Current</option>
            </select>
          </div>
        </div>
        
        <div class="divider"></div>
        
        <h3 class="font-semibold mb-4">Bank Proof Document</h3>
        <div class="form-group">
          <label class="label-text">Cancelled Cheque or Bank Statement *</label>
          <div class="upload-zone" onclick="triggerUpload('bankProof')">
            <input type="file" id="bankProofInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
            <svg class="icon-lg text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
            <p class="text-sm text-muted">Upload cancelled cheque or recent bank statement</p>
            <p class="text-xs text-muted mt-1">You may mask your balance for privacy</p>
          </div>
          <div id="bankProofPreview"></div>
        </div>
      `;
    }

    function renderStep5() {
      const data = formData.step5;
      return `
        <div class="step-header">
          <h2>Tax & Regulatory Information</h2>
          <p>Provide your tax identification details for compliance purposes.</p>
        </div>
        
        <div class="form-grid form-grid-2 gap-6">
          <div class="form-group">
            <label class="label-text">PAN Number *</label>
            <input type="text" class="input-premium" name="panNumber" value="${data.panNumber || ''}" placeholder="ABCDE1234F" style="text-transform: uppercase;">
            <p class="helper-text">10-character alphanumeric code</p>
          </div>
          
          <div class="form-group">
            <label class="label-text">Tax Residency Country *</label>
            <select class="select-premium" name="taxResidency">
              <option value="">Select country</option>
              <option value="india" ${data.taxResidency === 'india' ? 'selected' : ''}>India</option>
              <option value="usa" ${data.taxResidency === 'usa' ? 'selected' : ''}>United States</option>
              <option value="uk" ${data.taxResidency === 'uk' ? 'selected' : ''}>United Kingdom</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="label-text">GST Number (Optional)</label>
            <input type="text" class="input-premium" name="gstNumber" value="${data.gstNumber || ''}" placeholder="e.g., 27AABCU9603R1ZM">
          </div>
          
          <div class="form-group">
            <label class="label-text">CIN / LLPIN (if applicable)</label>
            <input type="text" class="input-premium" name="cin" value="${data.cin || ''}" placeholder="Corporate Identification Number">
          </div>
        </div>
      `;
    }

    function renderStep6() {
      const data = formData.step6;
      return `
        <div class="step-header">
          <h2>Declarations & Consent</h2>
          <p>Please review and accept the following declarations to complete your KYC verification.</p>
        </div>
        
        <div class="space-y-4">
          <div class="checkbox-container">
            <input type="checkbox" id="accuracyDeclaration" name="accuracyDeclaration" ${data.accuracyDeclaration ? 'checked' : ''}>
            <label for="accuracyDeclaration">I hereby declare that all information provided is true, accurate, and complete to the best of my knowledge. I understand that providing false information may result in legal action.</label>
          </div>
          
          <div class="checkbox-container">
            <input type="checkbox" id="eligibilityDeclaration" name="eligibilityDeclaration" ${data.eligibilityDeclaration ? 'checked' : ''}>
            <label for="eligibilityDeclaration">I confirm that I am legally eligible to raise funds and that my business complies with all applicable laws and regulations in my jurisdiction.</label>
          </div>
          
          <div class="checkbox-container">
            <input type="checkbox" id="commissionAgreement" name="commissionAgreement" ${data.commissionAgreement ? 'checked' : ''}>
            <label for="commissionAgreement">I understand and agree to SmartPitchHub's platform commission and fee structure as outlined in the Terms of Service.</label>
          </div>
          
          <div class="checkbox-container">
            <input type="checkbox" id="refundAgreement" name="refundAgreement" ${data.refundAgreement ? 'checked' : ''}>
            <label for="refundAgreement">I acknowledge the refund policy and compliance requirements. I understand that funds may be held in escrow until all conditions are met.</label>
          </div>
          
          <div class="checkbox-container">
            <input type="checkbox" id="backgroundCheckConsent" name="backgroundCheckConsent" ${data.backgroundCheckConsent ? 'checked' : ''}>
            <label for="backgroundCheckConsent">I consent to SmartPitchHub conducting background verification checks as part of the KYC process. This may include identity verification, credit checks, and regulatory screening.</label>
          </div>
        </div>
      `;
    }

    function attachFormListeners() {
      const inputs = document.querySelectorAll('.input-premium, .select-premium');
      inputs.forEach(input => {
        input.addEventListener('change', (e) => {
          const stepKey = `step${currentStep}`;
          formData[stepKey][e.target.name] = e.target.value;
          updateNavButtons();
        });
        input.addEventListener('input', (e) => {
          const stepKey = `step${currentStep}`;
          formData[stepKey][e.target.name] = e.target.value;
          updateNavButtons();
        });
      });
      
      const checkboxes = document.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
          const stepKey = `step${currentStep}`;
          formData[stepKey][e.target.name] = e.target.checked;
          updateNavButtons();
        });
      });

      // --- ADDED: Listen for file changes ---
      const fileInputs = document.querySelectorAll('input[type="file"]');
      fileInputs.forEach(fileInput => {
          fileInput.addEventListener('change', (e) => {
              if (e.target.files && e.target.files[0]) {
                  // Get ID base name (e.g. 'govIdInput' -> 'govId')
                  let key = e.target.id.replace('Input', '');
                  fileStorage[key] = e.target.files[0];
                  
                  // Update UI
                  let previewId = key + 'Preview';
                  let previewEl = document.getElementById(previewId);
                  if(previewEl) {
                      previewEl.innerHTML = `<div class="upload-preview" id="${key}PreviewBox">
                      <div class="upload-preview-icon"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                      <div class="upload-preview-info"><div class="upload-preview-name">${e.target.files[0].name}</div></div>
                    </div>`;
                  }
              }
          });
      });
    }

    function canProceed() {
      const data = formData[`step${currentStep}`];
      switch (currentStep) {
        case 1:
          return data.fullName && data.dateOfBirth && data.nationality;
        case 2:
          return data.legalName && data.businessType && data.industry;
        case 3:
          return data.role && data.ownershipPercentage && data.isUBO;
        case 4:
          return data.accountHolderName && data.bankName && data.accountNumber;
        case 5:
          return data.panNumber && data.taxResidency;
        case 6:
          return data.accuracyDeclaration && 
                 data.eligibilityDeclaration && 
                 data.commissionAgreement && 
                 data.refundAgreement && 
                 data.backgroundCheckConsent;
        default:
          return true;
      }
    }

    function updateNavButtons() {
      const prevBtn = document.getElementById('prevBtn');
      const nextBtn = document.getElementById('nextBtn');
      
      prevBtn.disabled = currentStep === 1;
      nextBtn.disabled = !canProceed();
      
      if (currentStep === 6) {
        nextBtn.innerHTML = `
          <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
          Submit KYC for Verification
        `;
      } else {
        nextBtn.innerHTML = `
          Continue
          <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        `;
      }
    }

    // ============= File Upload =============
    function triggerUpload(type) {
      document.getElementById(type + 'Input').click();
    }

    // ============= Navigation =============
    document.getElementById('prevBtn').addEventListener('click', () => {
      if (currentStep > 1) {
        currentStep--;
        renderAll();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });

    document.getElementById('nextBtn').addEventListener('click', async () => {
      if (currentStep < 6) {
        if (!completedSteps.includes(currentStep)) {
          completedSteps.push(currentStep);
        }
        currentStep++;
        renderAll();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        // Submit
        submitForm();
      }
    });

    // --- MODIFIED SUBMIT FORM ---
    async function submitForm() {
      const nextBtn = document.getElementById('nextBtn');
      nextBtn.disabled = true;
      nextBtn.innerHTML = `
        <svg class="icon-sm animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-width="4" stroke="currentColor" fill="none" stroke-dasharray="31.415, 31.415" stroke-dashoffset="0"></circle></svg>
        Submitting...
      `;
      
      const payload = new FormData();
      
      // 1. Append Text Data
      for(let step in formData) {
          for(let key in formData[step]) {
              payload.append(key, formData[step][key]);
          }
      }
      
      // 2. Append Consents
      const checks = document.querySelectorAll('input[type="checkbox"]');
      checks.forEach(c => payload.append(c.name, c.checked ? 'true' : 'false'));

      // 3. Append Files
      for(let key in fileStorage) {
          payload.append(key, fileStorage[key]);
      }

      try {
          // Use window.location.href to avoid 404 filename errors
          const response = await fetch(window.location.href, {
              method: 'POST',
              body: payload
          });
          
          const text = await response.text();
          let result;
          try {
              result = JSON.parse(text);
          } catch(e) {
              console.error("Server Raw Response:", text);
              throw new Error("Server Error. Check console for details.");
          }

          if(result.success) {
              completedSteps.push(6);
              document.getElementById('kycFormContainer').classList.add('hidden');
              document.getElementById('statusCard').classList.remove('hidden');
              if(document.getElementById('submissionTime')) {
                  document.getElementById('submissionTime').textContent = 'Submitted on ' + new Date().toLocaleString();
              }
          } else {
              throw new Error(result.message || "Unknown error occurred");
          }
      } catch(err) {
          console.error(err);
          alert(err.message);
          nextBtn.disabled = false;
          nextBtn.innerHTML = "Submit KYC for Verification";
      }
    }

    function renderAll() {
      // If rejected, show reason banner
      if (KYC_DATA.status === 'rejected') {
          const banner = document.getElementById('rejectionBanner');
          if (banner) {
              banner.innerHTML = `
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                  <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <div style="background: #ef4444; color: white; padding: 0.5rem; border-radius: 50%;">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </div>
                    <div>
                      <h3 style="color: #ef4444; font-weight: 600; font-size: 1.125rem;">KYC Rejected</h3>
                      <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.25rem;">Your previous application was not approved. Please fix the issues below and resubmit.</p>
                      <div style="margin-top: 1rem; padding: 1rem; background: var(--secondary); border-radius: 6px; border-left: 4px solid #ef4444;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--muted-foreground); font-weight: 600;">Reason for Rejection:</span>
                        <p style="color: var(--foreground); margin-top: 0.25rem; font-weight: 500;">${KYC_DATA.rejectionReason || 'No specific reason provided.'}</p>
                      </div>
                    </div>
                  </div>
                </div>
              `;
              banner.classList.remove('hidden');
          }
      }

      renderStepIndicator();
      renderFormContent();
      updateNavButtons();
    }

    // ============= Initial Render =============
    if (KYC_DATA.status === 'pending' || KYC_DATA.status === 'verified') {
        const scard = document.getElementById('statusCard');
        const sicon = scard.querySelector('.status-icon');
        const stitle = scard.querySelector('h2');
        const sdesc = scard.querySelector('.text-muted');
        const sbadge = scard.querySelector('span');

        if (KYC_DATA.status === 'verified') {
            sicon.className = 'status-icon success';
            sicon.innerHTML = '<svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
            stitle.textContent = 'Verification Successful!';
            sdesc.textContent = 'Your business is now verified. You can proceed to create pitches and raise funding.';
            sbadge.className = 'badge-verified';
            sbadge.textContent = 'Verified';
        }

        document.getElementById('kycFormContainer').classList.add('hidden');
        scard.classList.remove('hidden');
    } else {
        renderAll();
    }

    // Add spin animation
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
  </script>
</body>
</html>