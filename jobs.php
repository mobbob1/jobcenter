<?php
require_once 'includes/db_connect.php';

// Check for error messages
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error_message = 'There was an error processing your request. Please try again.';
            break;
        default:
            $error_message = 'An unknown error occurred.';
    }
}

// Initialize filters
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$job_type = isset($_GET['job_type']) ? $_GET['job_type'] : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT j.*, c.company_name, c.logo, cat.name as category_name 
          FROM jobs j 
          JOIN companies c ON j.company_id = c.id 
          JOIN categories cat ON j.category_id = cat.id 
          WHERE j.status = 'active'";

$params = [];
$types = "";

// Add filters
if (!empty($keyword)) {
    $query .= " AND (j.title LIKE ? OR j.description LIKE ?)";
    $keyword_param = "%$keyword%";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= "ss";
}

if (!empty($location)) {
    $query .= " AND j.location LIKE ?";
    $location_param = "%$location%";
    $params[] = $location_param;
    $types .= "s";
}

if ($category > 0) {
    $query .= " AND j.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if (!empty($job_type)) {
    $query .= " AND j.job_type = ?";
    $params[] = $job_type;
    $types .= "s";
}

// Count total results for pagination
$count_query = str_replace("j.*, c.company_name, c.logo, cat.name as category_name", "COUNT(*) as total", $query);
$stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total_results = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $per_page);

// Get jobs with pagination
$query .= " ORDER BY j.is_featured DESC, j.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$jobs = $stmt->get_result();

