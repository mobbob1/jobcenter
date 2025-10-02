<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db_connect.php';

// Google OAuth Configuration
$client_id = 'YOUR_GOOGLE_CLIENT_ID'; // Replace with your actual Google Client ID
$client_secret = 'YOUR_GOOGLE_CLIENT_SECRET'; // Replace with your actual Google Client Secret
$redirect_uri = 'http://localhost/oyenishinningstar/auth/google-callback.php';

// Error handling
$error = '';

// Process the callback from Google
if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $data = array(
        'code' => $_GET['code'],
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    );

    $curl = curl_init($token_url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);
    
    $token_data = json_decode($response);
    
    if (isset($token_data->access_token)) {
        // Get user profile information
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_data->access_token;
        
        $curl = curl_init($user_info_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $user_info = curl_exec($curl);
        curl_close($curl);
        
        $google_user = json_decode($user_info);
        
        if (isset($google_user->id)) {
            // Check if user exists in database
            $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
            $stmt->bind_param("ss", $google_user->id, $google_user->email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists, log them in
                $user = $result->fetch_assoc();
                
                // Update Google ID if it's not set
                if (empty($user['google_id'])) {
                    $update = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                    $update->bind_param("si", $google_user->id, $user['id']);
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
                // Generate a username from email
                $username = explode('@', $google_user->email)[0];
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
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, google_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("sssss", $username, $google_user->email, $hashed_password, $user_type, $google_user->id);
                    $stmt->execute();
                    
                    $user_id = $conn->insert_id;
                    
                    // Insert into job_seekers or companies table
                    if ($user_type == 'jobseeker') {
                        $stmt = $conn->prepare("INSERT INTO job_seekers (user_id, first_name, surname) VALUES (?, ?, ?)");
                        $first_name = isset($google_user->given_name) ? $google_user->given_name : '';
                        $surname = isset($google_user->family_name) ? $google_user->family_name : '';
                        $stmt->bind_param("iss", $user_id, $first_name, $surname);
                        $stmt->execute();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO companies (user_id, company_name) VALUES (?, ?) ");
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
            $error = "Failed to get user information from Google";
        }
    } else {
        $error = "Failed to get access token from Google";
    }
} else {
    $error = "Authorization code not received from Google";
}

// If there was an error, redirect to login page with error message
if (!empty($error)) {
    $_SESSION['login_error'] = $error;
    header("Location: ../login.php");
    exit;
}
?>
