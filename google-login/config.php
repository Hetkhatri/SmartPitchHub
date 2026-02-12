<?php
// Load Composer's autoloader (Go up one level to find vendor folder)
require_once '../vendor/autoload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize Google Client
$client = new Google_Client();

// ---------------------------------------------------------
// YOUR GOOGLE KEYS (Inserted)
// ---------------------------------------------------------
$clientID = '1055257647436-4ti9vtjifch1ic16dkdkep2bvhse75rb.apps.googleusercontent.com';
$clientSecret = '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';

// CRITICAL: This must match EXACTLY what you put in Google Console
// Ensure your port (8080) and folder name are correct.
$redirectUri = 'http://localhost:8080/SmartPitchHub-1/google-login/callback.php'; 
// ---------------------------------------------------------

$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

?>