<?php
require_once 'includes/db_connect.php';
session_start();

// Check for error messages
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error_message = 'There was an error processing your request. Please try again.';
            break;
        default:
            $error_message = 'An unknown error occurred.';
    }
}

// Get job ID from URL
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no job ID provided, redirect to jobs page
if ($job_id <= 0) {
    header('Location: jobs.php');
    exit;
}

// Get job details
$job_query = "SELECT j.*, c.company_name, c.logo, c.website, c.company_description, 
              cat.name as category_name 
              FROM jobs j 
              JOIN companies c ON j.company_id = c.id 
              JOIN categories cat ON j.category_id = cat.id 
              WHERE j.id = ? AND j.status = 'active'";

$stmt = $conn->prepare($job_query);
if ($stmt === false) {
    // Handle prepare error with more detailed information
    $error_message = "Database error: " . $conn->error;
    // Log the error for debugging
    error_log("SQL Prepare Error in job-details.php: " . $conn->error);
    // Don't redirect, display the error on the page
    // header('Location: jobs.php?error=1');
    // exit;
} else {
    $stmt->bind_param('i', $job_id);
    if (!$stmt->execute()) {
        // Handle execution error
        $error_message = "Database error: " . $stmt->error;
        // Log the error for debugging
        error_log("SQL Execute Error in job-details.php: " . $stmt->error);
        // Don't redirect, display the error on the page
        // header('Location: jobs.php?error=1');
        // exit;
    } else {
        $result = $stmt->get_result();

        // If job not found, redirect to jobs page
        if ($result->num_rows === 0) {
            header('Location: jobs.php');
            exit;
        }

        $job = $result->fetch_assoc();
    }
}

// Resolve job_seeker_id (if logged-in user is a jobseeker)
$job_seeker_id = null;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'jobseeker') {
    $q = $conn->prepare('SELECT id FROM job_seekers WHERE user_id = ?');
    if ($q) {
        $uid = (int)$_SESSION['user_id'];
        $q->bind_param('i', $uid);
        if ($q->execute()) {
            $rs = $q->get_result();
            if ($rs && $rs->num_rows > 0) {
                $job_seeker_id = (int)$rs->fetch_assoc()['id'];
            }
        }
    }
}

// Process job application form
$application_submitted = false;
$application_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $application_error_message = 'Please log in to apply for this job.';
    } else {
        // Get form data
        $first_name = trim($_POST['first_name']);
        $surname = trim($_POST['surname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $location = trim($_POST['location']);
        $cover_letter = trim($_POST['cover_letter']);
        $user_id = $_SESSION['user_id'];
        
        // Validate form data
        if (empty($first_name) || empty($surname) || empty($email) || empty($phone)) {
            $application_error_message = 'Please fill in all required fields.';
        } else {
            // Handle CV upload
            $cv_filename = null;
            if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
                $allowed_extensions = ['pdf', 'doc', 'docx'];
                $file_extension = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $application_error_message = 'Only PDF, DOC, and DOCX files are allowed for CV.';
                } else {
                    // Create upload directory if it doesn't exist
                    $upload_dir = 'uploads/cv/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $cv_filename = 'cv_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $cv_filename;
                    
                    // Move uploaded file
                    if (!move_uploaded_file($_FILES['cv']['tmp_name'], $upload_path)) {
                        $application_error_message = 'Failed to upload CV. Please try again.';
                    }
                }
            } else {
                $application_error_message = 'Please upload your CV.';
            }
            
            // If no errors, insert application into database
            if (empty($application_error_message)) {
                $application_query = "INSERT INTO applications (job_id, job_seeker_id, first_name, surname, email, phone, location, cv_file, cover_letter, status, applied_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                
                $stmt = $conn->prepare($application_query);
                if ($stmt === false) {
                    // Handle prepare error with more detailed information
                    $error_message = "Database error: " . $conn->error;
                    // Log the error for debugging
                    error_log("SQL Prepare Error in job-details.php: " . $conn->error);
                    // Don't redirect, display the error on the page
                    // header('Location: jobs.php?error=1');
                    // exit;
                } else {
                    $cv_path = 'uploads/cv/' . $cv_filename;
                    $stmt->bind_param('iisssssss', $job_id, $job_seeker_id, $first_name, $surname, $email, $phone, $location, $cv_path, $cover_letter);
                    
                    if (!$stmt->execute()) {
                        // Handle execution error
                        $error_message = "Database error: " . $stmt->error;
                        // Log the error for debugging
                        error_log("SQL Execute Error in job-details.php: " . $stmt->error);
                        // Don't redirect, display the error on the page
                        // header('Location: jobs.php?error=1');
                        // exit;
                    }
                    $application_submitted = true;
                }
            }
        }
    }
}

