<?php
// Only start session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../includes/db_connect.php';

// Restrict to employer users
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'employer') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Load existing company profile
$company = [
    'company_name' => '', 'industry' => '', 'company_size' => '', 'founded_year' => '',
    'company_description' => '', 'website' => '', 'phone' => '', 'email' => '', 'logo' => '',
    'address' => '', 'city' => '', 'state' => '', 'country' => '', 'postal_code' => '',
    'facebook_url' => '', 'twitter_url' => '', 'linkedin_url' => ''
];
$company_id = null;

$stmt = $conn->prepare('SELECT * FROM companies WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $company = $res->fetch_assoc();
    $company_id = (int)$company['id'];
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $company_size = trim($_POST['company_size'] ?? '');
    $founded_year = trim($_POST['founded_year'] ?? '');
    $company_description = trim($_POST['company_description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $facebook_url = trim($_POST['facebook_url'] ?? '');
    $twitter_url = trim($_POST['twitter_url'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');

    if ($company_name === '') { $errors[] = 'Company name is required.'; }

    // Handle logo upload
    $new_logo = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','image/gif'];
            if (!in_array($_FILES['logo']['type'], $allowed)) {
                $errors[] = 'Logo must be JPG, PNG or GIF.';
            } else {
                $upload_dir = '../uploads/company_logos/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $file_name = 'company_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $file_name)) {
                    $new_logo = $file_name;
                } else {
                    $errors[] = 'Failed to upload logo.';
                }
            }
        } else {
            $errors[] = 'Error uploading logo.';
        }
    }

    if (empty($errors)) {
        if ($company_id) {
            if ($new_logo !== '') {
                $sql = 'UPDATE companies SET company_name=?, industry=?, company_size=?, founded_year=?, company_description=?, website=?, phone=?, email=?, address=?, city=?, state=?, country=?, postal_code=?, facebook_url=?, twitter_url=?, linkedin_url=?, logo=? WHERE id=? AND user_id=?';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssssssssssssssiii', $company_name, $industry, $company_size, $founded_year, $company_description, $website, $phone, $email, $address, $city, $state, $country, $postal_code, $facebook_url, $twitter_url, $linkedin_url, $new_logo, $company_id, $user_id);
            } else {
                $sql = 'UPDATE companies SET company_name=?, industry=?, company_size=?, founded_year=?, company_description=?, website=?, phone=?, email=?, address=?, city=?, state=?, country=?, postal_code=?, facebook_url=?, twitter_url=?, linkedin_url=? WHERE id=? AND user_id=?';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssssssssssssssii', $company_name, $industry, $company_size, $founded_year, $company_description, $website, $phone, $email, $address, $city, $state, $country, $postal_code, $facebook_url, $twitter_url, $linkedin_url, $company_id, $user_id);
            }
        } else {
            $logo_to_save = $new_logo ?: '';
            $sql = 'INSERT INTO companies (user_id, company_name, industry, company_size, founded_year, company_description, website, phone, email, address, city, state, country, postal_code, facebook_url, twitter_url, linkedin_url, logo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssssssssssssssss', $user_id, $company_name, $industry, $company_size, $founded_year, $company_description, $website, $phone, $email, $address, $city, $state, $country, $postal_code, $facebook_url, $twitter_url, $linkedin_url, $logo_to_save);
        }

        if ($stmt && $stmt->execute()) {
            $success = 'Company profile saved successfully.';
            if (!$company_id) { $company_id = $stmt->insert_id; }
        } else {
            $errors[] = 'Database error: ' . ($stmt ? $stmt->error : $conn->error);
        }
    }

    // Reload data
    $stmt = $conn->prepare('SELECT * FROM companies WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) { $company = $res->fetch_assoc(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - JobConnect</title>
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
                <h1 class="h3 mb-0">Company Profile</h1>
                <small class="text-muted">Manage your company information</small>
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

        <form method="post" enctype="multipart/form-data" class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Industry</label>
                        <input type="text" name="industry" class="form-control" value="<?php echo htmlspecialchars($company['industry']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Company Size</label>
                        <input type="text" name="company_size" class="form-control" value="<?php echo htmlspecialchars($company['company_size']); ?>" placeholder="e.g. 11-50">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Founded Year</label>
                        <input type="number" name="founded_year" class="form-control" min="1800" max="2100" value="<?php echo htmlspecialchars($company['founded_year']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($company['website']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($company['phone']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company['email']); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">About Company</label>
                        <textarea name="company_description" class="form-control" rows="4"><?php echo htmlspecialchars($company['company_description']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($company['address']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($company['city']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($company['state']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($company['country']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($company['postal_code']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Facebook URL</label>
                        <input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars($company['facebook_url']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Twitter URL</label>
                        <input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars($company['twitter_url']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">LinkedIn URL</label>
                        <input type="url" name="linkedin_url" class="form-control" value="<?php echo htmlspecialchars($company['linkedin_url']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo (JPG, PNG, GIF)</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <?php if (!empty($company['logo'])): ?>
                            <div class="mt-2">
                                <img src="../uploads/company_logos/<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" style="max-height:60px">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </div>
            </div>
        </form>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
