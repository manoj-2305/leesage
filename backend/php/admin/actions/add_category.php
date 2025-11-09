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
    if (!isset($_POST['name']) || empty($_POST['name'])) {
        $response['message'] = 'Category name is required';
        echo json_encode($response);
        exit();
    }
    
    $pdo = getDBConnection();
    
    // Insert category
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, parent_id, is_active) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'],
        $_POST['description'] ?? '',
        !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
        isset($_POST['is_active']) ? 1 : 1 // Default to active if not specified
    ]);
    
    $category_id = $pdo->lastInsertId();
    
    // Handle category image if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../../assets/images/categories/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $tmp_name = $_FILES['image']['tmp_name'];
        $name = basename($_FILES['image']['name']);
        $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $new_filename = 'category_' . $category_id . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $file_path)) {
            // Update category with image path
            $relative_path = 'assets/images/categories/' . $new_filename;
            $stmt = $pdo->prepare("UPDATE categories SET image = ? WHERE id = ?");
            $stmt->execute([$relative_path, $category_id]);
        }
    }
    
    // Log admin activity
    logAdminActivity('add_category', 'Added new category: ' . $_POST['name']);
    
    $response['success'] = true;
    $response['message'] = 'Category added successfully';
    $response['category_id'] = $category_id;
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error adding category: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error adding category: ' . $e->getMessage());
}

echo json_encode($response);
?>
