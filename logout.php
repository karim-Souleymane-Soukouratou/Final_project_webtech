<?php
// Start the session to access session variables
session_start();

// 1. Unset all session variables
$_SESSION = array();

//  Destroy the session cookie parameters 
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect the user to landing page
header("Location: index.php");
exit;
?>