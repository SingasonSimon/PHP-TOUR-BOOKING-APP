<?php
// tour_app/index.php
$pageTitle = "Available Tours"; // Set page title for header include
require_once 'includes/header.php'; // Includes session_start, doctype, head, nav bar, message display

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tour_booking_db');

// --- Initialize variables ---
$tours = [];
$dbError = null;
$searchTerm = ''; // Variable to hold the search term

// --- Check for Search Term ---
if (isset($_GET['search_term'])) {
    $searchTerm = trim(htmlspecialchars($_GET['search_term']));
}

// --- Establish Database Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    $dbError = "Database connection failed: " . $conn->connect_error;
    error_log("Index DB Connect Error: " . $conn->connect_error);
} else {
    // --- Build SQL Query with Search (including image_filename) ---
    $sql = "SELECT id, name, location_city, price, image_filename FROM tours"; // Added image_filename
    $params = []; // Array to hold parameters for binding
    $types = "";  // String for bind_param types

    if (!empty($searchTerm)) {
        // Add WHERE clause if search term exists
        $sql .= " WHERE name LIKE ? OR location_city LIKE ?";
        $likeTerm = "%" . $searchTerm . "%"; // Prepare term for LIKE search
        // Add params and types for prepared statement
        $params[] = $likeTerm;
        $params[] = $likeTerm;
        $types .= "ss"; // Two string parameters
    }
    $sql .= " ORDER BY name ASC";

    // --- Prepare and Execute Statement ---
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Bind parameters if they exist
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result(); // Get result object

        if ($result) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $tours[] = $row; // Add tour row to the array
                }
            }
            $result->free(); // Free result set
        } else {
             $dbError = "Error fetching tours: " . $stmt->error;
             error_log("Index Fetch Error: " . $stmt->error);
        }
        $stmt->close(); // Close the statement
    } else {
        $dbError = "Error preparing database query: " . $conn->error;
        error_log("Index Prepare Error: " . $conn->error);
    }
    $conn->close(); // Close the connection
}

?>

    <?php // Page specific content starts here ?>
    <div class="content-box list-page"> <?php // Apply main container class ?>

        <h1>Available Tours</h1>

        <?php // Search Form ?>
        <div class="search-container">
           <form action="index.php" method="GET" style="display: inline-block;">
                <input type="text" name="search_term" placeholder="Search by name or location..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit">Search</button>
                <?php if (!empty($searchTerm)): ?>
                    <a href="index.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php // Display DB Error if connection or query failed ?>
        <?php if ($dbError !== null): ?>
            <p class="db-error"><?php echo htmlspecialchars($dbError); ?></p>
        <?php endif; ?>

         <?php // Optional: Show search results feedback ?>
         <?php if (!empty($searchTerm) && $dbError === null): ?>
            <p style="text-align: center; margin-bottom: 15px; font-style: italic;">
                Showing results for "<?php echo htmlspecialchars($searchTerm); ?>"
            </p>
         <?php endif; ?>

        <?php // --- Tour List Display (with Images) --- ?>
        <ul class="tour-list">
            <?php if (!empty($tours)): ?>
                <?php foreach ($tours as $tour): ?>
                    <li class="tour-item"> <?php // Styled with Flexbox in style.css ?>
                        <?php // Image Container (Flex Item 1) ?>
                        <div class="tour-image-container">
                            <?php // Display image or placeholder ?>
                            <?php
                                $imagePath = "uploads/" . htmlspecialchars($tour['image_filename'] ?? '');
                                $placeholderPath = "images/safari-ke.jpeg"; // Define placeholder path
                                $finalImagePath = (!empty($tour['image_filename']) && file_exists($imagePath)) ? $imagePath : $placeholderPath;
                            ?>
                            <img src="<?php echo $finalImagePath; ?>"
                                 alt="<?php echo (!empty($tour['image_filename']) && file_exists($imagePath)) ? htmlspecialchars($tour['name']) : 'Placeholder'; ?>"
                                 class="tour-list-image <?php echo (!empty($tour['image_filename']) && file_exists($imagePath)) ? '' : 'placeholder'; ?>">
                        </div>

                        <?php // Details Container (Flex Item 2) ?>
                        <div class="tour-item-details">
                             <h2><a href="tour_details.php?id=<?php echo htmlspecialchars($tour['id']); ?>"><?php echo htmlspecialchars($tour['name']); ?></a></h2>
                             <p class="tour-location"> <?php // Styled via CSS ?>
                                 <?php echo htmlspecialchars($tour['location_city']); ?>
                             </p>
                             <p class="tour-price"> <?php // Styled via CSS ?>
                                 <span class="currency-symbol">KES</span> <?php echo htmlspecialchars(number_format($tour['price'], 2)); ?>
                             </p>
                             <?php // Can add description snippet here later ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php elseif ($dbError === null): // Only show 'no tours' if there wasn't a DB error ?>
                <li class="no-tours">
                    <?php if (!empty($searchTerm)): ?>
                        No tours found matching "<?php echo htmlspecialchars($searchTerm); ?>".
                    <?php else: ?>
                        No tours currently available.
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        </ul>
        <?php // --- END Tour List --- ?>

    </div> <?php // End content-box ?>

<?php
require_once 'includes/footer.php'; // Include the footer
?>