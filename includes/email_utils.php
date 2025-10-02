<?php
/**
 * Email Utility Functions for JobConnect
 * 
 * This file contains functions for sending various types of emails
 * including application status updates, job alerts, etc.
 */

// Use PHPMailer for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $altBody Plain text alternative body
 * @param array $attachments Optional array of attachments [['path' => '/path/to/file', 'name' => 'filename']]
 * @return bool True if email was sent successfully, false otherwise
 */
function send_email($to, $subject, $body, $altBody = '', $attachments = []) {
    // Load PHPMailer if not already loaded
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        // Use SMTP for production, comment out for local testing with mail()
        /*
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'user@example.com';
        $mail->Password = 'password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        */
        
        // For local testing, use PHP's mail() function
        $mail->isMail();
        
        // Sender
        $mail->setFrom('noreply@jobconnect.com', 'JobConnect');
        $mail->addReplyTo('support@jobconnect.com', 'JobConnect Support');
        
        // Recipients
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        isset($attachment['name']) ? $attachment['name'] : ''
                    );
                }
            }
        }
        
        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log('Email could not be sent. Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send application status update notification to applicant
 * 
 * @param int $application_id The ID of the application
 * @param string $status The new status of the application
 * @param string $notes Optional notes about the status change
 * @return bool True if email was sent successfully, false otherwise
 */
function send_application_status_notification($application_id, $status, $notes = '') {
    global $conn;
    
    // Get application details (align with schema)
    $query = "SELECT a.*, j.title AS job_title, c.company_name AS company_name,
              COALESCE(u.email, a.email) AS applicant_email,
              COALESCE(CONCAT(js.first_name, ' ', js.surname), CONCAT(a.first_name, ' ', a.surname)) AS applicant_name
              FROM applications a
              LEFT JOIN jobs j ON a.job_id = j.id
              LEFT JOIN companies c ON j.company_id = c.id
              LEFT JOIN job_seekers js ON a.job_seeker_id = js.id
              LEFT JOIN users u ON js.user_id = u.id
              WHERE a.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $application = $result->fetch_assoc();
    
    // Prepare email content
    $subject = "Your application status has been updated - JobConnect";
    
    // Status-specific messages
    $status_message = '';
    switch ($status) {
        case 'approved':
            $status_message = "Congratulations! Your application has been approved.";
            break;
        case 'rejected':
            $status_message = "Thank you for your interest. Unfortunately, your application was not selected at this time.";
            break;
        case 'shortlisted':
            $status_message = "Good news! Your application has been shortlisted for further consideration.";
            break;
        case 'interview':
            $status_message = "Great news! You've been selected for an interview. The employer will contact you soon with details.";
            break;
        default:
            $status_message = "Your application status has been updated to " . ucfirst($status) . ".";
    }
    
    // Build the email body
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4a6cf7; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #666; }
            .button { display: inline-block; background-color: #4a6cf7; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Application Status Update</h2>
            </div>
            <div class="content">
                <p>Dear ' . htmlspecialchars($application['applicant_name']) . ',</p>
                
                <p>' . $status_message . '</p>
                
                <p>Application details:</p>
                <ul>
                    <li><strong>Job:</strong> ' . htmlspecialchars($application['job_title']) . '</li>
                    <li><strong>Company:</strong> ' . htmlspecialchars($application['company_name']) . '</li>
                    <li><strong>Status:</strong> ' . ucfirst($status) . '</li>
                </ul>';
    
    // Add notes if available
    if (!empty($notes)) {
        $body .= '<p><strong>Additional notes:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>';
    }
    
    $body .= '
                <a href="http://localhost/oyenishinningstar/my-applications.php" class="button">View Your Applications</a>
                
                <p>If you have any questions, please contact our support team.</p>
                
                <p>Best regards,<br>JobConnect Team</p>
            </div>
            <div class="footer">
                <p>This is an automated message, please do not reply directly to this email.</p>
                <p>&copy; ' . date('Y') . ' JobConnect. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Send the email
    return send_email($application['applicant_email'], $subject, $body);
}

/**
 * Send job alert to user
 * 
 * @param int $user_id The ID of the user to send the alert to
 * @param array $jobs Array of job data to include in the alert
 * @return bool True if email was sent successfully, false otherwise
 */
function send_job_alert($user_id, $jobs) {
    global $conn;
    
    // Get user details
    $query = "SELECT email, full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    // Prepare email content
    $subject = "New Job Matches - JobConnect";
    
    // Build the email body
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4a6cf7; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .job-item { border-bottom: 1px solid #ddd; padding: 15px 0; }
            .job-title { color: #4a6cf7; font-weight: bold; font-size: 18px; }
            .job-company { font-weight: bold; }
            .job-meta { color: #666; font-size: 14px; margin: 5px 0; }
            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #666; }
            .button { display: inline-block; background-color: #4a6cf7; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>New Job Matches</h2>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($user['full_name']) . ',</p>
                
                <p>We found some new job listings that match your preferences:</p>';
    
    // Add jobs to the email
    if (!empty($jobs)) {
        foreach ($jobs as $job) {
            $body .= '
                <div class="job-item">
                    <div class="job-title">' . htmlspecialchars($job['title']) . '</div>
                    <div class="job-company">' . htmlspecialchars($job['company_name']) . '</div>
                    <div class="job-meta">
                        <span>' . htmlspecialchars($job['location']) . '</span> | 
                        <span>' . htmlspecialchars($job['type']) . '</span> | 
                        <span>$' . number_format($job['salary_min']) . ' - $' . number_format($job['salary_max']) . '</span>
                    </div>
                    <div class="job-description">' . substr(htmlspecialchars($job['description']), 0, 150) . '...</div>
                    <a href="http://localhost/oyenishinningstar/job-details.php?id=' . $job['id'] . '" class="button">View Job</a>
                </div>';
        }
    } else {
        $body .= '<p>No new job matches found at this time.</p>';
    }
    
    $body .= '
                <p>You can manage your job alerts in your account settings.</p>
                
                <p>Best regards,<br>JobConnect Team</p>
            </div>
            <div class="footer">
                <p>This is an automated message. To unsubscribe from job alerts, 
                   <a href="http://localhost/oyenishinningstar/account-settings.php">update your preferences</a>.</p>
                <p>&copy; ' . date('Y') . ' JobConnect. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Send the email
    return send_email($user['email'], $subject, $body);
}
?>
