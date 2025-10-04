<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'jobseeker') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Resolve job_seeker_id
$js_id = null;
$js_stmt = $conn->prepare('SELECT id FROM job_seekers WHERE user_id = ?');
$js_stmt->bind_param('i', $user_id);
$js_stmt->execute();
$js_res = $js_stmt->get_result();
if ($js_res && $js_res->num_rows > 0) { $js_id = (int)$js_res->fetch_assoc()['id']; }

if ($js_id === null) {
    $saved_jobs = [];
} else {
    // Unsave action
    if (isset($_GET['unsave'])) {
        $job_id = (int)$_GET['unsave'];
        $del = $conn->prepare('DELETE FROM saved_jobs WHERE job_id = ? AND job_seeker_id = ?');
        $del->bind_param('ii', $job_id, $js_id);
        $del->execute();
        header('Location: saved-jobs.php');
        exit();
    }

    $sql = "SELECT j.id, j.title, j.location, j.job_type, j.created_at, c.company_name, c.logo
            FROM saved_jobs s
            JOIN jobs j ON s.job_id = j.id
            JOIN companies c ON j.company_id = c.id
            WHERE s.job_seeker_id = ?
            ORDER BY s.saved_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $js_id);
    $stmt->execute();
    $saved_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs - JobConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Saved Jobs</h1>
                <small class="text-muted">Jobs you have saved to review later</small>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <?php if ($js_id === null): ?>
            <div class="alert alert-warning">Complete your profile to save jobs. <a href="profile.php" class="alert-link">Go to Profile</a>.</div>
        <?php elseif (empty($saved_jobs)): ?>
            <div class="text-center py-5">
                <p class="mb-3">You haven't saved any jobs yet.</p>
                <a href="../jobs.php" class="btn btn-primary">Browse Jobs</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($saved_jobs as $job): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="company-logo me-2">
                                        <?php if (!empty($job['logo'])): ?>
                                            <img src="../uploads/company_logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-building text-secondary"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-0"><a class="text-decoration-none" href="../job-details.php?id=<?php echo (int)$job['id']; ?>"><?php echo htmlspecialchars($job['title']); ?></a></h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($job['company_name']); ?></small>
                                    </div>
                                </div>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($job['location']); ?></small>
                                    <div class="btn-group">
                                        <a href="../job-details.php?id=<?php echo (int)$job['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="saved-jobs.php?unsave=<?php echo (int)$job['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this saved job?')">Remove</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
