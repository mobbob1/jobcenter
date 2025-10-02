<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Dashboard statistics
$stats = [
    'total_users' => 0,
    'total_employers' => 0,
    'total_job_seekers' => 0,
    'total_jobs' => 0,
    'active_jobs' => 0,
    'pending_jobs' => 0,
    'total_applications' => 0,
    'recent_users' => [],
    'recent_jobs' => [],
    'recent_applications' => []
];

// Get user counts
$user_query = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN user_type = 'employer' THEN 1 ELSE 0 END) as total_employers,
                SUM(CASE WHEN user_type = 'jobseeker' THEN 1 ELSE 0 END) as total_job_seekers
               FROM users";
$user_result = $conn->query($user_query);
if ($user_result && $user_result->num_rows > 0) {
    $user_stats = $user_result->fetch_assoc();
    $stats['total_users'] = $user_stats['total_users'];
    $stats['total_employers'] = $user_stats['total_employers'];
    $stats['total_job_seekers'] = $user_stats['total_job_seekers'];
}

// Get job counts
$job_query = "SELECT 
               COUNT(*) as total_jobs,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
               SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as pending_jobs
              FROM jobs";
$job_result = $conn->query($job_query);
if ($job_result && $job_result->num_rows > 0) {
    $job_stats = $job_result->fetch_assoc();
    $stats['total_jobs'] = $job_stats['total_jobs'];
    $stats['active_jobs'] = $job_stats['active_jobs'];
    $stats['pending_jobs'] = $job_stats['pending_jobs'];
}

// Get application count
$app_query = "SELECT COUNT(*) as total_applications FROM applications";
$app_result = $conn->query($app_query);
if ($app_result && $app_result->num_rows > 0) {
    $app_stats = $app_result->fetch_assoc();
    $stats['total_applications'] = $app_stats['total_applications'];
}

// Get recent users
$recent_users_query = "SELECT u.*, 
                       CASE 
                         WHEN u.user_type = 'employer' THEN c.company_name
                         WHEN u.user_type = 'jobseeker' THEN CONCAT(js.first_name, ' ', js.surname)
                         ELSE u.username
                       END as display_name
                       FROM users u
                       LEFT JOIN companies c ON u.id = c.user_id AND u.user_type = 'employer'
                       LEFT JOIN job_seekers js ON u.id = js.user_id AND u.user_type = 'jobseeker'
                       ORDER BY u.created_at DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_query);
if ($recent_users_result && $recent_users_result->num_rows > 0) {
    while ($user = $recent_users_result->fetch_assoc()) {
        $stats['recent_users'][] = $user;
    }
}

// Get recent jobs
$recent_jobs_query = "SELECT j.*, c.company_name
                      FROM jobs j
                      LEFT JOIN companies c ON j.company_id = c.id
                      ORDER BY j.created_at DESC LIMIT 5";
$recent_jobs_result = $conn->query($recent_jobs_query);
if ($recent_jobs_result && $recent_jobs_result->num_rows > 0) {
    while ($job = $recent_jobs_result->fetch_assoc()) {
        $stats['recent_jobs'][] = $job;
    }
}

// Get recent applications
$recent_apps_query = "SELECT a.*, j.title as job_title, c.company_name as company_name,
                      js.first_name, js.surname, u.email
                      FROM applications a
                      LEFT JOIN jobs j ON a.job_id = j.id
                      LEFT JOIN companies c ON j.company_id = c.id
                      LEFT JOIN job_seekers js ON a.job_seeker_id = js.id
                      LEFT JOIN users u ON js.user_id = u.id
                      ORDER BY a.applied_at DESC LIMIT 5";
$recent_apps_result = $conn->query($recent_apps_query);
if ($recent_apps_result && $recent_apps_result->num_rows > 0) {
    while ($app = $recent_apps_result->fetch_assoc()) {
        $stats['recent_applications'][] = $app;
    }
}

