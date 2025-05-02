<?php
// tour_app/admin/add_tour.php
$pageTitle = "Add New Tour";
require_once 'includes/admin_header.php'; // Includes admin check, head, nav etc.

// --- DB Config ---
define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');

$errors = []; // Array to hold validation errors

// --- Fetch Categories for Dropdown ---
$categories = [];
$conn_cat = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn_cat->connect_error) {
    $sql_cat = "SELECT id, name FROM categories ORDER BY name ASC";
    $result_cat = $conn_cat->query($sql_cat);
    if ($result_cat) { while ($row_cat = $result_cat->fetch_assoc()) { $categories[] = $row_cat; } $result_cat->free(); }
    else { error_log("Fetch Categories Error: " . $conn_cat->error); $errors[] = "Could not load categories."; }
    $conn_cat->close();
} else { $errors[] = "Category DB connection failed.";} // Add error if connection fails

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Get and Sanitize Data
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $location_city = trim(htmlspecialchars($_POST['location_city'] ?? ''));
    $description = trim(htmlspecialchars($_POST['description'] ?? ''));
    $price_str = trim($_POST['price'] ?? '');
    $categoryId = isset($_POST['category_id']) && !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $capacity_str = trim($_POST['capacity'] ?? '');
    $latitude_str = trim($_POST['latitude'] ?? '');
    $longitude_str = trim($_POST['longitude'] ?? '');
    // ** Get Initial Date **
    $initialDate = trim($_POST['initial_date'] ?? '');

    // Initialize variables for DB
    $price = null; $capacity = null; $latitude = null; $longitude = null;
    $validatedInitialDate = null; // Store validated date or null

    // 2. Validate Data
    if (empty($name)) { $errors[] = "Tour Name is required."; }
    if (empty($location_city)) { $errors[] = "Location City is required."; }
    if ($price_str === '') { $errors[] = "Price is required."; } elseif (!is_numeric($price_str) || (float)$price_str < 0) { $errors[] = "Price must be a valid positive number."; } else { $price = (float)$price_str; }
    if ($categoryId !== null) { $validCategoryIds = array_column($categories, 'id'); if (!in_array($categoryId, $validCategoryIds)) { $errors[] = "Invalid category selected."; } }
    if ($capacity_str !== '') { if (!ctype_digit($capacity_str)) { $errors[] = "Capacity must be a valid whole number (or leave blank)."; } else { $capacity = (int)$capacity_str; } }
    if ($latitude_str !== '') { if (!is_numeric($latitude_str) || $latitude_str < -90 || $latitude_str > 90) { $errors[] = "Latitude must be valid (-90 to 90) or blank."; } else { $latitude = (float)$latitude_str; } }
    if ($longitude_str !== '') { if (!is_numeric($longitude_str) || $longitude_str < -180 || $longitude_str > 180) { $errors[] = "Longitude must be valid (-180 to 180) or blank."; } else { $longitude = (float)$longitude_str; } }

    // ** Validate Initial Date (if provided) **
    if (!empty($initialDate)) {
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $initialDate)) {
             $errors[] = "Invalid format for Initial Available Date (Use YYYY-MM-DD).";
        } elseif (strtotime($initialDate) < strtotime(date('Y-m-d'))) {
             $errors[] = "Initial Available Date cannot be in the past.";
        } else {
             $validatedInitialDate = $initialDate; // Store validated date
        }
    }

    // 3. Insert into Database (if no validation errors)
    if (empty($errors)) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            $errors[] = "Database connection failed: " . $conn->connect_error;
            error_log("Add Tour DB Connect Error: " . $conn->connect_error);
        } else {
            $conn->begin_transaction(); // Start transaction
            $tourAdded = false;
            $newTourId = null;
            $dateAddedSuccessfully = true; // Assume true unless date insert fails

            try {
                // Insert into tours table
                $sql = "INSERT INTO tours (name, location_city, description, price, category_id, capacity, latitude, longitude, image_filename)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)"; // Assume no image on initial add yet
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("DB Error (Prepare Tour): " . $conn->error);

                // Types: s, s, s, d, i, i, d, d (8 params - category_id and capacity are nullable integers)
                $stmt->bind_param("sssdiidd", // Adjusted type string
                    $name, $location_city, $description, $price, $categoryId,
                    $capacity, $latitude, $longitude
                );

                if (!$stmt->execute()) throw new Exception("DB Error (Execute Tour): " . $stmt->error);
                $newTourId = $conn->insert_id; // Get the ID of the newly inserted tour
                $stmt->close();
                $tourAdded = true;

                // Insert initial date if provided and valid
                if ($validatedInitialDate !== null && $newTourId > 0) {
                    $sql_date_insert = "INSERT INTO tour_dates (tour_id, tour_date, capacity_override) VALUES (?, ?, NULL)";
                    $stmt_date = $conn->prepare($sql_date_insert);
                    if ($stmt_date) {
                        $stmt_date->bind_param("is", $newTourId, $validatedInitialDate);
                        if (!$stmt_date->execute()) {
                            // Date insert failed - log it, maybe set warning message, but don't stop commit?
                            error_log("Failed to insert initial date for tour ID $newTourId: " . $stmt_date->error);
                            $dateAddedSuccessfully = false; // Mark that date failed
                        }
                        $stmt_date->close();
                    } else {
                         error_log("Failed to prepare initial date insert for tour ID $newTourId: " . $conn->error);
                         $dateAddedSuccessfully = false; // Mark that date failed
                    }
                }

                // If we reach here without exceptions, commit
                $conn->commit();

                // Prepare success message (potentially with warning)
                 $_SESSION['admin_message'] = "Tour '" . htmlspecialchars($name) . "' added successfully!";
                 if (!$dateAddedSuccessfully && $validatedInitialDate !== null) {
                     $_SESSION['admin_message'] .= " However, failed to add initial date.";
                     $_SESSION['admin_message_type'] = 'warning';
                 }
                 header("Location: index.php"); // Redirect after success
                 exit;

            } catch (Exception $e) {
                 $conn->rollback(); // Rollback on any error
                 $errors[] = "Failed to add tour: " . $e->getMessage();
                 error_log("Add Tour Transaction Error: " . $e->getMessage());
            }
            $conn->close();
        }
    }
    // If errors occurred, script continues and redisplays form below...
} // End POST handling
?>

    <?php // Main content starts here ?>
    <div class="content-box admin-page">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php // Display validation errors if they exist
        if (!empty($errors)): ?>
            <div class="form-errors">
                <p><strong>Please fix the following issues:</strong></p>
                <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php // Add Tour Form - includes enctype for future file uploads ?>
        <form action="add_tour.php" method="POST" enctype="multipart/form-data" class="data-form">
            <?php // Name, Location, Description, Price fields... ?>
            <div class="form-group"><label for="name">Tour Name:</label><input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required></div>
            <div class="form-group"><label for="location_city">Location City:</label><input type="text" id="location_city" name="location_city" value="<?php echo isset($_POST['location_city']) ? htmlspecialchars($_POST['location_city']) : ''; ?>" required></div>
            <div class="form-group"><label for="description">Description:</label><textarea id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea></div>
            <div class="form-group"><label for="price">Price (KES):</label><input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required></div>

            <?php // Category Dropdown ?>
            <div class="form-group">
                <label for="category_id">Category:</label>
                <select name="category_id" id="category_id">
                    <option value="">-- Select Category (Optional) --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php // Capacity, Lat, Lon fields... ?>
            <div class="form-group"><label for="capacity">Capacity:</label><input type="number" id="capacity" name="capacity" min="0" value="<?php echo isset($_POST['capacity']) ? htmlspecialchars($_POST['capacity']) : ''; ?>"><small>Leave blank if no limit.</small></div>
            <div class="form-group"><label for="latitude">Latitude:</label><input type="text" id="latitude" name="latitude" value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>"><small>Optional. E.g., -1.2833</small></div>
            <div class="form-group"><label for="longitude">Longitude:</label><input type="text" id="longitude" name="longitude" value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>"><small>Optional. E.g., 36.8167</small></div>

            <?php // --- ADDED: Initial Available Date --- ?>
            <div class="form-group">
                <label for="initial_date">Initial Available Date (Optional):</label>
                <input type="date" id="initial_date" name="initial_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['initial_date']) ? htmlspecialchars($_POST['initial_date']) : ''; ?>">
                <small>Add one starting date to make this tour bookable immediately.</small>
            </div>

            <?php // --- File Upload (Keep for consistency, handle in POST) --- ?>
            <div class="form-group">
                 <label for="tour_image">Tour Image:</label>
                 <input type="file" id="tour_image" name="tour_image" accept="image/jpeg, image/png, image/gif">
                 <small>Optional. Upload JPG, PNG, or GIF (Max 5MB).</small>
            </div>

            <div class="form-group form-actions">
                <button type="submit" class="submit-button">Add Tour</button>
                <a href="index.php" style="margin-left: 15px;">Cancel</a>
            </div>
        </form>

    </div> <?php // End content-box ?>

<?php
require_once 'includes/admin_footer.php'; // Include the footer
?>