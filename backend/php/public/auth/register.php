<?php
// Register API endpoint

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

    // Debugging: Log raw input and decoded JSON
    $rawInput = file_get_contents('php://input');
    error_log('Register Raw Input: ' . ($rawInput ?: 'No raw input'));
    $decodedInput = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Register JSON Decode Error: ' . json_last_error_msg());
    } else {
        error_log('Register JSON Decoded Data: ' . print_r($decodedInput, true));
    }
    error_log('Register $_POST Data: ' . print_r($_POST, true));
    
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'password', 'confirm_password', 'terms_accepted'];
    $missingFields = validateRequiredFields($input, $requiredFields);
    if (!empty($missingFields)) {
        sendErrorResponse('All fields are required.');
        exit();
    }
    
    // Sanitize inputs
    $firstName = sanitizeInput($input['first_name']);
    $lastName = sanitizeInput($input['last_name']);
    $email = sanitizeInput($input['email']);
    $password = $input['password']; // Don't sanitize password
    $confirmPassword = $input['confirm_password']; // Don't sanitize password
    $termsAccepted = (bool)$input['terms_accepted'];
    
    // Validate email format
    if (!isValidEmail($email)) {
        sendErrorResponse('Invalid email format.');
        exit();
    }
    
    // Validate password match
    if ($password !== $confirmPassword) {
        sendErrorResponse('Passwords do not match.');
        exit();
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        sendErrorResponse('Password must be at least 8 characters long.');
        exit();
    }
    
    // Validate terms acceptance
    if (!$termsAccepted) {
        sendErrorResponse('You must accept the terms and conditions.');
        exit();
    }
    
    // Check if email already exists
    $existingUser = getUserByEmail($email);
    if ($existingUser) {
        sendErrorResponse('Email already registered.');
        exit();
    }
    
    // Create new user
    $userId = createUser($firstName, $lastName, $email, $password);
    
    if (!$userId) {
        sendErrorResponse('Registration failed. Please try again.');
        exit();
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Get IP address and user agent for security logging
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Create new session
    $token = createUserSession($userId, $ipAddress, $userAgent);
    
    // Set session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['session_token'] = $token;
    $_SESSION['initiated'] = true;
    
    // Send success response
    sendSuccessResponse([
        'message' => 'Registration successful!',
        'user' => [
            'id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error
    logError('Registration Error: ' . $e->getMessage());
    
    // Send generic error message
    sendErrorResponse('An error occurred during registration. Please try again later.');
}
?>
