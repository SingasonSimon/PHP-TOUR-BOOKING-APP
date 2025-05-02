<?php
// tour_app/admin/view_bookings.php
$pageTitle = "View Bookings"; // Set page title
require_once 'includes/admin_header.php'; // Includes check, head, nav etc.

define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');

$bookings = [];
$dbError = null;

// --- DB Connection & Fetch Logic ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { $dbError = "Connection failed: " . $conn->connect_error; error_log("View Bookings DB Connect Error: " . $conn->connect_error); }
else {
    $sql = "SELECT b.id AS booking_id, t.name AS tour_name, b.user_name, b.user_email, b.user_phone, b.num_travelers, b.start_date, b.notes, b.total_price, b.status, b.booking_time
            FROM bookings b JOIN tours t ON b.tour_id = t.id
            ORDER BY b.booking_time DESC";
    $result = $conn->query($sql);
    if ($result) { if ($result->num_rows > 0) { while ($row = $result->fetch_assoc()) { $bookings[] = $row; } } $result->free(); }
    else { $dbError = "Error fetching bookings: " . $conn->error; error_log("View Bookings Fetch Error: " . $conn->error); }
    $conn->close();
}
?>
    <?php // Main content starts here ?>
    <div class="content-box admin-page admin-container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php // Admin nav bar is in header include ?>
        <?php // Session message display is in header include ?>

        <?php if ($dbError): ?>
            <p class="error"><?php echo htmlspecialchars($dbError); ?></p>
        <?php endif; ?>

        <h2>All Tour Bookings</h2>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Booking ID</th> <th>Tour Name</th> <th>Customer Name</th> <th>Email</th>
                        <th>Phone</th> <th>Travelers</th> <th>Date</th> <th>Price (KES)</th>
                        <th>Status</th> <th>Booked On</th> <th>Notes</th>
                        <?php //<th>Actions</th> ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['tour_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($booking['user_phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($booking['num_travelers']); ?></td>
                                <td><?php echo htmlspecialchars($booking['start_date'] ? date('M j, Y', strtotime($booking['start_date'])) : 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(number_format($booking['total_price'], 2)); ?></td>
                                <td><span class="status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>"><?php echo htmlspecialchars(ucfirst($booking['status'])); ?></span></td>
                                <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($booking['booking_time']))); ?></td>
                                <td class="notes-col"><?php echo nl2br(htmlspecialchars($booking['notes'] ?? '')); ?></td>
                                <?php /* Add Actions like Cancel later
                                <td>
                                    <a href="cancel_booking.php?id=<?php echo $booking['booking_id']; ?>" class="action-cancel">Cancel</a>
                                </td>
                                */ ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" style="text-align: center;">No bookings found yet.</td></tr> <?php // Updated colspan ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div> <?php // End content-box ?>

<?php
require_once 'includes/admin_footer.php'; // Include the footer
?>