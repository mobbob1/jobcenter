<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle company deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $company_id = $_GET['delete'];
    
    // Check if company exists
    $check_query = "SELECT id FROM companies WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        $delete_error = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $company_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            // Check if company has jobs
            $jobs_query = "SELECT COUNT(*) as job_count FROM jobs WHERE company_id = ?";
            $jobs_stmt = $conn->prepare($jobs_query);
            if ($jobs_stmt === false) {
                $delete_error = "Error preparing jobs statement: " . $conn->error;
            } else {
                $jobs_stmt->bind_param("i", $company_id);
                $jobs_stmt->execute();
                $jobs_result = $jobs_stmt->get_result();
                $job_count = $jobs_result->fetch_assoc()['job_count'];
                
                if ($job_count > 0) {
                    $delete_error = "Cannot delete company with active jobs. Please delete or reassign the jobs first.";
                } else {
                    // Delete the company
                    $delete_query = "DELETE FROM companies WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    if ($delete_stmt === false) {
                        $delete_error = "Error preparing delete statement: " . $conn->error;
                    } else {
                        $delete_stmt->bind_param("i", $company_id);
                        
                        if ($delete_stmt->execute()) {
                            $delete_success = "Company deleted successfully.";
                        } else {
                            $delete_error = "Error deleting company: " . $delete_stmt->error;
                        }
                    }
                }
            }
        } else {
            $delete_error = "Company not found.";
        }
    }
}

// Handle company verification status change
if (isset($_GET['action']) && ($_GET['action'] === 'verify' || $_GET['action'] === 'unverify') && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $company_id = $_GET['id'];
    $action = $_GET['action'];
    $is_verified = ($action === 'verify') ? 1 : 0;
    
    // Update verification status
    $verify_query = "UPDATE companies SET is_verified = ? WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    if ($verify_stmt === false) {
        $status_error = "Error preparing statement: " . $conn->error;
    } else {
        $verify_stmt->bind_param("ii", $is_verified, $company_id);
        
        if ($verify_stmt->execute()) {
            $status_success = "Company " . ($is_verified ? "verified" : "unverified") . " successfully.";
        } else {
            $status_error = "Error updating company verification status: " . $verify_stmt->error;
        }
    }
}

