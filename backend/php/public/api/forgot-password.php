<?php
// Forgot password API endpoint

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
    if (!isset($data['email']) || empty($data['email'])) {
        sendErrorResponse('Email is required', 400);
        exit;
    }
    
    // Validate email format
    if (!isValidEmail($data['email'])) {
        sendErrorResponse('Invalid email format', 400);
        exit;
    }
    
    // Check if user exists
    $user = getUserByEmail($data['email']);
    if (!$user) {
        // For security reasons, don't reveal if email exists or not
        sendSuccessResponse([
            'message' => 'If your email is registered, you will receive a password reset link.'
        ]);
        exit;
    }
    
    // Generate reset token
    $token = generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Save reset token to database
    $success = savePasswordResetToken($user['id'], $token, $expires);
    
    if ($success) {
        // In a real application, send an email with the reset link
        // For this example, we'll just return the token in the response
        // In production, you would use a proper email service
        
        // Example email content
        $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
        $emailSubject = SITE_NAME . ' - Password Reset Request';
        $emailBody = "Hello {$user['first_name']},\n\n";
        $emailBody .= "You recently requested to reset your password for your " . SITE_NAME . " account. ";
        $emailBody .= "Click the link below to reset it.\n\n";
        $emailBody .= $resetLink . "\n\n";
        $emailBody .= "If you did not request a password reset, please ignore this email or contact support ";
        $emailBody .= "if you have questions.\n\n";
        $emailBody .= "This password reset link is only valid for the next 60 minutes.\n\n";
        $emailBody .= "Thanks,\n";
        $emailBody .= "The " . SITE_NAME . " Team";
        
        // Log the email for development purposes
        logError('Password Reset Email: ' . $emailBody);
        
        // In development, return the token for testing
        if (ENVIRONMENT === 'development') {
            sendSuccessResponse([
                'message' => 'Password reset link has been sent to your email.',
                'dev_token' => $token,  // Only include in development
                'dev_link' => $resetLink  // Only include in development
            ]);
        } else {
            sendSuccessResponse([
                'message' => 'Password reset link has been sent to your email.'
            ]);
        }
    } else {
        sendErrorResponse('Failed to process password reset request');
    }
} catch (Exception $e) {
    logError('Forgot Password Error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while processing your request');
}
?>