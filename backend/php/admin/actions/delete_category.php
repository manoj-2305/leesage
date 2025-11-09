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

// Check if category_id is provided
if (!isset($_POST['category_id']) || empty($_POST['category_id'])) {
    $response['message'] = 'Category ID is required';
    echo json_encode($response);
    exit();
}

$category_id = (int)$_POST['category_id'];
error_log('delete_category.php: Received category_id: ' . $category_id);

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT name, image FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('delete_category.php: Query result for category_id ' . $category_id . ': ' . json_encode($category));
    
    if (!$category) {
        $response['message'] = 'Category not found';
        echo json_encode($response);
        exit();
    }
    
    // Check if category has products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $product_count = $stmt->fetchColumn();
    
    if ($product_count > 0 && (!isset($_POST['force_delete']) || $_POST['force_delete'] != 1)) {
        $response['message'] = 'Cannot delete category with associated products. Use force delete to remove anyway.';
        $response['has_products'] = true;
        $response['product_count'] = $product_count;
        echo json_encode($response);
        exit();
    }
    
    // Check if category has subcategories
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $stmt->execute([$category_id]);
    $subcategory_count = $stmt->fetchColumn();
    
    if ($subcategory_count > 0 && (!isset($_POST['force_delete']) || $_POST['force_delete'] != 1)) {
        $response['message'] = 'Cannot delete category with subcategories. Use force delete to remove anyway.';
        $response['has_subcategories'] = true;
        $response['subcategory_count'] = $subcategory_count;
        echo json_encode($response);
        exit();
    }
    
    // If force delete, update products to remove this category
    if (isset($_POST['force_delete']) && $_POST['force_delete'] == 1 && $product_count > 0) {
        $stmt = $pdo->prepare("DELETE FROM product_categories WHERE category_id = ?");
        $stmt->execute([$category_id]);
    }
    
    // If force delete, update subcategories to set parent_id to NULL or to parent's parent
    if (isset($_POST['force_delete']) && $_POST['force_delete'] == 1 && $subcategory_count > 0) {
        // Get parent_id of current category
        $stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $parent_id = $stmt->fetchColumn();
        
        // Update subcategories to use grandparent as parent
        $stmt = $pdo->prepare("UPDATE categories SET parent_id = ? WHERE parent_id = ?");
        $stmt->execute([$parent_id, $category_id]);
    }
    
    // Delete category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    
    $pdo->commit();
    
    // Delete image file if exists
    if (!empty($category['image'])) {
        $full_path = __DIR__ . '/../../../../' . $category['image'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }
    
    // Log admin activity
    logAdminActivity('delete_category', 'Deleted category: ' . $category['name']);
    
    $response['success'] = true;
    $response['message'] = 'Category deleted successfully';
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error deleting category: ' . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error deleting category: ' . $e->getMessage());
}

echo json_encode($response);
?>
