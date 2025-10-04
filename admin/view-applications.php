<?php
session_start();
require_once '../includes/db_connect.php';

// Admin guard
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($job_id <= 0) {
    $page_error = 'Invalid job selected.';
}

// Fetch job info
$job = null;
if (empty($page_error)) {
    $js = $conn->prepare('SELECT j.id, j.title, c.company_name FROM jobs j JOIN companies c ON j.company_id = c.id WHERE j.id = ?');
    if ($js) {
        $js->bind_param('i', $job_id);
        $js->execute();
        $jr = $js->get_result();
        if ($jr && $jr->num_rows > 0) {
            $job = $jr->fetch_assoc();
        } else {
            $page_error = 'Job not found.';
        }
    } else {
        $page_error = 'Database error: ' . $conn->error;
    }
}

// Allowed application statuses per schema
$allowed_status = ['pending','reviewed','shortlisted','rejected','hired'];

$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
if ($status && !in_array($status, $allowed_status, true)) { $status = ''; }
$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');

$flash = '';
$error = '';

// Handle status change
if (empty($page_error) && isset($_GET['action']) && $_GET['action'] === 'set_status') {
    $app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $to = isset($_GET['to']) ? strtolower($_GET['to']) : '';
    if ($app_id > 0 && in_array($to, $allowed_status, true)) {
        $up = $conn->prepare('UPDATE applications SET status = ? WHERE id = ? AND job_id = ?');
        if ($up) {
            $up->bind_param('sii', $to, $app_id, $job_id);
            if ($up->execute()) {
                $flash = 'Application status updated to ' . ucfirst($to) . '.';
            } else {
                $error = 'Failed to update status: ' . $up->error;
            }
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

$applications = [];
$total_pages = 0;

if (empty($page_error)) {
    // Build applications query
    $sql = "SELECT a.*, 
                   COALESCE(NULLIF(CONCAT(js.first_name,' ',js.surname),' '), CONCAT(a.first_name,' ',a.surname)) AS applicant_name,
                   COALESCE(u.email, a.email) AS applicant_email
            FROM applications a
            LEFT JOIN job_seekers js ON a.job_seeker_id = js.id
            LEFT JOIN users u ON js.user_id = u.id
            WHERE a.job_id = ?";
    $types = 'i';
    $params = [$job_id];

    if ($status) { $sql .= ' AND a.status = ?'; $types .= 's'; $params[] = $status; }
    if ($date) { $sql .= ' AND DATE(a.applied_at) = ?'; $types .= 's'; $params[] = $date; }
    if ($search !== '') {
        $sql .= ' AND (a.first_name LIKE ? OR a.surname LIKE ? OR a.email LIKE ? OR js.first_name LIKE ? OR js.surname LIKE ? OR u.email LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ssssss';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY a.applied_at DESC LIMIT ?, ?';
    $types .= 'ii';
    $params[] = $offset; $params[] = $records_per_page;

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $applications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $error = 'Database error: ' . $conn->error;
    }

    // Count for pagination
    $count_sql = "SELECT COUNT(*) AS total
                  FROM applications a
                  LEFT JOIN job_seekers js ON a.job_seeker_id = js.id
                  LEFT JOIN users u ON js.user_id = u.id
                  WHERE a.job_id = ?";
    $ctypes = 'i';
    $cparams = [$job_id];
    if ($status) { $count_sql .= ' AND a.status = ?'; $ctypes .= 's'; $cparams[] = $status; }
    if ($date) { $count_sql .= ' AND DATE(a.applied_at) = ?'; $ctypes .= 's'; $cparams[] = $date; }
    if ($search !== '') {
        $count_sql .= ' AND (a.first_name LIKE ? OR a.surname LIKE ? OR a.email LIKE ? OR js.first_name LIKE ? OR js.surname LIKE ? OR u.email LIKE ?)';
        $like = '%' . $search . '%';
        $ctypes .= 'ssssss';
        array_push($cparams, $like, $like, $like, $like, $like, $like);
    }
    $cstmt = $conn->prepare($count_sql);
    if ($cstmt) {
        $cstmt->bind_param($ctypes, ...$cparams);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        $total = $cres ? (int)$cres->fetch_assoc()['total'] : 0;
        $total_pages = (int)ceil($total / $records_per_page);
    }
}

$page_title = 'Applications for: ' . ($job ? $job['title'] : 'Unknown Job');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - JobConnect Admin</title>
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
                    <h1 class="h2 mb-0"><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if ($job): ?>
                        <small class="text-muted">Company: <?php echo htmlspecialchars($job['company_name']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-applications.php?job_id=<?php echo $job_id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list"></i> All Applications
                    </a>
                </div>
            </div>

            <?php if (!empty($page_error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
            <?php endif; ?>
            <?php if (!empty($flash)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3" action="view-applications.php">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search applicants..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <?php foreach ($allowed_status as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo ($status === $st) ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>CV</th>
                            <th>Applied</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($page_error) && !empty($applications)): ?>
                            <?php foreach ($applications as $row): ?>
                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['applicant_name'] ?: trim($row['first_name'] . ' ' . $row['surname'])); ?></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($row['applicant_email'] ?: $row['email']); ?>"><?php echo htmlspecialchars($row['applicant_email'] ?: $row['email']); ?></a></td>
                                    <td>
                                        <?php 
                                            $st = $row['status'];
                                            $badge = 'secondary';
                                            if ($st === 'pending') $badge = 'info';
                                            elseif ($st === 'reviewed') $badge = 'warning';
                                            elseif ($st === 'shortlisted') $badge = 'primary';
                                            elseif ($st === 'rejected') $badge = 'danger';
                                            elseif ($st === 'hired') $badge = 'success';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($st); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['cv_file'])): ?>
                                            <a href="../<?php echo htmlspecialchars($row['cv_file']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-file"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['applied_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-outline-dark" href="view-application.php?id=<?php echo (int)$row['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php foreach ($allowed_status as $st): if ($st !== $row['status']): ?>
                                                <a class="btn btn-sm btn-outline-<?php echo $st==='rejected'?'danger':($st==='hired'?'success':($st==='reviewed'?'warning':($st==='shortlisted'?'primary':'info'))); ?>" href="view-applications.php?job_id=<?php echo $job_id; ?>&action=set_status&id=<?php echo (int)$row['id']; ?>&to=<?php echo $st; ?><?php echo $status?('&status='.urlencode($status)) : ''; ?><?php echo $search!==''?('&search='.urlencode($search)) : ''; ?><?php echo $date?('&date='.urlencode($date)) : ''; ?>">
                                                    <?php echo ucfirst($st); ?>
                                                </a>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php if (!empty($row['cover_letter'])): ?>
                                <tr class="table-light">
                                    <td colspan="7">
                                        <strong>Cover Letter</strong>
                                        <div class="mt-2" style="white-space:pre-wrap;">
                                            <?php echo nl2br(htmlspecialchars($row['cover_letter'])); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center"><?php echo !empty($page_error) ? htmlspecialchars($page_error) : 'No applications found'; ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?job_id=<?php echo $job_id; ?>&page=<?php echo max(1, $page-1); ?><?php echo $status?('&status='.urlencode($status)) : ''; ?><?php echo $search!==''?('&search='.urlencode($search)) : ''; ?><?php echo $date?('&date='.urlencode($date)) : ''; ?>">Previous</a>
                    </li>
                    <?php for ($i=1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?job_id=<?php echo $job_id; ?>&page=<?php echo $i; ?><?php echo $status?('&status='.urlencode($status)) : ''; ?><?php echo $search!==''?('&search='.urlencode($search)) : ''; ?><?php echo $date?('&date='.urlencode($date)) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?job_id=<?php echo $job_id; ?>&page=<?php echo min($total_pages, $page+1); ?><?php echo $status?('&status='.urlencode($status)) : ''; ?><?php echo $search!==''?('&search='.urlencode($search)) : ''; ?><?php echo $date?('&date='.urlencode($date)) : ''; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
