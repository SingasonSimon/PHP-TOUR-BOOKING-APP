<?php
// tour_app/my_bookings.php
$pageTitle = "My Bookings";
require_once 'includes/header.php'; // Includes session_start, head, nav bar etc.

// --- Ensure user is logged in ---
// (Although header might handle redirects, explicit check is good)
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in (header should handle message)
    header("Location: login.php");
    exit;
}
$userId = $_SESSION['user_id']; // Get logged-in user's ID

// --- Database Config ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tour_booking_db');

$bookings = [];
$dbError = null;

// --- Establish Database Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    $dbError = "Connection failed: " . $conn->connect_error;
    error_log("My Bookings DB Connect Error: " . $conn->connect_error);
} else {
    // --- Fetch Bookings for the Logged-in User ---
    $sql = "SELECT
                b.id AS booking_id,
                t.name AS tour_name,
                t.id AS tour_id,
                b.num_travelers,
                b.start_date,
                b.total_price,
                b.status,
                b.booking_time
            FROM bookings b
            JOIN tours t ON b.tour_id = t.id
            WHERE b.user_id = ?  -- Filter by the logged-in user's ID
            ORDER BY b.start_date DESC, b.booking_time DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId); // Bind the user ID
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
            $result->free();
        } else {
            $dbError = "Error fetching your bookings: " . $stmt->error;
            error_log("My Bookings Fetch Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $dbError = "Error preparing database query: " . $conn->error;
        error_log("My Bookings Prepare Error: " . $conn->error);
    }
    $conn->close();
}
?>

    <?php // Main content starts here ?>
    <div class="content-box my-bookings-page"> <?php // Add specific class if needed ?>
        <h1>My Bookings</h1>

        <?php if ($dbError): ?>
            <p class="error"><?php echo htmlspecialchars($dbError); ?></p>
        <?php endif; ?>

        <?php if (empty($bookings) && !$dbError): ?>
            <p style="text-align: center; margin-top: 20px;">You haven't booked any tours yet.</p>
            <p style="text-align: center;"><a href="index.php">Browse Available Tours</a></p>
        <?php elseif (!empty($bookings)): ?>
            <div style="overflow-x:auto;"> <?php // Responsive wrapper ?>
                <table class="admin-table"> <?php // Re-use admin table style for now ?>
                    <thead>
                        <tr>
                            <th>Tour Name</th>
                            <th>Travelers</th>
                            <th>Tour Date</th>
                            <th>Total Price (KES)</th>
                            <th>Status</th>
                            <th>Booked On</th>
                            <?php //<th>Actions</th> ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <?php // Link tour name back to details page ?>
                                <td><a href="tour_details.php?id=<?php echo htmlspecialchars($booking['tour_id']); ?>"><?php echo htmlspecialchars($booking['tour_name']); ?></a></td>
                                <td><?php echo htmlspecialchars($booking['num_travelers']); ?></td>
                                <td><?php echo htmlspecialchars($booking['start_date'] ? date('D, M j, Y', strtotime($booking['start_date'])) : 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(number_format($booking['total_price'], 2)); ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($booking['booking_time']))); ?></td>
                                <?php /* Add cancel button maybe later?
                                <td>
                                    <?php if ($booking['status'] === 'confirmed' && strtotime($booking['start_date']) > time()): // Example: Can cancel future confirmed bookings ?>
                                        <a href="cancel_my_booking.php?id=<?php echo $booking['booking_id']; ?>" class="action-cancel" onclick="return confirm('Are you sure?')">Cancel</a>
                                    <?php endif; ?>
                                </td>
                                */ ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div> <?php // End content-box ?>

<?php
require_once 'includes/footer.php'; // Include the footer
?>