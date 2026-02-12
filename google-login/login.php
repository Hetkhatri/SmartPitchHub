<?php
require_once 'config.php';

// FORCE Google to ask for account selection every time
// This fixes the issue where users are automatically logged back in after logging out
$client->setPrompt('select_account');

// Generate the Google Login URL
$login_url = $client->createAuthUrl();

// Redirect user to Google
header('Location: ' . $login_url);
exit();
?>