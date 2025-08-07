<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    // Redirect based on user type
    if ($_SESSION['user_type'] == 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    } elseif ($_SESSION['user_type'] == 'employer') {
        header("Location: employer/dashboard.php");
        exit;
    } elseif ($_SESSION['user_type'] == 'jobseeker') {
        header("Location: jobseeker/dashboard.php");
        exit;
    }
}

$error = '';
$success = '';

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password, user_type, status FROM users WHERE (username = ? OR email = ?)");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['status'] != 'active') {
                $error = "Your account is not active. Please contact support.";
            } else {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Password is correct, start a new session
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login time
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Redirect based on user type
                    if ($user['user_type'] == 'admin') {
                        header("Location: admin/dashboard.php");
                        exit;
                    } elseif ($user['user_type'] == 'employer') {
                        header("Location: employer/dashboard.php");
                        exit;
                    } elseif ($user['user_type'] == 'jobseeker') {
                        header("Location: jobseeker/dashboard.php");
                        exit;
                    } else {
                        // Fallback for any other user type
                        header("Location: index.php");
                        exit;
                    }
                } else {
                    $error = "Invalid username or password";
                }
            }
        } else {
            $error = "Invalid username or password";
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
    <title>Login - JobConnect</title>
    <!-- Use CDN links instead of local files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="container py-5">
            <div class="auth-card">
                <h2 class="card-title">Login to Your Account</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="remember" id="remember" class="form-check-input">
                            <label for="remember" class="form-check-label">Remember me</label>
                        </div>
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <div class="social-login mb-4">
                    <a href="auth/social-login.php?provider=google" class="social-login-btn google">
                        <i class="fab fa-google"></i> Login with Google
                    </a>
                    <a href="auth/social-login.php?provider=facebook" class="social-login-btn facebook">
                        <i class="fab fa-facebook-f"></i> Login with Facebook
                    </a>
                </div>
                
                <p class="text-center">Don't have an account? 
                    <a href="register.php?type=jobseeker">Register as Job Seeker</a> or 
                    <a href="register.php?type=employer">Register as Employer</a>
                </p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Use CDN links instead of local files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
