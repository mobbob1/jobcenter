<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobConnect - Find Your Dream Job</title>
    <!-- Using CDN links instead of local files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="row">
                    <div class="col-md-8 offset-md-2 text-center">
                        <h1>Find Your Dream Job Today</h1>
                        <p class="lead">Connect with top employers and discover opportunities that match your skills</p>
                        
                        <!-- Search Form -->
                        <div class="search-form">
                            <form action="search-jobs.php" method="GET">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="text" name="keyword" class="form-control" placeholder="Job title, keywords">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" name="location" class="form-control" placeholder="Location">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary btn-block">Search</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Jobs Section -->
        <section class="featured-jobs">
            <div class="container">
                <h2 class="section-title">Featured Jobs</h2>
                <div class="row">
                    <?php
                    require_once 'includes/db_connect.php';
                    
                    // Get featured jobs
                    $sql = "SELECT j.*, c.company_name, c.logo FROM jobs j 
                            JOIN companies c ON j.company_id = c.id 
                            WHERE j.is_featured = 1 AND j.status = 'active'
                            ORDER BY j.created_at DESC LIMIT 6";
                    
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($job = $result->fetch_assoc()) {
                            ?>
                            <div class="col-md-4 mb-4">
                                <div class="job-card">
                                    <div class="company-logo">
                                        <img src="uploads/company_logos/<?php echo $job['logo']; ?>" alt="<?php echo $job['company_name']; ?>">
                                    </div>
                                    <div class="job-info">
                                        <h3><a href="job-details.php?id=<?php echo $job['id']; ?>"><?php echo $job['title']; ?></a></h3>
                                        <p class="company"><?php echo $job['company_name']; ?></p>
                                        <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo $job['location']; ?></p>
                                        <p class="job-type <?php echo strtolower($job['job_type']); ?>"><?php echo $job['job_type']; ?></p>
                                        <p class="deadline">Deadline: <?php echo date('d M Y', strtotime($job['deadline'])); ?></p>
                                        <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-12 text-center"><p>No featured jobs available at the moment.</p></div>';
                    }
                    ?>
                </div>
                <div class="text-center mt-4">
                    <a href="jobs.php" class="btn btn-outline-primary">View All Jobs</a>
                </div>
            </div>
        </section>

        <!-- Job Categories Section -->
        <section class="job-categories">
            <div class="container">
                <h2 class="section-title">Popular Job Categories</h2>
                <div class="row">
                    <?php
                    // Get job categories
                    $sql = "SELECT c.*, COUNT(j.id) as job_count 
                            FROM categories c 
                            LEFT JOIN jobs j ON c.id = j.category_id AND j.status = 'active'
                            GROUP BY c.id 
                            ORDER BY job_count DESC 
                            LIMIT 8";
                    
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($category = $result->fetch_assoc()) {
                            ?>
                            <div class="col-md-3 col-sm-6 mb-4">
                                <div class="category-card">
                                    <div class="category-icon">
                                        <i class="<?php echo $category['icon']; ?>"></i>
                                    </div>
                                    <h3><?php echo $category['name']; ?></h3>
                                    <p><?php echo $category['job_count']; ?> Jobs Available</p>
                                    <a href="jobs.php?category=<?php echo $category['id']; ?>" class="stretched-link"></a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-12 text-center"><p>No categories available.</p></div>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works">
            <div class="container">
                <h2 class="section-title">How It Works</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="step-card">
                            <div class="step-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h3>Create an Account</h3>
                            <p>Sign up as a job seeker or employer to access all features</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step-card">
                            <div class="step-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3>Search Jobs</h3>
                            <p>Find the perfect job match for your skills and experience</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step-card">
                            <div class="step-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3>Apply for Jobs</h3>
                            <p>Submit your application with your CV and get hired</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Top Employers Section -->
        <section class="top-employers">
            <div class="container">
                <h2 class="section-title">Top Employers</h2>
                <div class="row">
                    <?php
                    // Get top employers
                    $sql = "SELECT c.*, COUNT(j.id) as job_count 
                            FROM companies c 
                            JOIN jobs j ON c.id = j.company_id AND j.status = 'active'
                            GROUP BY c.id 
                            ORDER BY job_count DESC 
                            LIMIT 6";
                    
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($company = $result->fetch_assoc()) {
                            ?>
                            <div class="col-md-2 col-sm-4 col-6 mb-4">
                                <div class="employer-logo">
                                    <a href="company-profile.php?id=<?php echo $company['id']; ?>">
                                        <img src="uploads/company_logos/<?php echo $company['logo']; ?>" alt="<?php echo $company['company_name']; ?>">
                                    </a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-12 text-center"><p>No employers available.</p></div>';
                    }
                    ?>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Using CDN links for JavaScript files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Basic JavaScript functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('JobConnect website loaded successfully!');
    });
    </script>
</body>
</html>
