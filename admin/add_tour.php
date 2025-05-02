<?php
// tour_app/admin/add_tour.php
$pageTitle = "Add New Tour"; // Set page title
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
    if ($result_cat) {
        while ($row_cat = $result_cat->fetch_assoc()) {
            $categories[] = $row_cat;
        }
        $result_cat->free();
    } else {
        // Handle error fetching categories if needed
         if(empty($errors) && empty($pageError)) { // Only show error if no other errors present
             $errors[] = "Could not load tour categories.";
         }
         error_log("Fetch Categories Error: " . $conn_cat->error);
    }
    $conn_cat->close();
} // else: connection error already handled perhaps
// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get and Sanitize Text/Numeric Data
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    // ... (get location, description, price, category, capacity, lat, lon as before) ...
    $location_city = trim(htmlspecialchars($_POST['location_city'] ?? '')); $description = trim(htmlspecialchars($_POST['description'] ?? '')); $price_str = trim($_POST['price'] ?? ''); $categoryId = isset($_POST['category_id']) && !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null; $capacity_str = trim($_POST['capacity'] ?? ''); $latitude_str = trim($_POST['latitude'] ?? ''); $longitude_str = trim($_POST['longitude'] ?? '');
    $price = null; $capacity = null; $latitude = null; $longitude = null; $errors = [];

    // 2. Validate Text/Numeric Data
    // ... (keep existing validation logic for name, location, price, category, capacity, lat, lon) ...
     if (empty($name)) { $errors[] = "Tour Name required."; } if (empty($location_city)) { $errors[] = "Location required."; } if ($price_str === '') { $errors[] = "Price required."; } elseif (!is_numeric($price_str) || (float)$price_str < 0) { $errors[] = "Price invalid."; } else { $price = (float)$price_str; } if ($categoryId !== null) { /* Optional: Validate category ID */ } if ($capacity_str !== '') { if (!ctype_digit($capacity_str)) { $errors[] = "Capacity invalid."; } else { $capacity = (int)$capacity_str; } } if ($latitude_str !== '') { if (!is_numeric($latitude_str) || $latitude_str < -90 || $latitude_str > 90) { $errors[] = "Latitude invalid."; } else { $latitude = (float)$latitude_str; } } if ($longitude_str !== '') { if (!is_numeric($longitude_str) || $longitude_str < -180 || $longitude_str > 180) { $errors[] = "Longitude invalid."; } else { $longitude = (float)$longitude_str; } }

    // 3. Handle Image Upload
    $imageToSave = null; // Filename to save in DB (null if no upload/error)
    $uploadDir = '../uploads/'; // Relative path from /admin/ to /uploads/
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB

    if (isset($_FILES['tour_image']) && $_FILES['tour_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['tour_image']['tmp_name'];
        $fileName = $_FILES['tour_image']['name'];
        $fileSize = $_FILES['tour_image']['size'];
        $fileType = mime_content_type($fileTmpPath); // More reliable than $_FILES['type']
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        if ($fileSize > $maxFileSize) {
            $errors[] = "Image file is too large (Max: 5MB).";
        } elseif (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid image file type (Allowed: JPG, PNG, GIF).";
        } else {
            // Create a unique filename
            $newFileName = uniqid('tour_', true) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imageToSave = $newFileName; // Store only the filename
            } else {
                $errors[] = "Failed to move uploaded image.";
                error_log("File upload move error for: " . $destPath);
            }
        }
    } elseif (isset($_FILES['tour_image']) && $_FILES['tour_image']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['tour_image']['error'] !== UPLOAD_ERR_OK) {
         // Handle other upload errors (permissions, partial upload etc.)
         $errors[] = "Error uploading image. Code: " . $_FILES['tour_image']['error'];
         error_log("File upload error code: " . $_FILES['tour_image']['error']);
    }

    // 4. Insert into Database (if no validation or upload errors)
    if (empty($errors)) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) { /* ... handle error ... */ }
        else {
            // *** SQL includes image_filename ***
            $sql = "INSERT INTO tours (name, location_city, description, price, category_id, capacity, latitude, longitude, image_filename)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 9 placeholders
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                 // *** bind_param includes image_filename (s) ***
                 // Types: s, s, s, d, i, i, d, d, s (9 types)
                 $stmt->bind_param("sssdiidds",
                     $name, $location_city, $description, $price, $categoryId,
                     $capacity, $latitude, $longitude,
                     $imageToSave // Will bind NULL if no valid image was uploaded
                 );
                 if ($stmt->execute()) {
                     $_SESSION['admin_message'] = "Tour '" . htmlspecialchars($name) . "' added successfully!";
                     header("Location: index.php"); exit;
                 } else { /* ... handle error ... */ }
                 $stmt->close();
            } else { /* ... handle error ... */ }
            $conn->close();
        }
    } // End empty($errors) check
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
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php // --- Add Tour Form --- ?>
        <form action="add_tour.php" method="POST" enctype="multipart/form-data" class="data-form">
            <?php // Form groups for name, location, description, price, capacity, lat, lon ?>
            <div class="form-group"><label for="name">Tour Name:</label><input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required></div>
            <div class="form-group"><label for="location_city">Location City:</label><input type="text" id="location_city" name="location_city" value="<?php echo isset($_POST['location_city']) ? htmlspecialchars($_POST['location_city']) : ''; ?>" required></div>
            <div class="form-group"><label for="description">Description:</label><textarea id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea></div>
            <div class="form-group"><label for="price">Price (KES):</label><input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required></div>
            <div class="form-group">
            <label for="category_id">Category:</label>
            <select name="category_id" id="category_id">
                <option value="">-- Select Category (Optional) --</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>"
                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="tour_image">Tour Image:</label>
            <input type="file" id="tour_image" name="tour_image" accept="image/jpeg, image/png, image/gif">
            <small>Optional. Upload JPG, PNG, or GIF.</small>
        </div>
            <div class="form-group"><label for="capacity">Capacity:</label><input type="number" id="capacity" name="capacity" min="0" value="<?php echo isset($_POST['capacity']) ? htmlspecialchars($_POST['capacity']) : ''; ?>"><small>Leave blank if no limit.</small></div>
            <div class="form-group"><label for="latitude">Latitude:</label><input type="text" id="latitude" name="latitude" value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>"><small>Optional. E.g., -1.2833</small></div>
            <div class="form-group"><label for="longitude">Longitude:</label><input type="text" id="longitude" name="longitude" value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>"><small>Optional. E.g., 36.8167</small></div>

            <div class="form-group form-actions">
                <button type="submit" class="submit-button">Add Tour</button>
                <a href="index.php" style="margin-left: 15px;">Cancel</a>
            </div>
        </form>

    </div> <?php // End content-box ?>

<?php
require_once 'includes/admin_footer.php'; // Include the footer
?>