$page_title = "Admin Dashboard";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JobConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <span data-feather="calendar"></span>
                            This week
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_jobs']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <span class="text-success me-2">
                                        <i class="fas fa-check-circle"></i> <?php echo $stats['active_jobs']; ?> Active
                                    </span>
                                    <span class="text-warning me-2">
                                        <i class="fas fa-clock"></i> <?php echo $stats['pending_jobs']; ?> Pending
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Users</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <span class="text-primary me-2">
                                        <i class="fas fa-user-tie"></i> <?php echo $stats['total_employers']; ?> Employers
                                    </span>
                                    <span class="text-info me-2">
                                        <i class="fas fa-user"></i> <?php echo $stats['total_job_seekers']; ?> Job Seekers
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_applications']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <span class="text-warning me-2">
                                        <i class="fas fa-clock"></i> <?php echo $stats['total_applications']; ?> Pending
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Approvals</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_jobs']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="pending-jobs.php" class="small text-warning">
                                        View pending jobs <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Jobs and Applications -->
                <div class="row">
                    <!-- Recent Jobs -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Jobs</h6>
                                <a href="manage-jobs.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Company</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($stats['recent_jobs'])): ?>
                                                <?php foreach ($stats['recent_jobs'] as $job): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>" target="_blank">
                                                                <?php echo $job['title']; ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo $job['company_name']; ?></td>
                                                        <td>
                                                            <?php if ($job['status'] == 'active'): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php elseif ($job['status'] == 'inactive'): ?>
                                                                <span class="badge bg-warning text-dark">Pending</span>
                                                            <?php elseif ($job['status'] == 'expired'): ?>
                                                                <span class="badge bg-danger">Expired</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    Action
                                                                </button>
                                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                                    <li><a class="dropdown-item" href="edit-job.php?id=<?php echo $job['id']; ?>">Edit</a></li>
                                                                    <li><a class="dropdown-item" href="view-applications.php?job_id=<?php echo $job['id']; ?>">View Applications</a></li>
                                                                    <?php if ($job['status'] == 'pending'): ?>
                                                                        <li><a class="dropdown-item text-success" href="approve-job.php?id=<?php echo $job['id']; ?>">Approve</a></li>
                                                                    <?php elseif ($job['status'] == 'active'): ?>
                                                                        <li><a class="dropdown-item text-warning" href="deactivate-job.php?id=<?php echo $job['id']; ?>">Deactivate</a></li>
                                                                    <?php else: ?>
                                                                        <li><a class="dropdown-item text-success" href="activate-job.php?id=<?php echo $job['id']; ?>">Activate</a></li>
                                                                    <?php endif; ?>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li><a class="dropdown-item text-danger" href="delete-job.php?id=<?php echo $job['id']; ?>" onclick="return confirm('Are you sure you want to delete this job?')">Delete</a></li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No jobs found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Applications -->
                    <div class="col-md-12 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-primary bg-gradient text-white">
                                <h6 class="m-0 font-weight-bold">Recent Applications Dashboard</h6>
                                <a href="manage-applications.php" class="btn btn-sm btn-light">View All Applications</a>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="card border-left-warning shadow h-100 py-2">
                                            <div class="card-body">
                                                <div class="row no-gutters align-items-center">
                                                    <div class="col mr-2">
                                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_applications']; ?></div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Get more detailed recent applications with user details -->
                                <?php if (!empty($stats['recent_applications'])): ?>
                                    <h6 class="font-weight-bold text-primary mt-4 mb-3">Recent Applications</h6>
                                    <div class="row">
                                        <?php foreach ($stats['recent_applications'] as $app): ?>
                                            <div class="col-md-6 mb-4">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center mb-3">
                                                            <div class="me-3">
                                                                <?php if (!empty($app['profile_image']) && file_exists('../uploads/profile_images/' . $app['profile_image'])): ?>
                                                                    <img src="../uploads/profile_images/<?php echo $app['profile_image']; ?>" alt="Applicant" class="rounded-circle" width="50" height="50">
                                                                <?php else: ?>
                                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                        <i class="fas fa-user"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0 font-weight-bold"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['surname']); ?></h6>
                                                                <div class="small text-muted">
                                                                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($app['email']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-primary fw-bold">Applied For:</span>
                                                                <?php 
                                                                $status_class = '';
                                                                $status_text = ucfirst($app['status']);
                                                                
                                                                switch($app['status']) {
                                                                    case 'pending':
                                                                        $status_class = 'bg-warning text-dark';
                                                                        break;
                                                                    case 'reviewed':
                                                                        $status_class = 'bg-info';
                                                                        break;
                                                                    case 'shortlisted':
                                                                        $status_class = 'bg-success';
                                                                        break;
                                                                    case 'interview':
                                                                        $status_class = 'bg-primary';
                                                                        break;
                                                                    case 'approved':
                                                                        $status_class = 'bg-success';
                                                                        break;
                                                                    case 'rejected':
                                                                        $status_class = 'bg-danger';
                                                                        break;
                                                                    default:
                                                                        $status_class = 'bg-secondary';
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                            </div>
                                                            
                                                            <div class="d-flex align-items-center">
                                                                <div class="me-2">
                                                                    <?php if (!empty($app['company_name']) && !empty($app['company_logo']) && file_exists('../uploads/company_logos/' . $app['company_logo'])): ?>
                                                                        <img src="../uploads/company_logos/<?php echo $app['company_logo']; ?>" alt="Company" width="30" height="30">
                                                                    <?php else: ?>
                                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                                                            <i class="fas fa-building text-secondary"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($app['job_title']); ?></div>
                                                                    <div class="small text-muted">
                                                                        <?php echo !empty($app['company_name']) ? htmlspecialchars($app['company_name']) : 'Company'; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="small text-muted">
                                                                <i class="far fa-clock me-1"></i> Applied <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                                                            </div>
                                                            <div>
                                                                <a href="view-application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye me-1"></i> View
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                                    <i class="fas fa-cog"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><h6 class="dropdown-header">Update Status</h6></li>
                                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $app['id']; ?>&action=status&status=pending">Mark as Pending</a></li>
                                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $app['id']; ?>&action=status&status=reviewed">Mark as Reviewed</a></li>
                                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $app['id']; ?>&action=status&status=shortlisted">Shortlist</a></li>
                                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $app['id']; ?>&action=status&status=interview">Schedule Interview</a></li>
                                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $app['id']; ?>&action=status&status=approved">Approve</a></li>
                                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $app['id']; ?>&action=status&status=rejected">Reject</a></li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li><a class="dropdown-item" href="view-cv.php?id=<?php echo $app['id']; ?>">View CV</a></li>
                                                                    <li><a class="dropdown-item" href="mailto:<?php echo $app['email']; ?>">Email Applicant</a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> No recent applications found.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="add-job.php" class="btn btn-primary btn-block w-100">
                                            <i class="fas fa-plus-circle me-1"></i> Add New Job
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="add-user.php" class="btn btn-success btn-block w-100">
                                            <i class="fas fa-user-plus me-1"></i> Add New User
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="manage-categories.php" class="btn btn-info btn-block w-100">
                                            <i class="fas fa-tags me-1"></i> Manage Categories
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="reports.php" class="btn btn-secondary btn-block w-100">
                                            <i class="fas fa-chart-bar me-1"></i> View Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize feather icons
        feather.replace();
    </script>
</body>
</html>
