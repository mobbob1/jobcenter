<?php
// Ensure app_url helper is available (same implementation as header.php)
if (!function_exists('app_url')) {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $appRootFs = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__))), '/');
    $baseUrl = str_replace($docRoot, '', $appRootFs);
    if ($baseUrl === false) { $baseUrl = ''; }
    if ($baseUrl === '' || $baseUrl[0] !== '/') { $baseUrl = '/' . ltrim($baseUrl, '/'); }
    function app_url($path) {
        global $baseUrl;
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
?>
<footer class="site-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h5>JobConnect</h5>
                <p>Your trusted platform for finding the perfect job match. Connect with top employers and discover opportunities that match your skills and experience.</p>
                <div class="social-links mt-3">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h5>For Job Seekers</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo app_url('jobs.php'); ?>">Browse Jobs</a></li>
                    <li><a href="<?php echo app_url('register.php?type=jobseeker'); ?>">Create Account</a></li>
                    <li><a href="<?php echo app_url('jobseeker/profile.php'); ?>">Job Seeker Profile</a></li>
                    <li><a href="<?php echo app_url('jobseeker/saved-jobs.php'); ?>">Saved Jobs</a></li>
                    <li><a href="<?php echo app_url('job-alerts.php'); ?>">Job Alerts</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h5>For Employers</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo app_url('register.php?type=employer'); ?>">Register Company</a></li>
                    <li><a href="<?php echo app_url('employer/post-job.php'); ?>">Post a Job</a></li>
                    <li><a href="<?php echo app_url('employer/manage-jobs.php'); ?>">Manage Jobs</a></li>
                    <li><a href="<?php echo app_url('employer/applications.php'); ?>">Browse Applications</a></li>
                    <li><a href="<?php echo app_url('pricing.php'); ?>">Pricing Plans</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo app_url('about.php'); ?>">About Us</a></li>
                    <li><a href="<?php echo app_url('contact.php'); ?>">Contact Us</a></li>
                    <li><a href="<?php echo app_url('privacy-policy.php'); ?>">Privacy Policy</a></li>
                    <li><a href="<?php echo app_url('terms.php'); ?>">Terms & Conditions</a></li>
                    <li><a href="<?php echo app_url('faq.php'); ?>">FAQs</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="copyright">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> JobConnect. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>Designed with <i class="fas fa-heart text-danger"></i> for job seekers and employers</p>
                </div>
            </div>
        </div>
    </div>
</footer>
