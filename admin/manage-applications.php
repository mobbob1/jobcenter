<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle application status change
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $application_id = $_GET['id'];
    $action = $_GET['action'];
    
    // Check if application exists
    $check_query = "SELECT id FROM applications WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        $error_message = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $application_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $new_status = '';
            
            if ($action === 'approve') {
                $new_status = 'approved';
            } elseif ($action === 'reject') {
                $new_status = 'rejected';
            } elseif ($action === 'shortlist') {
                $new_status = 'shortlisted';
            } elseif ($action === 'interview') {
                $new_status = 'interview';
            } elseif ($action === 'reset') {
                $new_status = 'pending';
            }
            
            if (!empty($new_status)) {
                $update_query = "UPDATE applications SET status = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt === false) {
                    $error_message = "Error preparing update statement: " . $conn->error;
                } else {
                    $update_stmt->bind_param("si", $new_status, $application_id);
                    
                    if ($update_stmt->execute()) {
                        $status_success = "Application status updated to " . ucfirst($new_status) . ".";
                    } else {
                        $status_error = "Error updating application status: " . $conn->error;
                    }
                }
            }
        } else {
            $status_error = "Application not found.";
        }
    }
}

// Handle application deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $application_id = $_GET['delete'];
    
    // Check if application exists
    $check_query = "SELECT id, cv_path FROM applications WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        $error_message = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $application_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $application_data = $check_result->fetch_assoc();
            
            // Delete the application
            $delete_query = "DELETE FROM applications WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            if ($delete_stmt === false) {
                $error_message = "Error preparing delete statement: " . $conn->error;
            } else {
                $delete_stmt->bind_param("i", $application_id);
                
                if ($delete_stmt->execute()) {
                    // Delete CV file if it exists
                    if (!empty($application_data['cv_path']) && file_exists('../' . $application_data['cv_path'])) {
                        unlink('../' . $application_data['cv_path']);
                    }
                    
                    $delete_success = "Application deleted successfully.";
                } else {
                    $delete_error = "Error deleting application: " . $conn->error;
                }
            }
        } else {
            $delete_error = "Application not found.";
        }
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$filter_job = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$filter_date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';

// Build the query
$query = "SELECT a.*, j.title as job_title, j.company_id, c.name as company_name, 
          js.first_name as applicant_first_name, js.surname as applicant_surname, js.email as applicant_email, js.phone as applicant_phone
          FROM applications a
          LEFT JOIN jobs j ON a.job_id = j.id
          LEFT JOIN companies c ON j.company_id = c.id
          LEFT JOIN job_seekers js ON a.job_seeker_id = js.id
          LEFT JOIN users u ON js.user_id = u.id";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(a.first_name LIKE ? OR a.surname LIKE ? OR j.title LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_status)) {
    $where_clauses[] = "a.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_job > 0) {
    $where_clauses[] = "a.job_id = ?";
    $params[] = $filter_job;
    $types .= "i";
}