// Check if user has already applied for this job
$has_applied = false;
if (isset($_SESSION['user_id'])) {
    if ($job_seeker_id !== null) {
        $check_application = "SELECT id FROM applications WHERE job_id = ? AND job_seeker_id = ?";
        $stmt = $conn->prepare($check_application);
        if ($stmt === false) {
            // Handle prepare error with more detailed information
            $error_message = "Database error: " . $conn->error;
            error_log("SQL Prepare Error in job-details.php: " . $conn->error);
        } else {
            if ($stmt->bind_param('ii', $job_id, $job_seeker_id)) {
                if (!$stmt->execute()) {
                    $error_message = "Database error: " . $stmt->error;
                    error_log("SQL Execute Error in job-details.php: " . $stmt->error);
                } else {
                    $has_applied = ($stmt->get_result()->num_rows > 0);
                }
            } else {
                $error_message = "Database error: " . $stmt->error;
                error_log("SQL Bind Param Error in job-details.php: " . $stmt->error);
            }
        }
    } else {
        // No profile yet; skip duplicate enforcement
        $has_applied = false;
    }
} else {
    // Not logged in
    $has_applied = false;
}

// Get similar jobs
$similar_jobs_query = "SELECT j.id, j.title, j.location, j.job_type, c.company_name, c.logo 
                      FROM jobs j 
                      JOIN companies c ON j.company_id = c.id 
                      WHERE j.category_id = ? AND j.id != ? AND j.status = 'active' 
                      ORDER BY j.created_at DESC LIMIT 3";

