<?php
// tour_app/admin/edit_tour.php
$pageTitle = "Edit Tour"; // Set default title
require_once 'includes/admin_header.php'; // Includes check, session_start

define('DB_HOST', 'localhost'); /* ... */
define('DB_USER', 'root');      /* ... */
define('DB_PASS', '');          /* ... */
define('DB_NAME', 'tour_booking_db'); /* ... */

$tour = null;
$pageError = null;
$errors = [];
$tourId = null;

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

// Determine Tour ID (from GET on initial load, or POST on submission)
if ($_SERVER["REQUEST_METHOD"] == "POST") { $tourId = isset($_POST['tour_id']) ? (int)$_POST['tour_id'] : 0; }
else { $tourId = isset($_GET['id']) ? (int)$_GET['id'] : 0; }

// Validate Tour ID
if ($tourId <= 0) { $pageError = "Invalid or missing Tour ID."; }

// --- Handle Form Submission (POST request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Get and Sanitize Data from POST
    $tourId = isset($_POST['tour_id']) ? (int)$_POST['tour_id'] : 0;
    // ... (get name, location, description, price, categoryId, capacity, lat, lon as before) ...
    $name = trim(htmlspecialchars($_POST['name'] ?? '')); $location_city = trim(htmlspecialchars($_POST['location_city'] ?? '')); $description = trim(htmlspecialchars($_POST['description'] ?? '')); $price_str = trim($_POST['price'] ?? ''); $categoryId = isset($_POST['category_id']) && !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null; $capacity_str = trim($_POST['capacity'] ?? ''); $latitude_str = trim($_POST['latitude'] ?? ''); $longitude_str = trim($_POST['longitude'] ?? '');
    $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] == '1'; // Check if delete checkbox was checked
    $price = null; $capacity = null; $latitude = null; $longitude = null; $errors = [];

    // 2. Validate Text/Numeric Data
    // ... (keep existing validation logic) ...
    if (empty($name)) { $errors[] = "Tour Name required."; } // etc...

    // 3. Handle Image Upload / Deletion
    $imageToUpdate = false; // Flag to determine if image column needs update
    $newImageFilename = null; // Filename to potentially update in DB
    $uploadDir = '../uploads/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB

    // -- Check for new upload --
    if (isset($_FILES['tour_image']) && $_FILES['tour_image']['error'] === UPLOAD_ERR_OK) {
        // ... (Same validation logic as add_tour: check size, type) ...
        $fileTmpPath = $_FILES['tour_image']['tmp_name']; $fileName = $_FILES['tour_image']['name']; $fileSize = $_FILES['tour_image']['size']; $fileType = mime_content_type($fileTmpPath); $fileNameCmps = explode(".", $fileName); $fileExtension = strtolower(end($fileNameCmps));
        if ($fileSize > $maxFileSize) { $errors[] = "Image file too large (Max: 5MB)."; }
        elseif (!in_array($fileType, $allowedTypes)) { $errors[] = "Invalid image file type (JPG, PNG, GIF)."; }
        else {
            $newFileName = uniqid('tour_', true) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $newImageFilename = $newFileName; // Store new filename
                $imageToUpdate = true; // Mark image for DB update
            } else { $errors[] = "Failed to move uploaded image."; error_log("Edit upload move error: " . $destPath); }
        }
    } elseif (isset($_FILES['tour_image']) && $_FILES['tour_image']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['tour_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading image. Code: " . $_FILES['tour_image']['error']; error_log("Edit upload error code: " . $_FILES['tour_image']['error']);
    }

    // -- Check for delete request --
    if ($deleteImage && !$imageToUpdate) { // Only delete if not replaced by new upload
        $newImageFilename = null; // Set to null to clear DB column
        $imageToUpdate = true; // Mark image for DB update (to NULL)
    }

    // 4. Update Database (if no validation errors)
    if (empty($errors)) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) { /* ... handle error ... */ }
        else {
            // Fetch old filename BEFORE updating DB, if we need to delete it
            $oldFilename = null;
            if ($imageToUpdate) { // If we're changing the image (new upload or delete)
                $sql_old = "SELECT image_filename FROM tours WHERE id = ?";
                $stmt_old = $conn->prepare($sql_old);
                if($stmt_old) {
                    $stmt_old->bind_param("i", $tourId);
                    $stmt_old->execute();
                    $res_old = $stmt_old->get_result();
                    if ($row_old = $res_old->fetch_assoc()) {
                        $oldFilename = $row_old['image_filename'];
                    }
                    $stmt_old->close();
                }
            }

            // *** SQL includes image_filename ***
            $sql = "UPDATE tours SET
                        name = ?, location_city = ?, description = ?, price = ?,
                        category_id = ?, capacity = ?, latitude = ?, longitude = ?,
                        image_filename = ?  -- Added image filename
                    WHERE id = ?"; // 10 placeholders total
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                 // *** bind_param includes image_filename (s) ***
                 // Types: s, s, s, d, i, i, d, d, s, i (10 types)
                 $currentImageFilename = $imageToUpdate ? $newImageFilename : ($oldFilename ?? null); // Determine value to save
                 if (!$imageToUpdate && isset($tour['image_filename'])) {
                    // If not updating image and form was redisplayed, use original value
                    $currentImageFilename = $tour['image_filename'];
                 }


                 $stmt->bind_param("sssdiiddsi", // Update type string
                     $name, $location_city, $description, $price, $categoryId,
                     $capacity, $latitude, $longitude,
                     $currentImageFilename, // Use the final filename (new, null, or original)
                     $tourId // WHERE clause ID
                 );

                 if ($stmt->execute()) {
                     // Delete old file ONLY if update was successful AND a new file was uploaded OR delete was checked
                     if ($imageToUpdate && !empty($oldFilename) && $oldFilename !== $newImageFilename) {
                          $oldFilePath = $uploadDir . $oldFilename;
                          if (file_exists($oldFilePath)) {
                              @unlink($oldFilePath); // Suppress errors if file missing
                          }
                     }

                     if ($stmt->affected_rows > 0) { $_SESSION['admin_message'] = "Tour updated successfully!"; }
                     else { $_SESSION['admin_message'] = "No changes detected."; $_SESSION['admin_message_type'] = 'info'; }
                     header("Location: index.php"); exit;

                 } else { $errors[] = "Failed to update tour: " . $stmt->error; error_log("Edit Tour Execute Error: " . $stmt->error); }
                 $stmt->close();
            } else { $errors[] = "Failed to prepare update query: " . $conn->error; error_log("Edit Tour Prepare Error: " . $conn->error); }
            $conn->close();
        }
    } // End empty($errors)

    // If errors occurred, repopulate $tour with submitted data
    if (!empty($errors)) {
         $pageError = "Please correct the errors below.";
         // Overwrite $tour array with POST data (fetch original image again if needed for display)
         $tour = [ /* ... repopulate $tour with POST data including category_id ... */ ];
          $tour = ['id' => $tourId, 'name' => $_POST['name'] ?? '', 'location_city' => $_POST['location_city'] ?? '', 'description' => $_POST['description'] ?? '', 'price' => $_POST['price'] ?? '', 'category_id' => $_POST['category_id'] ?? null, 'capacity' => $_POST['capacity'] ?? '', 'latitude' => $_POST['latitude'] ?? '', 'longitude' => $_POST['longitude'] ?? '', 'image_filename' => $oldFilename ?? $tour['image_filename'] ?? null ]; // Try to preserve original image on error
    }
} // End POST handling


