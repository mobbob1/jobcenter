<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle job deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $job_id = $_GET['delete'];
    
    // Check if job exists
    $check_query = "SELECT id FROM jobs WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        $delete_error = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $job_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Delete the job
            $delete_query = "DELETE FROM jobs WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            if ($delete_stmt === false) {
                $delete_error = "Error preparing delete statement: " . $conn->error;
            } else {
                $delete_stmt->bind_param("i", $job_id);
                
                if ($delete_stmt->execute()) {
                    // Also delete related applications
                    $delete_apps_query = "DELETE FROM applications WHERE job_id = ?";
                    $delete_apps_stmt = $conn->prepare($delete_apps_query);
                    if ($delete_apps_stmt === false) {
                        $delete_error = "Error preparing delete applications statement: " . $conn->error;
                    } else {
                        $delete_apps_stmt->bind_param("i", $job_id);
                        $delete_apps_stmt->execute();
                        
                        $delete_success = "Job and related applications deleted successfully.";
                    }
                } else {
                    $delete_error = "Error deleting job: " . $conn->error;
                }
            }
        } else {
            $delete_error = "Job not found.";
        }
    }
}

// Handle job status change
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $job_id = $_GET['id'];
    $action = $_GET['action'];
    
    // Check if job exists
    $check_query = "SELECT id FROM jobs WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        $status_error = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $job_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $new_status = '';
            
            if ($action === 'approve') {
                $new_status = 'active';
            } elseif ($action === 'reject') {
                $new_status = 'rejected';
            } elseif ($action === 'archive') {
                $new_status = 'archived';
            } elseif ($action === 'feature') {
                // Toggle featured status
                $featured_query = "UPDATE jobs SET is_featured = NOT is_featured WHERE id = ?";
                $featured_stmt = $conn->prepare($featured_query);
                if ($featured_stmt === false) {
                    $status_error = "Error preparing featured statement: " . $conn->error;
                } else {
                    $featured_stmt->bind_param("i", $job_id);
                    
                    if ($featured_stmt->execute()) {
                        // Get the new featured status
                        $get_status = "SELECT is_featured FROM jobs WHERE id = ?";
                        $get_stmt = $conn->prepare($get_status);
                        if ($get_stmt === false) {
                            $status_error = "Error preparing get status statement: " . $conn->error;
                        } else {
                            $get_stmt->bind_param("i", $job_id);
                            $get_stmt->execute();
                            $get_result = $get_stmt->get_result();
                            $job_data = $get_result->fetch_assoc();
                            
                            $status_success = "Job " . ($job_data['is_featured'] ? "featured" : "unfeatured") . " successfully.";
                        }
                    } else {
                        $status_error = "Error updating job featured status: " . $conn->error;
                    }
                }
            }
            
            if (!empty($new_status)) {
                $update_query = "UPDATE jobs SET status = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt === false) {
                    $status_error = "Error preparing update statement: " . $conn->error;
                } else {
                    $update_stmt->bind_param("si", $new_status, $job_id);
                    
                    if ($update_stmt->execute()) {
                        $status_success = "Job status updated to " . ucfirst($new_status) . ".";
                    } else {
                        $status_error = "Error updating job status: " . $conn->error;
                    }
                }
            }
        } else {
            $status_error = "Job not found.";
        }
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_featured = isset($_GET['featured']) ? (int)$_GET['featured'] : -1; // -1 for all, 0 for not featured, 1 for featured

// Build the query
$query = "SELECT j.*, c.company_name, cat.name as category_name, 
          COUNT(a.id) as application_count, u.username as employer_username, u.email as employer_email
          FROM jobs j
          LEFT JOIN companies c ON j.company_id = c.id
          LEFT JOIN categories cat ON j.category_id = cat.id
          LEFT JOIN applications a ON j.id = a.job_id
          LEFT JOIN users u ON c.user_id = u.id";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(j.title LIKE ? OR j.location LIKE ? OR c.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($filter_status)) {
    $where_clauses[] = "j.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_category > 0) {
    $where_clauses[] = "j.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";
}

if ($filter_featured >= 0) {
    $where_clauses[] = "j.is_featured = ?";
    $params[] = $filter_featured;
    $types .= "i";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY j.id ORDER BY j.created_at DESC LIMIT ?, ?";
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
    $count_query = "SELECT COUNT(*) as total FROM jobs j
               LEFT JOIN companies c ON j.company_id = c.id
               LEFT JOIN categories cat ON j.category_id = cat.id";
    
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
            $count_params = array_slice($params, 0, -2);
            if (!empty($count_params)) {
                $count_types = substr($types, 0, -2);
                $count_stmt->bind_param($count_types, ...$count_params);
            }
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $total_records = $count_row['total'];
        $total_pages = ceil($total_records / $records_per_page);
    }
}