if (!empty($filter_date)) {
    $where_clauses[] = "DATE(a.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY a.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt === false) {
    $error_message = "Error preparing statement: " . $conn->error;
    $result = null;
    $total_records = 0;
    $total_pages = 0;
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Count total records for pagination
    $count_query = "SELECT COUNT(*) as total FROM applications a
                LEFT JOIN jobs j ON a.job_id = j.id
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN job_seekers js ON a.job_seeker_id = js.id
                LEFT JOIN users u ON js.user_id = u.id";

    if (!empty($where_clauses)) {
        $count_query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt === false) {
        $error_message = "Error preparing count statement: " . $conn->error;
        $total_records = 0;
        $total_pages = 0;
    } else {
        if (!empty($params)) {
            // Remove the last two parameters (offset and limit)
            $count_types = substr($types, 0, -2);
            $count_params = array_slice($params, 0, -2);
            $count_stmt->bind_param($count_types, ...$count_params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $total_records = $count_row['total'];
        $total_pages = ceil($total_records / $records_per_page);
    }
}

// Get jobs for filter dropdown
$jobs_query = "SELECT id, title FROM jobs ORDER BY title";
$jobs_result = $conn->query($jobs_query);

$page_title = "Manage Applications";
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
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($delete_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $delete_success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($delete_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $delete_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($status_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $status_success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($status_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $status_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Search and Filter Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="manage-applications.php" class="row g-3">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search applications..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($filter_status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="shortlisted" <?php echo ($filter_status === 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="interview" <?php echo ($filter_status === 'interview') ? 'selected' : ''; ?>>Interview</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="job_id" class="form-select">
                                    <option value="0">All Jobs</option>
                                    <?php while ($job = $jobs_result->fetch_assoc()): ?>
                                        <option value="<?php echo $job['id']; ?>" <?php echo ($filter_job == $job['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Applications Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Applicant</th>
                                <th>Job</th>
                                <th>Company</th>
                                <th>Status</th>
                                <th>CV</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($result) && $result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['applicant_first_name'] . ' ' . $row['applicant_surname']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['applicant_email']); ?></small>
                                        </td>
                                        <td>
                                            <a href="../job-details.php?id=<?php echo $row['job_id']; ?>" target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars($row['job_title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($row['status'] === 'approved') ? 'success' : 
                                                    (($row['status'] === 'pending') ? 'warning' : 
                                                    (($row['status'] === 'rejected') ? 'danger' : 
                                                    (($row['status'] === 'shortlisted') ? 'info' : 'primary'))); 
                                            ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['cv_path'])): ?>
                                                <a href="../<?php echo $row['cv_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-file-pdf"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No CV</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $row['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $row['id']; ?>">
                                                    <li><a class="dropdown-item" href="view-application.php?id=<?php echo $row['id']; ?>"><i class="fas fa-eye"></i> View Details</a></li>
                                                    
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <li><a class="dropdown-item text-success" href="manage-applications.php?action=approve&id=<?php echo $row['id']; ?>"><i class="fas fa-check"></i> Approve</a></li>
                                                        <li><a class="dropdown-item text-danger" href="manage-applications.php?action=reject&id=<?php echo $row['id']; ?>"><i class="fas fa-times"></i> Reject</a></li>
                                                        <li><a class="dropdown-item text-info" href="manage-applications.php?action=shortlist&id=<?php echo $row['id']; ?>"><i class="fas fa-list"></i> Shortlist</a></li>
                                                    <?php elseif ($row['status'] === 'shortlisted'): ?>
                                                        <li><a class="dropdown-item text-primary" href="manage-applications.php?action=interview&id=<?php echo $row['id']; ?>"><i class="fas fa-user-tie"></i> Set for Interview</a></li>
                                                        <li><a class="dropdown-item text-success" href="manage-applications.php?action=approve&id=<?php echo $row['id']; ?>"><i class="fas fa-check"></i> Approve</a></li>
                                                        <li><a class="dropdown-item text-danger" href="manage-applications.php?action=reject&id=<?php echo $row['id']; ?>"><i class="fas fa-times"></i> Reject</a></li>
                                                    <?php else: ?>
                                                        <li><a class="dropdown-item text-warning" href="manage-applications.php?action=reset&id=<?php echo $row['id']; ?>"><i class="fas fa-undo"></i> Reset to Pending</a></li>
                                                    <?php endif; ?>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>"><i class="fas fa-trash-alt"></i> Delete</a></li>
                                                </ul>
                                            </div>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $row['id']; ?>">Confirm Deletion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete this application from <strong><?php echo htmlspecialchars($row['applicant_first_name'] . ' ' . $row['applicant_surname']); ?></strong> for the job <strong><?php echo htmlspecialchars($row['job_title']); ?></strong>?
                                                            <p class="text-danger mt-2">This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <a href="manage-applications.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger">Delete Application</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No applications found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['job_id']) ? '&job_id='.$_GET['job_id'] : '').(isset($_GET['date']) ? '&date='.$_GET['date'] : ''); ?>">Previous</a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo (isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['job_id']) ? '&job_id='.$_GET['job_id'] : '').(isset($_GET['date']) ? '&date='.$_GET['date'] : ''); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['job_id']) ? '&job_id='.$_GET['job_id'] : '').(isset($_GET['date']) ? '&date='.$_GET['date'] : ''); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <!-- Application Status Summary -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                Application Status Summary
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $status_query = "SELECT status, COUNT(*) as count FROM applications GROUP BY status";
                                    $status_result = $conn->query($status_query);
                                    
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'shortlisted' => 'info',
                                        'interview' => 'primary'
                                    ];
                                    
                                    while ($status = $status_result->fetch_assoc()):
                                        $color = isset($status_colors[$status['status']]) ? $status_colors[$status['status']] : 'secondary';
                                    ?>
                                    <div class="col-md-2 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h5 class="card-title"><?php echo $status['count']; ?></h5>
                                                <p class="card-text">
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($status['status']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
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
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/admin.js"></script>
    
    <script>
        // Simple export to CSV functionality
        document.getElementById('exportBtn').addEventListener('click', function() {
            let table = document.querySelector('table');
            let rows = Array.from(table.querySelectorAll('tr'));
            
            let csvContent = rows.map(row => {
                let cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    // Get text content and remove any commas to avoid CSV issues
                    let content = cell.textContent.trim().replace(/,/g, ' ');
                    // Remove action buttons text
                    if (content === 'Actions') return '';
                    if (cell.querySelector('.dropdown')) return '';
                    return `"${content}"`;
                }).filter(text => text !== '').join(',');
            }).join('\n');
            
            let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement('a');
            let url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'applications_export_<?php echo date('Y-m-d'); ?>.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>
