<?php
// tour_app/confirm_booking.php
$pageTitle = "Booking Confirmation";
// Start session early in case of errors before header include
if (session_status() == PHP_SESSION_NONE) { session_start(); }

define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');

$confirmationMessage = null;
$pageError = null;

// --- Validate POST data ---
if ($_SERVER["REQUEST_METHOD"] == "POST"
    && isset($_POST['booking_id']) && is_numeric($_POST['booking_id']) && $_POST['booking_id'] > 0
    && isset($_POST['paid_amount']) && is_numeric($_POST['paid_amount']))
{
    $bookingId = (int)$_POST['booking_id'];
    $paidAmount = (float)$_POST['paid_amount'];

    // --- Connect to DB and process confirmation ---
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) { $pageError = "Database connection failed: " . $conn->connect_error; }
    else {
        $conn->begin_transaction(); $updateSuccess = false;
        try { // Fetch Booking Details, Check Status/Amount, Check Capacity/Availability, Update Status... (logic remains same)
             $booking = null; $sql_get_booking = "SELECT tour_id, num_travelers, total_price, status, start_date FROM bookings WHERE id = ? FOR UPDATE"; $stmt_get = $conn->prepare($sql_get_booking); if (!$stmt_get) throw new Exception("DB Error B1"); $stmt_get->bind_param("i", $bookingId); $stmt_get->execute(); $result_get = $stmt_get->get_result();
             if ($booking = $result_get->fetch_assoc()) { if ($booking['status'] !== 'pending') throw new Exception("Booking not pending."); if (abs((float)$booking['total_price'] - $paidAmount) > 0.01) throw new Exception("Payment amount mismatch."); $selectedDate = $booking['start_date']; if ($selectedDate === null) throw new Exception("Booking date missing."); } else { throw new Exception("Booking ID not found."); } $stmt_get->close();
             $tourId = $booking['tour_id']; $numTravelers = $booking['num_travelers']; $capacity = null;
             $sql_tour = "SELECT t.capacity as default_capacity, td.capacity_override FROM tours t LEFT JOIN tour_dates td ON t.id = td.tour_id AND td.tour_date = ? WHERE t.id = ?"; $stmt_tour = $conn->prepare($sql_tour); if (!$stmt_tour) throw new Exception("DB Error T1"); $stmt_tour->bind_param("si", $selectedDate, $tourId); $stmt_tour->execute(); $result_tour = $stmt_tour->get_result();
             if ($tourData = $result_tour->fetch_assoc()) { $capacity = ($tourData['capacity_override'] !== null) ? (int)$tourData['capacity_override'] : (($tourData['default_capacity'] !== null) ? (int)$tourData['default_capacity'] : null); } else { throw new Exception("Associated tour not found."); } $stmt_tour->close();
             if ($capacity !== null) { $currentBookedSlots = 0; $sql_booked = "SELECT SUM(num_travelers) as total_booked FROM bookings WHERE tour_id = ? AND start_date = ? AND status = 'confirmed'"; $stmt_booked = $conn->prepare($sql_booked); if (!$stmt_booked) throw new Exception("DB Error B2"); $stmt_booked->bind_param("is", $tourId, $selectedDate); $stmt_booked->execute(); $result_booked = $stmt_booked->get_result(); if ($row_booked = $result_booked->fetch_assoc()) { $currentBookedSlots = (int)$row_booked['total_booked']; } $stmt_booked->close(); $availableSlots = $capacity - $currentBookedSlots; if ($availableSlots < $numTravelers) { throw new Exception("Sorry, the tour became fully booked for " . htmlspecialchars($selectedDate) . "."); } }
             $updateSql = "UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'"; $stmt_update = $conn->prepare($updateSql); if (!$stmt_update) throw new Exception("DB Error U1"); $stmt_update->bind_param("i", $bookingId); $stmt_update->execute();
             if ($stmt_update->affected_rows === 1) { $updateSuccess = true; $conn->commit(); $confirmationMessage = "<h2>Booking Confirmed! (ID: " . $bookingId . ")</h2><p>Your booking is confirmed for " . htmlspecialchars(date('D, M j, Y', strtotime($selectedDate))) . ".</p><p>Thank you!</p>"; }
             else { throw new Exception("Could not confirm booking (status changed?)."); } $stmt_update->close();
        } catch (Exception $e) { $conn->rollback(); $pageError = "Booking Confirmation Failed: " . $e->getMessage(); error_log("Confirm Fail ID $bookingId: " . $e->getMessage()); }
        $conn->close();
    }
} else { $pageError = "Invalid confirmation request or missing data."; }

// Include Header AFTER processing is done
require_once 'includes/header.php';
?>

    <div class="content-box confirmation-page">
        <h1>Booking Confirmation</h1>

        <?php // Display Confirmation or Error Message ?>
        <?php if ($confirmationMessage !== null): ?>
            <div class="confirmation-message"><?php echo $confirmationMessage; ?></div>
        <?php elseif ($pageError !== null): ?>
            <p class="error"><?php echo $pageError; // Already contains htmlspecialchars if needed from exception ?></p>
        <?php else: ?>
             <p class="error">An unknown error occurred during confirmation.</p>
        <?php endif; ?>

        <p style="text-align: center;"><a href="index.php">Return to Tour List</a></p>
     </div>

<?php
require_once 'includes/footer.php'; // Include the footer
?>