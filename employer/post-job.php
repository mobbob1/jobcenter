<?php
session_start();
require_once '../includes/db_connect.php';

// Check if employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'employer') {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Get company ID for the logged-in employer
$employer_id = $_SESSION['user_id'];
$company_query = "SELECT id FROM companies WHERE user_id = ?";
$stmt = $conn->prepare($company_query);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$company_result = $stmt->get_result();

if ($company_result->num_rows == 0) {
    $error_message = "Please complete your company profile before posting a job.";
} else {
    $company_data = $company_result->fetch_assoc();
    $company_id = $company_data['id'];
    
    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Get form data
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $requirements = trim($_POST['requirements']);
        $location = trim($_POST['location']);
        $job_type = $_POST['job_type'];
        $category_id = $_POST['category_id'];
        $salary_min = $_POST['salary_min'];
        $salary_max = $_POST['salary_max'];
        $deadline = $_POST['deadline'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // Validate form data
        if (empty($title) || empty($description) || empty($requirements) || empty($location)) {
            $error_message = "Please fill all required fields.";
        } else {
            // Insert job into database
            $sql = "INSERT INTO jobs (title, description, requirements, location, job_type, 
                    category_id, company_id, salary_min, salary_max, deadline, is_featured, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                // Handle prepare error
                $error_message = "Error preparing statement: " . $conn->error;
            } else {
                $status = 'pending';
                $stmt->bind_param("sssssiiissi", $title, $description, $requirements, $location, 
                                $job_type, $category_id, $company_id, $salary_min, $salary_max, 
                                $deadline, $is_featured);
                
                if ($stmt->execute()) {
                    $success_message = "Job posted successfully! It will be reviewed by an admin before being published.";
                } else {
                    $error_message = "Error: " . $stmt->error;
                }
            }
        }
    }
}

// Get job categories for dropdown
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Job - JobConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Include Summernote for rich text editor -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h2 class="h4 mb-0">Post a New Job</h2>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success"><?php echo $success_message; ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                            <?php endif; ?>
                            
                            <?php if (empty($error_message) || $error_message != "Please complete your company profile before posting a job."): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Job Title*</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Job Category*</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                                <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="job_type" class="form-label">Job Type*</label>
                                        <select class="form-select" id="job_type" name="job_type" required>
                                            <option value="">Select Job Type</option>
                                            <option value="Full Time">Full Time</option>
                                            <option value="Part Time">Part Time</option>
                                            <option value="Contract">Contract</option>
                                            <option value="Freelance">Freelance</option>
                                            <option value="Internship">Internship</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location*</label>
                                        <input type="text" class="form-control" id="location" name="location" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="salary_min" class="form-label">Minimum Salary (GHS)</label>
                                            <input type="number" class="form-control" id="salary_min" name="salary_min">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="salary_max" class="form-label">Maximum Salary (GHS)</label>
                                            <input type="number" class="form-control" id="salary_max" name="salary_max">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deadline" class="form-label">Application Deadline*</label>
                                        <input type="date" class="form-control" id="deadline" name="deadline" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Job Description*</label>
                                        <textarea class="form-control summernote" id="description" name="description" rows="6" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="requirements" class="form-label">Requirements*</label>
                                        <textarea class="form-control summernote" id="requirements" name="requirements" rows="6" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured">
                                        <label class="form-check-label" for="is_featured">Feature this job (additional fee may apply)</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Post Job</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p>You need to complete your company profile before posting jobs.</p>
                                    <a href="profile.php" class="btn btn-primary">Complete Company Profile</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link']]
                ]
            });
        });
    </script>
</body>
</html>
