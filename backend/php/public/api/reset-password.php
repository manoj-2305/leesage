<?php
// Reset password API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

try {
    // Get input data
    $data = getInputData();
    
    // Validate required fields
    $requiredFields = ['token', 'new_password', 'confirm_password'];
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
    
    // Verify reset token
    $user = getUserByResetToken($data['token']);
    if (!$user) {
        sendErrorResponse('Invalid or expired reset token', 400);
        exit;
    }
    
    // Reset password
    $success = resetUserPassword($user['id'], $data['new_password']);
    
    if ($success) {
        // Clear reset token
        clearResetToken($user['id']);
        
        // Automatically log in the user
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Generate a unique session token
        $sessionToken = generateToken(32);
        
        // Set session expiration time
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . SESSION_LIFETIME . ' seconds'));
        
        // Create user session in database
        createUserSession($user['id'], $sessionToken, $expiresAt);
        
        // Update last login time
        updateLastLogin($user['id']);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['session_token'] = $sessionToken;
        
        sendSuccessResponse([
            'message' => 'Password has been reset successfully',
            'isLoggedIn' => true,
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email']
            ]
        ]);
    } else {
        sendErrorResponse('Failed to reset password');
    }
} catch (Exception $e) {
    logError('Reset Password Error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while resetting password');
}
?>