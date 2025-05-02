<?php
// tour_app/booking.php (Updated for Date Selection, Validation, Pending Save, Includes)

// Start session early for potential redirects/messages, header needs it too
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Check if user is logged in (Redirect before doing anything else) ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // Store intended URL
    $_SESSION['error_message'] = "Please log in or register to make a booking.";
    header("Location: login.php");
    exit;
}
$loggedInUserId = $_SESSION['user_id']; // Get logged-in user ID

// --- Database Config ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tour_booking_db');

// --- Initialize Variables ---
$tourName = "Selected Tour"; // Default name
$tourId = null;
$pageError = null;
$validationErrors = [];
$showForm = true; // Show form by default
$bookingSuccess = false; // Flag for successful pending booking insertion
$availableDates = []; // To store available dates for the dropdown/validation

// --- Determine Tour ID (from GET initially, or POST on submission/error) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tourId = isset($_POST['tour_id']) ? (int)$_POST['tour_id'] : null;
} else { // GET Request
    $tourId = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
}

// --- Validate Tour ID ---
if ($tourId === null || $tourId <= 0) {
    $pageError = "Invalid or missing Tour ID specified.";
    $showForm = false;
}

// --- If Tour ID is valid, fetch Tour Name and Available Dates (Needed before header for Title) ---
if ($pageError === null) {
    $conn_fetch = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn_fetch->connect_error) {
        $pageError = "Database connection failed. Cannot load booking details.";
        $showForm = false; // Cannot show form without tour details
        error_log("Booking Page DB Connect Error: " . $conn_fetch->connect_error);
    } else {
        // Fetch Tour Name first for the page title
        $sql_name = "SELECT name FROM tours WHERE id = ?";
        $stmt_name = $conn_fetch->prepare($sql_name);
        if ($stmt_name) {
            $stmt_name->bind_param("i", $tourId);
            $stmt_name->execute();
            $result_name = $stmt_name->get_result();
            if ($result_name && $row_name = $result_name->fetch_assoc()) {
                $tourName = $row_name['name'];
            } else {
                 // Only fatal if on initial GET request
                 if ($_SERVER["REQUEST_METHOD"] != "POST") {
                    $pageError = "Selected tour not found (ID: " . $tourId . ").";
                    $showForm = false;
                 } // On POST, validation errors will handle it later if ID is bad
            }
            $stmt_name->close();
        } else {
             $pageError = "Database query error (Tour Name Fetch): " . $conn_fetch->error; $showForm = false;
             error_log("Booking Page Fetch Name Prepare Error: " . $conn_fetch->error);
        }

        // Fetch Available Future Dates if no fatal error yet
        if ($pageError === null) {
            $sql_dates = "SELECT tour_date FROM tour_dates WHERE tour_id = ? AND tour_date >= CURDATE() ORDER BY tour_date ASC";
            $stmt_dates = $conn_fetch->prepare($sql_dates);
            if ($stmt_dates) {
                $stmt_dates->bind_param("i", $tourId);
                $stmt_dates->execute();
                $result_dates = $stmt_dates->get_result();
                while ($row_date = $result_dates->fetch_assoc()) {
                     $dateObj = date_create($row_date['tour_date']);
                     $availableDates[] = [
                         'value' => $row_date['tour_date'], // YYYY-MM-DD
                         'display' => date_format($dateObj, 'D, M j, Y') // e.g., Sat, May 10, 2025
                     ];
                }
                $stmt_dates->close();
                 if (empty($availableDates) && $_SERVER["REQUEST_METHOD"] != "POST") {
                     // If no dates found on initial load, inform user but maybe still show form disabled?
                     // Or set pageError = "No available dates..." and $showForm = false;
                     // Let's allow form display but dropdown will show message.
                 }
            } else {
                // Non-fatal error fetching dates? Log it but maybe allow proceeding
                 error_log("Error preparing available dates query: " . $conn_fetch->error);
            }
        }
        $conn_fetch->close();
    }
} // End initial fetch logic


