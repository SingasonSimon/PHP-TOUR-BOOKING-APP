<?php
// tour_app/admin/includes/admin_header.php
// This file includes the session check, doctype, head, and admin navigation bar.

// Ensure admin check runs first (includes session_start)
require_once __DIR__ . '/../admin_check.php'; // Use __DIR__ for reliable path

// --- Display session message if set ---
// (Moved message display here for consistency across all admin pages)
$adminMessage = '';
$messageType = 'success'; // Default type
if (isset($_SESSION['admin_message'])) {
    $adminMessage = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
    if (isset($_SESSION['admin_message_type'])) {
        $messageType = $_SESSION['admin_message_type'];
        unset($_SESSION['admin_message_type']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php // Dynamically set title based on the calling page (optional) ?>
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Area'; ?> - Tour Booking</title>
    <link rel="stylesheet" href="../style.css"> <?php // Link CSS from parent directory ?>
    <style>
        /* Minor override if needed - keep styles mainly in style.css */
        /* Ensure admin container has enough top margin if navbar is fixed later */
         .admin-page .content-box { margin-top: 20px; }
         /* Ensure user status bar links have enough contrast if needed */
         .user-status-bar.admin-nav-bar a { /* Target admin bar links specifically */
             /* Add specific styles if needed */
             margin-left: 12px;
             margin-right: 12px;
             padding: 5px 0;
         }
         .user-status-bar.admin-nav-bar .admin-logout { margin-left: 30px;}
    </style>
</head>
<body>

    <?php // --- Admin Navigation Bar (using user-status-bar style) --- ?>
    <div class="user-status-bar admin-nav-bar"> <?php // Reuse class, add specific if needed ?>
        <div> <?php // Left side - Admin Links ?>
            <span style="font-weight:bold; margin-right: 15px;">Admin Menu:</span>
            <a href="index.php">Manage Tours</a>
            <a href="add_tour.php">Add Tour</a>
            <a href="view_bookings.php">View Bookings</a>
            <a href="../index.php" target="_blank">View Site</a> <?php // Open main site in new tab ?>
        </div>
        <div> <?php // Right side - User Info & Logout ?>
            <span>Logged in as: <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></strong></span>
            <a href="../logout.php" class="admin-logout">Logout</a>
        </div>
    </div>
     <?php // --- END Admin Navigation Bar --- ?>

     <?php // --- Display Admin Message (moved from individual pages) ---
    if (!empty($adminMessage)): ?>
        <div class="admin-message <?php echo $messageType === 'error' ? 'error' : 'success'; ?>" style="max-width: 1100px; margin-left: auto; margin-right: auto;">
            <?php echo htmlspecialchars($adminMessage); ?>
        </div>
    <?php endif;
    // --- END Admin Message Display --- ?>

    <?php // The rest of the specific page content will start after this include ?>