// Get categories for filter
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs - JobConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Custom styles for job cards */
        .company-logo {
            width: 100%;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .company-logo img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
        }
        /* Ensure footer stays at bottom */
        main {
            min-height: calc(100vh - 300px); /* Adjust based on footer height */
            overflow: visible;
        }
        .jobs-listing {
            overflow: visible;
        }
        /* Enhanced job cards container */
        .jobs-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }
        /* Ensure job cards take full width */
        .job-card {
            width: 100%;
            margin-bottom: 1rem;
        }
        .job-card .card {
            width: 100%;
            display: block;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .job-card .row {
                flex-direction: column;
            }
            .job-card .col-md-3 {
                text-align: left !important;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- Page Header -->
        <section class="page-header bg-light py-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-8">
                        <h1 class="mb-3">Browse Jobs</h1>
                        <p class="lead">Find the perfect job that matches your skills and experience</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Search Section -->
        <section class="search-section py-4 bg-white border-bottom">
            <div class="container">
                <form action="jobs.php" method="GET" class="job-search-form">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="keyword" class="form-control" placeholder="Job title or keywords" value="<?php echo htmlspecialchars($keyword); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                <input type="text" name="location" class="form-control" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-select">
                                <option value="0">All Categories</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo $cat['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="job-type-filters">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="job_type" id="all_types" value="" <?php echo empty($job_type) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="all_types">All Types</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="job_type" id="full_time" value="Full-Time" <?php echo ($job_type == 'Full-Time') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="full_time">Full-Time</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="job_type" id="part_time" value="Part-Time" <?php echo ($job_type == 'Part-Time') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="part_time">Part-Time</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="job_type" id="contract" value="Contract" <?php echo ($job_type == 'Contract') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="contract">Contract</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="job_type" id="freelance" value="Freelance" <?php echo ($job_type == 'Freelance') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="freelance">Freelance</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="job_type" id="internship" value="Internship" <?php echo ($job_type == 'Internship') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="internship">Internship</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Jobs Listing -->
        <section class="jobs-listing py-5">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <!-- Results Summary -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <p class="mb-0">
                                <strong><?php echo $total_results; ?></strong> jobs found
                                <?php if (!empty($keyword) || !empty($location) || $category > 0 || !empty($job_type)): ?>
                                    <a href="jobs.php" class="btn btn-sm btn-outline-secondary ms-2">Clear Filters</a>
                                <?php endif; ?>
                            </p>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    Sort By: Newest First
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                                    <li><a class="dropdown-item" href="#">Newest First</a></li>
                                    <li><a class="dropdown-item" href="#">Oldest First</a></li>
                                    <li><a class="dropdown-item" href="#">Most Relevant</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger mb-4">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Job Cards -->
                        <?php if ($jobs->num_rows > 0): ?>
                            <div class="jobs-container">
                                <?php while ($job = $jobs->fetch_assoc()): ?>
                                    <div class="job-card mb-4">
                                        <div class="card shadow-sm">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-2 col-sm-3 mb-3 mb-md-0">
                                                        <div class="company-logo">
                                                            <img src="uploads/company_logos/<?php echo $job['logo']; ?>" alt="<?php echo $job['company_name']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-7 col-sm-9">
                                                        <h2 class="h5 mb-1">
                                                            <a href="job-details.php?id=<?php echo $job['id']; ?>" class="text-dark text-decoration-none">
                                                                <?php echo $job['title']; ?>
                                                                <?php if ($job['is_featured']): ?>
                                                                    <span class="badge bg-warning text-dark ms-2">Featured</span>
                                                                <?php endif; ?>
                                                            </a>
                                                        </h2>
                                                        <p class="company mb-2"><?php echo $job['company_name']; ?></p>
                                                        <div class="job-meta mb-2">
                                                            <span class="location me-3"><i class="fas fa-map-marker-alt me-1"></i> <?php echo $job['location']; ?></span>
                                                            <span class="job-type me-3 <?php echo strtolower($job['job_type']); ?>">
                                                                <i class="fas fa-briefcase me-1"></i> <?php echo $job['job_type']; ?>
                                                            </span>
                                                            <span class="category me-3"><i class="fas fa-tags me-1"></i> <?php echo $job['category_name']; ?></span>
                                                        </div>
                                                        <div class="salary-range mb-2">
                                                            <?php if (!empty($job['min_salary']) && !empty($job['max_salary'])): ?>
                                                                <i class="fas fa-money-bill-wave me-1"></i> 
                                                                GHS <?php echo number_format($job['min_salary']); ?> - GHS <?php echo number_format($job['max_salary']); ?>
                                                            <?php elseif (!empty($job['min_salary'])): ?>
                                                                <i class="fas fa-money-bill-wave me-1"></i> 
                                                                From GHS <?php echo number_format($job['min_salary']); ?>
                                                            <?php elseif (!empty($job['max_salary'])): ?>
                                                                <i class="fas fa-money-bill-wave me-1"></i> 
                                                                Up to GHS <?php echo number_format($job['max_salary']); ?>
                                                            <?php else: ?>
                                                                <i class="fas fa-money-bill-wave me-1"></i> Salary not specified
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="job-excerpt d-none d-md-block">
                                                            <?php 
                                                            $description = strip_tags($job['description']);
                                                            echo substr($description, 0, 120) . (strlen($description) > 120 ? '...' : ''); 
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 mt-3 mt-md-0 text-md-end">
                                                        <div class="deadline mb-2">
                                                            <small class="text-muted">
                                                                <i class="far fa-clock me-1"></i> Deadline: 
                                                                <?php echo date('M d, Y', strtotime($job['deadline'])); ?>
                                                            </small>
                                                        </div>
                                                        <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary">View Details</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?keyword=<?php echo urlencode($keyword); ?>&location=<?php echo urlencode($location); ?>&category=<?php echo $category; ?>&job_type=<?php echo urlencode($job_type); ?>&page=<?php echo ($page - 1); ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?keyword=<?php echo urlencode($keyword); ?>&location=<?php echo urlencode($location); ?>&category=<?php echo $category; ?>&job_type=<?php echo urlencode($job_type); ?>&page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?keyword=<?php echo urlencode($keyword); ?>&location=<?php echo urlencode($location); ?>&category=<?php echo $category; ?>&job_type=<?php echo urlencode($job_type); ?>&page=<?php echo ($page + 1); ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <img src="assets/img/no-results.svg" alt="No Jobs Found" class="img-fluid mb-3" style="max-width: 200px;">
                                <h3>No Jobs Found</h3>
                                <p class="text-muted">We couldn't find any jobs matching your search criteria.</p>
                                <a href="jobs.php" class="btn btn-primary mt-3">Clear Filters</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when job type or category changes
        document.querySelectorAll('input[name="job_type"]').forEach(input => {
            input.addEventListener('change', function() {
                document.querySelector('.job-search-form').submit();
            });
        });
        
        document.querySelector('select[name="category"]').addEventListener('change', function() {
            document.querySelector('.job-search-form').submit();
        });
    </script>
</body>
</html>
