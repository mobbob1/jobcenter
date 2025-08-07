<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle category addition
if (isset($_POST['add_category'])) {
    $name = trim($conn->real_escape_string($_POST['name']));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $icon = trim($conn->real_escape_string($_POST['icon'] ?? 'fas fa-briefcase')); // Default icon
    
    if (!empty($name)) {
        // Check if category already exists
        $check_query = "SELECT id FROM categories WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt === false) {
            $error_message = "Error preparing statement: " . $conn->error;
        } else {
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $insert_query = "INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                if ($insert_stmt === false) {
                    $error_message = "Error preparing insert statement: " . $conn->error;
                } else {
                    $insert_stmt->bind_param("sss", $name, $description, $icon);
                    
                    if ($insert_stmt->execute()) {
                        $success_message = "Category '$name' added successfully.";
                    } else {
                        $error_message = "Error adding category: " . $conn->error;
                    }
                }
            } else {
                $error_message = "A category with this name already exists.";
            }
        }
    } else {
        $error_message = "Category name cannot be empty.";
    }
}

// Handle category edit
if (isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $name = trim($conn->real_escape_string($_POST['name']));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $icon = trim($conn->real_escape_string($_POST['icon'] ?? 'fas fa-briefcase'));
    
    if (!empty($name)) {
        // Check if another category with the same name exists
        $check_query = "SELECT id FROM categories WHERE name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt === false) {
            $error_message = "Error preparing statement: " . $conn->error;
        } else {
            $check_stmt->bind_param("si", $name, $category_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $update_query = "UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt === false) {
                    $error_message = "Error preparing update statement: " . $conn->error;
                } else {
                    $update_stmt->bind_param("sssi", $name, $description, $icon, $category_id);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Category updated successfully.";
                    } else {
                        $error_message = "Error updating category: " . $conn->error;
                    }
                }
            } else {
                $error_message = "Another category with this name already exists.";
            }
        }
    } else {
        $error_message = "Category name cannot be empty.";
    }
}

