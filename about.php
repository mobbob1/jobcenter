<?php
session_start();
$page_title = "About Us";
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
    <style>
        .team-member {
            transition: transform 0.3s ease;
        }
        .team-member:hover {
            transform: translateY(-5px);
        }
        .social-icons a {
            color: #6c757d;
            margin-right: 10px;
            transition: color 0.3s ease;
        }
        .social-icons a:hover {
            color: #0d6efd;
        }
        .feature-box {
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            height: 100%;
            transition: all 0.3s ease;
        }
        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="hero-small bg-primary text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h1>About JobConnect</h1>
                    <p class="lead">Connecting talented professionals with great companies</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="assets/images/about-main.jpg" alt="About JobConnect" class="img-fluid rounded shadow">
                </div>
                <div class="col-lg-6">
                    <h2 class="mb-4">Our Story</h2>
                    <p class="lead">JobConnect was founded in 2023 with a simple mission: to transform how people find jobs and how companies find talent.</p>
                    <p>In today's fast-paced job market, both job seekers and employers face numerous challenges. Job seekers struggle to find positions that match their skills and career goals, while employers find it difficult to identify candidates who are the perfect fit for their organizations.</p>
                    <p>That's where JobConnect comes in. We've built a platform that uses innovative technology and a deep understanding of the job market to create meaningful connections between talented professionals and forward-thinking companies.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Mission & Vision Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h2 class="mb-4">Our Mission & Vision</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="feature-box bg-white">
                        <div class="feature-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3>Our Mission</h3>
                        <p>To empower job seekers and employers by creating a transparent, efficient, and user-friendly platform that simplifies the job search and recruitment process.</p>
                        <p>We strive to provide the tools, resources, and support needed for both parties to make informed decisions and find the perfect match.</p>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="feature-box bg-white">
                        <div class="feature-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3>Our Vision</h3>
                        <p>To become the leading job platform that transforms the way people find employment and companies build their teams.</p>
                        <p>We envision a world where every professional finds fulfilling work that matches their skills and aspirations, and where companies can easily connect with the talent they need to thrive.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Values Section -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h2 class="mb-4">Our Core Values</h2>
                    <p class="lead">These principles guide everything we do at JobConnect</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-box bg-white">
                        <div class="feature-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h3>Integrity</h3>
                        <p>We believe in honesty, transparency, and ethical practices in all our interactions with job seekers, employers, and partners.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box bg-white">
                        <div class="feature-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h3>Innovation</h3>
                        <p>We continuously seek new ways to improve our platform and services to better serve our users and stay ahead of industry trends.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box bg-white">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Inclusivity</h3>
                        <p>We are committed to creating a diverse and inclusive platform that provides equal opportunities for all job seekers and employers.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Team Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h2 class="mb-4">Meet Our Team</h2>
                    <p class="lead">The passionate professionals behind JobConnect</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card team-member h-100">
                        <img src="assets/images/team-1.jpg" class="card-img-top" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title">John Doe</h5>
                            <p class="text-muted">Founder & CEO</p>
                            <p class="card-text">John brings over 15 years of experience in HR and recruitment technology.</p>
                            <div class="social-icons">
                                <a href="#"><i class="fab fa-linkedin"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card team-member h-100">
                        <img src="assets/images/team-2.jpg" class="card-img-top" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title">Jane Smith</h5>
                            <p class="text-muted">CTO</p>
                            <p class="card-text">Jane leads our technology team with expertise in AI and machine learning.</p>
                            <div class="social-icons">
                                <a href="#"><i class="fab fa-linkedin"></i></a>
                                <a href="#"><i class="fab fa-github"></i></a>
                                <a href="#"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card team-member h-100">
                        <img src="assets/images/team-3.jpg" class="card-img-top" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title">Michael Johnson</h5>
                            <p class="text-muted">Head of Operations</p>
                            <p class="card-text">Michael ensures our platform runs smoothly and efficiently for all users.</p>
                            <div class="social-icons">
                                <a href="#"><i class="fab fa-linkedin"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h2 class="display-4 fw-bold text-primary">10K+</h2>
                    <p class="lead">Job Seekers</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h2 class="display-4 fw-bold text-primary">500+</h2>
                    <p class="lead">Companies</p>
                </div>
                <div class="col-md-4">
                    <h2 class="display-4 fw-bold text-primary">5K+</h2>
                    <p class="lead">Successful Placements</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <h2>Ready to find your dream job or perfect candidate?</h2>
                    <p class="lead mb-0">Join thousands of job seekers and employers who trust JobConnect.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-light btn-lg">Get Started Today</a>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