// Handle company featured status change
if (isset($_GET['action']) && $_GET['action'] === 'feature' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $company_id = $_GET['id'];
    
    // Toggle featured status
    $feature_query = "UPDATE companies SET is_featured = NOT is_featured WHERE id = ?";
    $feature_stmt = $conn->prepare($feature_query);
    if ($feature_stmt === false) {
        $status_error = "Error preparing statement: " . $conn->error;
    } else {
        $feature_stmt->bind_param("i", $company_id);
        
        if ($feature_stmt->execute()) {
            // Get the new featured status
            $get_status = "SELECT is_featured FROM companies WHERE id = ?";
            $get_stmt = $conn->prepare($get_status);
            if ($get_stmt === false) {
                $status_error = "Error preparing get status statement: " . $conn->error;
            } else {
                $get_stmt->bind_param("i", $company_id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                
                if ($get_result && $get_result->num_rows > 0) {
                    $company_data = $get_result->fetch_assoc();
                    $status_success = "Company " . ($company_data['is_featured'] ? "featured" : "unfeatured") . " successfully.";
                } else {
                    $status_error = "Error retrieving company status.";
                }
            }
        } else {
            $status_error = "Error updating company featured status: " . $feature_stmt->error;
        }
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_verified = isset($_GET['verified']) ? (int)$_GET['verified'] : -1; // -1 for all, 0 for unverified, 1 for verified
$filter_featured = isset($_GET['featured']) ? (int)$_GET['featured'] : -1; // -1 for all, 0 for not featured, 1 for featured
$filter_industry = isset($_GET['industry']) ? $conn->real_escape_string($_GET['industry']) : '';

// Build the query
$query = "SELECT c.*, u.username, u.email, COUNT(j.id) as job_count 
          FROM companies c
          LEFT JOIN users u ON c.user_id = u.id
          LEFT JOIN jobs j ON c.id = j.company_id";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(c.company_name LIKE ? OR c.industry LIKE ? OR c.city LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssss";
}

if ($filter_verified >= 0) {
    $where_clauses[] = "c.is_verified = ?";
    $params[] = $filter_verified;
    $types .= "i";
}

if ($filter_featured >= 0) {
    $where_clauses[] = "c.is_featured = ?";
    $params[] = $filter_featured;
    $types .= "i";
}

if (!empty($filter_industry)) {
    $where_clauses[] = "c.industry = ?";
    $params[] = $filter_industry;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT ?, ?";
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
    $count_query = "SELECT COUNT(*) as total FROM companies c
                   LEFT JOIN users u ON c.user_id = u.id";
    
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

// Get industries for filter dropdown
$industries_query = "SELECT DISTINCT industry FROM companies WHERE industry IS NOT NULL AND industry != '' ORDER BY industry";
$industries_result = $conn->query($industries_query);

$page_title = "Manage Companies";
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
                        <a href="add-company.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New Company
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
                        <form method="GET" action="manage-companies.php" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search companies..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="verified" class="form-select">
                                    <option value="-1" <?php echo ($filter_verified == -1) ? 'selected' : ''; ?>>All Verification</option>
                                    <option value="1" <?php echo ($filter_verified == 1) ? 'selected' : ''; ?>>Verified</option>
                                    <option value="0" <?php echo ($filter_verified == 0 && $filter_verified !== -1) ? 'selected' : ''; ?>>Unverified</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="featured" class="form-select">
                                    <option value="-1" <?php echo ($filter_featured == -1) ? 'selected' : ''; ?>>All Featured Status</option>
                                    <option value="1" <?php echo ($filter_featured == 1) ? 'selected' : ''; ?>>Featured</option>
                                    <option value="0" <?php echo ($filter_featured == 0 && $filter_featured !== -1) ? 'selected' : ''; ?>>Not Featured</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="industry" class="form-select">
                                    <option value="">All Industries</option>
                                    <?php if ($industries_result && $industries_result->num_rows > 0): ?>
                                        <?php while ($industry = $industries_result->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($industry['industry']); ?>" <?php echo ($filter_industry == $industry['industry']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($industry['industry']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Companies Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Logo</th>
                                <th>Company Name</th>
                                <th>Industry</th>
                                <th>Location</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Jobs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($result) && $result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <img src="<?php echo !empty($row['logo']) ? '../uploads/companies/' . htmlspecialchars($row['logo']) : '../assets/img/default-company.png'; ?>" 
                                                 alt="<?php echo htmlspecialchars($row['company_name']); ?>" 
                                                 class="img-thumbnail" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        </td>
                                        <td>
                                            <a href="../company-profile.php?id=<?php echo $row['id']; ?>" target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars($row['company_name']); ?>
                                            </a>
                                            <?php if ($row['is_featured']): ?>
                                                <span class="badge bg-info ms-1"><i class="fas fa-star"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['industry'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                            $location_parts = [];
                                            if (!empty($row['city'])) $location_parts[] = $row['city'];
                                            if (!empty($row['state'])) $location_parts[] = $row['state'];
                                            if (!empty($row['country'])) $location_parts[] = $row['country'];
                                            echo !empty($location_parts) ? htmlspecialchars(implode(', ', $location_parts)) : 'N/A';
                                            ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if (!empty($row['email'])): ?>
                                                    <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($row['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['phone'])): ?>
                                                    <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($row['phone']); ?></div>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($row['is_verified']): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="manage-jobs.php?company_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                                <?php echo $row['job_count']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $row['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $row['id']; ?>">
                                                    <li>
                                                        <a class="dropdown-item" href="view-company.php?id=<?php echo $row['id']; ?>">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="edit-company.php?id=<?php echo $row['id']; ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                    </li>
                                                    <?php if ($row['is_verified']): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="?action=unverify&id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to remove verification from this company?');">
                                                                <i class="fas fa-times-circle"></i> Remove Verification
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <a class="dropdown-item" href="?action=verify&id=<?php echo $row['id']; ?>">
                                                                <i class="fas fa-check-circle"></i> Verify Company
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="?action=feature&id=<?php echo $row['id']; ?>">
                                                            <i class="fas fa-star"></i> <?php echo $row['is_featured'] ? 'Unfeature' : 'Feature'; ?> Company
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this company? This action cannot be undone.');">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No companies found</td>
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
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_verified >= 0 ? '&verified=' . $filter_verified : ''; ?><?php echo $filter_featured >= 0 ? '&featured=' . $filter_featured : ''; ?><?php echo !empty($filter_industry) ? '&industry=' . urlencode($filter_industry) : ''; ?>" aria-label="First">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_verified >= 0 ? '&verified=' . $filter_verified : ''; ?><?php echo $filter_featured >= 0 ? '&featured=' . $filter_featured : ''; ?><?php echo !empty($filter_industry) ? '&industry=' . urlencode($filter_industry) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_verified >= 0 ? '&verified=' . $filter_verified : ''; ?><?php echo $filter_featured >= 0 ? '&featured=' . $filter_featured : ''; ?><?php echo !empty($filter_industry) ? '&industry=' . urlencode($filter_industry) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_verified >= 0 ? '&verified=' . $filter_verified : ''; ?><?php echo $filter_featured >= 0 ? '&featured=' . $filter_featured : ''; ?><?php echo !empty($filter_industry) ? '&industry=' . urlencode($filter_industry) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_verified >= 0 ? '&verified=' . $filter_verified : ''; ?><?php echo $filter_featured >= 0 ? '&featured=' . $filter_featured : ''; ?><?php echo !empty($filter_industry) ? '&industry=' . urlencode($filter_industry) : ''; ?>" aria-label="Last">
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
