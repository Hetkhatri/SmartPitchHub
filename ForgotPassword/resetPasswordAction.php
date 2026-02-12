<?php
session_start();
require_once '../db.php'; // Connect to database

if (isset($_POST['reset_password'])) {
    $token = $_POST['token']; // Get token from hidden input
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $currentDate = date("Y-m-d H:i:s");

    // 1. Check if passwords match
    if ($new_pass !== $confirm_pass) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset-password.php?token=" . $token);
        exit();
    }

    // 2. Validate Token Again (Security Check)
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expiry > ?");
    $stmt->bind_param("ss", $token, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $email_from_token = $row['email'];

        // 3. Find User Table (Entrepreneur, Investor, or Admin)
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        $table_to_update = "";

        // Check Entrepreneurs
        $check = $conn->query("SELECT id FROM entrepreneurs WHERE email = '$email_from_token'");
        if($check->num_rows > 0) $table_to_update = "entrepreneurs";
        // Check Investors
        elseif($conn->query("SELECT id FROM investors WHERE email = '$email_from_token'")->num_rows > 0) {
            $table_to_update = "investors";
        }
        // Check Admins
        elseif($conn->query("SELECT id FROM admins WHERE email = '$email_from_token'")->num_rows > 0) {
            $table_to_update = "admins";
        }

        // 4. Update Password
        if ($table_to_update) {
            $update = $conn->prepare("UPDATE $table_to_update SET password = ? WHERE email = ?");
            $update->bind_param("ss", $hashed_password, $email_from_token);
            
            if ($update->execute()) {
                // Delete the used token so it can't be used again
                $conn->query("DELETE FROM password_resets WHERE email = '$email_from_token'");
                
                // Redirect with Success Flag
                $_SESSION['msg'] = "Password updated successfully!";
                header("Location: reset-password.php?success=1");
                exit();
            } else {
                $_SESSION['error'] = "Failed to update password in database.";
            }
        } else {
            $_SESSION['error'] = "User account not found.";
        }
    } else {
        $_SESSION['error'] = "This link is invalid or has expired.";
    }

    // If we got here, something failed. Redirect back with token.
    header("Location: reset-password.php?token=" . $token);
    exit();
} else {
    // If accessed directly without POST
    header("Location: ../login.php");
    exit();
}
?>