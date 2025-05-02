<?php
// tour_app/admin/delete_tour.php
// Include header primarily for admin check and session start
require_once 'includes/admin_header.php'; // THIS WILL OUTPUT HTML - NOT IDEAL FOR PURE ACTION SCRIPT
// Better: Just require admin_check.php directly if no HTML needed
// require_once 'admin_check.php';

define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');

$tourId = null;
$errorMessage = null;
$successMessage = null;

// 1. Get Tour ID from URL and Validate
if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
    $tourId = (int)$_GET['id'];
} else {
    $_SESSION['admin_message'] = "Invalid Tour ID specified for deletion.";
    $_SESSION['admin_message_type'] = 'error';
    header("Location: index.php"); exit;
}

// 2. Attempt to Delete the Tour
if ($tourId) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $_SESSION['admin_message'] = "DB connection failed: " . $conn->connect_error; $_SESSION['admin_message_type'] = 'error';
        error_log("Delete Tour DB Connect Error: " . $conn->connect_error);
    } else {
        $sql = "DELETE FROM tours WHERE id = ?"; $stmt = $conn->prepare($sql);
        if ($stmt) { $stmt->bind_param("i", $tourId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 1) { $successMessage = "Tour (ID: " . $tourId . ") and associated data deleted!"; }
                else { $errorMessage = "Tour (ID: " . $tourId . ") not found or already deleted."; }
            } else { $errorMessage = "Error executing delete: " . $stmt->error; error_log("Delete Tour Execute Error: " . $stmt->error); }
            $stmt->close();
        } else { $errorMessage = "Error preparing delete statement: " . $conn->error; error_log("Delete Tour Prepare Error: " . $conn->error); }
        $conn->close();
    }
    // Set session message
    if ($successMessage) { $_SESSION['admin_message'] = $successMessage; $_SESSION['admin_message_type'] = 'success'; }
    elseif ($errorMessage) { $_SESSION['admin_message'] = $errorMessage; $_SESSION['admin_message_type'] = 'error'; }
}

// Redirect back to the admin list page
header("Location: index.php");
exit;
?>
<?php
// NOTE: No HTML output needed or desired from delete script after redirect.
// Including admin_footer.php is unnecessary here.
?>