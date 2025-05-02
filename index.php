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
    $sql = "SELECT id, name, location_city, price, image_filename FROM tours"; // Select image_filename
    $params = []; // Array to hold parameters for binding
    $types = "";  // String for bind_param types

    if (!empty($searchTerm)) {
        $sql .= " WHERE name LIKE ? OR location_city LIKE ?";
        $likeTerm = "%" . $searchTerm . "%";
        $params[] = &$likeTerm; // Pass by reference needed before PHP 8.1
        $params[] = &$likeTerm;
        $types .= "ss";
    }
    $sql .= " ORDER BY name ASC";

    // --- Prepare and Execute Statement ---
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $tours[] = $row;
                }
            }
            $result->free();
        } else {
             $dbError = "Error fetching tours: " . $stmt->error;
             error_log("Index Fetch Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $dbError = "Error preparing database query: " . $conn->error;
        error_log("Index Prepare Error: " . $conn->error);
    }
    $conn->close();
}

?>

    <?php // Page specific content starts here ?>
    <div class="content-box list-page"> <?php // Apply main container class ?>

        <h1>Available Tours</h1>

        <?php // Search Form (remains the same) ?>
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
                            <?php
                                // --- CORRECTED IMAGE LOGIC ---
                                $imageFilename = $tour['image_filename'] ?? null;
                                $imagePath = null;
                                $placeholderPath = "uploads/placeholder.png"; // CORRECT placeholder path

                                // Check if a specific image exists for the tour
                                if (!empty($imageFilename)) {
                                    $potentialPath = "uploads/" . $imageFilename; // CORRECT folder
                                    if (file_exists($potentialPath)) {
                                        $imagePath = $potentialPath;
                                    }
                                }

                                // Use the tour image if found, otherwise use the placeholder
                                $finalImagePath = $imagePath ?: $placeholderPath;
                                // Check if we are actually using the placeholder path
                                $isPlaceholder = ($finalImagePath === $placeholderPath);

                            ?>
                            <img src="<?php echo htmlspecialchars($finalImagePath); ?>"
                                 alt="<?php echo $isPlaceholder ? 'Placeholder' : htmlspecialchars($tour['name']); ?>"
                                 class="tour-list-image <?php echo $isPlaceholder ? 'placeholder' : ''; ?>"
                                 <?php // Add basic error handling for broken images ?>
                                 onerror="this.onerror=null; this.src='<?php echo $placeholderPath; ?>'; this.classList.add('placeholder'); this.alt='Placeholder';" >
                        </div>

                        <?php // Details Container (Flex Item 2) ?>
                        <div class="tour-item-details">
                             <h2><a href="tour_details.php?id=<?php echo htmlspecialchars($tour['id']); ?>"><?php echo htmlspecialchars($tour['name']); ?></a></h2>
                             <p class="tour-location">
                                 <?php echo htmlspecialchars($tour['location_city']); ?>
                             </p>
                             <p class="tour-price">
                                 <span class="currency-symbol">KES</span> <?php echo htmlspecialchars(number_format($tour['price'], 2)); ?>
                             </p>
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