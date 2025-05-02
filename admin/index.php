<?php
// tour_app/admin/index.php
$pageTitle = "Manage Tours"; // Set page title for header
require_once 'includes/admin_header.php'; // Includes check, head, nav etc.

// --- Database Config ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tour_booking_db');

$tours = [];
$dbError = null;

// --- DB Connection & Fetch Logic ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    $dbError = "Connection failed: " . $conn->connect_error;
    error_log("Admin Index DB Connect Error: " . $conn->connect_error);
} else {
    $sql = "SELECT id, name, location_city, price, capacity, latitude, longitude FROM tours ORDER BY id ASC";
    $result = $conn->query($sql);
    if ($result) {
        if ($result->num_rows > 0) { while ($row = $result->fetch_assoc()) { $tours[] = $row; } }
        $result->free();
    } else {
        $dbError = "Error fetching tours: " . $conn->error;
        error_log("Admin Index Fetch Error: " . $conn->error);
    }
    $conn->close();
}
?>
    <?php // Main content starts here ?>
    <div class="content-box admin-page admin-container">

        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php // Admin nav bar is now in header include ?>
        <?php // Session message display is now in header include ?>

        <?php if ($dbError): ?>
            <p class="error"><?php echo htmlspecialchars($dbError); ?></p>
        <?php endif; ?>

        <h2>Existing Tours</h2>
        <div style="overflow-x:auto;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Location</th><th>Price (KES)</th>
                        <th>Capacity</th><th>Lat</th><th>Lon</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tours)): ?>
                        <?php foreach ($tours as $tour): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tour['id']); ?></td>
                                <td><?php echo htmlspecialchars($tour['name']); ?></td>
                                <td><?php echo htmlspecialchars($tour['location_city']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($tour['price'], 2)); ?></td>
                                <td><?php echo ($tour['capacity'] === null) ? '<i>N/A</i>' : htmlspecialchars($tour['capacity']); ?></td>
                                <td><?php echo ($tour['latitude'] === null) ? '<i>N/A</i>' : htmlspecialchars($tour['latitude']); ?></td>
                                <td><?php echo ($tour['longitude'] === null) ? '<i>N/A</i>' : htmlspecialchars($tour['longitude']); ?></td>
                                <td style="white-space: nowrap;">
                                    <a href="edit_tour.php?id=<?php echo $tour['id']; ?>" class="action-edit">Edit</a>
                                    <a href="delete_tour.php?id=<?php echo $tour['id']; ?>" class="action-delete" onclick="return confirm('Are you SURE you want to delete the tour \'<?php echo addslashes(htmlspecialchars($tour['name'])); ?>\'? This action cannot be undone and will delete associated bookings and dates!');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align: center;">No tours found. <a href="add_tour.php">Add one now!</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div> <?php // End responsive wrapper ?>
    </div> <?php // End content-box ?>

<?php
require_once 'includes/admin_footer.php'; // Include the footer
?>