// Handle category deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    // Check if category exists
    $check_query = "SELECT id, name FROM categories WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt === false) {
        $error_message = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $category = $check_result->fetch_assoc();
            
            // Check if category is being used in jobs
            $job_check_query = "SELECT COUNT(*) as job_count FROM jobs WHERE category_id = ?";
            $job_check_stmt = $conn->prepare($job_check_query);
            if ($job_check_stmt === false) {
                $error_message = "Error preparing job check statement: " . $conn->error;
            } else {
                $job_check_stmt->bind_param("i", $category_id);
                $job_check_stmt->execute();
                $job_check_result = $job_check_stmt->get_result();
                $job_count = $job_check_result->fetch_assoc()['job_count'];
                
                if ($job_count > 0) {
                    $error_message = "Cannot delete category '{$category['name']}' because it is associated with $job_count job(s).";
                } else {
                    // Delete the category
                    $delete_query = "DELETE FROM categories WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    if ($delete_stmt === false) {
                        $error_message = "Error preparing delete statement: " . $conn->error;
                    } else {
                        $delete_stmt->bind_param("i", $category_id);
                        
                        if ($delete_stmt->execute()) {
                            $success_message = "Category '{$category['name']}' deleted successfully.";
                        } else {
                            $error_message = "Error deleting category: " . $conn->error;
                        }
                    }
                }
            }
        } else {
            $error_message = "Category not found.";
        }
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build the query
$query = "SELECT c.*, COUNT(j.id) as job_count 
          FROM categories c 
          LEFT JOIN jobs j ON c.id = j.category_id";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY c.id ORDER BY c.name ASC LIMIT ?, ?";
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
    $count_query = "SELECT COUNT(*) as total FROM categories";
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
            array_pop($params);
            array_pop($params);
            if (!empty($params)) {
                $count_types = substr($types, 0, -2);
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

$page_title = "Manage Categories";
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
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> Add New Category
                        </button>
                    </div>
                </div>
                
                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="manage-categories.php" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search categories..." name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="manage-categories.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Categories Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Jobs</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result !== null && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><i class="<?php echo htmlspecialchars($row['icon']); ?>"></i></td>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($row['description'])) {
                                                        echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : '');
                                                    } else {
                                                        echo '<span class="text-muted">No description</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['job_count'] > 0): ?>
                                                        <a href="manage-jobs.php?category_id=<?php echo $row['id']; ?>" class="badge bg-info text-decoration-none">
                                                            <?php echo $row['job_count']; ?> job<?php echo $row['job_count'] > 1 ? 's' : ''; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">0 jobs</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-category-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editCategoryModal" 
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($row['description']); ?>"
                                                            data-icon="<?php echo htmlspecialchars($row['icon']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($row['job_count'] == 0): ?>
                                                        <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $row['id']; ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Cannot delete category with associated jobs">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Delete Modal -->
                                                    <div class="modal fade" id="deleteCategoryModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="deleteCategoryModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteCategoryModalLabel<?php echo $row['id']; ?>">Confirm Deletion</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete the category <strong><?php echo htmlspecialchars($row['name']); ?></strong>?
                                                                    <p class="text-danger mt-2">This action cannot be undone.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <a href="manage-categories.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger">Delete Category</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No categories found</td>
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
                                    <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(isset($_GET['search']) ? '&search='.$_GET['search'] : ''); ?>">Previous</a>
                                </li>
                                
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo (isset($_GET['search']) ? '&search='.$_GET['search'] : ''); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(isset($_GET['search']) ? '&search='.$_GET['search'] : ''); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="icon" class="form-label">Icon Class</label>
                            <div class="input-group">
                                <span class="input-group-text"><i id="iconPreview" class="fas fa-briefcase"></i></span>
                                <input type="text" class="form-control" id="icon" name="icon" value="fas fa-briefcase">
                            </div>
                            <div class="form-text">Enter a Font Awesome icon class (e.g., fas fa-briefcase, fas fa-code)</div>
                            <div class="mt-2">
                                <p>Suggested icons:</p>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary icon-option" data-icon="fas fa-briefcase"><i class="fas fa-briefcase"></i></button>
                                    <button type="button" class="btn btn-outline-secondary icon-option" data-icon="fas fa-code"><i class="fas fa-code"></i></button>
                                    <button type="button" class="btn btn-outline-secondary icon-option" data-icon="fas fa-chart-line"><i class="fas fa-chart-line"></i></button>
                                    <button type="button" class="btn btn-outline-secondary icon-option" data-icon="fas fa-graduation-cap"><i class="fas fa-graduation-cap"></i></button>
                                    <button type="button" class="btn btn-outline-secondary icon-option" data-icon="fas fa-hospital"><i class="fas fa-hospital"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_category_id" name="category_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_icon" class="form-label">Icon Class</label>
                            <div class="input-group">
                                <span class="input-group-text"><i id="editIconPreview" class="fas fa-briefcase"></i></span>
                                <input type="text" class="form-control" id="edit_icon" name="icon">
                            </div>
                            <div class="form-text">Enter a Font Awesome icon class (e.g., fas fa-briefcase, fas fa-code)</div>
                            <div class="mt-2">
                                <p>Suggested icons:</p>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary edit-icon-option" data-icon="fas fa-briefcase"><i class="fas fa-briefcase"></i></button>
                                    <button type="button" class="btn btn-outline-secondary edit-icon-option" data-icon="fas fa-code"><i class="fas fa-code"></i></button>
                                    <button type="button" class="btn btn-outline-secondary edit-icon-option" data-icon="fas fa-chart-line"><i class="fas fa-chart-line"></i></button>
                                    <button type="button" class="btn btn-outline-secondary edit-icon-option" data-icon="fas fa-graduation-cap"><i class="fas fa-graduation-cap"></i></button>
                                    <button type="button" class="btn btn-outline-secondary edit-icon-option" data-icon="fas fa-hospital"><i class="fas fa-hospital"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/admin.js"></script>
    
    <script>
        // Icon preview for Add Modal
        document.getElementById('icon').addEventListener('input', function() {
            updateIconPreview(this.value, 'iconPreview');
        });
        
        // Icon options for Add Modal
        document.querySelectorAll('.icon-option').forEach(function(button) {
            button.addEventListener('click', function() {
                const icon = this.getAttribute('data-icon');
                document.getElementById('icon').value = icon;
                updateIconPreview(icon, 'iconPreview');
            });
        });
        
        // Icon preview for Edit Modal
        document.getElementById('edit_icon').addEventListener('input', function() {
            updateIconPreview(this.value, 'editIconPreview');
        });
        
        // Icon options for Edit Modal
        document.querySelectorAll('.edit-icon-option').forEach(function(button) {
            button.addEventListener('click', function() {
                const icon = this.getAttribute('data-icon');
                document.getElementById('edit_icon').value = icon;
                updateIconPreview(icon, 'editIconPreview');
            });
        });
        
        // Update icon preview
        function updateIconPreview(iconClass, previewId) {
            const preview = document.getElementById(previewId);
            preview.className = '';
            preview.className = iconClass;
        }
        
        // Populate Edit Modal
        document.querySelectorAll('.edit-category-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const icon = this.getAttribute('data-icon');
                
                document.getElementById('edit_category_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_icon').value = icon;
                updateIconPreview(icon, 'editIconPreview');
            });
        });
    </script>
</body>
</html>
