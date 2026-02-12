<?php
require_once 'db.php';

// Password for all users: Password123
$hashedPassword = password_hash('Password123', PASSWORD_DEFAULT);

try {
    // Disable foreign key checks for truncation
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // List of tables to clear
    $tables = [
        'entrepreneurs',
        'investors',
        'entrepreneur_kyc_details',
        'investor_kyc_details',
        'pitches',
        'pitch_documents',
        'investments',
        'otp_verifications',
        'investor_bids',
        'bid_purchase_orders',
        'password_resets'
    ];

    foreach ($tables as $table) {
        $conn->query("TRUNCATE TABLE $table");
        echo "Table $table cleared.<br>";
    }

    // 1. Insert 3 Investors (All Pending KYC)
    $investors = [
        ['Investor One', 'investor1@example.com', '9000000001'],
        ['Investor Two', 'investor2@example.com', '9000000002'],
        ['Investor Three', 'investor3@example.com', '9000000003']
    ];

    foreach ($investors as $inv) {
        $stmt = $conn->prepare("INSERT INTO investors (name, email, contact, password, status, kyc_status) VALUES (?, ?, ?, ?, 'active', 'pending')");
        $stmt->bind_param("ssss", $inv[0], $inv[1], $inv[2], $hashedPassword);
        $stmt->execute();
    }
    echo "3 Investors created (KYC Pending).<br>";

    // 2. Insert 4 Entrepreneurs (All Pending KYC)
    $entrepreneurs = [
        ['Entrepreneur One', 'ent1@example.com', '8000000001', 'Startup One'],
        ['Entrepreneur Two', 'ent2@example.com', '8000000002', 'Startup Two'],
        ['Entrepreneur Three', 'ent3@example.com', '8000000003', 'Startup Three'],
        ['Entrepreneur Four', 'ent4@example.com', '8000000004', 'Startup Four']
    ];

    foreach ($entrepreneurs as $ent) {
        $stmt = $conn->prepare("INSERT INTO entrepreneurs (name, email, contact, password, status, kyc_status, startup_name, total_shares, available_shares) VALUES (?, ?, ?, ?, 'active', 'pending', ?, 50000, 50000)");
        $stmt->bind_param("sssss", $ent[0], $ent[1], $ent[2], $hashedPassword, $ent[3]);
        $stmt->execute();
        $ent_id = $conn->insert_id;

        // Add dummy KYC details for this entrepreneur
        $kyc_stmt = $conn->prepare("INSERT INTO entrepreneur_kyc_details 
            (entrepreneur_id, full_name, dob, nationality, residential_address, city, state, country, postal_code, 
            legal_name, brand_name, business_type, industry, incorporation_date, startup_stage, business_address, 
            role, ownership_percentage, account_holder_name, bank_name, account_number, ifsc_code, status) 
            VALUES (?, ?, '1990-01-01', 'Indian', '123 Startup Street', 'Bangalore', 'Karnataka', 'India', '560001', 
            ?, ?, 'pvt_ltd', 'Technology', '2023-01-01', 'MVP', '456 Business Park', 'Founder', 75.50, ?, 'HDFC', '1234567890', 'HDFC001', 'pending')");
        
        $kyc_stmt->bind_param("issss", $ent_id, $ent[0], $ent[3], $ent[3], $ent[0]);
        $kyc_stmt->execute();
    }
    echo "4 Entrepreneurs created (KYC Pending with dummy details).<br>";

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<br><strong>Database Reset Successfully!</strong><br>";
    echo "All users password: <strong>Password123</strong>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>