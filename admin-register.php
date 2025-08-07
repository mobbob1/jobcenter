<?php
session_start();
require_once 'includes/db_connect.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['user_type'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    } elseif ($_SESSION['user_type'] == 'employer') {
        header("Location: employer/dashboard.php");
        exit;
    } else {
        header("Location: jobseeker/dashboard.php");
        exit;
    }
}

// Check if there's a registration token in the URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;

// In a real application, you would store these tokens securely in the database
// For demonstration purposes, we're using a hardcoded token
$admin_registration_token = "admin_secure_token_12345"; // This should be stored securely and generated randomly

if ($token === $admin_registration_token) {
    $valid_token = true;
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || 
        empty($first_name) || empty($last_name) || empty($phone)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || 
              !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = "Password must include at least one uppercase letter, one lowercase letter, one number, and one special character";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into users table
                $user_type = 'admin';
                $status = 'active'; // Admin accounts are active by default
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $user_type, $status);
                $stmt->execute();
                
                $user_id = $conn->insert_id;
                
                // Insert into admins table
                $stmt = $conn->prepare("INSERT INTO admins (user_id, first_name, last_name, phone, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $first_name, $last_name, $phone, $email);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $success = "Admin registration successful! You can now log in.";
                
                // Redirect to login page after a delay
                header("refresh:5;url=login.php");
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - JobConnect</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="container py-5">
            <div class="auth-card">
                <h2 class="card-title">Admin Registration</h2>
                
                <?php if (!$valid_token): ?>
                    <div class="alert alert-danger">
                        <p>Invalid or missing registration token. Admin registration requires a valid token.</p>
                        <p>Please contact the system administrator for assistance.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form action="admin-register.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" id="phone" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" name="password" id="password" class="form-control" required>
                                    <small class="form-text text-muted">Password must be at least 8 characters long and include uppercase, lowercase, number, and special character</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <div class="form-check">
                                <input type="checkbox" name="terms" id="terms" class="form-check-input" required>
                                <label for="terms" class="form-check-label">I agree to the <a href="terms.php">Terms & Conditions</a> and <a href="privacy-policy.php">Privacy Policy</a></label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Register as Admin</button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <p class="text-center mt-4">Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
