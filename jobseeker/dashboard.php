<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connect.php';

// Check if jobseeker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

// Get jobseeker info
$jobseeker_query = "SELECT * FROM jobseekers WHERE user_id = ?";
$stmt = $conn->prepare($jobseeker_query);
$stmt->bind_param("i", $jobseeker_id);
$stmt->execute();
$jobseeker_result = $stmt->get_result();
$has_profile = ($jobseeker_result->num_rows > 0);
$jobseeker_data = [];

if ($has_profile) {
    $jobseeker_data = $jobseeker_result->fetch_assoc();
}

// Get job application statistics
$total_applications = 0;
$pending_applications = 0;
$viewed_applications = 0;
$shortlisted_applications = 0;

if ($has_profile) {
    // Total applications
    $applications_query = "SELECT COUNT(*) as total FROM job_applications WHERE jobseeker_id = ?";
    $stmt = $conn->prepare($applications_query);
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $total_applications = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pending applications
    $pending_query = "SELECT COUNT(*) as pending FROM job_applications WHERE jobseeker_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($pending_query);
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $pending_applications = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Viewed applications
    $viewed_query = "SELECT COUNT(*) as viewed FROM job_applications WHERE jobseeker_id = ? AND status = 'viewed'";
    $stmt = $conn->prepare($viewed_query);
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $viewed_applications = $stmt->get_result()->fetch_assoc()['viewed'];
    
    // Shortlisted applications
    $shortlisted_query = "SELECT COUNT(*) as shortlisted FROM job_applications WHERE jobseeker_id = ? AND status = 'shortlisted'";
    $stmt = $conn->prepare($shortlisted_query);
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $shortlisted_applications = $stmt->get_result()->fetch_assoc()['shortlisted'];
}

// Get recent applications
$recent_applications = [];
if ($has_profile) {
    $recent_applications_query = "SELECT ja.*, j.title as job_title, j.location as job_location, c.company_name, c.logo as company_logo 
                               FROM job_applications ja 
                               JOIN jobs j ON ja.job_id = j.id 
                               JOIN companies c ON j.company_id = c.id 
                               WHERE ja.jobseeker_id = ? 
                               ORDER BY ja.applied_date DESC 
                               LIMIT 5";
    $stmt = $conn->prepare($recent_applications_query);
    $stmt->bind_param("i", $jobseeker_id);
    $stmt->execute();
    $recent_applications_result = $stmt->get_result();
    while ($application = $recent_applications_result->fetch_assoc()) {
        $recent_applications[] = $application;
    }
}

// Get recommended jobs
$recommended_jobs = [];
$recommended_jobs_query = "SELECT j.*, c.company_name, c.logo as company_logo 
                         FROM jobs j 
                         JOIN companies c ON j.company_id = c.id 
                         WHERE j.status = 'active' 
                         ORDER BY j.created_at DESC 
                         LIMIT 3";
