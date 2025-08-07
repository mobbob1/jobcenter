<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get admin information
$admin_query = "SELECT * FROM users WHERE id = ? AND user_type = 'admin'";
$admin_stmt = $conn->prepare($admin_query);
if ($admin_stmt === false) {
    $error_message = "Error preparing statement: " . $conn->error;
    $admin_data = null;
} else {
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    
    if ($admin_result && $admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
    } else {
        $error_message = "Admin profile not found.";
        $admin_data = null;
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // Validate input
    $validation_errors = [];
    
    if (empty($first_name)) {
        $validation_errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $validation_errors[] = "Last name is required.";
    }
    
    if (empty($email)) {
        $validation_errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Please enter a valid email address.";
    }
    
    // Check if email is already in use by another user
    if (!empty($email) && $email !== $admin_data['email']) {
        $check_email_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_email_stmt = $conn->prepare($check_email_query);
        if ($check_email_stmt === false) {
            $validation_errors[] = "Error checking email: " . $conn->error;
        } else {
            $check_email_stmt->bind_param("si", $email, $admin_id);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $validation_errors[] = "Email is already in use by another user.";
            }
        }
    }
    
    // Process profile image upload if provided
    $profile_image = $admin_data['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $validation_errors[] = "Only JPG, PNG, and GIF images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $validation_errors[] = "Image size should not exceed 2MB.";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/profile_images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                // Delete old profile image if it exists and is not the default
                if (!empty($admin_data['profile_image']) && $admin_data['profile_image'] !== 'default.png' && file_exists($upload_dir . $admin_data['profile_image'])) {
                    unlink($upload_dir . $admin_data['profile_image']);
                }
                
                $profile_image = $new_filename;
            } else {
                $validation_errors[] = "Failed to upload image. Please try again.";
            }
        }
    }
    
    // Handle password change if requested
    $password_updated = false;
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password)) {
            $validation_errors[] = "Current password is required to change password.";
        } elseif (empty($new_password)) {
            $validation_errors[] = "New password cannot be empty.";
        } elseif ($new_password !== $confirm_password) {
            $validation_errors[] = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 8) {
            $validation_errors[] = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            if (!password_verify($current_password, $admin_data['password'])) {
                $validation_errors[] = "Current password is incorrect.";
            } else {
                // Password is valid, will update it below if no other errors
                $password_updated = true;
            }
        }
    }
    
    // Update profile if validation passes
    if (empty($validation_errors)) {
        if ($password_updated) {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, profile_image = ?, password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            if ($update_stmt === false) {
                $error_message = "Error preparing update statement: " . $conn->error;
            } else {
                $update_stmt->bind_param("ssssssi", $first_name, $last_name, $email, $phone, $profile_image, $hashed_password, $admin_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Profile updated successfully with new password.";
                    
                    // Refresh admin data
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    $admin_data = $admin_result->fetch_assoc();
                } else {
                    $error_message = "Error updating profile: " . $conn->error;
                }
            }
        } else {
            // Update without changing password
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, profile_image = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            if ($update_stmt === false) {
                $error_message = "Error preparing update statement: " . $conn->error;
            } else {
                $update_stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $profile_image, $admin_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Profile updated successfully.";
                    
                    // Refresh admin data
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    $admin_data = $admin_result->fetch_assoc();
                } else {
                    $error_message = "Error updating profile: " . $conn->error;
                }
            }
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
    }
}

$page_title = "Admin Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .nav-link.active {
            color: #2470dc;
        }
        
        .profile-header {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($admin_data): ?>
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-body text-center">
                                    <img src="<?php echo !empty($admin_data['profile_image']) ? '../uploads/profile_images/' . $admin_data['profile_image'] : '../uploads/profile_images/default.png'; ?>" 
                                         alt="Admin Profile" class="profile-image mb-4">
                                    
                                    <h4 class="mb-0"><?php echo htmlspecialchars($admin_data['first_name'] . ' ' . $admin_data['last_name']); ?></h4>
                                    <p class="text-muted mb-2">Administrator</p>
                                    
                                    <div class="mt-4">
                                        <p class="mb-1"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($admin_data['email']); ?></p>
                                        <?php if (!empty($admin_data['phone'])): ?>
                                            <p class="mb-1"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($admin_data['phone']); ?></p>
                                        <?php endif; ?>
                                        <p class="mb-1"><i class="fas fa-calendar me-2"></i> Member since <?php echo date('F Y', strtotime($admin_data['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="" enctype="multipart/form-data">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($admin_data['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($admin_data['last_name']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($admin_data['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="profile_image" class="form-label">Profile Image</label>
                                            <input type="file" class="form-control" id="profile_image" name="profile_image">
                                            <small class="text-muted">Leave empty to keep current image. Max size: 2MB. Allowed formats: JPG, PNG, GIF</small>
                                        </div>
                                        
                                        <hr class="my-4">
                                        
                                        <h5 class="mb-3">Change Password</h5>
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password">
                                            <small class="text-muted">Required only if you want to change your password</small>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Account Security</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6>Two-Factor Authentication</h6>
                                        <p class="text-muted">Enhance your account security by enabling two-factor authentication.</p>
                                        <button type="button" class="btn btn-outline-primary" disabled>
                                            <i class="fas fa-lock me-1"></i> Enable 2FA (Coming Soon)
                                        </button>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6>Login History</h6>
                                        <p class="text-muted">View your recent login activity.</p>
                                        <button type="button" class="btn btn-outline-secondary" disabled>
                                            <i class="fas fa-history me-1"></i> View Login History (Coming Soon)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Unable to load admin profile. Please try again later.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Preview profile image before upload
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-image').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
