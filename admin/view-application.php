<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/email_utils.php'; // Include email utilities

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-applications.php");
    exit();
}

$application_id = $_GET['id'];

// Fetch application details with related information
$query = "SELECT a.*, j.title as job_title, j.description as job_description, 
          j.requirements as job_requirements, j.salary_min, j.salary_max, j.location as job_location,
          j.type as job_type, j.deadline, j.status as job_status, j.created_at as job_created_at,
          c.name as company_name, c.logo as company_logo, c.website as company_website,
          c.description as company_description, c.location as company_location,
          u.full_name as applicant_name, u.email as applicant_email, u.phone as applicant_phone,
          u.profile_image as applicant_image, u.created_at as user_created_at
          FROM applications a
          LEFT JOIN jobs j ON a.job_id = j.id
          LEFT JOIN companies c ON j.company_id = c.id
          LEFT JOIN users u ON a.user_id = u.id
          WHERE a.id = ?";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    $_SESSION['error'] = "Error preparing statement: " . $conn->error;
    header("Location: manage-applications.php");
    exit();
}

$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Application not found.";
    header("Location: manage-applications.php");
    exit();
}

$application = $result->fetch_assoc();

// Handle application status change
if (isset($_POST['update_status']) && isset($_POST['status'])) {
    $new_status = $conn->real_escape_string($_POST['status']);
    $admin_notes = $conn->real_escape_string($_POST['admin_notes'] ?? '');
    $current_status = $application['status'];
    $send_email = isset($_POST['send_email']) ? true : false;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update application status
        $update_query = "UPDATE applications SET status = ?, admin_notes = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        if ($update_stmt === false) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }
        
        $update_stmt->bind_param("ssi", $new_status, $admin_notes, $application_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update application status: " . $update_stmt->error);
        }
        
        // If status has changed, add to history
        if ($new_status !== $current_status) {
            $history_query = "INSERT INTO application_status_history (application_id, status, notes, changed_by) 
                             VALUES (?, ?, ?, ?)";
            $history_stmt = $conn->prepare($history_query);
            if ($history_stmt === false) {
                throw new Exception("Error preparing history statement: " . $conn->error);
            }
            
            $history_stmt->bind_param("issi", $application_id, $new_status, $admin_notes, $_SESSION['user_id']);
            if (!$history_stmt->execute()) {
                throw new Exception("Failed to insert history: " . $history_stmt->error);
            }
            
            // Send email notification if requested
            if ($send_email) {
                $email_sent = send_application_status_notification($application_id, $new_status, $admin_notes);
                if (!$email_sent) {
                    // Log email failure but don't stop the transaction
                    error_log("Failed to send email notification for application ID: $application_id");
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $status_success = "Application status updated to " . ucfirst($new_status) . ".";
        if ($send_email) {
            $status_success .= " Email notification " . ($email_sent ? "sent" : "failed to send") . ".";
        }
        
        // Refresh application data
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $status_error = "Error updating application status: " . $e->getMessage();
    }
}

// Get status history
$history_query = "SELECT h.*, u.full_name as changed_by_name 
                 FROM application_status_history h
                 LEFT JOIN users u ON h.changed_by = u.id
                 WHERE h.application_id = ? 
                 ORDER BY h.created_at DESC";
$history_stmt = $conn->prepare($history_query);
if ($history_stmt === false) {
    $_SESSION['error'] = "Error preparing history statement: " . $conn->error;
    header("Location: manage-applications.php");
    exit();
}

$history_stmt->bind_param("i", $application_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

$page_title = "View Application";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JobConnect Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .application-header {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 8px 12px;
        }
        .profile-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        .company-logo {
            max-width: 120px;
            max-height: 80px;
            object-fit: contain;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -34px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #007bff;
        }
        .cv-preview {
            height: 600px;
            width: 100%;
            border: 1px solid #dee2e6;
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
                        <div class="btn-group me-2">
                            <a href="manage-applications.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Applications
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($status_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $status_success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($status_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $status_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Application Header -->
                <div class="application-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3>Application #<?php echo $application_id; ?></h3>
                            <h4 class="text-primary"><?php echo htmlspecialchars($application['job_title']); ?></h4>
                            <p class="mb-1">
                                <strong>Company:</strong> <?php echo htmlspecialchars($application['company_name']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Applied on:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($application['created_at'])); ?>
                            </p>
                            <p class="mb-0">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo ($application['status'] === 'approved') ? 'success' : 
                                        (($application['status'] === 'pending') ? 'warning' : 
                                        (($application['status'] === 'rejected') ? 'danger' : 
                                        (($application['status'] === 'shortlisted') ? 'info' : 'primary'))); 
                                ?> status-badge">
                                    <?php echo ucfirst($application['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                <i class="fas fa-edit"></i> Update Status
                            </button>
                            <a href="manage-applications.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-list"></i> All Applications
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Applicant Information -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Applicant Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if (!empty($application['applicant_image'])): ?>
                                        <img src="../<?php echo $application['applicant_image']; ?>" alt="Profile Image" class="profile-image mb-3">
                                    <?php else: ?>
                                        <div class="profile-image mb-3 bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user fa-3x text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['surname']); ?></h5>
                                    <?php if (!empty($application['applicant_name']) && $application['applicant_name'] != $application['first_name'] . ' ' . $application['surname']): ?>
                                        <p class="text-muted">(User: <?php echo htmlspecialchars($application['applicant_name']); ?>)</p>
                                    <?php endif; ?>
                                </div>
                                
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <i class="fas fa-envelope text-muted me-2"></i> 
                                        <?php echo htmlspecialchars($application['email']); ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-phone text-muted me-2"></i> 
                                        <?php echo htmlspecialchars($application['phone']); ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-map-marker-alt text-muted me-2"></i> 
                                        <?php echo htmlspecialchars($application['location']); ?>
                                    </li>
                                    <?php if (!empty($application['user_created_at'])): ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-user-plus text-muted me-2"></i> 
                                        User since: <?php echo date('M d, Y', strtotime($application['user_created_at'])); ?>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                                
                                <div class="mt-3">
                                    <a href="mailto:<?php echo $application['email']; ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                        <i class="fas fa-envelope"></i> Send Email
                                    </a>
                                    <?php if (!empty($application['user_id'])): ?>
                                    <a href="manage-users.php?id=<?php echo $application['user_id']; ?>" class="btn btn-sm btn-outline-secondary w-100">
                                        <i class="fas fa-user"></i> View User Profile
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Job Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Job Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if (!empty($application['company_logo'])): ?>
                                        <img src="../<?php echo $application['company_logo']; ?>" alt="Company Logo" class="company-logo mb-3">
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($application['job_title']); ?></h5>
                                    <p class="text-muted"><?php echo htmlspecialchars($application['company_name']); ?></p>
                                </div>
                                
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <i class="fas fa-map-marker-alt text-muted me-2"></i> 
                                        <?php echo htmlspecialchars($application['job_location']); ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-briefcase text-muted me-2"></i> 
                                        <?php echo ucfirst($application['job_type']); ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-money-bill-wave text-muted me-2"></i> 
                                        <?php 
                                        if (!empty($application['salary_min']) && !empty($application['salary_max'])) {
                                            echo '$' . number_format($application['salary_min']) . ' - $' . number_format($application['salary_max']);
                                        } elseif (!empty($application['salary_min'])) {
                                            echo 'From $' . number_format($application['salary_min']);
                                        } elseif (!empty($application['salary_max'])) {
                                            echo 'Up to $' . number_format($application['salary_max']);
                                        } else {
                                            echo 'Not specified';
                                        }
                                        ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-calendar-alt text-muted me-2"></i> 
                                        Deadline: <?php echo date('M d, Y', strtotime($application['deadline'])); ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-info-circle text-muted me-2"></i> 
                                        Job Status: 
                                        <span class="badge bg-<?php 
                                            echo ($application['job_status'] === 'active') ? 'success' : 
                                                (($application['job_status'] === 'pending') ? 'warning' : 
                                                (($application['job_status'] === 'closed') ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($application['job_status']); ?>
                                        </span>
                                    </li>
                                </ul>
                                
                                <div class="mt-3">
                                    <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                        <i class="fas fa-external-link-alt"></i> View Job Posting
                                    </a>
                                    <a href="manage-jobs.php?id=<?php echo $application['job_id']; ?>" class="btn btn-sm btn-outline-secondary w-100">
                                        <i class="fas fa-edit"></i> Manage Job
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Application Details -->
                    <div class="col-md-8">
                        <!-- Application Content -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Application Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>Cover Letter</h6>
                                        <div class="p-3 bg-light rounded">
                                            <?php if (!empty($application['cover_letter'])): ?>
                                                <p><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></p>
                                            <?php else: ?>
                                                <p class="text-muted">No cover letter provided.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Additional Information</h6>
                                        <div class="p-3 bg-light rounded">
                                            <?php if (!empty($application['additional_info'])): ?>
                                                <p><?php echo nl2br(htmlspecialchars($application['additional_info'])); ?></p>
                                            <?php else: ?>
                                                <p class="text-muted">No additional information provided.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6>Admin Notes</h6>
                                        <div class="p-3 bg-light rounded mb-3">
                                            <?php if (!empty($application['admin_notes'])): ?>
                                                <p><?php echo nl2br(htmlspecialchars($application['admin_notes'])); ?></p>
                                            <?php else: ?>
                                                <p class="text-muted">No admin notes yet.</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateNotesModal">
                                            <i class="fas fa-edit"></i> Update Notes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- CV Preview -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">CV / Resume</h5>
                                <?php if (!empty($application['cv_path'])): ?>
                                <a href="../<?php echo $application['cv_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i> Open in New Tab
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($application['cv_path'])): ?>
                                    <?php 
                                    $file_extension = pathinfo($application['cv_path'], PATHINFO_EXTENSION);
                                    if (strtolower($file_extension) === 'pdf'): 
                                    ?>
                                        <embed src="../<?php echo $application['cv_path']; ?>" type="application/pdf" class="cv-preview">
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-file"></i> CV file is not a PDF. 
                                            <a href="../<?php echo $application['cv_path']; ?>" target="_blank" class="btn btn-sm btn-primary ms-2">
                                                Download File
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No CV file uploaded with this application.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Status History -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Status History</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php if ($history_result->num_rows > 0): ?>
                                        <?php while ($history = $history_result->fetch_assoc()): ?>
                                            <div class="timeline-item">
                                                <p class="mb-0"><strong><?php echo ucfirst($history['status']); ?></strong></p>
                                                <p class="text-muted mb-0">
                                                    <?php echo date('F d, Y \a\t h:i A', strtotime($history['created_at'])); ?>
                                                    <?php if (!empty($history['changed_by_name'])): ?>
                                                        by <?php echo htmlspecialchars($history['changed_by_name']); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if (!empty($history['notes'])): ?>
                                                    <p class="mt-1"><?php echo nl2br(htmlspecialchars($history['notes'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="timeline-item">
                                            <p class="mb-0"><strong><?php echo ucfirst($application['status']); ?></strong></p>
                                            <p class="text-muted">
                                                <?php echo date('F d, Y \a\t h:i A', strtotime($application['created_at'])); ?>
                                            </p>
                                            <p class="text-muted">Initial application status</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">Update Application Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo ($application['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="shortlisted" <?php echo ($application['status'] === 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="interview" <?php echo ($application['status'] === 'interview') ? 'selected' : ''; ?>>Interview</option>
                                <option value="approved" <?php echo ($application['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($application['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"><?php echo htmlspecialchars($application['admin_notes'] ?? ''); ?></textarea>
                            <div class="form-text">These notes will be visible to administrators only.</div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="send_email" name="send_email" checked>
                            <label class="form-check-label" for="send_email">Send email notification to applicant</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Notes Modal -->
    <div class="modal fade" id="updateNotesModal" tabindex="-1" aria-labelledby="updateNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateNotesModalLabel">Update Admin Notes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="admin_notes_only" class="form-label">Admin Notes</label>
                            <textarea class="form-control" id="admin_notes_only" name="admin_notes" rows="6"><?php echo htmlspecialchars($application['admin_notes'] ?? ''); ?></textarea>
                            <div class="form-text">These notes are only visible to administrators.</div>
                        </div>
                        <input type="hidden" name="status" value="<?php echo $application['status']; ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Save Notes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/admin.js"></script>
</body>
</html>