// Get categories for filter dropdown
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);

$page_title = "Manage Jobs";
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
                        <a href="add-job.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New Job
                        </a>
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
                        <form method="GET" action="manage-jobs.php" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search jobs..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="expired" <?php echo ($filter_status === 'expired') ? 'selected' : ''; ?>>Expired</option>
                                    <option value="rejected" <?php echo ($filter_status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="archived" <?php echo ($filter_status === 'archived') ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="category" class="form-select">
                                    <option value="0">All Categories</option>
                                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($filter_category == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="featured" class="form-select">
                                    <option value="-1" <?php echo ($filter_featured == -1) ? 'selected' : ''; ?>>All Jobs</option>
                                    <option value="1" <?php echo ($filter_featured == 1) ? 'selected' : ''; ?>>Featured</option>
                                    <option value="0" <?php echo ($filter_featured == 0 && $filter_featured !== -1) ? 'selected' : ''; ?>>Not Featured</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Jobs Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Company</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Applications</th>
                                <th>Deadline</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($result) && $result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <a href="../job-details.php?id=<?php echo $row['id']; ?>" target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars($row['title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($row['status'] === 'active') ? 'success' : 
                                                    (($row['status'] === 'pending') ? 'warning' : 
                                                    (($row['status'] === 'expired') ? 'secondary' : 
                                                    (($row['status'] === 'rejected') ? 'danger' : 'dark'))); 
                                            ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['is_featured']): ?>
                                                <span class="badge bg-info"><i class="fas fa-star"></i> Featured</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">Regular</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="manage-applications.php?job_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                                <?php echo $row['application_count']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php 
                                            $deadline = new DateTime($row['deadline']);
                                            $now = new DateTime();
                                            $is_expired = $deadline < $now;
                                            $days_left = $now->diff($deadline)->days;
                                            
                                            if ($is_expired) {
                                                echo '<span class="text-danger">Expired</span>';
                                            } else {
                                                echo date('M d, Y', strtotime($row['deadline']));
                                                echo ' <small class="text-muted">(' . $days_left . ' days left)</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $row['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $row['id']; ?>">
                                                    <li><a class="dropdown-item" href="../job-details.php?id=<?php echo $row['id']; ?>" target="_blank"><i class="fas fa-eye"></i> View</a></li>
                                                    <li><a class="dropdown-item" href="edit-job.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit"></i> Edit</a></li>
                                                    
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <li><a class="dropdown-item text-success" href="manage-jobs.php?action=approve&id=<?php echo $row['id']; ?>"><i class="fas fa-check"></i> Approve</a></li>
                                                        <li><a class="dropdown-item text-danger" href="manage-jobs.php?action=reject&id=<?php echo $row['id']; ?>"><i class="fas fa-times"></i> Reject</a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($row['is_featured']): ?>
                                                        <li><a class="dropdown-item" href="manage-jobs.php?action=feature&id=<?php echo $row['id']; ?>"><i class="far fa-star"></i> Unfeature</a></li>
                                                    <?php else: ?>
                                                        <li><a class="dropdown-item text-info" href="manage-jobs.php?action=feature&id=<?php echo $row['id']; ?>"><i class="fas fa-star"></i> Feature</a></li>
                                                    <?php endif; ?>
                                                    
                                                    <li><a class="dropdown-item text-secondary" href="manage-jobs.php?action=archive&id=<?php echo $row['id']; ?>"><i class="fas fa-archive"></i> Archive</a></li>
                                                    
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
                                                            Are you sure you want to delete job: <strong><?php echo htmlspecialchars($row['title']); ?></strong>?
                                                            <p class="text-danger mt-2">This will also delete all applications for this job. This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <a href="manage-jobs.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger">Delete Job</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No jobs found</td>
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
                            <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['category']) ? '&category='.$_GET['category'] : '').(isset($_GET['featured']) ? '&featured='.$_GET['featured'] : ''); ?>">Previous</a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo (isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['category']) ? '&category='.$_GET['category'] : '').(isset($_GET['featured']) ? '&featured='.$_GET['featured'] : ''); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : '').(isset($_GET['category']) ? '&category='.$_GET['category'] : '').(isset($_GET['featured']) ? '&featured='.$_GET['featured'] : ''); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/admin.js"></script>
</body>
</html>
