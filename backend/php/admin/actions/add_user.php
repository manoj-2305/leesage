<?php
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../../../database/config.php';

// Include authentication check
require_once __DIR__ . '/../auth/check_session.php';

$response = ['success' => false, 'message' => ''];

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

try {
    // Validate required fields
    $required_fields = ['name', 'email', 'password'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $response['message'] = ucfirst($field) . ' is required';
            echo json_encode($response);
            exit();
        }
    }
    
    $fullName = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1; // Default to active
    
    // Split full name into first and last name
    $nameParts = explode(' ', trim($fullName), 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        echo json_encode($response);
        exit();
    }
    
    $pdo = getDBConnection();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $response['message'] = 'Email already exists';
        echo json_encode($response);
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user with correct column names
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$firstName, $lastName, $email, $hashed_password, $status]);
    
    $user_id = $pdo->lastInsertId();
    
    // Log admin activity
    logAdminActivity('add_user', 'Added new user: ' . $email);
    
    $response['success'] = true;
    $response['message'] = 'User added successfully';
    $response['user_id'] = $user_id;
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error adding user: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error adding user: ' . $e->getMessage());
}

echo json_encode($response);
?>
