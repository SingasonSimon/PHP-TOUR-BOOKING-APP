<?php
// tour_app/logout.php
session_start(); // Access the session

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie (optional but good practice)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page with a logged-out message
header("Location: login.php?logged_out=true");
exit;
?>