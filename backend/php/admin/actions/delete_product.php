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

// Check if product_id is provided
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    $response['message'] = 'Product ID is required';
    echo json_encode($response);
    exit();
}

$product_id = (int)$_POST['product_id'];

// Get current admin
$admin = getCurrentAdmin();

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Get product details for logging
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit();
    }
    
    // Get product images to delete files
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete product (cascade will delete related records)
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    
    $pdo->commit();
    
    // Delete image files after database transaction is complete
    foreach ($images as $image_path) {
        $full_path = __DIR__ . '/../../../../' . $image_path;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }
    
    // Try to remove product directory
    $product_dir = __DIR__ . '/../../../../assets/images/products/' . $product_id . '/';
    if (file_exists($product_dir) && is_dir($product_dir)) {
        rmdir($product_dir);
    }
    
    // Log admin activity
    logAdminActivity('delete_product', 'Deleted product: ' . $product['name']);
    
    $response['success'] = true;
    $response['message'] = 'Product deleted successfully';
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error deleting product: ' . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error deleting product: ' . $e->getMessage());
}

echo json_encode($response);
?>
