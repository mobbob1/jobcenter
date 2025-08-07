<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Get current settings
function get_settings($conn) {
    $settings = [];
    $query = "SELECT * FROM settings";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings;
}

// Check if settings table exists, create if not
$check_table_query = "SHOW TABLES LIKE 'settings'";
$table_exists = $conn->query($check_table_query);

if ($table_exists && $table_exists->num_rows == 0) {
    // Create settings table
    $create_table_query = "CREATE TABLE settings (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table_query) === TRUE) {
        // Insert default settings
        $default_settings = [
            ['site_name', 'Job Portal', 'Name of the website'],
            ['site_email', 'admin@example.com', 'Admin email address'],
            ['jobs_per_page', '10', 'Number of jobs to display per page'],
            ['allow_registration', '1', 'Allow new user registrations'],
            ['enable_job_alerts', '1', 'Enable job alert emails'],
            ['maintenance_mode', '0', 'Put site in maintenance mode'],
            ['footer_text', '© 2023 Job Portal. All rights reserved.', 'Footer copyright text'],
            ['social_facebook', '', 'Facebook page URL'],
            ['social_twitter', '', 'Twitter profile URL'],
            ['social_linkedin', '', 'LinkedIn profile URL']
        ];
        
        $insert_query = "INSERT INTO settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        
        if ($insert_stmt) {
            $insert_stmt->bind_param("sss", $key, $value, $description);
            
            foreach ($default_settings as $setting) {
                $key = $setting[0];
                $value = $setting[1];
                $description = $setting[2];
                $insert_stmt->execute();
            }
        }
    } else {
        $error_message = "Error creating settings table: " . $conn->error;
    }
}

// Get current settings
$settings = get_settings($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general'])) {
        // Update general settings
        $site_name = trim($_POST['site_name']);
        $site_email = trim($_POST['site_email']);
        $jobs_per_page = (int)$_POST['jobs_per_page'];
        $footer_text = trim($_POST['footer_text']);
        
        // Validate
        $validation_errors = [];
        
        if (empty($site_name)) {
            $validation_errors[] = "Site name cannot be empty.";
        }
        
        if (empty($site_email)) {
            $validation_errors[] = "Site email cannot be empty.";
        } elseif (!filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Please enter a valid email address.";
        }
        
        if ($jobs_per_page < 1) {
            $validation_errors[] = "Jobs per page must be at least 1.";
        }
        
        if (empty($validation_errors)) {
            // Update settings
            $update_query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $update_stmt = $conn->prepare($update_query);
            
            if ($update_stmt) {
                $update_stmt->bind_param("ss", $key, $value);
                
                $settings_to_update = [
                    'site_name' => $site_name,
                    'site_email' => $site_email,
                    'jobs_per_page' => $jobs_per_page,
                    'footer_text' => $footer_text
                ];
                
                foreach ($settings_to_update as $key => $value) {
                    $update_stmt->execute();
                }
                
                $success_message = "General settings updated successfully.";
                $settings = get_settings($conn); // Refresh settings
            } else {
                $error_message = "Error updating settings: " . $conn->error;
            }
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    } elseif (isset($_POST['update_features'])) {
        // Update feature settings
        $allow_registration = isset($_POST['allow_registration']) ? 1 : 0;
        $enable_job_alerts = isset($_POST['enable_job_alerts']) ? 1 : 0;
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        // Update settings
        $update_query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $update_stmt = $conn->prepare($update_query);
        
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $key, $value);
            
            $settings_to_update = [
                'allow_registration' => $allow_registration,
                'enable_job_alerts' => $enable_job_alerts,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($settings_to_update as $key => $value) {
                $update_stmt->execute();
            }
            
            $success_message = "Feature settings updated successfully.";
            $settings = get_settings($conn); // Refresh settings
        } else {
            $error_message = "Error updating settings: " . $conn->error;
        }
    } elseif (isset($_POST['update_social'])) {
        // Update social media settings
        $social_facebook = trim($_POST['social_facebook']);
        $social_twitter = trim($_POST['social_twitter']);
        $social_linkedin = trim($_POST['social_linkedin']);
        
        // Validate URLs if provided
        $validation_errors = [];
        
        if (!empty($social_facebook) && !filter_var($social_facebook, FILTER_VALIDATE_URL)) {
            $validation_errors[] = "Please enter a valid Facebook URL.";
        }
        
        if (!empty($social_twitter) && !filter_var($social_twitter, FILTER_VALIDATE_URL)) {
            $validation_errors[] = "Please enter a valid Twitter URL.";
        }
        
        if (!empty($social_linkedin) && !filter_var($social_linkedin, FILTER_VALIDATE_URL)) {
            $validation_errors[] = "Please enter a valid LinkedIn URL.";
        }
        
        if (empty($validation_errors)) {
            // Update settings
            $update_query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $update_stmt = $conn->prepare($update_query);
            
            if ($update_stmt) {
                $update_stmt->bind_param("ss", $key, $value);
                
                $settings_to_update = [
                    'social_facebook' => $social_facebook,
                    'social_twitter' => $social_twitter,
                    'social_linkedin' => $social_linkedin
                ];
                
                foreach ($settings_to_update as $key => $value) {
                    $update_stmt->execute();
                }
                
                $success_message = "Social media settings updated successfully.";
                $settings = get_settings($conn); // Refresh settings
            } else {
                $error_message = "Error updating settings: " . $conn->error;
            }
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }
}

