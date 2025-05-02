<?php
// tour_app/mock_payment.php
$pageTitle = "Simulate Payment";
require_once 'includes/header.php'; // Use header include

// Parameter validation and fetching booking details... (logic remains the same)
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id']) || $_GET['booking_id'] <= 0 || !isset($_GET['amount']) || !is_numeric($_GET['amount'])) { $pageError = "Invalid payment request."; } else { $bookingId = (int)$_GET['booking_id']; $amountDue = (float)$_GET['amount']; $pageError = null; /* Fetch $userName, $tourName */ define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db'); $tourName = 'Selected Tour'; $userName = 'Customer'; $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if (!$conn->connect_error) { $sql = "SELECT b.user_name, t.name as tour_name FROM bookings b JOIN tours t ON b.tour_id = t.id WHERE b.id = ? AND b.status = 'pending'"; $stmt = $conn->prepare($sql); if ($stmt) { $stmt->bind_param("i", $bookingId); $stmt->execute(); $result = $stmt->get_result(); if ($data = $result->fetch_assoc()) { $tourName = $data['tour_name']; $userName = $data['user_name']; } else { $pageError = "Booking not found or not pending."; } $stmt->close(); } else { $pageError = "DB Error."; } $conn->close(); } else {$pageError = "DB Error.";} }
?>

    <div class="content-box payment-page">
        <h2>Payment Simulation</h2>

        <?php if ($pageError !== null): ?>
            <p class="error"><?php echo htmlspecialchars($pageError); ?></p>
            <p style="text-align: center;"><a href="index.php">Return to Tours</a></p>
        <?php else: ?>
             <?php // Display payment details and form (remains the same) ?>
             <p>Hello, <?php echo htmlspecialchars($userName); ?>!</p>
             <p>Please confirm payment for: <strong><?php echo htmlspecialchars($tourName); ?></strong></p>
             <p>Amount Due:</p>
             <div class="amount">KES <?php echo htmlspecialchars(number_format($amountDue, 2)); ?></div>
             <form action="confirm_booking.php" method="POST">
                 <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($bookingId); ?>">
                 <input type="hidden" name="paid_amount" value="<?php echo htmlspecialchars($amountDue); ?>">
                 <button type="submit" class="payment-button">Simulate Successful Payment</button>
             </form>
             <p class="disclaimer">(This is not a real payment gateway.)</p>
             <p class="cancel-link"><a href="index.php">Cancel and Return to Tours</a></p>
        <?php endif; ?>
    </div>

<?php
require_once 'includes/footer.php'; // Include the footer
?>