// --- Handle POST request (Form Submission Processing) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $showForm = false; // Assume success initially

    // Get and Sanitize POST data
    // tourId already retrieved and validated above
    $userName = isset($_POST['user_name']) ? trim(htmlspecialchars($_POST['user_name'])) : '';
    $userEmail = isset($_POST['user_email']) ? trim(htmlspecialchars($_POST['user_email'])) : '';
    $userPhone = isset($_POST['user_phone']) ? trim(htmlspecialchars($_POST['user_phone'])) : '';
    $numTravelers = isset($_POST['num_travelers']) ? (int)$_POST['num_travelers'] : 0;
    $selectedDate = isset($_POST['selected_date']) ? trim($_POST['selected_date']) : '';
    $notes = isset($_POST['notes']) ? trim(htmlspecialchars($_POST['notes'])) : '';

    // Validation (including date check against $availableDates fetched earlier)
    if (empty($userName)) { $validationErrors[] = "Name is required."; }
    if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) { $validationErrors[] = "Valid email is required."; }
    if ($numTravelers <= 0) { $validationErrors[] = "Number of travelers must be at least 1."; }
    if (empty($selectedDate)) {
        $validationErrors[] = "Please select an available tour date.";
    } else {
        $isValidDate = false;
        $validDateValues = array_column($availableDates, 'value');
        if (in_array($selectedDate, $validDateValues)) {
            if (strtotime($selectedDate) >= strtotime(date('Y-m-d'))) { $isValidDate = true; }
            else { $validationErrors[] = "Selected date must be today or in the future."; }
        } else { $validationErrors[] = "The selected date is not available for this tour."; }
    }

    // --- Proceed only if validation passes ---
    if (empty($validationErrors) && $pageError === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            $pageError = "Database connection failed: " . $conn->connect_error;
        } else {
            $conn->begin_transaction(); $calculatedPrice = 0; $bookingId = null;
            try { // Get Tour Price, Capacity & Check Availability logic...
                $capacity = null; $tourPrice = 0; $availableSlots = null;
                $sql_tour = "SELECT t.price, t.capacity as default_capacity, td.capacity_override FROM tours t LEFT JOIN tour_dates td ON t.id = td.tour_id AND td.tour_date = ? WHERE t.id = ?";
                $stmt_tour = $conn->prepare($sql_tour); if (!$stmt_tour) throw new Exception("DB Error T"); $stmt_tour->bind_param("si", $selectedDate, $tourId); $stmt_tour->execute(); $result_tour = $stmt_tour->get_result();
                if ($tourData = $result_tour->fetch_assoc()) { $tourPrice = (float)$tourData['price']; $capacity = ($tourData['capacity_override'] !== null) ? (int)$tourData['capacity_override'] : (($tourData['default_capacity'] !== null) ? (int)$tourData['default_capacity'] : null); } else { throw new Exception("Tour data missing."); } $stmt_tour->close();
                if ($capacity !== null) { $currentBookedSlots = 0; $sql_bookings = "SELECT SUM(num_travelers) as total_booked FROM bookings WHERE tour_id = ? AND start_date = ? AND status = 'confirmed'"; $stmt_bookings = $conn->prepare($sql_bookings); if (!$stmt_bookings) throw new Exception("DB Error B"); $stmt_bookings->bind_param("is", $tourId, $selectedDate); $stmt_bookings->execute(); $result_bookings = $stmt_bookings->get_result(); if ($row_bookings = $result_bookings->fetch_assoc()) { $currentBookedSlots = (int)$row_bookings['total_booked']; } $stmt_bookings->close(); $availableSlots = $capacity - $currentBookedSlots; $availableSlots = max(0, $availableSlots); if ($availableSlots < $numTravelers) { throw new Exception("Sorry, only " . $availableSlots . " slot(s) remaining for " . htmlspecialchars($selectedDate) . "."); } }
                $calculatedPrice = $tourPrice * $numTravelers;
                // Insert Pending Booking (including user_id)
                $insertSql = "INSERT INTO bookings (tour_id, user_id, user_name, user_email, user_phone, num_travelers, start_date, notes, status, total_price, booking_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
                $stmt_insert = $conn->prepare($insertSql); if (!$stmt_insert) throw new Exception("DB Error I1");
                $stmt_insert->bind_param("iisssissd", $tourId, $loggedInUserId, $userName, $userEmail, $userPhone, $numTravelers, $selectedDate, $notes, $calculatedPrice);
                if (!$stmt_insert->execute()) { throw new Exception("Error saving booking: " . $stmt_insert->error); }
                $bookingId = $conn->insert_id; $stmt_insert->close(); $conn->commit(); $bookingSuccess = true;
            } catch (Exception $e) { $conn->rollback(); $pageError = $e->getMessage(); error_log("Booking Txn Failed: " . $e->getMessage()); }
            $conn->close();
            // Redirect on Success
            if ($bookingSuccess && $bookingId !== null) { header("Location: mock_payment.php?booking_id=" . $bookingId . "&amount=" . $calculatedPrice); exit; }
        } // End DB connection check
    } // End validation check

    // If validation failed OR booking failed after validation, show form again
    if (!empty($validationErrors) || !$bookingSuccess) { $showForm = true; }

} // End POST handling

