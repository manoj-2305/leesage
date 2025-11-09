<?php
// Login API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';

// Set timezone to Kolkata (Asia/Kolkata)
date_default_timezone_set('Asia/Kolkata');

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Only POST requests are accepted.', 405);
    exit();
}

try {
    // Get JSON input
    $input = getInputData();
    // Validate required fields
    $missingFields = validateRequiredFields($input, ['email', 'password']);
    if (!empty($missingFields)) {
        sendErrorResponse('Email and password are required.');
        exit();
    }
    
    $email = sanitizeInput($input['email']);
    $password = $input['password']; // Don't sanitize password
    
    // Validate email format
    if (!isValidEmail($email)) {
        sendErrorResponse('Invalid email format.');
        exit();
    }
    
    // Get user by email
    $user = getUserByEmail($email);
    
    if (!$user) {
        // Use generic message for security
        sendErrorResponse('Invalid email or password.');
        exit();
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        // Use generic message for security
        sendErrorResponse('Invalid email or password.');
        exit();
    }
    
    // Check if user is active
    if (!$user['is_active']) {
        sendErrorResponse('Your account has been deactivated. Please contact support.');
        exit();
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Get IP address and user agent for security logging
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Create new session
    $token = createUserSession($user['id'], $ipAddress, $userAgent);
    
    // Update last login time
    updateLastLogin($user['id']);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['session_token'] = $token;
    $_SESSION['initiated'] = true;
    
    // Prepare user data for response (exclude sensitive information)
    $userData = [
        'id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'profile_image' => $user['profile_image'] ?? null
    ];
    
    // Send success response
    sendSuccessResponse([
        'message' => 'Login successful!',
        'user' => $userData
    ]);
    
} catch (Exception $e) {
    // Log the error
    logError('Login Error: ' . $e->getMessage());
    
    // Send generic error message
    sendErrorResponse('An error occurred during login. Please try again later.');
}
?>
