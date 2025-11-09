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

// Check if user_id is provided
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    $response['message'] = 'User ID is required';
    echo json_encode($response);
    exit();
}

$user_id = (int)$_POST['user_id'];

try {
    // Validate required fields
    $required_fields = ['name', 'email'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $response['message'] = ucfirst($field) . ' is required';
            echo json_encode($response);
            exit();
        }
    }
    
    $fullName = $_POST['name'];
    $email = $_POST['email'];
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    
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
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = 'User not found';
        echo json_encode($response);
        exit();
    }
    
    // Check if email already exists for another user
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $response['message'] = 'Email already exists for another user';
            echo json_encode($response);
            exit();
        }
    }
    
    // Update user with correct column names
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        // Update with new password
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password_hash = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$firstName, $lastName, $email, $hashed_password, $status, $user_id]);
    } else {
        // Update without changing password
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$firstName, $lastName, $email, $status, $user_id]);
    }
    
    // Log admin activity
    logAdminActivity('edit_user', 'Updated user: ' . $email);
    
    $response['success'] = true;
    $response['message'] = 'User updated successfully';
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error updating user: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error updating user: ' . $e->getMessage());
}

echo json_encode($response);
?>
