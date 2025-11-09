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
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = 'User not found';
        echo json_encode($response);
        exit();
    }
    
    // Check if user has orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $order_count = $stmt->fetchColumn();
    
    // If user has orders and force delete is not set, prevent deletion
    if ($order_count > 0 && (!isset($_POST['force_delete']) || $_POST['force_delete'] != 1)) {
        $response['message'] = 'Cannot delete user with orders. Use force delete to deactivate instead.';
        $response['has_orders'] = true;
        $response['order_count'] = $order_count;
        echo json_encode($response);
        exit();
    }
    
    // If force delete is set or user has no orders, proceed
    if (isset($_POST['force_delete']) && $_POST['force_delete'] == 1 && $order_count > 0) {
        // Instead of deleting, deactivate the user
        $stmt = $pdo->prepare("UPDATE users SET status = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Delete any active sessions
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $action_message = 'Deactivated user: ' . $user['email'];
        $response['message'] = 'User deactivated successfully';
    } else {
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Delete any sessions
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $action_message = 'Deleted user: ' . $user['email'];
        $response['message'] = 'User deleted successfully';
    }
    
    $pdo->commit();
    
    // Log admin activity
    logAdminActivity('delete_user', $action_message);
    
    $response['success'] = true;
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error deleting user: ' . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error deleting user: ' . $e->getMessage());
}

echo json_encode($response);
?>
