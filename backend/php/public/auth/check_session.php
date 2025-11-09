<?php
// Check session API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if user is authenticated
    logError('check_session.php: Starting session check.');
    $isAuthenticated = isAuthenticated();
    logError('check_session.php: isAuthenticated: ' . ($isAuthenticated ? 'true' : 'false'));
    
    // Get user data if authenticated
    $userData = null;
    if ($isAuthenticated) {
        $userId = getCurrentUserId();
        $user = getUserById($userId);
        
        if ($user) {
            logError('check_session.php: User data retrieved for ID: ' . $userId);
            $userData = [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'profile_image' => $user['profile_image'] ?? null
            ];
        } else {
            // User not found in database, clear session
            logError('check_session.php: User ID ' . $userId . ' not found in database. Clearing session.');
            session_unset();
            session_destroy();
            $isAuthenticated = false;
        }
    }
    
    // Send response
    sendSuccessResponse([
        'isLoggedIn' => $isAuthenticated,
        'user' => $userData
    ]);
    
} catch (Exception $e) {
    // Log the error
    logError('Session Check Error: ' . $e->getMessage());
    
    // Clear session on error for security
    session_unset();
    session_destroy();
    
    // Send response
    logError('check_session.php: Exception caught: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
    sendErrorResponse('An error occurred while checking session.', 500);
}
?>
