<?php
session_start();
require_once '../includes/db_connect.php';

// Check if employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'employer') {
    header("Location: ../login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

// Get company info
$company_query = "SELECT * FROM companies WHERE user_id = ?";
$stmt = $conn->prepare($company_query);
if ($stmt === false) {
    error_log("SQL Error in employer dashboard: " . $conn->error);
} else {
    $stmt->bind_param("i", $employer_id);
    $stmt->execute();
    $company_result = $stmt->get_result();
    $has_company_profile = ($company_result->num_rows > 0);
    $company_id = 0;

    if ($has_company_profile) {
        $company_data = $company_result->fetch_assoc();
        $company_id = $company_data['id'];
    }
}

// Get job statistics
$total_jobs = 0;
$active_jobs = 0;
$pending_jobs = 0;
$total_applications = 0;

if ($has_company_profile) {
    // Total jobs
    $jobs_query = "SELECT COUNT(*) as total FROM jobs WHERE company_id = ?";
    $stmt = $conn->prepare($jobs_query);
    if ($stmt === false) {
        error_log("SQL Error in employer dashboard: " . $conn->error);
    } else {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $total_jobs = $stmt->get_result()->fetch_assoc()['total'];
    }
    
    // Active jobs
    $active_query = "SELECT COUNT(*) as active FROM jobs WHERE company_id = ? AND status = 'active'";
    $stmt = $conn->prepare($active_query);
    if ($stmt === false) {
        error_log("SQL Error in employer dashboard: " . $conn->error);
    } else {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $active_jobs = $stmt->get_result()->fetch_assoc()['active'];
    }
    
    // Pending jobs
    $pending_query = "SELECT COUNT(*) as pending FROM jobs WHERE company_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($pending_query);
    if ($stmt === false) {
        error_log("SQL Error in employer dashboard: " . $conn->error);
    } else {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $pending_jobs = $stmt->get_result()->fetch_assoc()['pending'];
    }
    
    // Total applications
    $applications_query = "SELECT COUNT(*) as applications FROM job_applications ja 
                          JOIN jobs j ON ja.job_id = j.id 
                          WHERE j.company_id = ?";
    $stmt = $conn->prepare($applications_query);
    if ($stmt === false) {
        // Handle prepare error - check if job_applications table exists
        $check_table_query = "SHOW TABLES LIKE 'job_applications'";
        $table_result = $conn->query($check_table_query);
        if ($table_result->num_rows == 0) {
            // Table doesn't exist, set applications to 0
            $total_applications = 0;
        } else {
            // Table exists but query failed for another reason
            error_log("SQL Error in employer dashboard: " . $conn->error);
            $total_applications = 0;
        }
    } else {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $total_applications = $stmt->get_result()->fetch_assoc()['applications'];
    }
}

// Get recent jobs
$recent_jobs = [];
if ($has_company_profile) {
    $recent_jobs_query = "SELECT j.*, COUNT(ja.id) as application_count 
                         FROM jobs j 
                         LEFT JOIN job_applications ja ON j.id = ja.job_id 
                         WHERE j.company_id = ? 
                         GROUP BY j.id 
                         ORDER BY j.created_at DESC 
                         LIMIT 5";
    $stmt = $conn->prepare($recent_jobs_query);
    if ($stmt === false) {
        error_log("SQL Error in employer dashboard: " . $conn->error);
    } else {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $recent_jobs_result = $stmt->get_result();
        while ($job = $recent_jobs_result->fetch_assoc()) {
            $recent_jobs[] = $job;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - JobConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="h3">Employer Dashboard</h1>
                    <p class="text-muted">Welcome back, <?php echo $_SESSION['username']; ?>!</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="post-job.php" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Post a New Job</a>
                </div>
            </div>

            <?php if (!$has_company_profile): ?>
                <div class="alert alert-warning">
                    <h4 class="alert-heading">Complete Your Profile!</h4>
                    <p>You need to complete your company profile before you can post jobs or access all features.</p>
                    <hr>
                    <a href="profile.php" class="btn btn-warning">Complete Profile</a>
                </div>
            <?php else: ?>
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Jobs</h6>
                                        <h2 class="mb-0"><?php echo $total_jobs; ?></h2>
                                    </div>
                                    <i class="fas fa-briefcase fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="manage-jobs.php" class="text-white">View All Jobs <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Active Jobs</h6>
                                        <h2 class="mb-0"><?php echo $active_jobs; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="manage-jobs.php?status=active" class="text-white">View Active Jobs <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Pending Jobs</h6>
                                        <h2 class="mb-0"><?php echo $pending_jobs; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="manage-jobs.php?status=pending" class="text-white">View Pending Jobs <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Applications</h6>
                                        <h2 class="mb-0"><?php echo $total_applications; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="applications.php" class="text-white">View Applications <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Jobs -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Jobs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_jobs) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Posted Date</th>
                                            <th>Status</th>
                                            <th>Applications</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_jobs as $job): ?>
                                            <tr>
                                                <td>
                                                    <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                        <?php echo $job['title']; ?>
                                                    </a>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($job['status'] == 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($job['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Closed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                        <?php echo $job['application_count']; ?> applications
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <a href="manage-jobs.php" class="btn btn-outline-primary">View All Jobs</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="mb-3">You haven't posted any jobs yet.</p>
                                <a href="post-job.php" class="btn btn-primary">Post Your First Job</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <a href="post-job.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-plus-circle me-2 text-primary"></i> Post a New Job
                                    </a>
                                    <a href="applications.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-users me-2 text-primary"></i> View All Applications
                                    </a>
                                    <a href="profile.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-building me-2 text-primary"></i> Update Company Profile
                                    </a>
                                    <a href="manage-jobs.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-briefcase me-2 text-primary"></i> Manage All Jobs
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Company Profile</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="company-logo me-3" style="width: 80px; height: 80px;">
                                        <img src="../uploads/company_logos/<?php echo $company_data['logo']; ?>" alt="<?php echo $company_data['company_name']; ?>">
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?php echo $company_data['company_name']; ?></h5>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-map-marker-alt me-1"></i> 
                                            <?php 
                                            $location = [];
                                            if (!empty($company_data['city'])) $location[] = $company_data['city'];
                                            if (!empty($company_data['state'])) $location[] = $company_data['state'];
                                            if (!empty($company_data['country'])) $location[] = $company_data['country'];
                                            echo !empty($location) ? implode(', ', $location) : 'Location not specified';
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Email</small>
                                        <span><?php echo $company_data['email']; ?></span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Phone</small>
                                        <span><?php echo $company_data['phone']; ?></span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Website</small>
                                        <a href="<?php echo $company_data['website']; ?>" target="_blank">
                                            <?php echo $company_data['website']; ?>
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Industry</small>
                                        <span><?php echo $company_data['industry']; ?></span>
                                    </div>
                                </div>
                                <div class="text-end mt-3">
                                    <a href="profile.php" class="btn btn-sm btn-outline-primary">Edit Profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
