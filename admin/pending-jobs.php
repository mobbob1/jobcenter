<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = "Pending Jobs";

// Handle job approval
if (isset($_POST['approve_job']) && isset($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    
    // Update job status to active
    $update = $conn->prepare("UPDATE jobs SET status = 'active' WHERE id = ?");
    if ($update === false) {
        $_SESSION['error_message'] = "Prepare failed: " . $conn->error;
    } else {
        $update->bind_param("i", $job_id);
        
        if ($update->execute()) {
            $_SESSION['success_message'] = "Job approved successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to approve job: " . $update->error;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: pending-jobs.php");
    exit;
}

// Handle job rejection
if (isset($_POST['reject_job']) && isset($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    
    // Update job status to inactive
    $update = $conn->prepare("UPDATE jobs SET status = 'inactive' WHERE id = ?");
    if ($update === false) {
        $_SESSION['error_message'] = "Prepare failed: " . $conn->error;
    } else {
        $update->bind_param("i", $job_id);
        
        if ($update->execute()) {
            $_SESSION['success_message'] = "Job rejected successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to reject job: " . $update->error;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: pending-jobs.php");
    exit;
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Check if categories table exists
$check_category_table = $conn->query("SHOW TABLES LIKE 'categories'");
$categories_exist = $check_category_table->num_rows > 0;

// Build the query - only show pending jobs
$query = "SELECT j.*, c.name as company_name";

// Only include category if the table exists
if ($categories_exist) {
    $query .= ", cat.name as category_name";
}

$query .= ", COUNT(a.id) as application_count, u.username as employer_username, u.email as employer_email
          FROM jobs j
          LEFT JOIN companies c ON j.company_id = c.id";

// Only join with categories if the table exists
if ($categories_exist) {
    $query .= " LEFT JOIN categories cat ON j.category_id = cat.id";
}

$query .= " LEFT JOIN applications a ON j.id = a.job_id
          LEFT JOIN users u ON c.user_id = u.id
          WHERE j.status = 'pending'";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(j.title LIKE ? OR j.location LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($categories_exist && $filter_category > 0) {
    $where_clauses[] = "j.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";
}

if (!empty($where_clauses)) {
    $query .= " AND " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY j.id ORDER BY j.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Check if prepare was successful
if ($stmt === false) {
    $error_message = "Error preparing statement: " . $conn->error;
    $result = null;
    $total_records = 0;
    $total_pages = 0;
} else {
    // Bind parameters if there are any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Count total records for pagination
    $count_query = "SELECT COUNT(*) as total FROM jobs j 
                    LEFT JOIN companies c ON j.company_id = c.id";
    
    // Only join with categories if the table exists
    if ($categories_exist) {
        $count_query .= " LEFT JOIN categories cat ON j.category_id = cat.id";
    }
    
    $count_query .= " WHERE j.status = 'pending'";
    
    if (!empty($where_clauses)) {
        $count_query .= " AND " . implode(" AND ", $where_clauses);
    }
    
    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt === false) {
        $error_message = "Error preparing count statement: " . $conn->error;
        $total_records = 0;
        $total_pages = 0;
    } else {
        if (!empty($params)) {
            // Remove the last two parameters (offset and limit) for the count query
            array_pop($params);
            array_pop($params);
            $count_types = substr($types, 0, -2);
            if (!empty($params)) {
                $count_stmt->bind_param($count_types, ...$params);
            }
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $total_records = $count_row['total'];
        $total_pages = ceil($total_records / $records_per_page);
    }
}

// Get all categories for filter dropdown if the table exists
$categories = [];
if ($categories_exist) {
    $cat_query = "SELECT id, name FROM categories ORDER BY name";
    $cat_result = $conn->query($cat_query);
    if ($cat_result && $cat_result->num_rows > 0) {
        while ($cat_row = $cat_result->fetch_assoc()) {
            $categories[] = $cat_row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom styles -->
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="pending-jobs.php" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search jobs..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="category">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $filter_category == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="pending-jobs.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Company</th>
                                <?php if ($categories_exist): ?>
                                <th>Category</th>
                                <?php endif; ?>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Applications</th>
                                <th>Posted</th>
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
                                        <?php if ($categories_exist): ?>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($row['job_type']); ?></span>
                                        </td>
                                        <td>
                                            <a href="manage-applications.php?job_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                                <?php echo $row['application_count']; ?> applications
                                            </a>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <form method="POST" action="pending-jobs.php" class="me-1">
                                                    <input type="hidden" name="job_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="approve_job" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this job?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="pending-jobs.php">
                                                    <input type="hidden" name="job_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="reject_job" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this job?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $categories_exist ? 9 : 8; ?>" class="text-center">No pending jobs found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_category > 0 ? '&category=' . $filter_category : ''; ?>" aria-label="First">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_category > 0 ? '&category=' . $filter_category : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_category > 0 ? '&category=' . $filter_category : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_category > 0 ? '&category=' . $filter_category : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_category > 0 ? '&category=' . $filter_category : ''; ?>" aria-label="Last">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
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
    <script>
        feather.replace();
    </script>
</body>
</html>
