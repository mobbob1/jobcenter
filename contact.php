<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/email_utils.php';

$page_title = "Contact Us";
$message = '';
$message_type = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Get form data
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $message_content = $conn->real_escape_string($_POST['message']);
    
    // Simple validation
    if (empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } else {
        // Save to database
        $query = "INSERT INTO contact_messages (name, email, subject, message, status, created_at) 
                  VALUES (?, ?, ?, ?, 'new', NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $name, $email, $subject, $message_content);
        
        if ($stmt->execute()) {
            // Send notification email to admin
            $admin_email = 'admin@jobconnect.com'; // Change to actual admin email
            $email_subject = 'New Contact Form Submission: ' . $subject;
            $email_body = "
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>$message_content</p>
            ";
            
            $email_sent = send_email($admin_email, $email_subject, $email_body);
            
            // Send confirmation email to user
            $user_subject = 'Thank you for contacting JobConnect';
            $user_message = "
                <h2>Thank You for Contacting JobConnect</h2>
                <p>Dear $name,</p>
                <p>We have received your message and will get back to you as soon as possible.</p>
                <p>Here's a summary of your inquiry:</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>$message_content</p>
                <p>Best regards,<br>The JobConnect Team</p>
            ";
            
            $user_email_sent = send_email($email, $user_subject, $user_message);
            
            $message = 'Your message has been sent successfully. We will get back to you soon!';
            $message_type = 'success';
            
            // Clear form data after successful submission
            $name = $email = $subject = $message_content = '';
        } else {
            $message = 'Sorry, there was an error sending your message. Please try again later.';
            $message_type = 'danger';
        }
    }
}
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
        .contact-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(13, 110, 253, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            color: #0d6efd;
            font-size: 24px;
        }
        .contact-info {
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            height: 100%;
            transition: all 0.3s ease;
        }
        .contact-info:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .map-container {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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
                    <h1>Contact Us</h1>
                    <p class="lead">We'd love to hear from you. Get in touch with our team.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-7 mb-4 mb-lg-0">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 p-md-5">
                            <h2 class="mb-4">Send Us a Message</h2>
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Your Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Your Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message_content) ? htmlspecialchars($message_content) : ''; ?></textarea>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="privacy" required>
                                    <label class="form-check-label" for="privacy">I agree to the <a href="#">privacy policy</a> and consent to being contacted regarding my inquiry.</label>
                                </div>
                                <button type="submit" name="contact_submit" class="btn btn-primary btn-lg">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="col-lg-5">
                    <div class="contact-info bg-white mb-4">
                        <div class="d-flex">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h4>Our Location</h4>
                                <p class="mb-0">123 JobConnect Avenue, Tech District<br>Silicon Valley, CA 94000</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-info bg-white mb-4">
                        <div class="d-flex">
                            <div class="contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h4>Call Us</h4>
                                <p class="mb-0">+1 (555) 123-4567<br>+1 (555) 987-6543</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-info bg-white mb-4">
                        <div class="d-flex">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h4>Email Us</h4>
                                <p class="mb-0">info@jobconnect.com<br>support@jobconnect.com</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-info bg-white">
                        <div class="d-flex">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h4>Office Hours</h4>
                                <p class="mb-0">Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 2:00 PM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Map Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h2 class="mb-4">Find Us</h2>
                    <p class="lead">Visit our office or get in touch with us</p>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="map-container">
                        <!-- Replace with your Google Maps embed code -->
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3172.3325395304414!2d-122.01473868469158!3d37.33463524513264!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x808fb59127ce078f%3A0x18e1c3ce7becf1b!2sApple%20Park!5e0!3m2!1sen!2sus!4v1637309850935!5m2!1sen!2sus" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- FAQ Section -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h2 class="mb-4">Frequently Asked Questions</h2>
                    <p class="lead">Find quick answers to common questions</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="contactFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How can I create an account on JobConnect?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    Creating an account is easy! Simply click on the "Register" button in the top right corner of our website. You'll be asked to provide some basic information, choose a username and password, and select whether you're a job seeker or employer.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    Is it free to post jobs on JobConnect?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    We offer both free and premium job posting options. Basic job listings are free, while premium listings offer enhanced visibility, featured placement, and additional tools to help you find the right candidates faster.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    How do I apply for a job on JobConnect?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    To apply for a job, first create an account as a job seeker. Then browse our job listings and click "Apply Now" on any position that interests you. You'll need to complete your profile with your resume, experience, and other relevant information before submitting applications.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    How can I get notified about new job postings?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    You can set up job alerts by specifying your preferences such as job category, location, and salary range. We'll send you email notifications when new jobs matching your criteria are posted on our platform.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <h2>Need more information?</h2>
                    <p class="lead mb-0">Our team is ready to assist you with any questions or concerns.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="tel:+15551234567" class="btn btn-light btn-lg me-2 mb-2 mb-md-0">
                        <i class="fas fa-phone-alt me-2"></i> Call Us
                    </a>
                    <a href="mailto:info@jobconnect.com" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-envelope me-2"></i> Email Us
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <!-- Form validation -->
    <script>
        // Simple form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const subject = document.getElementById('subject').value.trim();
                const message = document.getElementById('message').value.trim();
                const privacy = document.getElementById('privacy').checked;
                
                if (!name || !email || !subject || !message || !privacy) {
                    event.preventDefault();
                    alert('Please fill in all required fields and accept the privacy policy.');
                }
            });
        });
    </script>
</body>
</html>
