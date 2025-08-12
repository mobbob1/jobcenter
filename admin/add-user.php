<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = trim($_POST['user_type'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    
    // For admin users
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    // For job seekers
    $js_first_name = trim($_POST['js_first_name'] ?? '');
    $js_surname = trim($_POST['js_surname'] ?? '');
    $js_phone = trim($_POST['js_phone'] ?? '');
    
    // Required fields validation
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($user_type)) {
        $errors[] = "User type is required";
    }
    
    // Check if username already exists
    $check_username_query = "SELECT id FROM users WHERE username = ?";
    $check_username_stmt = $conn->prepare($check_username_query);
    $check_username_stmt->bind_param("s", $username);
    $check_username_stmt->execute();
    $check_username_result = $check_username_stmt->get_result();
    
    if ($check_username_result->num_rows > 0) {
        $errors[] = "Username already exists. Please choose a different username.";
    }
    
    // Check if email already exists
    $check_email_query = "SELECT id FROM users WHERE email = ?";
    $check_email_stmt = $conn->prepare($check_email_query);
    $check_email_stmt->bind_param("s", $email);
    $check_email_stmt->execute();
    $check_email_result = $check_email_stmt->get_result();
    
    if ($check_email_result->num_rows > 0) {
        $errors[] = "Email already exists. Please use a different email address.";
    }
    
    // Additional validations based on user type
    if ($user_type === 'admin') {
        if (empty($first_name)) {
            $errors[] = "First name is required for admin users";
        }
        if (empty($last_name)) {
            $errors[] = "Last name is required for admin users";
        }
    } elseif ($user_type === 'jobseeker') {
        if (empty($js_first_name)) {
            $errors[] = "First name is required for job seekers";
        }
        if (empty($js_surname)) {
            $errors[] = "Surname is required for job seekers";
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $insert_user_query = "INSERT INTO users (username, email, password, user_type, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_user_stmt = $conn->prepare($insert_user_query);
            $insert_user_stmt->bind_param("sssss", $username, $email, $hashed_password, $user_type, $status);
            
            if (!$insert_user_stmt->execute()) {
                throw new Exception("Error creating user account: " . $conn->error);
            }
            
            $user_id = $conn->insert_id;
            
            // Insert additional data based on user type
            if ($user_type === 'admin') {
                $insert_admin_query = "INSERT INTO admins (user_id, first_name, last_name) VALUES (?, ?, ?)";
                $insert_admin_stmt = $conn->prepare($insert_admin_query);
                $insert_admin_stmt->bind_param("iss", $user_id, $first_name, $last_name);
                
                if (!$insert_admin_stmt->execute()) {
                    throw new Exception("Error creating admin profile: " . $conn->error);
                }
            } elseif ($user_type === 'jobseeker') {
                $insert_js_query = "INSERT INTO job_seekers (user_id, first_name, surname, phone) VALUES (?, ?, ?, ?)";
                $insert_js_stmt = $conn->prepare($insert_js_query);
                $insert_js_stmt->bind_param("isss", $user_id, $js_first_name, $js_surname, $js_phone);
                
                if (!$insert_js_stmt->execute()) {
                    throw new Exception("Error creating job seeker profile: " . $conn->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            $success = true;
            
            // Reset form fields
            $username = $email = $password = $confirm_password = '';
            $first_name = $last_name = $js_first_name = $js_surname = $js_phone = '';
            $user_type = 'jobseeker';
            $status = 'active';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$page_title = "Add New User";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="manage-users.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    User added successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="add-user.php" method="POST" class="needs-validation" novalidate>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="card-title">Account Information</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a username.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="user_type" name="user_type" required>
                                        <option value="">Select User Type</option>
                                        <option value="admin" <?php echo (isset($user_type) && $user_type === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        <option value="employer" <?php echo (isset($user_type) && $user_type === 'employer') ? 'selected' : ''; ?>>Employer</option>
                                        <option value="jobseeker" <?php echo (isset($user_type) && $user_type === 'jobseeker') ? 'selected' : ''; ?>>Job Seeker</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a user type.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                    <div class="invalid-feedback">Please enter a password.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Please confirm your password.</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo (isset($status) && $status === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($status) && $status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo (isset($status) && $status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Admin Fields (shown only when user_type is admin) -->
                            <div class="row mb-4 admin-fields" id="admin-fields" style="display: none;">
                                <div class="col-12">
                                    <h5 class="card-title">Admin Information</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Job Seeker Fields (shown only when user_type is jobseeker) -->
                            <div class="row mb-4 jobseeker-fields" id="jobseeker-fields" style="display: none;">
                                <div class="col-12">
                                    <h5 class="card-title">Job Seeker Information</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="js_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="js_first_name" name="js_first_name" value="<?php echo htmlspecialchars($js_first_name ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="js_surname" class="form-label">Surname <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="js_surname" name="js_surname" value="<?php echo htmlspecialchars($js_surname ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="js_phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="js_phone" name="js_phone" value="<?php echo htmlspecialchars($js_phone ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Employer note (shown only when user_type is employer) -->
                            <div class="row mb-4 employer-note" id="employer-note" style="display: none;">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> For employer accounts, you'll need to create the user here first, then add the company details from the <a href="add-company.php">Add Company</a> page.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Add User</button>
                                    <a href="manage-users.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Form validation
    (function () {
        'use strict'
        
        // Fetch all forms we want to apply validation styles to
        var forms = document.querySelectorAll('.needs-validation')
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    
                    form.classList.add('was-validated')
                }, false)
            })
    })()
    
    // Show/hide fields based on user type
    document.getElementById('user_type').addEventListener('change', function() {
        const userType = this.value;
        const adminFields = document.getElementById('admin-fields');
        const jobseekerFields = document.getElementById('jobseeker-fields');
        const employerNote = document.getElementById('employer-note');
        
        // Hide all first
        adminFields.style.display = 'none';
        jobseekerFields.style.display = 'none';
        employerNote.style.display = 'none';
        
        // Show relevant fields based on selection
        if (userType === 'admin') {
            adminFields.style.display = 'flex';
        } else if (userType === 'jobseeker') {
            jobseekerFields.style.display = 'flex';
        } else if (userType === 'employer') {
            employerNote.style.display = 'block';
        }
    });
    
    // Trigger the change event on page load to set initial visibility
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.getElementById('user_type');
        if (userTypeSelect.value) {
            const event = new Event('change');
            userTypeSelect.dispatchEvent(event);
        }
    });
    </script>
</body>
</html>
