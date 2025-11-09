<?php
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../../../database/config.php';

// Set JSON content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Check if admin is logged in
    if (isset($_SESSION['admin_id']) && isset($_SESSION['session_token'])) {
        $admin_id = $_SESSION['admin_id'];
        $session_token = $_SESSION['session_token'];
        
        // Get database connection
        $pdo = getDBConnection();
        
        // Delete the session from database
        $stmt = $pdo->prepare("DELETE FROM admin_sessions WHERE admin_id = ? AND session_token = ?");
        $stmt->execute([$admin_id, $session_token]);
        
        // Log admin activity
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, action_description, ip_address, user_agent) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $admin_id,
            'logout',
            'Admin logged out successfully',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    $response = ['success' => true, 'message' => 'Logged out successfully'];
    
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Logout failed'];
}

echo json_encode($response);
?>
