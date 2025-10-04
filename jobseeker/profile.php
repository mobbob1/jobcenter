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
$errors = [];
$success = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $professional_title = trim($_POST['professional_title'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $experience_years = isset($_POST['experience_years']) ? (int)$_POST['experience_years'] : 0;

    // Validate
    if ($first_name === '') { $errors[] = 'First name is required.'; }
    if ($surname === '') { $errors[] = 'Surname is required.'; }

    // Handle profile image upload
    $new_profile_image = '';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['profile_image']['type'], $allowed)) {
                $errors[] = 'Profile image must be JPG, PNG or GIF.';
            } else {
                $upload_dir = '../uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $file_name = 'js_' . $user_id . '_' . time() . '.' . $ext;
                $dest = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                    $new_profile_image = $file_name;
                } else {
                    $errors[] = 'Failed to upload profile image.';
                }
            }
        } else {
            $errors[] = 'Error uploading profile image.';
        }
    }

    if (empty($errors)) {
        // Does profile exist?
        $exists = false;
        $existing_image = '';
        $check = $conn->prepare('SELECT id, profile_image FROM job_seekers WHERE user_id = ?');
        $check->bind_param('i', $user_id);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows > 0) {
            $exists = true;
            $row = $res->fetch_assoc();
            $existing_image = $row['profile_image'] ?? '';
        }

        if ($exists) {
            if ($new_profile_image !== '') {
                $sql = 'UPDATE job_seekers SET first_name = ?, surname = ?, professional_title = ?, phone = ?, experience_years = ?, city = ?, profile_image = ? WHERE user_id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssissi', $first_name, $surname, $professional_title, $phone, $experience_years, $city, $new_profile_image, $user_id);
            } else {
                $sql = 'UPDATE job_seekers SET first_name = ?, surname = ?, professional_title = ?, phone = ?, experience_years = ?, city = ? WHERE user_id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssisi', $first_name, $surname, $professional_title, $phone, $experience_years, $city, $user_id);
            }
        } else {
            $sql = 'INSERT INTO job_seekers (user_id, first_name, surname, professional_title, phone, experience_years, city, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $conn->prepare($sql);
            $img_to_save = $new_profile_image ?: '';
            $stmt->bind_param('issssiss', $user_id, $first_name, $surname, $professional_title, $phone, $experience_years, $city, $img_to_save);
        }

        if ($stmt && $stmt->execute()) {
            $success = 'Profile saved successfully.';
        } else {
            $errors[] = 'Database error: ' . ($stmt ? $stmt->error : $conn->error);
        }
    }
}

// Load profile
$jobseeker_data = [
    'first_name' => '',
    'surname' => '',
    'professional_title' => '',
    'phone' => '',
    'experience_years' => 0,
    'city' => '',
    'profile_image' => '',
    'email' => ''
];

$stmt = $conn->prepare('SELECT js.*, u.email FROM job_seekers js JOIN users u ON js.user_id = u.id WHERE js.user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $jobseeker_data = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - JobConnect</title>
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
                    <h1 class="h3 mb-0">My Profile</h1>
                    <small class="text-muted">Update your personal information</small>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>There were some problems:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Profile Photo</strong></div>
                        <div class="card-body text-center">
                            <div class="profile-img mb-3">
                                <?php if (!empty($jobseeker_data['profile_image'])): ?>
                                    <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($jobseeker_data['profile_image']); ?>" alt="Profile Picture" style="width:120px;height:120px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <div class="d-inline-flex align-items-center justify-content-center bg-light" style="width:120px;height:120px;border-radius:50%;">
                                        <i class="fas fa-user fa-3x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted d-block">JPG, PNG or GIF. Max 2MB.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Basic Information</strong></div>
                        <div class="card-body">
                            <form action="profile.php" method="POST" enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($jobseeker_data['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Surname <span class="text-danger">*</span></label>
                                        <input type="text" name="surname" class="form-control" value="<?php echo htmlspecialchars($jobseeker_data['surname']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Professional Title</label>
                                        <input type="text" name="professional_title" class="form-control" value="<?php echo htmlspecialchars($jobseeker_data['professional_title']); ?>" placeholder="e.g. Senior Software Engineer">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($jobseeker_data['email']); ?>" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($jobseeker_data['phone']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Experience (years)</label>
                                        <input type="number" name="experience_years" class="form-control" min="0" max="60" value="<?php echo (int)$jobseeker_data['experience_years']; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">City</label>
                                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($jobseeker_data['city']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Profile Photo</label>
                                        <input type="file" name="profile_image" class="form-control" accept="image/jpeg,image/png,image/gif">
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
