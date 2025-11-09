<?php
// User profile API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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

// Require authentication for all profile operations
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Get current user ID
$userId = getCurrentUserId();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get user profile
        try {
            $user = getUserById($userId);
            
            if (!$user) {
                sendErrorResponse('User not found', 404);
                exit;
            }
            
            // Remove sensitive data
            unset($user['password']);
            unset($user['reset_token']);
            unset($user['reset_expires']);
            
            sendSuccessResponse($user);
        } catch (Exception $e) {
            logError('Profile Get Error: ' . $e->getMessage());
            sendErrorResponse('Failed to retrieve profile');
        }
        break;
        
    case 'PUT':
    case 'POST':
        // Update user profile
        try {
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'email'];
            if (!validateRequiredFields($data, $requiredFields)) {
                sendErrorResponse('Missing required fields', 400);
                exit;
            }
            
            // Validate email format
            if (!isValidEmail($data['email'])) {
                sendErrorResponse('Invalid email format', 400);
                exit;
            }
            
            // Check if email is already in use by another user
            $existingUser = getUserByEmail($data['email']);
            if ($existingUser && $existingUser['id'] != $userId) {
                sendErrorResponse('Email is already in use', 400);
                exit;
            }
            
            // Prepare update data
            $updateData = [
                'first_name' => sanitizeInput($data['first_name']),
                'last_name' => sanitizeInput($data['last_name']),
                'email' => sanitizeInput($data['email']),
                'phone' => isset($data['phone']) ? sanitizeInput($data['phone']) : null,
                'address' => isset($data['address']) ? sanitizeInput($data['address']) : null,
                'city' => isset($data['city']) ? sanitizeInput($data['city']) : null,
                'state' => isset($data['state']) ? sanitizeInput($data['state']) : null,
                'zip_code' => isset($data['zip_code']) ? sanitizeInput($data['zip_code']) : null,
                'country' => isset($data['country']) ? sanitizeInput($data['country']) : null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Update user profile
            $success = updateUserProfile($userId, $updateData);
            
            if ($success) {
                // Get updated user data
                $updatedUser = getUserById($userId);
                unset($updatedUser['password']);
                unset($updatedUser['reset_token']);
                unset($updatedUser['reset_expires']);
                
                sendSuccessResponse([
                    'message' => 'Profile updated successfully',
                    'user' => $updatedUser
                ]);
            } else {
                sendErrorResponse('Failed to update profile');
            }
        } catch (Exception $e) {
            logError('Profile Update Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while updating profile');
        }
        break;
        
    case 'DELETE':
        // Not allowing profile deletion through API for security
        sendErrorResponse('Method not allowed', 405);
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>