// Set page title *before* including header
$pageTitle = "Book Tour: " . htmlspecialchars($tourName);
require_once 'includes/header.php'; // Include Header AFTER setting $pageTitle
?>

    <div class="content-box booking-page"> <?php // Apply main container class ?>
        <?php // Display fatal page errors first (e.g., invalid ID, DB connection)
        if ($pageError !== null && !$showForm): ?>
            <p class="error"><?php echo htmlspecialchars($pageError); ?></p>
            <p style="text-align: center;"><a href="index.php">Back to Tour List</a></p>

        <?php // Display form if needed
        elseif ($showForm && $tourId !== null): ?>
            <form action="booking.php?tour_id=<?php echo htmlspecialchars($tourId); ?>" method="POST" class="booking-form">
                <h2>Request Booking for: <?php echo htmlspecialchars($tourName); ?></h2>

                <?php // Display pageError here if it occurred during POST but we still show form (e.g., availability error)
                if ($pageError !== null): ?><p class="error"><?php echo htmlspecialchars($pageError); ?></p><?php endif; ?>
                <?php // Display validation errors if they exist
                if (!empty($validationErrors)): ?>
                    <div class="validation-errors">
                        <p><strong>Please fix the following issues:</strong></p>
                        <ul><?php foreach ($validationErrors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="tour_id" value="<?php echo htmlspecialchars($tourId); ?>">

                <div class="form-group"> <label for="user_name">Your Name:</label> <input type="text" id="user_name" name="user_name" value="<?php echo isset($_POST['user_name']) ? htmlspecialchars($_POST['user_name']) : ''; ?>" required></div>
                <div class="form-group"> <label for="user_email">Your Email:</label> <input type="email" id="user_email" name="user_email" value="<?php echo isset($_POST['user_email']) ? htmlspecialchars($_POST['user_email']) : ''; ?>" required></div>
                <div class="form-group"> <label for="user_phone">Phone Number:</label> <input type="tel" id="user_phone" name="user_phone" value="<?php echo isset($_POST['user_phone']) ? htmlspecialchars($_POST['user_phone']) : ''; ?>"></div>
                <div class="form-group"> <label for="num_travelers">Number of Travelers:</label> <input type="number" id="num_travelers" name="num_travelers" min="1" value="<?php echo isset($_POST['num_travelers']) ? htmlspecialchars($_POST['num_travelers']) : '1'; ?>" required></div>

                <?php // --- Date Selection Dropdown --- ?>
                <div class="form-group">
                    <label for="selected_date">Select Available Date:</label>
                    <select id="selected_date" name="selected_date" required>
                        <option value="">-- Please Select a Date --</option>
                        <?php if (!empty($availableDates)): ?>
                            <?php foreach ($availableDates as $dateInfo): ?>
                                <option value="<?php echo htmlspecialchars($dateInfo['value']); ?>" <?php echo (isset($_POST['selected_date']) && $_POST['selected_date'] == $dateInfo['value']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dateInfo['display']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: // No dates fetched, maybe show error differently ?>
                             <option value="" disabled><?php echo ($pageError && strpos($pageError, 'dates') !== false) ? 'Error loading dates' : 'No upcoming dates available'; ?></option>
                        <?php endif; ?>
                    </select>
                     <?php if (empty($availableDates) && !$pageError): ?>
                        <small>There are no upcoming dates scheduled for this tour.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group"><label for="notes">Notes / Special Requests:</label><textarea id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea></div>
                <div class="form-group"><button type="submit" class="submit-button">Check Availability & Proceed</button></div>
                <p style="text-align: center; font-size: 0.9em; color: #6c757d;">Submitting will check availability for the selected date.</p>
            </form>
            <p style="text-align: center; margin-top: 20px;"><a href="tour_details.php?id=<?php echo htmlspecialchars($tourId); ?>">&laquo; Back to Tour Details</a> | <a href="index.php">View All Tours</a></p>
        <?php endif; ?>
    </div>

<?php
require_once 'includes/footer.php'; // Include the footer
?>