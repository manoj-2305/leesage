<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

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

// Include database configuration
require_once __DIR__ . '/../../../database/config.php';

// Set session cookie parameters for 1 hour (3600 seconds)
session_set_cookie_params(3600);
// Start session
session_start();

// Function to generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to log admin activity
function logAdminActivity($pdo, $adminId, $action, $description) {
    $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, action_description, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $adminId,
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Main login logic

// Initialize response array
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $remember = filter_var($input['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }

    // Get database connection
    $pdo = getDBConnection();

    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Admin system not initialized. Please run the database setup.');
    }

    // Find admin user
    $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, role, is_active, login_attempts, account_locked 
                          FROM admin_users 
                          WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new Exception('Invalid username or password');
    }

    // Check if account is locked
    if ($admin['account_locked']) {
        throw new Exception('Account is locked. Please contact support.');
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        // Increment login attempts
        $stmt = $pdo->prepare("UPDATE admin_users SET login_attempts = login_attempts + 1 
                              WHERE id = ?");
        $stmt->execute([$admin['id']]);

        // Lock account after 5 failed attempts
        if ($admin['login_attempts'] >= 4) {
            $stmt = $pdo->prepare("UPDATE admin_users SET account_locked = 1 
                                  WHERE id = ?");
            $stmt->execute([$admin['id']]);
            throw new Exception('Account locked due to multiple failed login attempts');
        }

        throw new Exception('Invalid username or password');
    }

    // Reset login attempts on successful login
    $stmt = $pdo->prepare("UPDATE admin_users SET login_attempts = 0, last_login = NOW() 
                          WHERE id = ?");
    $stmt->execute([$admin['id']]);

    // Generate session token
    $sessionToken = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store session in database
    $stmt = $pdo->prepare("INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $admin['id'],
        $sessionToken,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $expiresAt
    ]);

    // Log successful login
    logAdminActivity($pdo, $admin['id'], 'login', 'Admin logged in successfully');

    // Set session variables
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['session_token'] = $sessionToken; // Changed from admin_token to session_token to match check_session.php
    error_log('login.php: Admin ID set in session: ' . $_SESSION['admin_id'] . ', Session token: ' . $_SESSION['session_token']);

    // The response is already being handled in the finally block, so no need to echo here.

    // Return success response
    $response = [
        'success' => true,
        'message' => 'Login successful',
        'token' => $sessionToken,
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'fullName' => $admin['full_name'],
            'role' => $admin['role']
        ]
    ];

} catch (Exception $e) {
    // Log error for debugging
    error_log('Admin login error: ' . $e->getMessage());
    
    // Set error response
    http_response_code(401); // Unauthorized or Bad Request
    $response['message'] = $e->getMessage();
} catch (PDOException $e) {
    // Log database error for debugging
    error_log('Database error: ' . $e->getMessage());
    
    // Set error response for database issues
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Database error: ' . $e->getMessage();
} finally {
    // Always return a JSON response
    echo json_encode($response);
}
?>
