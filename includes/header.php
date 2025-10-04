<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
// Initialize user_type variable to avoid undefined array key warnings
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Compute application base URL once (works whether app is at domain root or a subfolder)
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
<header class="site-header">
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand" href="<?php echo app_url('index.php'); ?>">
                <h1 class="m-0">JobConnect</h1>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo app_url('index.php'); ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'jobs.php') ? 'active' : ''; ?>" href="<?php echo app_url('jobs.php'); ?>">Browse Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'companies.php') ? 'active' : ''; ?>" href="<?php echo app_url('companies.php'); ?>">Companies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" href="<?php echo app_url('about.php'); ?>">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>" href="<?php echo app_url('contact.php'); ?>">Contact</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($user_type == 'employer'): ?>
                            <li class="nav-item me-2">
                                <a class="nav-link btn btn-outline-primary btn-sm px-3" href="<?php echo app_url('employer/post-job.php'); ?>">Post a Job</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo $username; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo app_url('employer/dashboard.php'); ?>">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('employer/profile.php'); ?>">Company Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('employer/manage-jobs.php'); ?>">Manage Jobs</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('employer/applications.php'); ?>">Applications</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('logout.php'); ?>">Logout</a></li>
                                </ul>
                            </li>
                        <?php elseif ($user_type == 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Admin: <?php echo $username; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo app_url('admin/dashboard.php'); ?>">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('admin/manage-jobs.php'); ?>">Manage Jobs</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('admin/manage-employers.php'); ?>">Manage Employers</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('admin/manage-applications.php'); ?>">Applications</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('logout.php'); ?>">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo $username; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo app_url('jobseeker/dashboard.php'); ?>">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('jobseeker/profile.php'); ?>">My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('jobseeker/applications.php'); ?>">My Applications</a></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('jobseeker/saved-jobs.php'); ?>">Saved Jobs</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo app_url('logout.php'); ?>">Logout</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('login.php'); ?>">Login</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="registerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Register
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="registerDropdown">
                                <li><a class="dropdown-item" href="<?php echo app_url('register.php?type=jobseeker'); ?>">Job Seeker</a></li>
                                <li><a class="dropdown-item" href="<?php echo app_url('register.php?type=employer'); ?>">Employer</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