$page_title = "System Settings";
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
        
        .settings-section {
            margin-bottom: 2rem;
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
                
                <div class="row">
                    <div class="col-12">
                        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                    <i class="fas fa-cog me-2"></i> General
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab" aria-controls="features" aria-selected="false">
                                    <i class="fas fa-toggle-on me-2"></i> Features
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab" aria-controls="social" aria-selected="false">
                                    <i class="fas fa-share-alt me-2"></i> Social Media
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="settingsTabsContent">
                            <!-- General Settings Tab -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">General Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="site_name" class="form-label">Site Name</label>
                                                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Job Portal'); ?>" required>
                                                <small class="text-muted">The name of your website</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="site_email" class="form-label">Site Email</label>
                                                <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email'] ?? 'admin@example.com'); ?>" required>
                                                <small class="text-muted">Used for system notifications and contact form</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="jobs_per_page" class="form-label">Jobs Per Page</label>
                                                <input type="number" class="form-control" id="jobs_per_page" name="jobs_per_page" value="<?php echo htmlspecialchars($settings['jobs_per_page'] ?? '10'); ?>" min="1" required>
                                                <small class="text-muted">Number of jobs to display per page</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="footer_text" class="form-label">Footer Text</label>
                                                <textarea class="form-control" id="footer_text" name="footer_text" rows="2"><?php echo htmlspecialchars($settings['footer_text'] ?? '© 2023 Job Portal. All rights reserved.'); ?></textarea>
                                                <small class="text-muted">Copyright text displayed in the footer</small>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="submit" name="update_general" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i> Save General Settings
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Features Settings Tab -->
                            <div class="tab-pane fade" id="features" role="tabpanel" aria-labelledby="features-tab">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">Feature Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3 form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="allow_registration" name="allow_registration" <?php echo (isset($settings['allow_registration']) && $settings['allow_registration'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allow_registration">Allow New User Registrations</label>
                                                <div class="text-muted small">If disabled, new users cannot register on the site</div>
                                            </div>
                                            
                                            <div class="mb-3 form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enable_job_alerts" name="enable_job_alerts" <?php echo (isset($settings['enable_job_alerts']) && $settings['enable_job_alerts'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_job_alerts">Enable Job Alert Emails</label>
                                                <div class="text-muted small">Send email notifications for new job postings</div>
                                            </div>
                                            
                                            <div class="mb-3 form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="maintenance_mode">Maintenance Mode</label>
                                                <div class="text-muted small">If enabled, the site will display a maintenance message to visitors</div>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="submit" name="update_features" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i> Save Feature Settings
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Social Media Settings Tab -->
                            <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">Social Media Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="social_facebook" class="form-label">
                                                    <i class="fab fa-facebook text-primary me-2"></i> Facebook URL
                                                </label>
                                                <input type="url" class="form-control" id="social_facebook" name="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="social_twitter" class="form-label">
                                                    <i class="fab fa-twitter text-info me-2"></i> Twitter URL
                                                </label>
                                                <input type="url" class="form-control" id="social_twitter" name="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="social_linkedin" class="form-label">
                                                    <i class="fab fa-linkedin text-primary me-2"></i> LinkedIn URL
                                                </label>
                                                <input type="url" class="form-control" id="social_linkedin" name="social_linkedin" value="<?php echo htmlspecialchars($settings['social_linkedin'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="submit" name="update_social" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i> Save Social Media Settings
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
