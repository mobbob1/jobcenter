<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Set default time period
$time_period = isset($_GET['period']) ? $_GET['period'] : 'all';

// Calculate date range based on time period
$end_date = date('Y-m-d');
$start_date = '';

switch ($time_period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        break;
    default:
        $start_date = '2000-01-01'; // Far past date for "all time"
}

// Function to get count with date filter
function get_count_with_filter($conn, $table, $where_clause = '', $date_field = 'created_at') {
    global $start_date, $end_date;
    
    $query = "SELECT COUNT(*) as count FROM $table";
    
    if (!empty($where_clause) && !empty($start_date)) {
        $query .= " WHERE $where_clause AND $date_field BETWEEN ? AND ?";
    } elseif (!empty($where_clause)) {
        $query .= " WHERE $where_clause";
    } elseif (!empty($start_date)) {
        $query .= " WHERE $date_field BETWEEN ? AND ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        return 0;
    }
    
    if (!empty($start_date) && strpos($query, '?') !== false) {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    return 0;
}

// Get job statistics
$total_jobs = get_count_with_filter($conn, 'jobs');
$active_jobs = get_count_with_filter($conn, 'jobs', "status = 'active'");
$pending_jobs = get_count_with_filter($conn, 'jobs', "status = 'pending'");
$featured_jobs = get_count_with_filter($conn, 'jobs', "is_featured = 1");

// Get user statistics
$total_users = get_count_with_filter($conn, 'users');
$employers = get_count_with_filter($conn, 'users', "user_type = 'employer'");
$jobseekers = get_count_with_filter($conn, 'users', "user_type = 'jobseeker'");

// Get application statistics
$total_applications = get_count_with_filter($conn, 'job_applications');
$pending_applications = get_count_with_filter($conn, 'job_applications', "status = 'pending'");
$approved_applications = get_count_with_filter($conn, 'job_applications', "status = 'approved'");
$rejected_applications = get_count_with_filter($conn, 'job_applications', "status = 'rejected'");

// Get monthly job data for chart
$job_chart_data = [];
$application_chart_data = [];
$user_chart_data = [];

// Function to get monthly data for charts
function get_monthly_data($conn, $table, $where_clause = '') {
    $months = [];
    $counts = [];
    
    $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
              FROM $table";
    
    if (!empty($where_clause)) {
        $query .= " WHERE $where_clause";
    }
    
    $query .= " GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                ORDER BY month ASC 
                LIMIT 12";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $date = new DateTime($row['month'] . '-01');
            $months[] = $date->format('M Y');
            $counts[] = (int)$row['count'];
        }
    }
    
    return ['labels' => $months, 'data' => $counts];
}

$job_chart_data = get_monthly_data($conn, 'jobs');
$application_chart_data = get_monthly_data($conn, 'job_applications');
$user_chart_data = get_monthly_data($conn, 'users');

// Get recent jobs
$recent_jobs_query = "SELECT j.id, j.title, j.location, j.created_at, j.status, u.username as employer
                      FROM jobs j
                      LEFT JOIN users u ON j.employer_id = u.id
                      ORDER BY j.created_at DESC
                      LIMIT 5";
$recent_jobs_result = $conn->query($recent_jobs_query);
$recent_jobs = [];

if ($recent_jobs_result && $recent_jobs_result->num_rows > 0) {
    while ($row = $recent_jobs_result->fetch_assoc()) {
        $recent_jobs[] = $row;
    }
}

// Get recent applications
$recent_applications_query = "SELECT a.id, a.status, a.created_at, j.title as job_title, u.username as applicant
                             FROM job_applications a
                             LEFT JOIN jobs j ON a.job_id = j.id
                             LEFT JOIN users u ON a.user_id = u.id
                             ORDER BY a.created_at DESC
                             LIMIT 5";
$recent_applications_result = $conn->query($recent_applications_query);
$recent_applications = [];

