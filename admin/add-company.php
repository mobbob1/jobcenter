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

// Get users for dropdown (employers only)
$users_query = "SELECT u.id, u.username, u.email 
               FROM users u 
               LEFT JOIN companies c ON u.id = c.user_id 
               WHERE u.user_type = 'employer' AND c.id IS NULL 
               ORDER BY u.username";
$users_result = $conn->query($users_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $company_name = trim($_POST['company_name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $company_size = trim($_POST['company_size'] ?? '');
    $founded_year = trim($_POST['founded_year'] ?? '');
    $company_description = trim($_POST['company_description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $facebook_url = trim($_POST['facebook_url'] ?? '');
    $twitter_url = trim($_POST['twitter_url'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    
    // Required fields validation
    if (empty($user_id)) {
        $errors[] = "Please select a user";
    }
    
    if (empty($company_name)) {
        $errors[] = "Company name is required";
    }
    
    // Check if user already has a company
    if (!empty($user_id)) {
        $check_query = "SELECT id FROM companies WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $errors[] = "Selected user already has a company associated with them";
            }
        }
    }
    
    // Email validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // URL validations
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid website URL";
    }
    
    if (!empty($facebook_url) && !filter_var($facebook_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid Facebook URL";
    }
    
    if (!empty($twitter_url) && !filter_var($twitter_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid Twitter URL";
    }
    
    if (!empty($linkedin_url) && !filter_var($linkedin_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid LinkedIn URL";
    }
    
    // Handle logo upload
    $logo = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['logo']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Logo must be a JPG, PNG, or GIF image";
        } else {
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($_FILES['logo']['size'] > $max_size) {
                $errors[] = "Logo size must be less than 2MB";
            } else {
                $upload_dir = '../uploads/companies/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['logo']['name']);
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    $logo = $file_name;
                } else {
                    $errors[] = "Failed to upload logo";
                }
            }
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $insert_query = "INSERT INTO companies (
            user_id, company_name, industry, company_size, founded_year, 
            company_description, website, logo, phone, email, 
            address, city, state, country, postal_code, 
            facebook_url, twitter_url, linkedin_url, is_featured, is_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $conn->prepare($insert_query);
        if ($insert_stmt) {
            $insert_stmt->bind_param(
                "issssssssssssssssiii",
                $user_id, $company_name, $industry, $company_size, $founded_year,
                $company_description, $website, $logo, $phone, $email,
                $address, $city, $state, $country, $postal_code,
                $facebook_url, $twitter_url, $linkedin_url, $is_featured, $is_verified
            );
            
            if ($insert_stmt->execute()) {
                $success = true;
                // Reset form fields
                $user_id = $company_name = $industry = $company_size = $founded_year = '';
                $company_description = $website = $phone = $email = $address = '';
                $city = $state = $country = $postal_code = '';
                $facebook_url = $twitter_url = $linkedin_url = '';
                $is_featured = $is_verified = 0;
            } else {
                $errors[] = "Error adding company: " . $insert_stmt->error;
            }
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }
    }
}

$page_title = "Add New Company";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JobConnect Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
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
                        <a href="manage-companies.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Companies
                        </a>
                    </div>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Company added successfully!
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
                        <form action="add-company.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="card-title">Basic Information</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="user_id" class="form-label">User Account <span class="text-danger">*</span></label>
                                    <select class="form-select" id="user_id" name="user_id" required>
                                        <option value="">Select User</option>
                                        <?php if ($users_result && $users_result->num_rows > 0): ?>
                                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                                <option value="<?php echo $user['id']; ?>" <?php echo (isset($user_id) && $user_id == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                    <div class="form-text">Select the employer user account to associate with this company</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo isset($company_name) ? htmlspecialchars($company_name) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="industry" class="form-label">Industry</label>
                                    <input type="text" class="form-control" id="industry" name="industry" value="<?php echo isset($industry) ? htmlspecialchars($industry) : ''; ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="company_size" class="form-label">Company Size</label>
                                    <select class="form-select" id="company_size" name="company_size">
                                        <option value="">Select Size</option>
                                        <option value="1-10" <?php echo (isset($company_size) && $company_size == '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                        <option value="11-50" <?php echo (isset($company_size) && $company_size == '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                        <option value="51-200" <?php echo (isset($company_size) && $company_size == '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                        <option value="201-500" <?php echo (isset($company_size) && $company_size == '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                        <option value="501-1000" <?php echo (isset($company_size) && $company_size == '501-1000') ? 'selected' : ''; ?>>501-1000 employees</option>
                                        <option value="1001+" <?php echo (isset($company_size) && $company_size == '1001+') ? 'selected' : ''; ?>>1001+ employees</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="founded_year" class="form-label">Founded Year</label>
                                    <input type="number" class="form-control" id="founded_year" name="founded_year" min="1800" max="<?php echo date('Y'); ?>" value="<?php echo isset($founded_year) ? htmlspecialchars($founded_year) : ''; ?>">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="company_description" class="form-label">Company Description</label>
                                    <textarea class="form-control summernote" id="company_description" name="company_description" rows="5"><?php echo isset($company_description) ? htmlspecialchars($company_description) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="logo" class="form-label">Company Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png,image/gif">
                                    <div class="form-text">Max size: 2MB. Allowed formats: JPG, PNG, GIF</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="website" class="form-label">Website URL</label>
                                    <input type="url" class="form-control" id="website" name="website" value="<?php echo isset($website) ? htmlspecialchars($website) : ''; ?>" placeholder="https://example.com">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="card-title">Contact Information</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($city) ? htmlspecialchars($city) : ''; ?>">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="state" name="state" value="<?php echo isset($state) ? htmlspecialchars($state) : ''; ?>">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" value="<?php echo isset($country) ? htmlspecialchars($country) : ''; ?>">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo isset($postal_code) ? htmlspecialchars($postal_code) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="card-title">Social Media</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="facebook_url" class="form-label">Facebook URL</label>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?php echo isset($facebook_url) ? htmlspecialchars($facebook_url) : ''; ?>" placeholder="https://facebook.com/company">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="twitter_url" class="form-label">Twitter URL</label>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?php echo isset($twitter_url) ? htmlspecialchars($twitter_url) : ''; ?>" placeholder="https://twitter.com/company">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                                    <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?php echo isset($linkedin_url) ? htmlspecialchars($linkedin_url) : ''; ?>" placeholder="https://linkedin.com/company/name">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="card-title">Status</h5>
                                    <hr>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_verified" name="is_verified" <?php echo (isset($is_verified) && $is_verified) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_verified">
                                            Verified Company
                                        </label>
                                        <div class="form-text">Verified companies appear with a verification badge</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?php echo (isset($is_featured) && $is_featured) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_featured">
                                            Featured Company
                                        </label>
                                        <div class="form-text">Featured companies appear at the top of listings</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="reset" class="btn btn-secondary">Reset</button>
                                <button type="submit" class="btn btn-primary">Add Company</button>
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
    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Initialize Summernote
        $(document).ready(function() {
            $('.summernote').summernote({
                placeholder: 'Enter company description here...',
                tabsize: 2,
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
            
            // Form validation
            (function () {
                'use strict'
                
                // Fetch all the forms we want to apply custom Bootstrap validation styles to
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
        });
    </script>
</body>
</html>
