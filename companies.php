<?php
session_start();
require_once 'includes/db_connect.php';

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$industry = isset($_GET['industry']) ? $conn->real_escape_string($_GET['industry']) : '';

// Build the query
$query = "SELECT c.*, 
          COUNT(DISTINCT j.id) as job_count,
          GROUP_CONCAT(DISTINCT IFNULL(j.location, '') SEPARATOR ', ') as job_locations,
          GROUP_CONCAT(DISTINCT IFNULL(cat.name, '') SEPARATOR ', ') as industries
          FROM companies c
          LEFT JOIN jobs j ON c.id = j.company_id AND j.status = 'active'
          LEFT JOIN categories cat ON j.category_id = cat.id";

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

if (!empty($location)) {
    $where_clauses[] = "j.location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

if (!empty($industry)) {
    $where_clauses[] = "cat.name LIKE ?";
    $params[] = "%$industry%";
    $types .= "s";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY c.id ORDER BY c.company_name ASC LIMIT ?, ?";
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt === false) {
    trigger_error('Wrong SQL: ' . $query . ' Error: ' . $conn->errno . ' ' . $conn->error, E_USER_ERROR);
}
if (!empty($params)) {
    if (!$stmt->bind_param($types, ...$params)) {
        trigger_error('Wrong SQL: ' . $query . ' Error: ' . $stmt->errno . ' ' . $stmt->error, E_USER_ERROR);
    }
}
if (!$stmt->execute()) {
    trigger_error('Wrong SQL: ' . $query . ' Error: ' . $stmt->errno . ' ' . $stmt->error, E_USER_ERROR);
}
$result = $stmt->get_result();

// Count total records for pagination
$count_query = "SELECT COUNT(DISTINCT c.id) as total FROM companies c
                LEFT JOIN jobs j ON c.id = j.company_id AND j.status = 'active'
                LEFT JOIN categories cat ON j.category_id = cat.id";

if (!empty($where_clauses)) {
    $count_query .= " WHERE " . implode(" AND ", $where_clauses);
}

$count_stmt = $conn->prepare($count_query);
if ($count_stmt === false) {
    trigger_error('Wrong SQL: ' . $count_query . ' Error: ' . $conn->errno . ' ' . $conn->error, E_USER_ERROR);
}
if (!empty($params)) {
    // Remove the last two parameters (offset and limit)
    array_pop($params);
    array_pop($params);
    if (!empty($params)) {
        if (!$count_stmt->bind_param(substr($types, 0, -2), ...$params)) {
            trigger_error('Wrong SQL: ' . $count_query . ' Error: ' . $count_stmt->errno . ' ' . $count_stmt->error, E_USER_ERROR);
        }
    }
}
if (!$count_stmt->execute()) {
    trigger_error('Wrong SQL: ' . $count_query . ' Error: ' . $count_stmt->errno . ' ' . $count_stmt->error, E_USER_ERROR);
}
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get all locations for filter dropdown
$locations_query = "SELECT DISTINCT location FROM jobs WHERE location != '' ORDER BY location";
$locations_result = $conn->query($locations_query);

// Get all industries/categories for filter dropdown
$industries_query = "SELECT DISTINCT name FROM categories ORDER BY name";
$industries_result = $conn->query($industries_query);

$page_title = "Companies";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JobConnect</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="hero-small bg-primary text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h1>Companies</h1>
                    <p class="lead">Discover great companies that are hiring now</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Companies Section -->
    <section class="py-5">
        <div class="container">
            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="companies.php" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Company name or keywords" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">All Locations</option>
                                <?php while ($location_row = $locations_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($location_row['location']); ?>" <?php echo (isset($_GET['location']) && $_GET['location'] == $location_row['location']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location_row['location']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="industry" class="form-label">Industry</label>
                            <select class="form-select" id="industry" name="industry">
                                <option value="">All Industries</option>
                                <?php while ($industry_row = $industries_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($industry_row['name']); ?>" <?php echo (isset($_GET['industry']) && $_GET['industry'] == $industry_row['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($industry_row['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Companies List -->
            <div class="row">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($company = $result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="company-logo me-3">
                                            <?php if (!empty($company['logo']) && file_exists('uploads/company_logos/' . $company['logo'])): ?>
                                                <img src="uploads/company_logos/<?php echo htmlspecialchars($company['logo']); ?>" alt="<?php echo htmlspecialchars($company['company_name']); ?>" class="img-fluid" style="max-width: 80px; max-height: 80px;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                                    <i class="fas fa-building fa-2x text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0">
                                                <a href="company-details.php?id=<?php echo $company['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                                </a>
                                            </h5>
                                            <?php if (!empty($company['location'])): ?>
                                                <p class="text-muted mb-0"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($company['location']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="card-text">
                                        <?php 
                                        if (!empty($company['description'])) {
                                            echo htmlspecialchars(substr($company['description'], 0, 100)) . (strlen($company['description']) > 100 ? '...' : '');
                                        } else {
                                            echo '<span class="text-muted">No description available</span>';
                                        }
                                        ?>
                                    </p>
                                    
                                    <?php if ($company['job_count'] > 0): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-primary"><?php echo $company['job_count']; ?> open position<?php echo $company['job_count'] > 1 ? 's' : ''; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($company['industries'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Industries: <?php echo htmlspecialchars($company['industries']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($company['job_locations'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Job Locations: <?php echo htmlspecialchars($company['job_locations']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <a href="company-details.php?id=<?php echo $company['id']; ?>" class="btn btn-outline-primary btn-sm">View Profile</a>
                                        <a href="jobs.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline-secondary btn-sm">View Jobs</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No companies found matching your criteria. Try adjusting your filters or search terms.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['location']) ? '&location='.$_GET['location'] : '').(isset($_GET['industry']) ? '&industry='.$_GET['industry'] : ''); ?>">Previous</a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo (isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['location']) ? '&location='.$_GET['location'] : '').(isset($_GET['industry']) ? '&industry='.$_GET['industry'] : ''); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(isset($_GET['search']) ? '&search='.$_GET['search'] : '').(isset($_GET['location']) ? '&location='.$_GET['location'] : '').(isset($_GET['industry']) ? '&industry='.$_GET['industry'] : ''); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
