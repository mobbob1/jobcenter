<?php
session_start();
require_once '../includes/db_connect.php';

// Admin guard
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Validate job id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage-jobs.php');
    exit();
}
$job_id = (int)$_GET['id'];

// Helper to generate slug
function generate_slug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Fetch companies and categories for selects
$companies_result = $conn->query("SELECT c.id, c.company_name, u.email FROM companies c JOIN users u ON c.user_id = u.id ORDER BY c.company_name");
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name");

// Load job
$job = null;
$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->bind_param('i', $job_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: manage-jobs.php');
    exit();
}
$job = $res->fetch_assoc();

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect inputs
    $company_id = (int)($_POST['company_id'] ?? $job['company_id']);
    $category_id = (int)($_POST['category_id'] ?? $job['category_id']);
    $title = trim($_POST['title'] ?? $job['title']);
    $description = trim($_POST['description'] ?? '');
    $responsibilities = strlen(trim($_POST['responsibilities'] ?? '')) ? trim($_POST['responsibilities']) : null;
    $requirements = strlen(trim($_POST['requirements'] ?? '')) ? trim($_POST['requirements']) : null;
    $benefits = strlen(trim($_POST['benefits'] ?? '')) ? trim($_POST['benefits']) : null;
    $job_type = trim($_POST['job_type'] ?? $job['job_type']);
    $job_level = trim($_POST['job_level'] ?? $job['job_level']);
    $experience_required = strlen(trim($_POST['experience_required'] ?? '')) ? trim($_POST['experience_required']) : null;
    $education_required = strlen(trim($_POST['education_required'] ?? '')) ? trim($_POST['education_required']) : null;
    $min_salary = ($_POST['min_salary'] !== '' && $_POST['min_salary'] !== null) ? (float)$_POST['min_salary'] : null;
    $max_salary = ($_POST['max_salary'] !== '' && $_POST['max_salary'] !== null) ? (float)$_POST['max_salary'] : null;
    $salary_period = strlen(trim($_POST['salary_period'] ?? '')) ? trim($_POST['salary_period']) : null;
    $salary_hidden = isset($_POST['salary_hidden']) ? 1 : 0;
    $location = trim($_POST['location'] ?? $job['location']);
    $address = strlen(trim($_POST['address'] ?? '')) ? trim($_POST['address']) : null;
    $is_remote = isset($_POST['is_remote']) ? 1 : 0;
    $application_url = strlen(trim($_POST['application_url'] ?? '')) ? trim($_POST['application_url']) : null;
    $deadline = trim($_POST['deadline'] ?? $job['deadline']);
    $vacancies = ($_POST['vacancies'] ?? '') !== '' ? (int)$_POST['vacancies'] : (int)$job['vacancies'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = trim($_POST['status'] ?? $job['status']);

    // Validate required
    $required = [
        'company_id' => $company_id,
        'category_id' => $category_id,
        'title' => $title,
        'description' => $description,
        'job_type' => $job_type,
        'job_level' => $job_level,
        'location' => $location,
        'deadline' => $deadline,
    ];
    foreach ($required as $field => $val) {
        if ($val === '' || $val === null) { $errors[] = ucfirst(str_replace('_',' ', $field)) . ' is required.'; }
    }

    // Validate salary
    if ($min_salary !== null && !is_numeric($min_salary)) { $errors[] = 'Minimum salary must be numeric.'; }
    if ($max_salary !== null && !is_numeric($max_salary)) { $errors[] = 'Maximum salary must be numeric.'; }
    if ($min_salary !== null && $max_salary !== null && $min_salary > $max_salary) { $errors[] = 'Minimum salary cannot exceed maximum salary.'; }

    // Validate status against schema
    $allowed_job_status = ['active','inactive','filled','expired'];
    if (!in_array($status, $allowed_job_status, true)) { $status = 'inactive'; }

    // Generate unique slug from title (if changed)
    $slug = generate_slug($title);
    $chk = $conn->prepare('SELECT id FROM jobs WHERE slug = ? AND id != ?');
    if ($chk) {
        $chk->bind_param('si', $slug, $job_id);
        $chk->execute();
        $cr = $chk->get_result();
        if ($cr && $cr->num_rows > 0) {
            $slug .= '-' . substr(md5(uniqid('', true)), 0, 6);
        }
    }

    if (empty($errors)) {
        // Build update dynamically
        $sql = 'UPDATE jobs SET company_id=?, category_id=?, title=?, slug=?, description=?, responsibilities=?, requirements=?, benefits=?, job_type=?, job_level=?, experience_required=?, education_required=?, min_salary=?, max_salary=?, salary_period=?, salary_hidden=?, location=?, address=?, is_remote=?, application_url=?, deadline=?, vacancies=?, is_featured=?, status=? WHERE id=?';
        $stmt2 = $conn->prepare($sql);
        if ($stmt2 === false) {
            $errors[] = 'Database error: ' . $conn->error;
        } else {
            $types = '';
            $params = [];
            // company_id, category_id
            $types .= 'ii'; array_push($params, $company_id, $category_id);
            // title, slug, description, responsibilities, requirements, benefits, job_type, job_level, experience_required, education_required
            $types .= 'ssssssssss'; array_push($params, $title, $slug, $description, $responsibilities, $requirements, $benefits, $job_type, $job_level, $experience_required, $education_required);
            // min_salary, max_salary
            $types .= 'dd'; array_push($params, $min_salary, $max_salary);
            // salary_period, salary_hidden
            $types .= 'si'; array_push($params, $salary_period, $salary_hidden);
            // location, address
            $types .= 'ss'; array_push($params, $location, $address);
            // is_remote, application_url
            $types .= 'is'; array_push($params, $is_remote, $application_url);
            // deadline, vacancies, is_featured, status
            $types .= 'siis'; array_push($params, $deadline, $vacancies, $is_featured, $status);
            // id
            $types .= 'i'; array_push($params, $job_id);

            // Replace nulls with proper nulls using reference binding
            // Prepare references for bind_param
            $bind_params = [];
            $bind_params[] = & $types;
            foreach ($params as $k => $v) {
                $bind_params[] = & $params[$k];
            }
            // call_user_func_array requires mysqli_stmt::bind_param by reference
            call_user_func_array([$stmt2, 'bind_param'], $bind_params);

            if ($stmt2->execute()) {
                $success_message = 'Job updated successfully.';
                // Reload latest job
                $stmt->execute();
                $res = $stmt->get_result();
                $job = $res->fetch_assoc();
            } else {
                $errors[] = 'Error updating job: ' . $stmt2->error;
            }
        }
    }
}