// Fetch Existing Tour Data for GET request or if POST failed
if ($pageError === null && ($_SERVER["REQUEST_METHOD"] != "POST" || !empty($errors))) {
    if ($tour === null) { // Only fetch if $tour not populated by failed POST
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) { $pageError = "DB connection failed: " . $conn->connect_error; error_log("Edit Tour GET DB Connect Error: " . $conn->connect_error); }
        else { $sql_fetch = "SELECT * FROM tours WHERE id = ?"; $stmt_fetch = $conn->prepare($sql_fetch);
            if ($stmt_fetch) { $stmt_fetch->bind_param("i", $tourId); $stmt_fetch->execute(); $result_fetch = $stmt_fetch->get_result();
                if ($result_fetch && $result_fetch->num_rows === 1) { $tour = $result_fetch->fetch_assoc(); $pageTitle = "Edit Tour: " . htmlspecialchars($tour['name']); /* Set specific title */ }
                else { $pageError = "Tour with ID " . $tourId . " not found."; } $stmt_fetch->close();
            } else { $pageError = "Error preparing query: " . $conn->error; error_log("Edit Tour GET Prepare Error: " . $conn->error); } $conn->close();
        }
    } // else $tour is already populated from failed POST data
}

?>

    <?php // Main content starts here ?>
    <div class="content-box admin-page">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php // Display errors like "Tour Not Found" or DB connection errors
        if (!empty($pageError) && $tour === null): ?>
            <div class="error">
                <?php echo htmlspecialchars($pageError); ?>
                <p><a href="index.php">Back to Tour List</a></p>
            </div>
            <?php elseif ($tour !== null): // Show form if tour data is loaded ?>

