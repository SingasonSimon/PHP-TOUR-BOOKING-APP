<?php
// tour_app/admin/admin_check.php

if (session_status() == PHP_SESSION_NONE) {
    // Start session if it hasn't been already (e.g., if accessed directly)
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login page
    $_SESSION['error_message'] = "Please log in to access the admin area.";
    header("Location: ../login.php"); // Redirect to login page in parent directory
    exit;
}

// Check if user has the 'admin' role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Logged in, but not an admin
    $_SESSION['error_message'] = "You do not have permission to access the admin area.";
     // Redirect to the main site index page (or show an access denied message)
    header("Location: ../index.php");
    exit;
}

// If we reach here, the user is logged in and is an admin.
?>