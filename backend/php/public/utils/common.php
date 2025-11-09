<?php
// Common utility functions for the public website

/**
 * Send a JSON response
 * @param array $data Data to send
 * @param int $statusCode HTTP status code
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send a success response
 * @param mixed $data Data to include in the response
 * @param string $message Success message
 * @param int $statusCode HTTP status code
 */
function sendSuccessResponse($data = null, $message = 'Success', $statusCode = 200) {
    $response = [
        'success' => true,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    sendJsonResponse($response, $statusCode);
}

/**
 * Send an error response
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param array $errors Detailed errors
 */
function sendErrorResponse($message = 'An error occurred', $statusCode = 400, $errors = []) {
    $response = [
        'success' => false,
        'message' => $message
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    sendJsonResponse($response, $statusCode);
}

/**
 * Log an error message
 * @param string $message Error message
 * @param string $level Error level
 */
function logError($message, $level = 'ERROR') {
    $logDir = __DIR__ . '/../../logs';


    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    error_log($logMessage, 3, $logFile);
}

/**
 * Get the current authenticated user ID
 * @return int|null User ID or null if not authenticated
 */
function getCurrentUserId() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Check if the user is authenticated
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    return getCurrentUserId() !== null;
}

/**
 * Require authentication for the current request
 * Sends an error response and exits if not authenticated
 */
/**
 * Get input data based on content type
 * Handles JSON, form-urlencoded, and multipart/form-data
 * @return array Decoded input data
 */


function requireAuthentication() {
    if (!isAuthenticated()) {
        sendErrorResponse('Authentication required', 401);
    }
}

/**
 * Generate a random token
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Get input data from request
 * @return array Sanitized input data
 */
function getInputData() {
    $inputData = [];
    
    // Handle JSON input
    $rawInput = file_get_contents('php://input');
    logError('Raw input: ' . $rawInput, 'DEBUG'); // Add this line for debugging

    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        logError('JSON data: ' . print_r($jsonData, true), 'DEBUG'); // Add this line for debugging
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $inputData = $jsonData;
        } else {
            // Log error if JSON decoding fails
            logError('JSON decoding error: ' . json_last_error_msg(), 'WARNING');
        }
    }
    
    // If inputData is still empty, it means no JSON was provided or it was invalid.
    // In this case, we should not fall back to $_POST for application/json requests
    // as $_POST would be empty. This ensures that only explicitly sent data is processed.
    
    // Sanitize all input
    array_walk_recursive($inputData, function(&$value) {
        $value = is_string($value) ? sanitizeInput($value) : $value;
    });
    
    return $inputData;
}

/**
 * Validate required fields
 * @param array $data Input data
 * @param array $requiredFields Required field names
 * @return array Missing fields
 */
function validateRequiredFields($data, $requiredFields) {
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        logError("Validating field: {$field}, value: " . (isset($data[$field]) ? (is_string($data[$field]) ? "'" . $data[$field] . "'" : var_export($data[$field], true)) : 'NOT SET'), 'DEBUG');
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missingFields[] = $field;
            logError("Field '{$field}' identified as missing.", 'DEBUG');
        }
    }
    
    logError('Missing fields before return: ' . implode(', ', $missingFields), 'DEBUG');
    return $missingFields;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format price for display
 * @param float $price Price to format
 * @return string Formatted price
 */
function formatPrice($price) {
    return number_format($price, 2, '.', ',');
}

/**
 * Generate a unique order number
 * @return string Order number
 */
function generateOrderNumber() {
    $prefix = 'LS';
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return $prefix . $timestamp . $random;
}
?>