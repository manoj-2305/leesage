<?php
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration if not already included
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/../../../database/config.php';
}

/**
 * Check if admin is logged in
 * @return bool True if admin is logged in, false otherwise
 */
function isAdminLoggedIn() {
    // Check if admin_id and session_token are set in session
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    // Get admin ID and session token from session
    $admin_id = $_SESSION['admin_id'];
    $session_token = $_SESSION['session_token'];
    
    try {
        $pdo = getDBConnection();
        
        // Check if session is valid and not expired
        $stmt = $pdo->prepare("SELECT * FROM admin_sessions 
                              WHERE admin_id = ? AND session_token = ? AND expires_at > NOW()");
        $stmt->execute([$admin_id, $session_token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            // Session invalid or expired, clear session
            session_unset();
            session_destroy();
            return false;
        }
        
        // Check if admin exists and is active
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = ? AND is_active = 1 AND account_locked = 0");
        $stmt->execute([$admin_id]);
        
        if ($stmt->rowCount() > 0) {
            return true;
        }
        
        // If admin not found or not active, clear session
        session_unset();
        session_destroy();
        return false;
        
    } catch (PDOException $e) {
        error_log('Error checking admin session: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get current admin user data
 * @return array|null Admin data or null if not logged in
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM admin_users WHERE id = ?");
        $stmt->execute([$admin_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting admin data: ' . $e->getMessage());
        return null;
    }
}

/**
 * Log admin activity
 * @param string $action_type Type of action performed
 * @param string $description Description of the action
 * @return bool True if logged successfully, false otherwise
 */
function logAdminActivity($action_type, $description = '') {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, action_description, ip_address, user_agent) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $admin_id,
            $action_type,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Error logging admin activity: ' . $e->getMessage());
        return false;
    }
}

// For direct script inclusion, check session and redirect if not logged in
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    if (!isAdminLoggedIn()) {
        // Redirect to login page if session is not valid
        header('Location: /Leesage/admin/index.html');
        exit();
    }
}
?>