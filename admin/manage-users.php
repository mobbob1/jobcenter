<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Don't allow admin to delete themselves
    if ($user_id == $_SESSION['user_id']) {
        $delete_error = "You cannot delete your own account.";
    } else {
        // Check if user exists and is not an admin
        $check_query = "SELECT user_type FROM users WHERE id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $user_data = $check_result->fetch_assoc();
            
            // Don't allow deleting other admins
            if ($user_data['user_type'] === 'admin') {
                $delete_error = "You cannot delete another admin account.";
            } else {
                // Delete the user
                $delete_query = "DELETE FROM users WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("i", $user_id);
                
                if ($delete_stmt->execute()) {
                    $delete_success = "User deleted successfully.";
                } else {
                    $delete_error = "Error deleting user: " . $conn->error;
                }
            }
        } else {
            $delete_error = "User not found.";
        }
    }
}

// Handle user status change
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];
    $action = $_GET['action'];
    
    // Don't allow admin to change their own status
    if ($user_id == $_SESSION['user_id']) {
        $status_error = "You cannot change your own account status.";
    } else {
        // Check if user exists
        $check_query = "SELECT user_type FROM users WHERE id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $user_data = $check_result->fetch_assoc();
            
            // Don't allow changing other admins' status
            if ($user_data['user_type'] === 'admin' && $_SESSION['user_id'] != $user_id) {
                $status_error = "You cannot change another admin's account status.";
            } else {
                $new_status = '';
                
                if ($action === 'activate') {
                    $new_status = 'active';
                } elseif ($action === 'deactivate') {
                    $new_status = 'inactive';
                } elseif ($action === 'suspend') {
                    $new_status = 'suspended';
                }
                
                if (!empty($new_status)) {
                    $update_query = "UPDATE users SET status = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("si", $new_status, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $status_success = "User status updated to " . ucfirst($new_status) . ".";
                    } else {
                        $status_error = "Error updating user status: " . $conn->error;
                    }
                }
            }
        } else {
            $status_error = "User not found.";
        }
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Build the query
$query = "SELECT u.*, 
          CASE 
            WHEN u.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
            WHEN u.user_type = 'employer' THEN c.company_name
            WHEN u.user_type = 'jobseeker' THEN CONCAT(js.first_name, ' ', js.surname)
            ELSE u.username
          END as display_name,
          COUNT(DISTINCT j.id) as job_count, 
          COUNT(DISTINCT app.id) as application_count 
          FROM users u 
          LEFT JOIN admins a ON u.id = a.user_id AND u.user_type = 'admin'
          LEFT JOIN companies c ON u.id = c.user_id AND u.user_type = 'employer'
          LEFT JOIN job_seekers js ON u.id = js.user_id AND u.user_type = 'jobseeker'
          LEFT JOIN jobs j ON c.id = j.company_id
          LEFT JOIN applications app ON (u.user_type = 'jobseeker' AND js.id = app.job_seeker_id)";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u.username LIKE ? OR u.email LIKE ? OR 
                         CASE 
                           WHEN u.user_type = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                           WHEN u.user_type = 'employer' THEN c.company_name
                           WHEN u.user_type = 'jobseeker' THEN CONCAT(js.first_name, ' ', js.surname)
                           ELSE u.username
                         END LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($filter_type)) {
    $where_clauses[] = "u.user_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($filter_status)) {
    $where_clauses[] = "u.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM users u";
if (!empty($where_clauses)) {
    $count_query .= " WHERE " . implode(" AND ", $where_clauses);
}

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    // Remove the last two parameters (offset and limit)
    array_pop($params);
    array_pop($params);
    if (!empty($params)) {
        $count_stmt->bind_param(substr($types, 0, -2), ...$params);
    }
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

$page_title = "Manage Users";
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
                        <a href="add-user.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add New User
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
                        <form method="GET" action="manage-users.php" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search users..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="type" class="form-select">
                                    <option value="">All User Types</option>
                                    <option value="admin" <?php echo ($filter_type === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    <option value="employer" <?php echo ($filter_type === 'employer') ? 'selected' : ''; ?>>Employer</option>
                                    <option value="jobseeker" <?php echo ($filter_type === 'jobseeker') ? 'selected' : ''; ?>>Job Seeker</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($filter_status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Jobs</th>
                                <th>Applications</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['display_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($row['user_type'] === 'admin') ? 'danger' : 
                                                    (($row['user_type'] === 'employer') ? 'primary' : 'success'); 
                                            ?>">
                                                <?php echo ucfirst($row['user_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($row['status'] === 'active') ? 'success' : 
                                                    (($row['status'] === 'inactive') ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['job_count']; ?></td>
                                        <td><?php echo $row['application_count']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $row['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $row['id']; ?>">
                                                    <li><a class="dropdown-item" href="view-user.php?id=<?php echo $row['id']; ?>"><i class="fas fa-eye"></i> View</a></li>
                                                    <li><a class="dropdown-item" href="edit-user.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit"></i> Edit</a></li>
                                                    <?php if ($row['status'] === 'active'): ?>
                                                        <li><a class="dropdown-item text-warning" href="manage-users.php?action=deactivate&id=<?php echo $row['id']; ?>"><i class="fas fa-user-slash"></i> Deactivate</a></li>
                                                    <?php elseif ($row['status'] === 'inactive'): ?>
                                                        <li><a class="dropdown-item text-success" href="manage-users.php?action=activate&id=<?php echo $row['id']; ?>"><i class="fas fa-user-check"></i> Activate</a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item text-danger" href="manage-users.php?action=suspend&id=<?php echo $row['id']; ?>"><i class="fas fa-ban"></i> Suspend</a></li>
                                                    <?php if ($row['user_type'] !== 'admin' || $_SESSION['user_id'] != $row['id']): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>"><i class="fas fa-trash-alt"></i> Delete</a></li>
                                                    <?php endif; ?>
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
                                                            Are you sure you want to delete user: <strong><?php echo htmlspecialchars($row['username']); ?></strong>?
                                                            <p class="text-danger mt-2">This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <a href="manage-users.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger">Delete User</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No users found</td>
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
                            <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['type']) ? '&type='.$_GET['type'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : ''); ?>">Previous</a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo (isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['type']) ? '&type='.$_GET['type'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : ''); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['type']) ? '&type='.$_GET['type'] : '').(isset($_GET['status']) ? '&status='.$_GET['status'] : ''); ?>">Next</a>
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
