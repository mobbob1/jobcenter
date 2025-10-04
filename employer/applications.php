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
$company_stmt = $conn->prepare('SELECT id, company_name FROM companies WHERE user_id = ?');
$company_stmt->bind_param('i', $user_id);
$company_stmt->execute();
$company_rs = $company_stmt->get_result();
if ($company_rs && $company_rs->num_rows > 0) {
    $company_id = (int)$company_rs->fetch_assoc()['id'];
}
if (!$company_id) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Applications - JobConnect</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    </head>
    <body>
    <?php include '../includes/header.php'; ?>
    <main class="py-5">
        <div class="container">
            <div class="alert alert-warning">You need to complete your company profile before viewing applications. <a href="profile.php" class="alert-link">Go to Company Profile</a>.</div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Allowed statuses in applications table
$allowed_status = ['pending','reviewed','shortlisted','rejected','hired'];
$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
if ($status && !in_array($status, $allowed_status, true)) { $status = ''; }

$job_id_filter = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$search = trim($_GET['q'] ?? '');

$flash = '';
$error = '';

// Handle status change action
if (isset($_GET['action']) && $_GET['action'] === 'set_status') {
    $app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $to = isset($_GET['to']) ? strtolower($_GET['to']) : '';
    if ($app_id > 0 && in_array($to, $allowed_status, true)) {
        $guard = $conn->prepare('UPDATE applications a JOIN jobs j ON a.job_id = j.id SET a.status = ? WHERE a.id = ? AND j.company_id = ?');
        if ($guard) {
            $guard->bind_param('sii', $to, $app_id, $company_id);
            if ($guard->execute()) {
                $flash = 'Application status updated to ' . ucfirst($to) . '.';
            } else {
                $error = 'Failed to update status: ' . $guard->error;
            }
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}

// Fetch list of this company's jobs for the job filter
$jobs_stmt = $conn->prepare('SELECT id, title FROM jobs WHERE company_id = ? ORDER BY created_at DESC');
$jobs_stmt->bind_param('i', $company_id);
$jobs_stmt->execute();
$jobs_rs = $jobs_stmt->get_result();
$company_jobs = $jobs_rs ? $jobs_rs->fetch_all(MYSQLI_ASSOC) : [];

// Build applications query
$sql = "SELECT a.id, a.job_id, a.first_name, a.surname, a.email, a.phone, a.location, a.cv_file, a.cover_letter, a.status, a.applied_at,
               j.title AS job_title
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE j.company_id = ?";
$types = 'i';
$params = [$company_id];

if ($status) { $sql .= ' AND a.status = ?'; $types .= 's'; $params[] = $status; }
if ($job_id_filter > 0) { $sql .= ' AND a.job_id = ?'; $types .= 'i'; $params[] = $job_id_filter; }
if ($search !== '') {
    $sql .= ' AND (a.first_name LIKE ? OR a.surname LIKE ? OR a.email LIKE ? OR j.title LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY a.applied_at DESC';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - JobConnect</title>
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
                <h1 class="h3 mb-0">Applications</h1>
                <small class="text-muted">Review applications for your jobs</small>
            </div>
            <a href="manage-jobs.php" class="btn btn-outline-secondary"><i class="fas fa-briefcase me-2"></i>Manage Jobs</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Job</label>
                        <select class="form-select" name="job_id" onchange="this.form.submit()">
                            <option value="0">All Jobs</option>
                            <?php foreach ($company_jobs as $j): ?>
                                <option value="<?php echo (int)$j['id']; ?>" <?php echo $job_id_filter===(int)$j['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars($j['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php foreach ($allowed_status as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $status===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Name, email, or job title">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div><button class="btn btn-outline-secondary w-100">Apply</button></div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="alert alert-info">No applications found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Job</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>CV</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['surname']); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($a['email']); ?>"><?php echo htmlspecialchars($a['email']); ?></a></td>
                                <td><a class="text-decoration-none" href="../job-details.php?id=<?php echo (int)$a['job_id']; ?>" target="_blank"><?php echo htmlspecialchars($a['job_title']); ?></a></td>
                                <td>
                                    <?php $st = $a['status']; $badge='secondary';
                                    if ($st==='pending') $badge='info'; elseif ($st==='reviewed') $badge='warning'; elseif ($st==='shortlisted') $badge='success'; elseif ($st==='rejected') $badge='danger'; elseif ($st==='hired') $badge='primary'; ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($st); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($a['applied_at'])); ?></td>
                                <td>
                                    <?php if (!empty($a['cv_file'])): ?>
                                        <a href="../<?php echo htmlspecialchars($a['cv_file']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View CV</a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php foreach ($allowed_status as $st): if ($st !== $a['status']): ?>
                                            <a class="btn btn-sm btn-outline-<?php echo $st==='rejected'?'danger':($st==='shortlisted'?'success':($st==='hired'?'primary':($st==='reviewed'?'warning':'info'))); ?>" href="applications.php?action=set_status&id=<?php echo (int)$a['id']; ?>&to=<?php echo $st; ?><?php echo $status?('&status='.urlencode($status)) : ''; ?><?php echo $job_id_filter>0?('&job_id='.(int)$job_id_filter):''; ?><?php echo $search!==''?('&q='.urlencode($search)) : ''; ?>">
                                                <?php echo ucfirst($st); ?>
                                            </a>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if (!empty($a['cover_letter'])): ?>
                            <tr class="table-light">
                                <td colspan="7">
                                    <strong>Cover Letter:</strong>
                                    <div class="mt-2" style="white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($a['cover_letter'])); ?></div>
                                </td>
                            </tr>
                            <?php endif; ?>
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
