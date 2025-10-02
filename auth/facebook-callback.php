<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connect.php';

// Facebook OAuth Configuration
$app_id = 'YOUR_FACEBOOK_APP_ID'; // Replace with your actual Facebook App ID
$app_secret = 'YOUR_FACEBOOK_APP_SECRET'; // Replace with your actual Facebook App Secret
$redirect_uri = 'http://localhost/oyenishinningstar/auth/facebook-callback.php';

// Error handling
$error = '';

// Process the callback from Facebook
if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $token_url = 'https://graph.facebook.com/v12.0/oauth/access_token';
    $token_url .= '?client_id=' . $app_id;
    $token_url .= '&redirect_uri=' . urlencode($redirect_uri);
    $token_url .= '&client_secret=' . $app_secret;
    $token_url .= '&code=' . $_GET['code'];
    
    $response = file_get_contents($token_url);
    $params = json_decode($response);
    
    if (isset($params->access_token)) {
        // Get user profile information
        $graph_url = 'https://graph.facebook.com/v12.0/me?fields=id,name,email,first_name,last_name';
        $graph_url .= '&access_token=' . $params->access_token;
        
        $user_info = file_get_contents($graph_url);
        $fb_user = json_decode($user_info);
        
        if (isset($fb_user->id)) {
            // Check if user exists in database
            $stmt = $conn->prepare("SELECT * FROM users WHERE facebook_id = ? OR email = ?");
            $email = isset($fb_user->email) ? $fb_user->email : $fb_user->id . '@facebook.com';
            $stmt->bind_param("ss", $fb_user->id, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists, log them in
                $user = $result->fetch_assoc();
                
                // Update Facebook ID if it's not set
                if (empty($user['facebook_id'])) {
                    $update = $conn->prepare("UPDATE users SET facebook_id = ? WHERE id = ?");
                    $update->bind_param("si", $fb_user->id, $user['id']);
                    $update->execute();
                    $update->close();
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['last_activity'] = time();
                
                // Redirect based on user type
                if ($user['user_type'] == 'admin') {
                    header("Location: ../admin/dashboard.php");
                    exit;
                } elseif ($user['user_type'] == 'employer') {
                    header("Location: ../employer/dashboard.php");
                    exit;
                } else {
                    header("Location: ../jobseeker/dashboard.php");
                    exit;
                }
            } else {
                // New user, register them
                // Generate a username from name or email
                if (isset($fb_user->email)) {
                    $username = explode('@', $fb_user->email)[0];
                } else {
                    $username = strtolower(str_replace(' ', '', $fb_user->name));
                }
                $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);
                
                // Check if username exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Append random number to username
                    $username .= rand(100, 999);
                }
                
                // Default to jobseeker type for social registrations
                $user_type = isset($_SESSION['social_register_type']) ? $_SESSION['social_register_type'] : 'jobseeker';
                
                // Generate a random password
                $random_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert into users table
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, facebook_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("sssss", $username, $email, $hashed_password, $user_type, $fb_user->id);
                    $stmt->execute();
                    
                    $user_id = $conn->insert_id;
                    
                    // Insert into job_seekers or companies table
                    if ($user_type == 'jobseeker') {
                        $stmt = $conn->prepare("INSERT INTO job_seekers (user_id, first_name, surname) VALUES (?, ?, ?)");
                        $first_name = isset($fb_user->first_name) ? $fb_user->first_name : '';
                        $surname = isset($fb_user->last_name) ? $fb_user->last_name : '';
                        $stmt->bind_param("iss", $user_id, $first_name, $surname);
                        $stmt->execute();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO companies (user_id, company_name) VALUES (?, ?)");
                        $company_name = "Company"; // Default company name
                        $stmt->bind_param("is", $user_id, $company_name);
                        $stmt->execute();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['last_activity'] = time();
                    
                    // Redirect based on user type
                    if ($user_type == 'employer') {
                        header("Location: ../employer/dashboard.php");
                        exit;
                    } else {
                        header("Location: ../jobseeker/dashboard.php");
                        exit;
                    }
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        } else {
            $error = "Failed to get user information from Facebook";
        }
    } else {
        $error = "Failed to get access token from Facebook";
    }
} else {
    $error = "Authorization code not received from Facebook";
}

// If there was an error, redirect to login page with error message
if (!empty($error)) {
    $_SESSION['login_error'] = $error;
    header("Location: ../login.php");
    exit;
}
?>
