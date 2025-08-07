<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Set page title
$page_title = "Add New Job";

// Function to generate slug from title
function generate_slug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $errors = [];
    
    // Required fields
    $required_fields = [
        'company_id' => 'Company',
        'category_id' => 'Category',
        'title' => 'Job Title',
        'description' => 'Job Description',
        'job_type' => 'Job Type',
        'job_level' => 'Job Level',
        'location' => 'Location',
        'deadline' => 'Application Deadline'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "$label is required.";
        }
    }
    
    // Validate deadline date
    if (!empty($_POST['deadline'])) {
        $deadline = new DateTime($_POST['deadline']);
        $today = new DateTime();
        
        if ($deadline < $today) {
            $errors[] = "Deadline date cannot be in the past.";
        }
    }
    
    // Validate salary if provided
    if (!empty($_POST['min_salary']) && !is_numeric($_POST['min_salary'])) {
        $errors[] = "Minimum salary must be a number.";
    }
    
    if (!empty($_POST['max_salary']) && !is_numeric($_POST['max_salary'])) {
        $errors[] = "Maximum salary must be a number.";
    }
    
    if (!empty($_POST['min_salary']) && !empty($_POST['max_salary'])) {
        if ($_POST['min_salary'] > $_POST['max_salary']) {
            $errors[] = "Minimum salary cannot be greater than maximum salary.";
        }
    }
    
    // Validate vacancies
    if (!empty($_POST['vacancies']) && (!is_numeric($_POST['vacancies']) || $_POST['vacancies'] < 1)) {
        $errors[] = "Vacancies must be a positive number.";
    }
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        // Generate slug from title
        $slug = generate_slug($_POST['title']);
        
        // Check if slug already exists
        $check_slug = $conn->prepare("SELECT id FROM jobs WHERE slug = ?");
        if ($check_slug === false) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $check_slug->bind_param("s", $slug);
            $check_slug->execute();
            $slug_result = $check_slug->get_result();
            
            if ($slug_result->num_rows > 0) {
                // Append random string to make slug unique
                $slug .= '-' . substr(md5(uniqid(mt_rand(), true)), 0, 5);
            }
            
            // Prepare data for insertion
            $company_id = (int)$_POST['company_id'];
            $category_id = (int)$_POST['category_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $responsibilities = !empty($_POST['responsibilities']) ? trim($_POST['responsibilities']) : null;
            $requirements = !empty($_POST['requirements']) ? trim($_POST['requirements']) : null;
            $benefits = !empty($_POST['benefits']) ? trim($_POST['benefits']) : null;
            $job_type = $_POST['job_type'];
            $job_level = $_POST['job_level'];
            $experience_required = !empty($_POST['experience_required']) ? trim($_POST['experience_required']) : null;
            $education_required = !empty($_POST['education_required']) ? trim($_POST['education_required']) : null;
            $min_salary = !empty($_POST['min_salary']) ? (float)$_POST['min_salary'] : null;
            $max_salary = !empty($_POST['max_salary']) ? (float)$_POST['max_salary'] : null;
            $salary_period = !empty($_POST['salary_period']) ? $_POST['salary_period'] : null;
            $salary_hidden = isset($_POST['salary_hidden']) ? 1 : 0;
            $location = trim($_POST['location']);
            $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
            $is_remote = isset($_POST['is_remote']) ? 1 : 0;
            $application_url = !empty($_POST['application_url']) ? trim($_POST['application_url']) : null;
            $deadline = $_POST['deadline'];
            $vacancies = !empty($_POST['vacancies']) ? (int)$_POST['vacancies'] : 1;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $status = $_POST['status'];
            
            // Insert job into database
            $insert_query = "INSERT INTO jobs (company_id, category_id, title, slug, description, responsibilities, 
                            requirements, benefits, job_type, job_level, experience_required, education_required, 
                            min_salary, max_salary, salary_period, salary_hidden, location, address, is_remote, 
                            application_url, deadline, vacancies, is_featured, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_query);
            if ($stmt === false) {
                $errors[] = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("iissssssssssddsisssssiss", 
                    $company_id, $category_id, $title, $slug, $description, $responsibilities, 
                    $requirements, $benefits, $job_type, $job_level, $experience_required, 
                    $education_required, $min_salary, $max_salary, $salary_period, $salary_hidden, 
                    $location, $address, $is_remote, $application_url, $deadline, $vacancies, 
                    $is_featured, $status);
                
                if ($stmt->execute()) {
                    // Success message
                    $success_message = "Job added successfully!";
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $errors[] = "Error adding job: " . $stmt->error;
                }
            }
        }
    }
}

