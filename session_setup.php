<?php
// session_manager.php
session_start();

function isLoggedIn() {
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

function redirectIfNotLoggedIn($redirectPage = "login.php") {
    if (!isLoggedIn()) {
        header("location: " . $redirectPage);
        exit;
    }
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        // Redirect based on user role
        if (isset($_SESSION["role"])) {
            if ($_SESSION["role"] === "entrepreneur") {
                header("location: Entrepreneur-dashboard.php");
            } else if ($_SESSION["role"] === "investor") {
                header("location: dashboards/investor-dashboard.php");
            }
        }
        exit;
    }
}
?>