$page_title = 'Edit Job';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?>: <?php echo htmlspecialchars($job['title']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-jobs.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
                    <a href="../job-details.php?id=<?php echo $job_id; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="fas fa-eye"></i> View</a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Fix the following:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h4>Basic Information</h4>
                                <div class="mb-3">
                                    <label class="form-label">Company</label>
                                    <select name="company_id" class="form-select" required>
                                        <option value="">Select Company</option>
                                        <?php if ($companies_result): while ($c = $companies_result->fetch_assoc()): ?>
                                            <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$job['company_id']===(int)$c['id'])?'selected':''; ?>>
                                                <?php echo htmlspecialchars($c['company_name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
                                            </option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php if ($categories_result): while ($cat = $categories_result->fetch_assoc()): ?>
                                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)$job['category_id']===(int)$cat['id'])?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Job Type</label>
                                    <select name="job_type" class="form-select" required>
                                        <?php foreach (['Full Time','Part Time','Contract','Freelance','Internship'] as $jt): ?>
                                            <option value="<?php echo $jt; ?>" <?php echo ($job['job_type']===$jt)?'selected':''; ?>><?php echo $jt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Job Level</label>
                                    <select name="job_level" class="form-select" required>
                                        <?php foreach (['Entry Level','Mid Level','Senior Level','Manager','Director'] as $jl): ?>
                                            <option value="<?php echo $jl; ?>" <?php echo ($job['job_level']===$jl)?'selected':''; ?>><?php echo $jl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>Location & Deadline</h4>
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Full Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($job['address']); ?></textarea>
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="is_remote" name="is_remote" value="1" <?php echo ($job['is_remote']? 'checked':''); ?>>
                                    <label class="form-check-label" for="is_remote">Remote friendly</label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">External Application URL</label>
                                    <input type="url" class="form-control" name="application_url" value="<?php echo htmlspecialchars($job['application_url']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Application Deadline</label>
                                    <input type="date" class="form-control" name="deadline" value="<?php echo htmlspecialchars($job['deadline']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Number of Vacancies</label>
                                    <input type="number" class="form-control" name="vacancies" min="1" value="<?php echo (int)$job['vacancies']; ?>">
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-12">
                                <h4>Job Description</h4>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control summernote" name="description" rows="6" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Responsibilities</label>
                                    <textarea class="form-control summernote" name="responsibilities" rows="5"><?php echo htmlspecialchars($job['responsibilities']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Requirements</label>
                                    <textarea class="form-control summernote" name="requirements" rows="5"><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Benefits</label>
                                    <textarea class="form-control summernote" name="benefits" rows="4"><?php echo htmlspecialchars($job['benefits']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h4>Qualifications</h4>
                                <div class="mb-3">
                                    <label class="form-label">Experience Required</label>
                                    <input type="text" class="form-control" name="experience_required" value="<?php echo htmlspecialchars($job['experience_required']); ?>" placeholder="e.g. 2+ years">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Education Required</label>
                                    <input type="text" class="form-control" name="education_required" value="<?php echo htmlspecialchars($job['education_required']); ?>" placeholder="e.g. Bachelor's Degree">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>Salary</h4>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Minimum Salary</label>
                                        <input type="number" step="0.01" class="form-control" name="min_salary" value="<?php echo htmlspecialchars($job['min_salary']); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Maximum Salary</label>
                                        <input type="number" step="0.01" class="form-control" name="max_salary" value="<?php echo htmlspecialchars($job['max_salary']); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Salary Period</label>
                                    <select class="form-select" name="salary_period">
                                        <option value="">Select Period</option>
                                        <?php foreach (['Hourly','Daily','Weekly','Monthly','Yearly'] as $sp): ?>
                                            <option value="<?php echo $sp; ?>" <?php echo ($job['salary_period']===$sp)?'selected':''; ?>><?php echo $sp; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="salary_hidden" name="salary_hidden" value="1" <?php echo ($job['salary_hidden']? 'checked':''); ?>>
                                    <label class="form-check-label" for="salary_hidden">Hide salary information</label>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h4>Status</h4>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <?php foreach (['active','inactive','filled','expired'] as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo ($job['status']===$st)?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="is_featured2" name="is_featured" value="1" <?php echo ($job['is_featured']? 'checked':''); ?>>
                                    <label class="form-check-label" for="is_featured2">Featured job</label>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="manage-jobs.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Job</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script>
$(function(){
    $('.summernote').summernote({
        placeholder: 'Enter content here...',
        tabsize: 2,
        height: 200,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
});
</script>
</body>
</html>
