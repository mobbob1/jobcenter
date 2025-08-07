<?php
// Only start session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get the provider and user type from URL parameters
$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$user_type = isset($_GET['type']) ? $_GET['type'] : 'jobseeker';

// Store the user type in session for the callback
$_SESSION['social_register_type'] = $user_type;

// Validate provider
if ($provider != 'google' && $provider != 'facebook') {
    $_SESSION['login_error'] = "Invalid social login provider";
    header("Location: ../login.php");
    exit;
}

// Configuration
if ($provider == 'google') {
    $client_id = 'YOUR_GOOGLE_CLIENT_ID'; // Replace with your actual Google Client ID
    $redirect_uri = 'http://localhost/oyenishinningstar/auth/google-callback.php';
    
    // Build Google OAuth URL
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
    $auth_url .= '?client_id=' . urlencode($client_id);
    $auth_url .= '&redirect_uri=' . urlencode($redirect_uri);
    $auth_url .= '&response_type=code';
    $auth_url .= '&scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email');
    $auth_url .= '&access_type=online';
    
    // Redirect to Google
    header("Location: $auth_url");
    exit;
} else if ($provider == 'facebook') {
    $app_id = 'YOUR_FACEBOOK_APP_ID'; // Replace with your actual Facebook App ID
    $redirect_uri = 'http://localhost/oyenishinningstar/auth/facebook-callback.php';
    
    // Build Facebook OAuth URL
    $auth_url = 'https://www.facebook.com/v12.0/dialog/oauth';
    $auth_url .= '?client_id=' . urlencode($app_id);
    $auth_url .= '&redirect_uri=' . urlencode($redirect_uri);
    $auth_url .= '&response_type=code';
    $auth_url .= '&scope=email';
    
    // Redirect to Facebook
    header("Location: $auth_url");
    exit;
}
?>
