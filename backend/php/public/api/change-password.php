<?php
// Change password API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require authentication
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

try {
    // Get current user ID
    $userId = getCurrentUserId();
    
    // Get input data
    $data = getInputData();
    
    // Validate required fields
    $requiredFields = ['current_password', 'new_password', 'confirm_password'];
    if (!validateRequiredFields($data, $requiredFields)) {
        sendErrorResponse('Missing required fields', 400);
        exit;
    }
    
    // Check if new password and confirm password match
    if ($data['new_password'] !== $data['confirm_password']) {
        sendErrorResponse('New password and confirm password do not match', 400);
        exit;
    }
    
    // Check if new password meets minimum length requirement
    if (strlen($data['new_password']) < 8) {
        sendErrorResponse('New password must be at least 8 characters long', 400);
        exit;
    }
    
    // Get user data
    $user = getUserById($userId);
    if (!$user) {
        sendErrorResponse('User not found', 404);
        exit;
    }
    
    // Verify current password
    if (!verifyPassword($data['current_password'], $user['password'])) {
        sendErrorResponse('Current password is incorrect', 400);
        exit;
    }
    
    // Change password
    $success = changeUserPassword($userId, $data['new_password']);
    
    if ($success) {
        sendSuccessResponse([
            'message' => 'Password changed successfully'
        ]);
    } else {
        sendErrorResponse('Failed to change password');
    }
} catch (Exception $e) {
    logError('Password Change Error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while changing password');
}
?>