<?php
// 1. Load Configuration and Database
require_once 'config.php';
require_once '../db.php'; 

if (isset($_GET['code'])) {
    try {
        // 2. Exchange the Authorization Code for an Access Token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if(isset($token['error'])){
            header("Location: ../login.php");
            exit();
        }

        $client->setAccessToken($token['access_token']);

        // 3. Get User Profile Data from Google
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email =  mysqli_real_escape_string($conn, $google_account_info->email);
        $name =  mysqli_real_escape_string($conn, $google_account_info->name);
        
        // 4. Check Database: Which table does this user belong to?
        $role = "";
        $userId = "";
        $userName = "";

        // A. Check Entrepreneurs
        $check = $conn->query("SELECT * FROM entrepreneurs WHERE email = '$email'");
        if($check->num_rows > 0){
            $row = $check->fetch_assoc();
            $role = "entrepreneur";
            $userId = $row['id'];
            $userName = $row['name'];
        }

        // B. Check Investors
        if(empty($role)){
             $check = $conn->query("SELECT * FROM investors WHERE email = '$email'");
             if($check->num_rows > 0){
                $row = $check->fetch_assoc();
                $role = "investor";
                $userId = $row['id'];
                $userName = $row['name'];
             }
        }

        // 5. Handle Login & Redirect based on Role
        if(!empty($role)){
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $userName;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;

            // --- DYNAMIC REDIRECTION ---
            if ($role == 'entrepreneur') {
                header("Location: ../dashboards/Entrepreneur-dashboard.php"); // Update with your actual path
            } elseif ($role == 'investor') {
                header("Location: ../dashboards/investor-dashboard.php");     // Update with your actual path
            } else {
                header("Location: ../dashboard.php"); // Fallback
            }
            exit();
        } else {
            // FAILURE
            echo "<script>
                alert('Error: No account found linked to this Google email ($email). Please sign up first.');
                window.location.href = '../register.php';
            </script>";
            exit();
        }

    } catch (Exception $e) {
        echo "Google Login Error: " . $e->getMessage();
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}
?>