<?php // Display validation errors from POST submission if they exist
if (!empty($errors)): ?>
    <div class="form-errors">
        <p><strong><?php echo htmlspecialchars($pageError ?: 'Please fix the following issues:'); ?></strong></p>
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php // --- Edit Tour Form --- ?>
<form action="edit_tour.php?id=<?php echo htmlspecialchars($tour['id']); ?>" method="POST" enctype="multipart/form-data" class="data-form">
    <input type="hidden" name="tour_id" value="<?php echo htmlspecialchars($tour['id']); ?>">

    <?php // Name, Location, Desc, Price fields... ?>
     <div class="form-group"><label for="name">Tour Name:</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($tour['name'] ?? ''); ?>" required></div>
     <div class="form-group"><label for="location_city">Location City:</label><input type="text" id="location_city" name="location_city" value="<?php echo htmlspecialchars($tour['location_city'] ?? ''); ?>" required></div>
     <div class="form-group"><label for="description">Description:</label><textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($tour['description'] ?? ''); ?></textarea></div>
     <div class="form-group"><label for="price">Price (KES):</label><input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($tour['price'] ?? ''); ?>" required></div>

    <?php // Category Dropdown ?>
    <div class="form-group">
        <label for="category_id">Category:</label>
        <select name="category_id" id="category_id">
            <option value="">-- Select Category (Optional) --</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category['id']); ?>"
                    <?php $selectedCategoryId = $tour['category_id'] ?? null; if ($selectedCategoryId == $category['id']) { echo ' selected'; } ?>>
                    <?php echo htmlspecialchars($category['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
             <label>Current Image:</label>
             <?php if (!empty($tour['image_filename'])): ?>
                 <img src="../uploads/<?php echo htmlspecialchars($tour['image_filename']); ?>" alt="Current image" style="max-width: 150px; height: auto; display: block; margin-bottom: 10px;">
                 <input type="checkbox" name="delete_image" id="delete_image" value="1">
                 <label for="delete_image" style="display: inline; font-weight: normal;">Delete current image?</label>
             <?php else: ?>
                 <p>No image currently uploaded.</p>
             <?php endif; ?>
         </div>
         <div class="form-group">
            <label for="tour_image">Upload New Image (Optional):</label>
            <input type="file" id="tour_image" name="tour_image" accept="image/jpeg, image/png, image/gif">
            <small>Replaces current image if selected. Upload JPG, PNG, or GIF.</small>
        </div>

    <?php // Capacity, Lat, Lon fields... ?>
    <div class="form-group"><label for="capacity">Capacity:</label><input type="number" id="capacity" name="capacity" min="0" value="<?php echo htmlspecialchars($tour['capacity'] ?? ''); ?>"><small>Leave blank if no limit.</small></div>
    <div class="form-group"><label for="latitude">Latitude:</label><input type="text" id="latitude" name="latitude" value="<?php echo htmlspecialchars($tour['latitude'] ?? ''); ?>"><small>Optional. E.g., -1.2833</small></div>
    <div class="form-group"><label for="longitude">Longitude:</label><input type="text" id="longitude" name="longitude" value="<?php echo htmlspecialchars($tour['longitude'] ?? ''); ?>"><small>Optional. E.g., 36.8167</small></div>

    <?php // Submit button... ?>
     <div class="form-group form-actions"><button type="submit" class="submit-button">Update Tour</button><a href="index.php" style="margin-left: 15px;">Cancel</a></div>
</form>
<?php // --- End Edit Tour Form --- ?>

<?php else: // Fallback if $tour is null ?>
 <p class="error">Could not load tour data.</p>
 <p><a href="index.php">Back to Tour List</a></p>
<?php endif; ?>

    </div> <?php // End content-box ?>

<?php
require_once 'includes/admin_footer.php'; // Include the footer
?>