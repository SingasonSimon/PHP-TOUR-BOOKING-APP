<?php
// tour_app/includes/header.php
// Include this at the top of all user-facing pages

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Start session if not already started
}

// --- Check for session messages (e.g., errors, success) ---
$sessionMessage = '';
$messageType = 'info'; // Default type

if (isset($_SESSION['error_message'])) {
    $sessionMessage = $_SESSION['error_message'];
    $messageType = 'error';
    unset($_SESSION['error_message']); // Clear message
} elseif (isset($_SESSION['success_message'])) {
    $sessionMessage = $_SESSION['success_message'];
    $messageType = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_GET['logged_out'])) { // Message from logout.php redirect
    $sessionMessage = "You have been logged out successfully.";
    $messageType = 'info';
}
// Add more message types if needed

// --- Default Page Title ---
$currentPageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) : "Tour Booking";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentPageTitle; ?></title>
    <link rel="stylesheet" href="style.css"> <?php // Link main stylesheet ?>

    <?php // Conditionally include Leaflet CSS (only needed on tour_details.php)
    if (isset($loadLeaflet) && $loadLeaflet === true): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <?php endif; ?>

    <?php // Conditionally include page-specific inline styles if needed ?>
    <?php if (isset($inlineStyles) && !empty($inlineStyles)): ?>
        <style><?php echo $inlineStyles; ?></style>
    <?php endif; ?>
</head>
<body>

    <?php // --- User Status Bar (Navbar) --- ?>
    <div class="user-status-bar">
        <div> <?php // Left side ?>
             <a href="index.php" style="font-weight: bold; color: #fff; margin-left: 0;">Tour Booking App</a>
        </div>
        <div> <?php // Right side ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                 <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                <?php // Link to Admin Area only if user role is admin ?>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin/index.php" class="admin-link">Admin Area</a>
                <?php endif; ?>
                <a href="my_bookings.php">My Bookings</a> <?php // Future link ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                 <span>Welcome, Guest!</span>
                 <a href="login.php">Login</a>
                 <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
    <?php // --- END User Status Bar --- ?>

    <?php // --- Display Session Messages Below Nav Bar ---
    if (!empty($sessionMessage)): ?>
        <div class="session-message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($sessionMessage); ?>
        </div>
    <?php endif;
    // --- END Session Message Display --- ?>

    <?php // Specific page content starts after this include ?>