$stmt = $conn->prepare($similar_jobs_query);
if ($stmt === false) {
    // Handle prepare error with more detailed information
    $error_message = "Database error: " . $conn->error;
    // Log the error for debugging
    error_log("SQL Prepare Error in job-details.php: " . $conn->error);
    // Don't redirect, display the error on the page
    // header('Location: jobs.php?error=1');
    // exit;
} else {
    if ($stmt->bind_param('ii', $job['category_id'], $job_id)) {
        if (!$stmt->execute()) {
            // Handle execution error
            $error_message = "Database error: " . $stmt->error;
            // Log the error for debugging
            error_log("SQL Execute Error in job-details.php: " . $stmt->error);
            // Don't redirect, display the error on the page
            // header('Location: jobs.php?error=1');
            // exit;
        }
        $similar_jobs = $stmt->get_result();
    } else {
        $error_message = "Database error: " . $stmt->error;
        // Log the error for debugging
        error_log("SQL Bind Param Error in job-details.php: " . $stmt->error);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - JobConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Custom styles for job details page */
        .company-logo-sm {
            max-width: 40px;
            max-height: 40px;
            object-fit: contain;
        }
        .company-logo-md {
            max-width: 100px;
            max-height: 100px;
            object-fit: contain;
        }
        /* Ensure footer stays at bottom */
        main {
            min-height: calc(100vh - 300px); /* Adjust based on footer height */
            overflow: hidden;
        }
        .job-details {
            overflow: visible;
        }
        /* Fix for similar jobs list */
        .list-group-item {
            overflow: hidden;
        }
        .list-group {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <main>
        <!-- Job Details Header -->
        <section class="page-header bg-light py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-3">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="jobs.php">Jobs</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($job['title']); ?></li>
                            </ol>
                        </nav>
                        <h1 class="mb-2"><?php echo htmlspecialchars($job['title']); ?></h1>
                        <div class="d-flex flex-wrap align-items-center mb-2">
                            <img src="<?php echo file_exists('uploads/company_logos/' . $job['logo']) ? 'uploads/company_logos/' . htmlspecialchars($job['logo']) : 'assets/img/default-company.png'; ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="company-logo-sm me-2" style="max-width: 40px; max-height: 40px; object-fit: contain;">
                            <span class="me-3"><?php echo htmlspecialchars($job['company_name']); ?></span>
                            <span class="location me-3"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="job-type me-3 <?php echo strtolower($job['job_type']); ?>">
                                <i class="fas fa-briefcase me-1"></i> <?php echo $job['job_type']; ?>
                            </span>
                            <span class="deadline me-3">
                                <i class="far fa-clock me-1"></i> Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <?php if (!$has_applied && !$application_submitted): ?>
                            <a href="#apply-form" class="btn btn-primary">Apply Now</a>
                        <?php elseif ($application_submitted): ?>
                            <button class="btn btn-success" disabled>Application Submitted</button>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Already Applied</button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary ms-2" onclick="window.print()">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Job Details Content -->
        <section class="job-details py-5">
            <div class="container">
                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- Job Description -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Job Description</h2>
                            </div>
                            <div class="card-body">
                                <?php echo $job['description']; ?>
                            </div>
                        </div>

                        <!-- Job Requirements -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Requirements</h2>
                            </div>
                            <div class="card-body">
                                <?php echo $job['requirements']; ?>
                            </div>
                        </div>

                        <!-- Job Benefits -->
                        <?php if (!empty($job['benefits'])): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Benefits</h2>
                            </div>
                            <div class="card-body">
                                <?php echo $job['benefits']; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Application Form -->
                        <div class="card shadow-sm mb-4" id="apply-form">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Apply for this Job</h2>
                            </div>
                            <div class="card-body">
                                <?php if ($application_submitted): ?>
                                    <div class="alert alert-success">
                                        <h4 class="alert-heading">Application Submitted!</h4>
                                        <p>Thank you for applying for this position. The employer will review your application and contact you if they are interested.</p>
                                    </div>
                                <?php elseif ($has_applied): ?>
                                    <div class="alert alert-info">
                                        <h4 class="alert-heading">You've Already Applied</h4>
                                        <p>You have already submitted an application for this job. You can check the status of your application in your dashboard.</p>
                                    </div>
                                <?php else: ?>
                                    <?php if (!empty($application_error_message)): ?>
                                        <div class="alert alert-danger"><?php echo $application_error_message; ?></div>
                                    <?php endif; ?>

                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                        <div class="alert alert-warning">
                                            <p>You need to <a href="login.php">log in</a> or <a href="register.php">register</a> to apply for this job.</p>
                                        </div>
                                    <?php else: ?>
                                        <form action="job-details.php?id=<?php echo $job_id; ?>#apply-form" method="POST" enctype="multipart/form-data">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="surname" class="form-label">Surname <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="surname" name="surname" required>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control" id="email" name="email" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="location" name="location" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="cv" class="form-label">Upload CV (PDF, DOC, DOCX) <span class="text-danger">*</span></label>
                                                <input type="file" class="form-control" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="cover_letter" class="form-label">Cover Letter</label>
                                                <textarea class="form-control" id="cover_letter" name="cover_letter" rows="5"></textarea>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="terms" required>
                                                <label class="form-check-label" for="terms">
                                                    I agree to the <a href="#">terms and conditions</a> and <a href="#">privacy policy</a>
                                                </label>
                                            </div>
                                            <button type="submit" name="apply_job" class="btn btn-primary">Submit Application</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Job Summary -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Job Summary</h2>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled job-info-list">
                                    <li class="mb-2">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        <strong>Posted:</strong> <?php echo date('M d, Y', strtotime($job['created_at'])); ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-briefcase me-2"></i>
                                        <strong>Job Type:</strong> <?php echo $job['job_type']; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-tags me-2"></i>
                                        <strong>Category:</strong> <?php echo $job['category_name']; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        <strong>Salary:</strong>
                                        <?php if (!empty($job['min_salary']) && !empty($job['max_salary'])): ?>
                                            GHS <?php echo number_format($job['min_salary']); ?> - GHS <?php echo number_format($job['max_salary']); ?>
                                        <?php elseif (!empty($job['min_salary'])): ?>
                                            From GHS <?php echo number_format($job['min_salary']); ?>
                                        <?php elseif (!empty($job['max_salary'])): ?>
                                            Up to GHS <?php echo number_format($job['max_salary']); ?>
                                        <?php else: ?>
                                            Not specified
                                        <?php endif; ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="far fa-clock me-2"></i>
                                        <strong>Deadline:</strong> <?php echo date('M d, Y', strtotime($job['deadline'])); ?>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Company Info -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">About the Company</h2>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <img src="<?php echo file_exists('uploads/company_logos/' . $job['logo']) ? 'uploads/company_logos/' . htmlspecialchars($job['logo']) : 'assets/img/default-company.png'; ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="company-logo-md">
                                    <h3 class="h6 mt-2"><?php echo htmlspecialchars($job['company_name']); ?></h3>
                                </div>
                                <div class="company-description">
                                    <?php echo substr($job['company_description'], 0, 200) . (strlen($job['company_description']) > 200 ? '...' : ''); ?>
                                </div>
                                <?php if (!empty($job['website'])): ?>
                                <div class="mt-3">
                                    <a href="<?php echo $job['website']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-globe me-1"></i> Visit Website
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Similar Jobs -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Similar Jobs</h2>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($similar_jobs->num_rows > 0): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while ($similar_job = $similar_jobs->fetch_assoc()): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0">
                                                        <img src="<?php echo file_exists('uploads/company_logos/' . $similar_job['logo']) ? 'uploads/company_logos/' . htmlspecialchars($similar_job['logo']) : 'assets/img/default-company.png'; ?>" alt="<?php echo htmlspecialchars($similar_job['company_name']); ?>" class="company-logo-sm" style="max-width: 30px; max-height: 30px; object-fit: contain;">
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h3 class="h6 mb-1">
                                                            <a href="job-details.php?id=<?php echo $similar_job['id']; ?>" class="text-dark text-decoration-none">
                                                                <?php echo $similar_job['title']; ?>
                                                            </a>
                                                        </h3>
                                                        <div class="small text-muted">
                                                            <span class="me-2"><?php echo htmlspecialchars($similar_job['company_name']); ?></span>
                                                            <span class="me-2"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($similar_job['location']); ?></span>
                                                            <span><i class="fas fa-briefcase"></i> <?php echo $similar_job['job_type']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="p-3 text-center">
                                        <p class="text-muted">No similar jobs found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Share Job -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h5 mb-0">Share This Job</h2>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-center">
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-primary me-2">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($job['title'] . ' at ' . $job['company_name']); ?>" target="_blank" class="btn btn-outline-info me-2">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&title=<?php echo urlencode($job['title']); ?>" target="_blank" class="btn btn-outline-secondary me-2">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                    <a href="mailto:?subject=<?php echo urlencode('Job Opportunity: ' . $job['title']); ?>&body=<?php echo urlencode('Check out this job: ' . 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-danger">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