$stmt = $conn->prepare($recommended_jobs_query);
$stmt->execute();
$recommended_jobs_result = $stmt->get_result();
while ($job = $recommended_jobs_result->fetch_assoc()) {
    $recommended_jobs[] = $job;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobseeker Dashboard - JobConnect</title>
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
                    <h1 class="h3">Jobseeker Dashboard</h1>
                    <p class="text-muted">Welcome back, <?php echo $_SESSION['username']; ?>!</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="my-resume.php" class="btn btn-primary"><i class="fas fa-file-alt me-2"></i>Manage Resume</a>
                </div>
            </div>

            <?php if (!$has_profile): ?>
                <div class="alert alert-warning">
                    <h4 class="alert-heading">Complete Your Profile!</h4>
                    <p>You need to complete your profile to apply for jobs and access all features.</p>
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
                                        <h6 class="text-uppercase">Total Applications</h6>
                                        <h2 class="mb-0"><?php echo $total_applications; ?></h2>
                                    </div>
                                    <i class="fas fa-paper-plane fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="applications.php" class="text-white">View All Applications <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Pending</h6>
                                        <h2 class="mb-0"><?php echo $pending_applications; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="applications.php?status=pending" class="text-white">View Pending <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Viewed</h6>
                                        <h2 class="mb-0"><?php echo $viewed_applications; ?></h2>
                                    </div>
                                    <i class="fas fa-eye fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="applications.php?status=viewed" class="text-white">View Details <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Shortlisted</h6>
                                        <h2 class="mb-0"><?php echo $shortlisted_applications; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="applications.php?status=shortlisted" class="text-white">View Shortlisted <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Applications -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_applications) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Company</th>
                                            <th>Applied Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_applications as $application): ?>
                                            <tr>
                                                <td>
                                                    <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="text-decoration-none">
                                                        <?php echo $application['job_title']; ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="company-logo-sm me-2">
                                                            <img src="../uploads/company_logos/<?php echo $application['company_logo']; ?>" alt="<?php echo $application['company_name']; ?>">
                                                        </div>
                                                        <?php echo $application['company_name']; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($application['applied_date'])); ?></td>
                                                <td>
                                                    <?php if ($application['status'] == 'pending'): ?>
                                                        <span class="badge bg-info">Pending</span>
                                                    <?php elseif ($application['status'] == 'viewed'): ?>
                                                        <span class="badge bg-warning text-dark">Viewed</span>
                                                    <?php elseif ($application['status'] == 'shortlisted'): ?>
                                                        <span class="badge bg-success">Shortlisted</span>
                                                    <?php elseif ($application['status'] == 'rejected'): ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($application['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view-application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <a href="applications.php" class="btn btn-outline-primary">View All Applications</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="mb-3">You haven't applied to any jobs yet.</p>
                                <a href="../jobs.php" class="btn btn-primary">Browse Jobs</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recommended Jobs and Profile -->
                <div class="row">
                    <div class="col-md-7">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Recommended Jobs</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($recommended_jobs) > 0): ?>
                                    <?php foreach ($recommended_jobs as $job): ?>
                                        <div class="job-card mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="company-logo-sm me-3">
                                                    <img src="../uploads/company_logos/<?php echo $job['company_logo']; ?>" alt="<?php echo $job['company_name']; ?>">
                                                </div>
                                                <div>
                                                    <h6 class="mb-0">
                                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                            <?php echo $job['title']; ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted"><?php echo $job['company_name']; ?></small>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap job-tags mb-2">
                                                <span class="badge bg-light text-dark me-2 mb-1">
                                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo $job['location']; ?>
                                                </span>
                                                <span class="badge bg-light text-dark me-2 mb-1">
                                                    <i class="fas fa-briefcase me-1"></i> <?php echo $job['job_type']; ?>
                                                </span>
                                                <span class="badge bg-light text-dark me-2 mb-1">
                                                    <i class="fas fa-money-bill-wave me-1"></i> <?php echo $job['salary_range']; ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">Posted <?php echo date('M d, Y', strtotime($job['created_at'])); ?></small>
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">Apply Now</a>
                                            </div>
                                        </div>
                                        <?php if (!$loop->last): ?><hr><?php endif; ?>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="../jobs.php" class="btn btn-outline-primary">Browse All Jobs</a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p>No recommended jobs available at the moment.</p>
                                        <a href="../jobs.php" class="btn btn-primary">Browse All Jobs</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">My Profile</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="profile-img me-3">
                                        <?php if (!empty($jobseeker_data['profile_picture'])): ?>
                                            <img src="../uploads/profile_pictures/<?php echo $jobseeker_data['profile_picture']; ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <div class="default-profile-img">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?php echo $jobseeker_data['first_name'] . ' ' . $jobseeker_data['last_name']; ?></h5>
                                        <p class="text-muted mb-0"><?php echo $jobseeker_data['job_title']; ?></p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Email</small>
                                        <span><?php echo $jobseeker_data['email']; ?></span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Phone</small>
                                        <span><?php echo $jobseeker_data['phone']; ?></span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Location</small>
                                        <span><?php echo $jobseeker_data['location']; ?></span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <small class="text-muted d-block">Experience</small>
                                        <span><?php echo $jobseeker_data['experience']; ?> years</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="profile-completion mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Profile Completion</span>
                                        <span>80%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 80%" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <div class="btn-group">
                                        <a href="profile.php" class="btn btn-outline-primary">Edit Profile</a>
                                        <a href="my-resume.php" class="btn btn-outline-success">Manage Resume</a>
                                    </div>
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