if ($recent_applications_result && $recent_applications_result->num_rows > 0) {
    while ($row = $recent_applications_result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
}

$page_title = "Reports & Analytics";
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
        
        .stats-card {
            border-left: 4px solid;
            border-radius: 4px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .stats-card-primary {
            border-left-color: #4e73df;
        }
        
        .stats-card-success {
            border-left-color: #1cc88a;
        }
        
        .stats-card-info {
            border-left-color: #36b9cc;
        }
        
        .stats-card-warning {
            border-left-color: #f6c23e;
        }
        
        .stats-card-danger {
            border-left-color: #e74a3b;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
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
                    
                    <div class="btn-group">
                        <a href="?period=week" class="btn btn-sm btn-outline-secondary <?php echo $time_period == 'week' ? 'active' : ''; ?>">Last Week</a>
                        <a href="?period=month" class="btn btn-sm btn-outline-secondary <?php echo $time_period == 'month' ? 'active' : ''; ?>">Last Month</a>
                        <a href="?period=year" class="btn btn-sm btn-outline-secondary <?php echo $time_period == 'year' ? 'active' : ''; ?>">Last Year</a>
                        <a href="?period=all" class="btn btn-sm btn-outline-secondary <?php echo $time_period == 'all' ? 'active' : ''; ?>">All Time</a>
                    </div>
                </div>
                
                <!-- Job Statistics -->
                <h4 class="mb-3">Job Statistics</h4>
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_jobs; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_jobs; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_jobs; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-info h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Featured Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $featured_jobs; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-star fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Statistics -->
                <h4 class="mb-3">User Statistics</h4>
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card stats-card-primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card stats-card-success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Employers</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employers; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-building fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card stats-card-info h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Job Seekers</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $jobseekers; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Application Statistics -->
                <h4 class="mb-3">Application Statistics</h4>
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_applications; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_applications; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $approved_applications; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card stats-card-danger h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Rejected Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rejected_applications; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-xl-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Monthly Job Postings</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="jobChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Monthly Applications</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="applicationChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-xl-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Jobs</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Employer</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_jobs)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No recent jobs found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_jobs as $job): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                                                        <td><?php echo htmlspecialchars($job['employer']); ?></td>
                                                        <td>
                                                            <?php if ($job['status'] == 'active'): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php elseif ($job['status'] == 'pending'): ?>
                                                                <span class="badge bg-warning text-dark">Pending</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary"><?php echo ucfirst($job['status']); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Applications</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Job</th>
                                                <th>Applicant</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_applications)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No recent applications found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_applications as $application): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                                        <td><?php echo htmlspecialchars($application['applicant']); ?></td>
                                                        <td>
                                                            <?php if ($application['status'] == 'approved'): ?>
                                                                <span class="badge bg-success">Approved</span>
                                                            <?php elseif ($application['status'] == 'pending'): ?>
                                                                <span class="badge bg-warning text-dark">Pending</span>
                                                            <?php elseif ($application['status'] == 'rejected'): ?>
                                                                <span class="badge bg-danger">Rejected</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary"><?php echo ucfirst($application['status']); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Chart.js configuration
        document.addEventListener('DOMContentLoaded', function() {
            // Job Chart
            var jobCtx = document.getElementById('jobChart').getContext('2d');
            var jobChart = new Chart(jobCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($job_chart_data['labels']); ?>,
                    datasets: [{
                        label: 'Jobs Posted',
                        data: <?php echo json_encode($job_chart_data['data']); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Application Chart
            var appCtx = document.getElementById('applicationChart').getContext('2d');
            var appChart = new Chart(appCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($application_chart_data['labels']); ?>,
                    datasets: [{
                        label: 'Applications Submitted',
                        data: <?php echo json_encode($application_chart_data['data']); ?>,
                        backgroundColor: 'rgba(28, 200, 138, 0.05)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointHoverBorderColor: 'rgba(28, 200, 138, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
