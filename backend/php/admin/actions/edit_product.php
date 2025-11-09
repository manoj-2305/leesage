<?php
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include database configuration
require_once __DIR__ . '/../../../database/config.php';

// Include authentication check
require_once __DIR__ . '/../auth/check_session.php';

$response = ['success' => false, 'message' => ''];

try {
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
    // Validate required fields
    $required_fields = ['product_name', 'price', 'sizes'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $response['message'] = 'Missing required field: ' . $field;
            echo json_encode($response);
            exit();
        }
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Get current stock quantity to track changes
    $sizes = json_decode($_POST['sizes'], true);
    
    // Update product
    $stmt = $pdo->prepare("UPDATE products SET 
                            name = ?, 
                            description = ?, 
                            price = ?, 
                            discount_price = ?, 
                            sku = ?, 
                            is_featured = ?, 
                            is_active = ?, 
                            updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?");
    $stmt->execute([
        $_POST['product_name'],
        $_POST['description'] ?? '',
        $_POST['price'],
        $_POST['discount_price'] ?? null,
        $_POST['sku'] ?? null,
        isset($_POST['is_featured']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
        $product_id
    ]);
    
    // Update product categories
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        // Delete existing categories
        $stmt = $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?");
        $stmt->execute([$product_id]);
        
        // Add new categories
        $categoryStmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
        foreach ($_POST['categories'] as $category_id) {
            $categoryStmt->execute([$product_id, $category_id]);
        }
    }
    
    // Handle product sizes
    $existing_sizes = [];
    $stmt = $pdo->prepare("SELECT id, size_name, stock_quantity FROM product_sizes WHERE product_id = ?");
    $stmt->execute([$product_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_sizes[$row['id']] = $row;
    }

    $size_ids_to_keep = [];
    $size_insert_stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size_name, stock_quantity, min_stock_level, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    $size_update_stmt = $pdo->prepare("UPDATE product_sizes SET size_name = ?, stock_quantity = ?, min_stock_level = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $inventory_stmt = $pdo->prepare("INSERT INTO inventory_history (product_id, quantity_change, reason, admin_id) VALUES (?, ?, ?, ?)");

    // Get size IDs from the form
    $size_ids = isset($_POST['size_ids']) && is_array($_POST['size_ids']) ? $_POST['size_ids'] : [];
    
    // Ensure size_ids array matches the sizes array length
    if (count($size_ids) < count($sizes)) {
        // Pad with empty values if size_ids is shorter
        $size_ids = array_pad($size_ids, count($sizes), '');
    }

    foreach ($sizes as $index => $size) {
        $size_id = isset($size_ids[$index]) && !empty($size_ids[$index]) ? (int)$size_ids[$index] : 0;
        $size_name = trim($size['size']);
        $stock_quantity = (int)$size['stock_quantity'];
        $min_stock_level = (int)($size['min_stock_level'] ?? 0);

        if ($size_id > 0 && isset($existing_sizes[$size_id])) {
            // Update existing size
            $old_stock = $existing_sizes[$size_id]['stock_quantity'];
            $stock_change = $stock_quantity - $old_stock;

            $size_update_stmt->execute([$size_name, $stock_quantity, $min_stock_level, $size_id]);
            $size_ids_to_keep[] = $size_id;

            if ($stock_change != 0) {
                $inventory_stmt->execute([$product_id, $stock_change, 'Stock updated via product edit', $admin['id']]);
            }
        } else {
            // Insert new size
            $size_insert_stmt->execute([$product_id, $size_name, $stock_quantity, $min_stock_level]);
            $new_size_id = $pdo->lastInsertId();
            $size_ids_to_keep[] = $new_size_id;

            if ($stock_quantity > 0) {
                $inventory_stmt->execute([$product_id, $stock_quantity, 'Initial stock for new product size', $admin['id']]);
            }
        }
    }

    // Delete sizes that were not in the submitted list
    $size_delete_stmt = $pdo->prepare("DELETE FROM product_sizes WHERE product_id = ? AND id NOT IN (" . implode(',', array_fill(0, count($size_ids_to_keep), '?')) . ")");
    if (!empty($size_ids_to_keep)) {
        $size_delete_stmt->execute(array_merge([$product_id], $size_ids_to_keep));
    } else {
        // If no sizes are submitted, delete all existing sizes for this product
        $size_delete_stmt = $pdo->prepare("DELETE FROM product_sizes WHERE product_id = ?");
        $size_delete_stmt->execute([$product_id]);
    }
    
    // Handle product images
    $upload_dir = __DIR__ . '/../../../../assets/images/products/' . $product_id . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle image deletions
    if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        $deleteStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
        $deleteFileStmt = $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
        
        foreach ($_POST['delete_images'] as $image_id) {
            $deleteStmt->execute([$image_id, $product_id]);
            $image_path = $deleteStmt->fetchColumn();
            
            if ($image_path) {
                $full_path = __DIR__ . '/../../../../' . $image_path;
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
                $deleteFileStmt->execute([$image_id, $product_id]);
            }
        }
    }
    
    // Handle new image uploads
    if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
        $imageStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)");
        
        // Get current highest display order
        $stmt = $pdo->prepare("SELECT MAX(display_order) FROM product_images WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $max_order = $stmt->fetchColumn() ?: 0;
        
        // Log for debugging
        error_log('Processing ' . count($_FILES['product_images']['name']) . ' new images for product ' . $product_id);
        
        for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
            if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                error_log('Processing new image ' . $i . ': ' . $_FILES['product_images']['name'][$i]);
                $tmp_name = $_FILES['product_images']['tmp_name'][$i];
                $name = basename($_FILES['product_images']['name'][$i]);
                $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Generate unique filename
                $new_filename = uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($tmp_name, $file_path)) {
                    // Save image path to database
                    $relative_path = 'assets/images/products/' . $product_id . '/' . $new_filename;
                    $is_primary = 0; // Default to non-primary
                    $imageStmt->execute([$product_id, $relative_path, $is_primary, $max_order + $i + 1]);
                    error_log('New image saved: ' . $relative_path);
                }
            }
        }
    }
    
    // Handle primary image setting
    if (isset($_POST['primary_image_id']) && !empty($_POST['primary_image_id'])) {
        // Reset all images to non-primary
        $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
        $stmt->execute([$product_id]);
        
        // Set specified image as primary
        $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
        $stmt->execute([$_POST['primary_image_id'], $product_id]);
    }
    
    // Log inventory change if stock quantity changed
    // Note: Stock changes are logged per-size in the size handling loop above
    // This is a placeholder for any overall product-level inventory logging if needed
    
    $pdo->commit();
    
    // Log admin activity
    logAdminActivity('edit_product', 'Updated product: ' . $_POST['product_name']);
    
    $response['success'] = true;
    $response['message'] = 'Product updated successfully';
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error updating product: ' . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error updating product: ' . $e->getMessage());
}

echo json_encode($response);
?>
