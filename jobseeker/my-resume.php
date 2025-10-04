<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connect.php';

// Ensure jobseeker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'jobseeker') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$messages = ['success' => '', 'error' => ''];

// Load or create job seeker profile reference
$job_seeker_id = null;
$js_stmt = $conn->prepare('SELECT id, cv_file FROM job_seekers WHERE user_id = ?');
$js_stmt->bind_param('i', $user_id);
$js_stmt->execute();
$js_res = $js_stmt->get_result();
$job_seeker = $js_res && $js_res->num_rows ? $js_res->fetch_assoc() : null;
$job_seeker_id = $job_seeker['id'] ?? null;
$current_cv = $job_seeker['cv_file'] ?? '';

// Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload/replace CV
    if (isset($_POST['action']) && $_POST['action'] === 'upload_cv') {
        if (!$job_seeker_id) {
            $messages['error'] = 'Please complete your profile first before uploading a CV.';
        } else if (!isset($_FILES['cv']) || $_FILES['cv']['error'] === UPLOAD_ERR_NO_FILE) {
            $messages['error'] = 'Please select a CV file to upload.';
        } else if ($_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
            $messages['error'] = 'Error uploading file. Please try again.';
        } else {
            $allowed_ext = ['pdf','doc','docx'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_ext)) {
                $messages['error'] = 'Invalid file type. Allowed: PDF, DOC, DOCX.';
            } else if ($_FILES['cv']['size'] > $max_size) {
                $messages['error'] = 'File too large. Max size is 5MB.';
            } else {
                $upload_dir = '../uploads/cv/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $new_name = 'cv_js_' . $job_seeker_id . '_' . time() . '.' . $file_ext;
                $dest = $upload_dir . $new_name;
                if (!move_uploaded_file($_FILES['cv']['tmp_name'], $dest)) {
                    $messages['error'] = 'Failed to save uploaded file.';
                } else {
                    // Optionally delete old file
                    if (!empty($current_cv) && file_exists($upload_dir . $current_cv)) {
                        @unlink($upload_dir . $current_cv);
                    }
                    $up = $conn->prepare('UPDATE job_seekers SET cv_file = ? WHERE id = ?');
                    $up->bind_param('si', $new_name, $job_seeker_id);
                    if ($up->execute()) {
                        $messages['success'] = 'CV uploaded successfully.';
                        $current_cv = $new_name;
                    } else {
                        $messages['error'] = 'Database error: ' . $conn->error;
                    }
                }
            }
        }
    }

    // Add Education
    if (isset($_POST['action']) && $_POST['action'] === 'add_education' && $job_seeker_id) {
        $degree = trim($_POST['degree'] ?? '');
        $institution = trim($_POST['institution'] ?? '');
        $field_of_study = trim($_POST['field_of_study'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? null;
        $is_current = isset($_POST['is_current']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');

        if ($degree && $institution && $start_date) {
            $sql = 'INSERT INTO education (job_seeker_id, degree, institution, field_of_study, start_date, end_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isssssis', $job_seeker_id, $degree, $institution, $field_of_study, $start_date, $end_date, $is_current, $description);
            if ($stmt->execute()) {
                $messages['success'] = 'Education added successfully.';
            } else {
                $messages['error'] = 'Failed to add education: ' . $conn->error;
            }
        } else {
            $messages['error'] = 'Please provide degree, institution and start date.';
        }
    }

    // Add Experience
    if (isset($_POST['action']) && $_POST['action'] === 'add_experience' && $job_seeker_id) {
        $job_title = trim($_POST['job_title'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? null;
        $is_current = isset($_POST['is_current']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');

        if ($job_title && $company_name && $start_date) {
            $sql = 'INSERT INTO experience (job_seeker_id, job_title, company_name, location, start_date, end_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isssssis', $job_seeker_id, $job_title, $company_name, $location, $start_date, $end_date, $is_current, $description);
            if ($stmt->execute()) {
                $messages['success'] = 'Experience added successfully.';
            } else {
                $messages['error'] = 'Failed to add experience: ' . $conn->error;
            }
        } else {
            $messages['error'] = 'Please provide job title, company name and start date.';
        }
    }
}

// Deletion handlers
if (isset($_GET['delete_edu']) && $job_seeker_id) {
    $id = (int)$_GET['delete_edu'];
    $del = $conn->prepare('DELETE FROM education WHERE id = ? AND job_seeker_id = ?');
    $del->bind_param('ii', $id, $job_seeker_id);
    $del->execute();
}
if (isset($_GET['delete_exp']) && $job_seeker_id) {
    $id = (int)$_GET['delete_exp'];
    $del = $conn->prepare('DELETE FROM experience WHERE id = ? AND job_seeker_id = ?');
    $del->bind_param('ii', $id, $job_seeker_id);
    $del->execute();
}

// Reload lists
$education = [];
$experience = [];
if ($job_seeker_id) {
    $e = $conn->prepare('SELECT * FROM education WHERE job_seeker_id = ? ORDER BY (is_current = 1) DESC, end_date DESC, start_date DESC');
    $e->bind_param('i', $job_seeker_id);
    $e->execute();
    $education = $e->get_result()->fetch_all(MYSQLI_ASSOC);

    $x = $conn->prepare('SELECT * FROM experience WHERE job_seeker_id = ? ORDER BY (is_current = 1) DESC, end_date DESC, start_date DESC');
    $x->bind_param('i', $job_seeker_id);
    $x->execute();
    $experience = $x->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Resume - JobConnect</title>
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
                <h1 class="h3 mb-0">My Resume</h1>
                <small class="text-muted">Upload your CV and manage your education and experience</small>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <?php if ($messages['success']): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $messages['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($messages['error']): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $messages['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$job_seeker_id): ?>
            <div class="alert alert-warning">
                You need to complete your profile before managing your resume. <a href="profile.php" class="alert-link">Go to Profile</a>.
            </div>
        <?php else: ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-white"><strong>Curriculum Vitae (CV)</strong></div>
                    <div class="card-body">
                        <?php if (!empty($current_cv)): ?>
                            <p class="mb-2"><i class="fas fa-file-pdf text-danger me-2"></i> <?php echo htmlspecialchars($current_cv); ?></p>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="../uploads/cv/<?php echo urlencode($current_cv); ?>" target="_blank"><i class="fas fa-download me-1"></i> View/Download</a>
                            </div>
                            <hr>
                        <?php else: ?>
                            <p class="text-muted">No CV uploaded yet.</p>
                            <hr>
                        <?php endif; ?>
                        <form action="my-resume.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_cv">
                            <div class="mb-3">
                                <label class="form-label">Upload CV (PDF, DOC, DOCX)</label>
                                <input type="file" name="cv" class="form-control" accept=".pdf,.doc,.docx" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Save CV</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Education</strong>
                    </div>
                    <div class="card-body">
                        <form action="my-resume.php" method="POST" class="mb-4">
                            <input type="hidden" name="action" value="add_education">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Degree</label>
                                    <input type="text" name="degree" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Institution</label>
                                    <input type="text" name="institution" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Field of Study</label>
                                    <input type="text" name="field_of_study" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edu_current" name="is_current">
                                        <label class="form-check-label" for="edu_current">I currently study here</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-outline-primary">Add Education</button>
                            </div>
                        </form>

                        <?php if (!empty($education)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Degree</th>
                                            <th>Institution</th>
                                            <th>Period</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($education as $ed): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ed['degree']); ?></td>
                                                <td><?php echo htmlspecialchars($ed['institution']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($ed['start_date']); ?> - 
                                                    <?php echo $ed['is_current'] ? 'Present' : htmlspecialchars($ed['end_date']); ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="my-resume.php?delete_edu=<?php echo (int)$ed['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this entry?')"><i class="fas fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No education added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Experience</strong>
                    </div>
                    <div class="card-body">
                        <form action="my-resume.php" method="POST" class="mb-4">
                            <input type="hidden" name="action" value="add_experience">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" name="job_title" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company</label>
                                    <input type="text" name="company_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="exp_current" name="is_current">
                                        <label class="form-check-label" for="exp_current">I currently work here</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-outline-primary">Add Experience</button>
                            </div>
                        </form>

                        <?php if (!empty($experience)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Company</th>
                                            <th>Period</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($experience as $ex): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ex['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($ex['company_name']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($ex['start_date']); ?> - 
                                                    <?php echo $ex['is_current'] ? 'Present' : htmlspecialchars($ex['end_date']); ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="my-resume.php?delete_exp=<?php echo (int)$ex['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this entry?')"><i class="fas fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No experience added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
