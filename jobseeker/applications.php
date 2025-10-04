<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connect.php';

// Check if jobseeker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'jobseeker') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$allowed_status = ['pending','reviewed','shortlisted','rejected','hired'];
if ($status_filter && !in_array($status_filter, $allowed_status, true)) {
    $status_filter = '';
}

// Get job_seeker profile id
$js_id = null;
$js_stmt = $conn->prepare('SELECT id FROM job_seekers WHERE user_id = ?');
$js_stmt->bind_param('i', $user_id);
$js_stmt->execute();
$js_res = $js_stmt->get_result();
if ($js_res && $js_res->num_rows > 0) {
    $js_id = (int)$js_res->fetch_assoc()['id'];
}

$applications = [];
if ($js_id !== null) {
    $sql = "SELECT a.id, a.status, a.applied_at, a.cv_file, j.id AS job_id, j.title, j.location, j.job_type,
                   c.company_name, c.logo AS company_logo
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            JOIN companies c ON j.company_id = c.id
            WHERE a.job_seeker_id = ?";
    $types = 'i';
    $params = [$js_id];
    if ($status_filter) {
        $sql .= ' AND a.status = ?';
        $types .= 's';
        $params[] = $status_filter;
    }
    $sql .= ' ORDER BY a.applied_at DESC';

    $stmt = $conn->prepare($sql);
    if ($status_filter) {
        $stmt->bind_param($types, $params[0], $params[1]);
    } else {
        $stmt->bind_param($types, $params[0]);
    }
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - JobConnect</title>
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
                <h1 class="h3 mb-0">My Applications</h1>
                <small class="text-muted">Track your job application statuses</small>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter by status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php foreach ($allowed_status as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo ($status_filter === $st ? 'selected' : ''); ?>><?php echo ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($js_id === null): ?>
            <div class="alert alert-warning">You need to complete your profile first. <a href="profile.php" class="alert-link">Go to Profile</a>.</div>
        <?php elseif (empty($applications)): ?>
            <div class="alert alert-info">No applications found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>CV</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><a href="../job-details.php?id=<?php echo (int)$app['job_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($app['title']); ?></a></td>
                                <td class="d-flex align-items-center">
                                    <div class="me-2" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid #eee;border-radius:4px;overflow:hidden;">
                                        <?php if (!empty($app['company_logo'])): ?>
                                            <img src="../uploads/company_logos/<?php echo htmlspecialchars($app['company_logo']); ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
                                        <?php else: ?>
                                            <i class="fas fa-building text-secondary"></i>
                                        <?php endif; ?>
                                    </div>
                                    <?php echo htmlspecialchars($app['company_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($app['location']); ?></td>
                                <td><?php echo htmlspecialchars($app['job_type']); ?></td>
                                <td>
                                    <?php $status = $app['status'];
                                    $badge = 'secondary';
                                    if ($status==='pending') $badge='info';
                                    elseif ($status==='reviewed') $badge='warning';
                                    elseif ($status==='shortlisted') $badge='success';
                                    elseif ($status==='rejected') $badge='danger';
                                    elseif ($status==='hired') $badge='primary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td>
                                    <?php if (!empty($app['cv_file'])): ?>
                                        <a href="../<?php echo htmlspecialchars($app['cv_file']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="../job-details.php?id=<?php echo (int)$app['job_id']; ?>" class="btn btn-sm btn-outline-primary">View Job</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
