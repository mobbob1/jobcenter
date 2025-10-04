<?php
session_start();
require_once '../includes/db_connect.php';

// Admin guard
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Validate id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage-companies.php');
    exit();
}
$company_id = (int)$_GET['id'];

// Fetch company
$company = null;
$stmt = $conn->prepare("SELECT c.*, u.username, u.email AS user_email,
    CONCAT_WS(', ', NULLIF(c.city,''), NULLIF(c.state,''), NULLIF(c.country,'')) AS company_location
    FROM companies c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
if ($stmt) {
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $rs = $stmt->get_result();
    if ($rs && $rs->num_rows > 0) {
        $company = $rs->fetch_assoc();
    } else {
        header('Location: manage-companies.php');
        exit();
    }
} else {
    $error_message = 'Database error: ' . $conn->error;
}

// Compute logo URL (handle both uploads/companies and uploads/company_logos)
$logo_url = '../assets/img/default-company.png';
if (!empty($company['logo'])) {
    $cand1 = '../uploads/company_logos/' . $company['logo'];
    $cand2 = '../uploads/companies/' . $company['logo'];
    if (file_exists($cand1)) { $logo_url = $cand1; }
    elseif (file_exists($cand2)) { $logo_url = $cand2; }
}

// Stats: jobs by status, totals
$job_counts = ['active'=>0,'inactive'=>0,'filled'=>0,'expired'=>0,'other'=>0];
$totals = ['jobs'=>0,'applications'=>0];

$st = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM jobs WHERE company_id = ? GROUP BY status");
if ($st) {
    $st->bind_param('i', $company_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $status = $row['status'];
        $cnt = (int)$row['cnt'];
        if (isset($job_counts[$status])) { $job_counts[$status] = $cnt; } else { $job_counts['other'] += $cnt; }
        $totals['jobs'] += $cnt;
    }
}

$ap = $conn->prepare("SELECT COUNT(*) AS total FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.company_id = ?");
if ($ap) {
    $ap->bind_param('i', $company_id);
    $ap->execute();
    $ar = $ap->get_result();
    if ($ar && $ar->num_rows) { $totals['applications'] = (int)$ar->fetch_assoc()['total']; }
}

// Recent jobs
$recent_jobs = [];
$rj = $conn->prepare("SELECT j.id, j.title, j.status, j.created_at, j.deadline,
    COUNT(a.id) AS app_count
    FROM jobs j LEFT JOIN applications a ON a.job_id = j.id
    WHERE j.company_id = ? GROUP BY j.id ORDER BY j.created_at DESC LIMIT 10");
if ($rj) {
    $rj->bind_param('i', $company_id);
    $rj->execute();
    $rjr = $rj->get_result();
    if ($rjr) { $recent_jobs = $rjr->fetch_all(MYSQLI_ASSOC); }
}

$page_title = 'View Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JobConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-0"><?php echo htmlspecialchars($company['company_name']); ?></h1>
                    <div class="mt-1">
                        <?php if ($company['is_verified']): ?>
                            <span class="badge bg-success me-1"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark me-1"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                        <?php endif; ?>
                        <?php if ($company['is_featured']): ?>
                            <span class="badge bg-info"><i class="fas fa-star"></i> Featured</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        <a href="manage-companies.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                        <a href="edit-company.php?id=<?php echo $company_id; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                        <a href="../company-profile.php?id=<?php echo $company_id; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="fas fa-external-link-alt"></i> View Public</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <img src="<?php echo $logo_url; ?>" alt="Logo" class="img-fluid mb-3" style="max-height:120px;object-fit:contain;">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($company['company_name']); ?></h5>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($company['industry'] ?: ''); ?></p>
                            <?php if (!empty($company['company_location'])): ?>
                                <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($company['company_location']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($company['website'])): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank"><i class="fas fa-globe me-1"></i> Website</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><strong>Contact</strong></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <?php if (!empty($company['email'])): ?><li class="mb-2"><i class="fas fa-envelope me-2 text-muted"></i> <?php echo htmlspecialchars($company['email']); ?></li><?php endif; ?>
                                <?php if (!empty($company['phone'])): ?><li class="mb-2"><i class="fas fa-phone me-2 text-muted"></i> <?php echo htmlspecialchars($company['phone']); ?></li><?php endif; ?>
                                <?php if (!empty($company['address'])): ?><li class="mb-2"><i class="fas fa-map me-2 text-muted"></i> <?php echo htmlspecialchars($company['address']); ?></li><?php endif; ?>
                                <li class="mb-2"><i class="fas fa-user me-2 text-muted"></i> Account: <?php echo htmlspecialchars($company['username']); ?> (<?php echo htmlspecialchars($company['user_email']); ?>)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><strong>Social</strong></div>
                        <div class="card-body">
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (!empty($company['facebook_url'])): ?><a href="<?php echo htmlspecialchars($company['facebook_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fab fa-facebook"></i></a><?php endif; ?>
                                <?php if (!empty($company['twitter_url'])): ?><a href="<?php echo htmlspecialchars($company['twitter_url']); ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="fab fa-twitter"></i></a><?php endif; ?>
                                <?php if (!empty($company['linkedin_url'])): ?><a href="<?php echo htmlspecialchars($company['linkedin_url']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fab fa-linkedin"></i></a><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-muted">Total Jobs</div>
                                    <div class="display-6"><?php echo (int)$totals['jobs']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-muted">Applications</div>
                                    <div class="display-6"><?php echo (int)$totals['applications']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-muted">Active</div>
                                    <div class="display-6 text-success"><?php echo (int)$job_counts['active']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-muted">Inactive</div>
                                    <div class="display-6 text-warning"><?php echo (int)$job_counts['inactive']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>About Company</strong>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($company['company_description'])): ?>
                                <div style="white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($company['company_description'])); ?></div>
                            <?php else: ?>
                                <div class="text-muted">No description provided.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Recent Jobs</strong>
                            <a href="manage-jobs.php?search=<?php echo urlencode($company['company_name']); ?>" class="btn btn-sm btn-outline-secondary">Manage Jobs</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Applications</th>
                                            <th>Posted</th>
                                            <th>Deadline</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recent_jobs)): foreach ($recent_jobs as $j): ?>
                                            <tr>
                                                <td><?php echo (int)$j['id']; ?></td>
                                                <td><a href="../job-details.php?id=<?php echo (int)$j['id']; ?>" target="_blank" class="text-decoration-none"><?php echo htmlspecialchars($j['title']); ?></a></td>
                                                <td>
                                                    <?php $st = $j['status']; $badge='secondary';
                                                    if ($st==='active') $badge='success'; elseif ($st==='inactive') $badge='warning'; elseif ($st==='filled') $badge='primary'; elseif ($st==='expired') $badge='dark'; ?>
                                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($st); ?></span>
                                                </td>
                                                <td><?php echo (int)$j['app_count']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($j['created_at'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($j['deadline'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../job-details.php?id=<?php echo (int)$j['id']; ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-eye"></i></a>
                                                        <a href="edit-job.php?id=<?php echo (int)$j['id']; ?>" class="btn btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                                        <a href="manage-applications.php?job_id=<?php echo (int)$j['id']; ?>" class="btn btn-outline-dark"><i class="fas fa-users"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr><td colspan="7" class="text-center">No jobs found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
