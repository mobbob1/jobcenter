<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'employer') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Resolve company_id
$company_id = null;
$company = null;
$cs = $conn->prepare('SELECT id, company_name, logo FROM companies WHERE user_id = ?');
$cs->bind_param('i', $user_id);
$cs->execute();
$cres = $cs->get_result();
if ($cres && $cres->num_rows > 0) {
    $company = $cres->fetch_assoc();
    $company_id = (int)$company['id'];
}

if (!$company_id) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Jobs - JobConnect</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    </head>
    <body>
    <?php include '../includes/header.php'; ?>
    <main class="py-5">
        <div class="container">
            <div class="alert alert-warning">
                You need to complete your company profile before managing jobs.
                <a href="profile.php" class="alert-link">Go to Company Profile</a>.
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Map request status parameter
$allowed_job_status = ['active','inactive','filled','expired'];
$status_param = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
if ($status_param === 'pending') { $status_param = 'inactive'; }
if ($status_param && !in_array($status_param, $allowed_job_status, true)) { $status_param = ''; }

$search = trim($_GET['q'] ?? '');

// Handle status update action
if (isset($_GET['action']) && $_GET['action'] === 'set_status') {
    $job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
    $new_status = isset($_GET['to']) ? strtolower($_GET['to']) : '';
    if ($job_id > 0 && in_array($new_status, $allowed_job_status, true)) {
        $up = $conn->prepare('UPDATE jobs SET status = ? WHERE id = ? AND company_id = ?');
        $up->bind_param('sii', $new_status, $job_id, $company_id);
        $up->execute();
    }
    $redirect = 'manage-jobs.php' . ($status_param ? ('?status=' . urlencode($_GET['status'])) : '');
    header('Location: ' . $redirect);
    exit();
}

// Fetch jobs
$sql = "SELECT j.*, 
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS app_count,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'pending') AS app_pending
        FROM jobs j WHERE j.company_id = ?";
$params = [$company_id];
$types = 'i';
if ($status_param) { $sql .= ' AND j.status = ?'; $params[] = $status_param; $types .= 's'; }
if ($search !== '') { $sql .= ' AND j.title LIKE ?'; $params[] = '%' . $search . '%'; $types .= 's'; }
$sql .= ' ORDER BY j.created_at DESC';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - JobConnect</title>
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
                <h1 class="h3 mb-0">Manage Jobs</h1>
                <small class="text-muted">Filter and manage your job listings</small>
            </div>
            <a href="post-job.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Post Job</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Job title">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="active" <?php echo $status_param==='active'?'selected':''; ?>>Active</option>
                            <option value="pending" <?php echo ($status_param==='inactive' && (($_GET['status'] ?? '')==='pending'))?'selected':''; ?>>Pending</option>
                            <option value="inactive" <?php echo ($status_param==='inactive' && (($_GET['status'] ?? '')!=='pending'))?'selected':''; ?>>Inactive</option>
                            <option value="filled" <?php echo $status_param==='filled'?'selected':''; ?>>Filled</option>
                            <option value="expired" <?php echo $status_param==='expired'?'selected':''; ?>>Expired</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div><button class="btn btn-outline-secondary">Apply</button></div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="alert alert-info">No jobs found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th>Posted</th>
                            <th>Deadline</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $j): ?>
                            <tr>
                                <td>
                                    <a class="text-decoration-none" href="../job-details.php?id=<?php echo (int)$j['id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($j['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php $st = $j['status']; $badge='secondary';
                                    if ($st==='active') $badge='success'; elseif ($st==='inactive') $badge='warning'; elseif ($st==='filled') $badge='primary'; elseif ($st==='expired') $badge='dark'; ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($st); ?></span>
                                </td>
                                <td>
                                    <a href="applications.php?job_id=<?php echo (int)$j['id']; ?>" class="text-decoration-none">
                                        <?php echo (int)$j['app_count']; ?> total
                                        <?php if ((int)$j['app_pending']>0): ?>
                                            <span class="badge bg-info ms-1"><?php echo (int)$j['app_pending']; ?> pending</span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($j['created_at'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($j['deadline'])); ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if ($j['status']!=='active'): ?>
                                            <a class="btn btn-sm btn-outline-success" href="manage-jobs.php?action=set_status&to=active&job_id=<?php echo (int)$j['id']; ?><?php echo $status_param?('&status='.urlencode($_GET['status'])):''; ?>">Activate</a>
                                        <?php endif; ?>
                                        <?php if ($j['status']!=='inactive'): ?>
                                            <a class="btn btn-sm btn-outline-warning" href="manage-jobs.php?action=set_status&to=inactive&job_id=<?php echo (int)$j['id']; ?><?php echo $status_param?('&status='.urlencode($_GET['status'])):''; ?>">Set Inactive</a>
                                        <?php endif; ?>
                                        <?php if ($j['status']!=='filled'): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="manage-jobs.php?action=set_status&to=filled&job_id=<?php echo (int)$j['id']; ?><?php echo $status_param?('&status='.urlencode($_GET['status'])):''; ?>">Mark Filled</a>
                                        <?php endif; ?>
                                        <?php if ($j['status']!=='expired'): ?>
                                            <a class="btn btn-sm btn-outline-dark" href="manage-jobs.php?action=set_status&to=expired&job_id=<?php echo (int)$j['id']; ?><?php echo $status_param?('&status='.urlencode($_GET['status'])):''; ?>">Mark Expired</a>
                                        <?php endif; ?>
                                    </div>
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
