<?php
// tour_app/login.php
$pageTitle = "Login";
// Session start, redirect check, success message display are all handled by header include now
require_once 'includes/header.php';

// Redirect if already logged in (check added in header, but keep here as safety)
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');
$errors = []; $loginIdentifier = '';

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (Keep the exact POST handling logic from login.php provided previously) ...
    // ... (Validation, DB Check, password_verify, Session Regen, Set Session Vars, Redirect) ...
    $loginIdentifier = trim($_POST['login_identifier'] ?? ''); $password = $_POST['password'] ?? '';
    if (empty($loginIdentifier)) { $errors[] = "Username or Email is required."; } if (empty($password)) { $errors[] = "Password is required."; }
    if (empty($errors)) { $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($conn->connect_error) { $errors[] = "DB connection failed."; error_log("Login DB Connect Error: " . $conn->connect_error); }
        else { $sql = "SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?"; $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param("ss", $loginIdentifier, $loginIdentifier); $stmt->execute(); $result = $stmt->get_result();
                if ($result && $user = $result->fetch_assoc()) { if (password_verify($password, $user['password'])) { session_regenerate_id(true); $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['user_role'] = $user['role']; header("Location: index.php"); exit; } else { $errors[] = "Invalid username/email or password."; } }
                else { $errors[] = "Invalid username/email or password."; } $stmt->close();
            } else { $errors[] = "Login failed (system error)."; error_log("Login Check Prepare Error: " . $conn->error); } $conn->close();
        }
    } // End empty $errors check
     // Keep identifier in form if errors occurred
     $loginIdentifier = isset($_POST['login_identifier']) ? htmlspecialchars($_POST['login_identifier']) : '';
} // End POST handling
?>

    <div class="content-box auth-page"> <?php // Apply container class ?>
        <h2>Login</h2>

        <?php // Session messages are now displayed in header include ?>

        <?php // Display login-specific errors if they exist
        if (!empty($errors)): ?>
            <div class="form-errors">
                <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
             <?php // Form fields remain the same (identifier, password, submit button) ?>
             <div class="form-group"><label for="login_identifier">Username or Email:</label><input type="text" id="login_identifier" name="login_identifier" value="<?php echo $loginIdentifier; ?>" required></div>
             <div class="form-group password-wrapper"><label for="login-password">Password:</label><input type="password" id="login-password" name="password" required><button type="button" class="toggle-password" data-target="login-password">Show</button></div>
             <div class="form-group"><button type="submit" class="submit-button">Login</button></div>
        </form>

        <p class="form-link">Don't have an account? <a href="register.php">Register here</a></p>
        <p class="form-link" style="margin-top: 5px;"><a href="index.php">Back to Tours</a></p>
    </div>

<?php
require_once 'includes/footer.php'; // Include the footer
?>