// Get companies for dropdown
$companies_query = "SELECT c.id, c.company_name, u.username, u.email 
                   FROM companies c 
                   JOIN users u ON c.user_id = u.id 
                   ORDER BY c.company_name";
$companies_result = $conn->query($companies_query);

// Get categories for dropdown
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .nav-link.active {
            color: #2470dc;
        }
        
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        
        .note-editor .dropdown-toggle::after {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="manage-jobs.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Jobs
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h4>Basic Information</h4>
                                    
                                    <div class="mb-3">
                                        <label for="company_id" class="form-label required-field">Company</label>
                                        <select class="form-select" id="company_id" name="company_id" required>
                                            <option value="">Select Company</option>
                                            <?php if ($companies_result && $companies_result->num_rows > 0): ?>
                                                <?php while ($company = $companies_result->fetch_assoc()): ?>
                                                    <option value="<?php echo $company['id']; ?>" <?php echo isset($_POST['company_id']) && $_POST['company_id'] == $company['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($company['company_name']); ?> (<?php echo htmlspecialchars($company['email']); ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a company.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label required-field">Category</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a category.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label required-field">Job Title</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                                        <div class="invalid-feedback">Please enter a job title.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="job_type" class="form-label required-field">Job Type</label>
                                        <select class="form-select" id="job_type" name="job_type" required>
                                            <option value="">Select Job Type</option>
                                            <option value="Full Time" <?php echo isset($_POST['job_type']) && $_POST['job_type'] == 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                                            <option value="Part Time" <?php echo isset($_POST['job_type']) && $_POST['job_type'] == 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                                            <option value="Contract" <?php echo isset($_POST['job_type']) && $_POST['job_type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                            <option value="Freelance" <?php echo isset($_POST['job_type']) && $_POST['job_type'] == 'Freelance' ? 'selected' : ''; ?>>Freelance</option>
                                            <option value="Internship" <?php echo isset($_POST['job_type']) && $_POST['job_type'] == 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a job type.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="job_level" class="form-label required-field">Job Level</label>
                                        <select class="form-select" id="job_level" name="job_level" required>
                                            <option value="">Select Job Level</option>
                                            <option value="Entry Level" <?php echo isset($_POST['job_level']) && $_POST['job_level'] == 'Entry Level' ? 'selected' : ''; ?>>Entry Level</option>
                                            <option value="Mid Level" <?php echo isset($_POST['job_level']) && $_POST['job_level'] == 'Mid Level' ? 'selected' : ''; ?>>Mid Level</option>
                                            <option value="Senior Level" <?php echo isset($_POST['job_level']) && $_POST['job_level'] == 'Senior Level' ? 'selected' : ''; ?>>Senior Level</option>
                                            <option value="Manager" <?php echo isset($_POST['job_level']) && $_POST['job_level'] == 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                            <option value="Director" <?php echo isset($_POST['job_level']) && $_POST['job_level'] == 'Director' ? 'selected' : ''; ?>>Director</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a job level.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h4>Location & Deadline</h4>
                                    
                                    <div class="mb-3">
                                        <label for="location" class="form-label required-field">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" required>
                                        <div class="invalid-feedback">Please enter a location.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Full Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_remote" name="is_remote" value="1" <?php echo isset($_POST['is_remote']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_remote">This job can be done remotely</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="application_url" class="form-label">External Application URL</label>
                                        <input type="url" class="form-control" id="application_url" name="application_url" value="<?php echo isset($_POST['application_url']) ? htmlspecialchars($_POST['application_url']) : ''; ?>">
                                        <small class="text-muted">Leave empty if applications should be submitted through this site.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deadline" class="form-label required-field">Application Deadline</label>
                                        <input type="date" class="form-control" id="deadline" name="deadline" value="<?php echo isset($_POST['deadline']) ? htmlspecialchars($_POST['deadline']) : ''; ?>" required>
                                        <div class="invalid-feedback">Please select a deadline date.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="vacancies" class="form-label">Number of Vacancies</label>
                                        <input type="number" class="form-control" id="vacancies" name="vacancies" min="1" value="<?php echo isset($_POST['vacancies']) ? (int)$_POST['vacancies'] : 1; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <h4>Job Description</h4>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label required-field">Description</label>
                                        <textarea class="form-control summernote" id="description" name="description" rows="5" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <div class="invalid-feedback">Please enter a job description.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="responsibilities" class="form-label">Responsibilities</label>
                                        <textarea class="form-control summernote" id="responsibilities" name="responsibilities" rows="5"><?php echo isset($_POST['responsibilities']) ? htmlspecialchars($_POST['responsibilities']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="requirements" class="form-label">Requirements</label>
                                        <textarea class="form-control summernote" id="requirements" name="requirements" rows="5"><?php echo isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="benefits" class="form-label">Benefits</label>
                                        <textarea class="form-control summernote" id="benefits" name="benefits" rows="5"><?php echo isset($_POST['benefits']) ? htmlspecialchars($_POST['benefits']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h4>Qualifications</h4>
                                    
                                    <div class="mb-3">
                                        <label for="experience_required" class="form-label">Experience Required</label>
                                        <input type="text" class="form-control" id="experience_required" name="experience_required" value="<?php echo isset($_POST['experience_required']) ? htmlspecialchars($_POST['experience_required']) : ''; ?>" placeholder="e.g. 2+ years">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="education_required" class="form-label">Education Required</label>
                                        <input type="text" class="form-control" id="education_required" name="education_required" value="<?php echo isset($_POST['education_required']) ? htmlspecialchars($_POST['education_required']) : ''; ?>" placeholder="e.g. Bachelor's Degree">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h4>Salary Information</h4>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="min_salary" class="form-label">Minimum Salary</label>
                                            <input type="number" class="form-control" id="min_salary" name="min_salary" step="0.01" min="0" value="<?php echo isset($_POST['min_salary']) ? htmlspecialchars($_POST['min_salary']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="max_salary" class="form-label">Maximum Salary</label>
                                            <input type="number" class="form-control" id="max_salary" name="max_salary" step="0.01" min="0" value="<?php echo isset($_POST['max_salary']) ? htmlspecialchars($_POST['max_salary']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="salary_period" class="form-label">Salary Period</label>
                                        <select class="form-select" id="salary_period" name="salary_period">
                                            <option value="">Select Period</option>
                                            <option value="Hourly" <?php echo isset($_POST['salary_period']) && $_POST['salary_period'] == 'Hourly' ? 'selected' : ''; ?>>Hourly</option>
                                            <option value="Daily" <?php echo isset($_POST['salary_period']) && $_POST['salary_period'] == 'Daily' ? 'selected' : ''; ?>>Daily</option>
                                            <option value="Weekly" <?php echo isset($_POST['salary_period']) && $_POST['salary_period'] == 'Weekly' ? 'selected' : ''; ?>>Weekly</option>
                                            <option value="Monthly" <?php echo isset($_POST['salary_period']) && $_POST['salary_period'] == 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                            <option value="Yearly" <?php echo isset($_POST['salary_period']) && $_POST['salary_period'] == 'Yearly' ? 'selected' : ''; ?>>Yearly</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="salary_hidden" name="salary_hidden" value="1" <?php echo isset($_POST['salary_hidden']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="salary_hidden">Hide salary information from job listing</label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h4>Job Status</h4>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo isset($_POST['status']) && $_POST['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="pending" <?php echo isset($_POST['status']) && $_POST['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_featured">Feature this job (will appear in featured section)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-secondary me-md-2">Reset</button>
                                <button type="submit" class="btn btn-primary">Add Job</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <script>
        // Initialize Summernote rich text editor
        $(document).ready(function() {
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
            
            // Bootstrap form validation
            (function () {
                'use strict'
                
                // Fetch all the forms we want to apply custom Bootstrap validation styles to
                var forms = document.querySelectorAll('.needs-validation')
                
                // Loop over them and prevent submission
                Array.prototype.slice.call(forms)
                    .forEach(function (form) {
                        form.addEventListener('submit', function (event) {
                            if (!form.checkValidity()) {
                                event.preventDefault()
                                event.stopPropagation()
                            }
                            
                            form.classList.add('was-validated')
                        }, false)
                    })
            })()
        });
    </script>
</body>
</html>
