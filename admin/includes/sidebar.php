<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <a href="../index.php" class="text-decoration-none">
                <h3 class="text-white">JobConnect</h3>
            </a>
            <div class="text-white-50 small">Admin Panel</div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'pending-jobs.php') ? 'active' : ''; ?>" href="pending-jobs.php">
                    <i class="fas fa-clock me-2"></i>
                    Pending Jobs
                    <?php
                    // Count pending jobs (treat 'inactive' as pending)
                    $pending_jobs_query = "SELECT COUNT(*) as count FROM jobs WHERE status = 'inactive'";
                    $pending_jobs_result = $conn->query($pending_jobs_query);
                    $pending_jobs_count = $pending_jobs_result->fetch_assoc()['count'];
                    if ($pending_jobs_count > 0):
                    ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo $pending_jobs_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['manage-jobs.php', 'edit-job.php', 'add-job.php'])) ? 'active' : ''; ?>" href="manage-jobs.php">
                    <i class="fas fa-briefcase me-2"></i>
                    Manage Jobs
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['manage-companies.php', 'add-company.php', 'edit-company.php', 'view-company.php'])) ? 'active' : ''; ?>" href="manage-companies.php">
                    <i class="fas fa-building me-2"></i>
                    Companies
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['manage-applications.php', 'view-application.php'])) ? 'active' : ''; ?>" href="manage-applications.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Applications
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo (in_array($current_page, ['manage-users.php', 'add-user.php', 'edit-user.php', 'view-user.php'])) ? 'active' : ''; ?>" href="manage-users.php">
                    <i class="fas fa-users me-2"></i>
                    Users
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage-categories.php') ? 'active' : ''; ?>" href="manage-categories.php">
                    <i class="fas fa-tags me-2"></i>
                    Categories
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Reports</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Analytics
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Settings</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user-cog me-2"></i>
                    Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Site Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
