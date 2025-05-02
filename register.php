<?php
// tour_app/register.php
$pageTitle = "Register";
require_once 'includes/header.php'; // Includes session_start, head, nav etc.

// Redirect if already logged in
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');
$errors = [];

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (Keep the exact POST handling logic from register.php provided previously) ...
    // ... (Validation, DB Check, Password Hash, Insert, Redirect on Success) ...
     $username = trim($_POST['username'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? ''; $confirmPassword = $_POST['confirm_password'] ?? '';
     if (empty($username)) { $errors[] = "Username is required."; } if (empty($email)) { $errors[] = "Email is required."; } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; } if (empty($password)) { $errors[] = "Password is required."; } elseif (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters long."; } if ($password !== $confirmPassword) { $errors[] = "Passwords do not match."; }
     if (empty($errors)) { $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($conn->connect_error) { $errors[] = "DB connection failed."; error_log("Reg DB Connect Error: " . $conn->connect_error); }
         else { $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?"; $stmt_check = $conn->prepare($sql_check); if ($stmt_check) { $stmt_check->bind_param("ss", $username, $email); $stmt_check->execute(); $result_check = $stmt_check->get_result(); if ($result_check->num_rows > 0) { $errors[] = "Username or Email already exists."; } $stmt_check->close(); } else { $errors[] = "Error checking user."; error_log("Reg Check Prepare Error: " . $conn->error); } $conn->close(); }
     }
     if (empty($errors)) { $hashedPassword = password_hash($password, PASSWORD_DEFAULT); if ($hashedPassword === false) { $errors[] = "Password error."; error_log("Pass hash failed: " . $username); }
         else { $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($conn->connect_error) { $errors[] = "DB insert connect error."; error_log("Reg DB Insert Connect Error: " . $conn->connect_error); }
             else { $sql_insert = "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())"; $stmt_insert = $conn->prepare($sql_insert);
                 if ($stmt_insert) { $stmt_insert->bind_param("sss", $username, $email, $hashedPassword);
                     if ($stmt_insert->execute()) { $_SESSION['success_message'] = "Registration successful! Please log in."; header("Location: login.php"); exit; }
                     else { $errors[] = "Registration failed."; error_log("Reg Insert Exec Error: " . $stmt_insert->error); } $stmt_insert->close();
                 } else { $errors[] = "Registration prepare error."; error_log("Reg Insert Prepare Error: " . $conn->error); } $conn->close();
             }
         }
     }
} // End POST handling
?>

    <div class="content-box auth-page"> <?php // Apply container class ?>
        <h2>Create Account</h2>

        <?php // Display errors if they exist
        if (!empty($errors)): ?>
            <div class="form-errors">
                <p><strong>Please fix the following issues:</strong></p>
                <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
             <?php // Form fields remain the same (name, email, pass, confirm pass, submit button) ?>
             <div class="form-group"><label for="username">Username:</label><input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required></div>
             <div class="form-group"><label for="email">Email:</label><input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required></div>
             <div class="form-group password-wrapper"><label for="password">Password:</label><input type="password" id="password" name="password" required><button type="button" class="toggle-password" data-target="password">Show</button><small>(Minimum 6 characters)</small></div>
             <div class="form-group password-wrapper"><label for="confirm_password">Confirm Password:</label><input type="password" id="confirm_password" name="confirm_password" required><button type="button" class="toggle-password" data-target="confirm_password">Show</button></div>
             <div class="form-group"><button type="submit" class="submit-button">Register</button></div>
        </form>

        <p class="form-link">Already have an account? <a href="login.php">Login here</a></p>
        <p class="form-link" style="margin-top: 5px;"><a href="index.php">Back to Tours</a></p>
    </div>

<?php
require_once 'includes/footer.php'; // Include the footer
?>