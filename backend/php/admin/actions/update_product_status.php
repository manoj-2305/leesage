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

// Check if required fields are provided
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    $response['message'] = 'Product ID is required';
    echo json_encode($response);
    exit();
}

// Check for status parameter (either 'status' or 'is_active')
$status = null;
if (isset($_POST['status']) && is_numeric($_POST['status'])) {
    $status = (int)$_POST['status'];
} elseif (isset($_POST['is_active']) && is_numeric($_POST['is_active'])) {
    $status = (int)$_POST['is_active'];
} else {
    $response['message'] = 'Status is required';
    echo json_encode($response);
    exit();
}

$product_id = (int)$_POST['product_id'];

// Validate status (0 = inactive, 1 = active)
if ($status !== 0 && $status !== 1) {
    $response['message'] = 'Invalid status value';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT name, is_active FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit();
    }
    
    // Update product status
    $stmt = $pdo->prepare("UPDATE products SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $product_id]);
    
    // Get current admin
    $admin = getCurrentAdmin();
    
    // Log admin activity
    $status_text = $status ? 'active' : 'inactive';
    logAdminActivity('update_product_status', "Updated product '{$product['name']}' status to {$status_text}");
    
    $response['success'] = true;
    $response['message'] = "Product status updated to {$status_text} successfully";
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error updating product status: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error updating product status: ' . $e->getMessage());
}

echo json_encode($response);
?>
