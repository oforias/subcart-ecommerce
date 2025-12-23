<?php

/**
 * Customer Logout Script
 * Destroys session variables and redirects to index.php
 * Requirements: 7.2, 7.3
 */

// Start session to access session variables
session_start();

// Destroy all session data
session_destroy();

// Unset all $_SESSION variables for extra security
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to index.php with appropriate headers
header('Location: ../index.php');
exit();