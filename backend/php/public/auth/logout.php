<?php
// Logout API endpoint

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

try {
    // Check if user is authenticated
    if (isAuthenticated()) {
        // Get session token
        $token = $_SESSION['session_token'] ?? null;
        
        // Delete session from database if token exists
        if ($token) {
            deleteUserSession($token);
        }
        
        // Clear session data
        session_unset();
        session_destroy();
        
        // Start a new session
        session_start();
        session_regenerate_id(true);
        
        // Send success response
        sendSuccessResponse([
            'message' => 'Logout successful!'
        ]);
    } else {
        // User not authenticated
        sendSuccessResponse([
            'message' => 'No active session to logout.'
        ]);
    }
} catch (Exception $e) {
    // Log the error
    logError('Logout Error: ' . $e->getMessage());
    
    // Clear session on error
    session_unset();
    session_destroy();
    
    // Send error response
    sendErrorResponse('An error occurred during logout. Please try again.');
}
?>