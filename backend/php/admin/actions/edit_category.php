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

try {
    // Validate required fields
    if (!isset($_POST['name']) || empty($_POST['name'])) {
        $response['message'] = 'Category name is required';
        echo json_encode($response);
        exit();
    }
    
    $pdo = getDBConnection();
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        $response['message'] = 'Category not found';
        echo json_encode($response);
        exit();
    }
    
    // Check if parent_id is not the same as category_id to prevent circular reference
    if (!empty($_POST['parent_id']) && $_POST['parent_id'] == $category_id) {
        $response['message'] = 'A category cannot be its own parent';
        echo json_encode($response);
        exit();
    }
    
    // Update category
    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ?, is_active = ? WHERE id = ?");
    $stmt->execute([
        $_POST['name'],
        $_POST['description'] ?? '',
        !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
        isset($_POST['is_active']) ? 1 : 0,
        $category_id
    ]);
    
    // Handle category image if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../../assets/images/categories/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Get current image to delete later
        $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $current_image = $stmt->fetchColumn();
        
        $tmp_name = $_FILES['image']['tmp_name'];
        $name = basename($_FILES['image']['name']);
        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $new_filename = 'category_' . $category_id . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $file_path)) {
            // Update category with new image path
            $relative_path = 'assets/images/categories/' . $new_filename;
            $stmt = $pdo->prepare("UPDATE categories SET image = ? WHERE id = ?");
            $stmt->execute([$relative_path, $category_id]);
            
            // Delete old image if exists
            if ($current_image && file_exists(__DIR__ . '/../../../../' . $current_image)) {
                unlink(__DIR__ . '/../../../../' . $current_image);
            }
        }
    } else if (isset($_POST['remove_image']) && $_POST['remove_image'] == 1) {
        // Remove image if requested
        $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $current_image = $stmt->fetchColumn();
        
        if ($current_image) {
            // Delete image file
            $full_path = __DIR__ . '/../../../../' . $current_image;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            
            // Update database to remove image reference
            $stmt = $pdo->prepare("UPDATE categories SET image = NULL WHERE id = ?");
            $stmt->execute([$category_id]);
        }
    }
    
    // Log admin activity
    logAdminActivity('edit_category', 'Updated category: ' . $_POST['name']);
    
    $response['success'] = true;
    $response['message'] = 'Category updated successfully';
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error updating category: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error updating category: ' . $e->getMessage());
}

echo json_encode($response);
?>
