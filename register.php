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

// Get registration type from URL parameter
$type = isset($_GET['type']) ? $_GET['type'] : 'jobseeker';
if ($type != 'jobseeker' && $type != 'employer') {
    $type = 'jobseeker'; // Default to jobseeker if invalid type
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Common fields for both user types
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
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
                $user_type = $_POST['user_type']; // 'jobseeker' or 'employer'
                $verification_token = bin2hex(random_bytes(16)); // Generate verification token
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, verification_token) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $user_type, $verification_token);
                $stmt->execute();
                
                $user_id = $conn->insert_id;
                
                // Insert additional details based on user type
                if ($user_type == 'jobseeker') {
                    $first_name = trim($_POST['first_name']);
                    $surname = trim($_POST['surname']);
                    $phone = trim($_POST['phone']);
                    
                    $stmt = $conn->prepare("INSERT INTO job_seekers (user_id, first_name, surname, phone) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $user_id, $first_name, $surname, $phone);
                    $stmt->execute();
                } else { // employer
                    $company_name = trim($_POST['company_name']);
                    $industry = trim($_POST['industry']);
                    $phone = trim($_POST['phone']);
                    
                    $stmt = $conn->prepare("INSERT INTO companies (user_id, company_name, industry, phone, email) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $user_id, $company_name, $industry, $phone, $email);
                    $stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Send verification email (in a real application)
                // sendVerificationEmail($email, $verification_token);
                
                $success = "Registration successful! Please check your email to verify your account.";
                
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
    <title>Register - JobConnect</title>
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
                <h2 class="card-title">
                    <?php echo ($type == 'employer') ? 'Register as Employer' : 'Register as Job Seeker'; ?>
                </h2>
                
                <ul class="nav nav-pills mb-4 justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($type == 'jobseeker') ? 'active' : ''; ?>" href="register.php?type=jobseeker">Job Seeker</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($type == 'employer') ? 'active' : ''; ?>" href="register.php?type=employer">Employer</a>
                    </li>
                </ul>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form action="register.php?type=<?php echo $type; ?>" method="POST">
                    <input type="hidden" name="user_type" value="<?php echo $type; ?>">
                    
                    <?php if ($type == 'jobseeker'): ?>
                        <!-- Job Seeker specific fields -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="surname" class="form-label">Surname *</label>
                                    <input type="text" name="surname" id="surname" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Employer specific fields -->
                        <div class="form-group">
                            <label for="company_name" class="form-label">Company Name *</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="industry" class="form-label">Industry *</label>
                            <select name="industry" id="industry" class="form-control" required>
                                <option value="">Select Industry</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Healthcare">Healthcare</option>
                                <option value="Education">Education</option>
                                <option value="Finance">Finance</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Sales">Sales</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Manufacturing">Manufacturing</option>
                                <option value="Retail">Retail</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Common fields for both user types -->
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
                                <small class="form-text text-muted">Password must be at least 6 characters long</small>
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
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <div class="social-login mb-4">
                    <a href="auth/social-login.php?provider=google&type=<?php echo $type; ?>" class="social-login-btn google">
                        <i class="fab fa-google"></i> Register with Google
                    </a>
                    <a href="auth/social-login.php?provider=facebook&type=<?php echo $type; ?>" class="social-login-btn facebook">
                        <i class="fab fa-facebook-f"></i> Register with Facebook
                    </a>
                </div>
                
                <p class="text-center">Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
