<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../auth/check_session.php';

$response = ['success' => false, 'message' => ''];

if (!isAdminLoggedIn()) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

$admin = getCurrentAdmin();

// Add debugging for form submissions
error_log('=== ADD PRODUCT REQUEST STARTED ===');
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Content Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log('X-Requested-With: ' . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
error_log('Request Timestamp: ' . ($_POST['request_timestamp'] ?? 'not set'));
error_log('Request ID: ' . ($_POST['request_id'] ?? 'not set'));
error_log('POST data keys: ' . json_encode(array_keys($_POST)));
error_log('FILES data keys: ' . json_encode(array_keys($_FILES)));

try {
    $required_fields = ['product_name', 'price', 'sizes'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    $name = trim($_POST['product_name']);
    $description = trim($_POST['description'] ?? '');
    $price = (float)$_POST['price'];
    $discount_price = isset($_POST['discount_price']) && $_POST['discount_price'] !== '' ? (float)$_POST['discount_price'] : null;
    $sizes = json_decode($_POST['sizes'], true);
    $sku = trim($_POST['sku'] ?? '');

    
    
    
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 1;

    $stmt = $pdo->prepare("
        INSERT INTO products (name, description, price, discount_price, sku, is_featured, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$name, $description, $price, $discount_price, $sku, $is_featured, $is_active]);

    $product_id = $pdo->lastInsertId();

    if (!empty($_POST['category_id'])) {
        $category_id = (int)$_POST['category_id'];
        $stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
        $stmt->execute([$product_id, $category_id]);
    }

    if (!empty($_FILES['product_images']['name'][0])) {
        $upload_dir = __DIR__ . '/../../../../assets/images/products/' . $product_id . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $imageStmt = $pdo->prepare("
            INSERT INTO product_images (product_id, image_path, is_primary, display_order)
            VALUES (?, ?, ?, ?)
        ");

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $display_order = 0;
        $is_first_image = true;
        
        // Log for debugging
        error_log('Processing ' . count($_FILES['product_images']['name']) . ' images for product ' . $product_id);

        foreach ($_FILES['product_images']['name'] as $key => $original_name) {
            if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                error_log('Processing image ' . $key . ': ' . $original_name);
                $tmp_name = $_FILES['product_images']['tmp_name'][$key];
                $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_exts)) {
                    throw new Exception("Invalid image type: $file_ext");
                }

                $new_filename = uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $new_filename;

                if (move_uploaded_file($tmp_name, $file_path)) {
                    $relative_path = 'assets/images/products/' . $product_id . '/' . $new_filename;
                    $is_primary = $is_first_image ? 1 : 0;
                    $imageStmt->execute([$product_id, $relative_path, $is_primary, $display_order]);
                    error_log('Image saved: ' . $relative_path);
                    $is_first_image = false;
                    $display_order++;
                } else {
                    throw new Exception('Failed to move uploaded file.');
                }
            }
        }
    }

    if (!empty($sizes)) {
        $sizeStmt = $pdo->prepare("
            INSERT INTO product_sizes (product_id, size_name, stock_quantity, min_stock_level, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $inventoryStmt = $pdo->prepare("
            INSERT INTO inventory_history (product_id, quantity_change, reason, admin_id)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($sizes as $size) {
            $size_name = trim($size['size']);
            $stock_quantity = (int)$size['stock_quantity'];
            $min_stock_level = (int)($size['min_stock_level'] ?? 0);

            $sizeStmt->execute([$product_id, $size_name, $stock_quantity, $min_stock_level]);
            $size_id = $pdo->lastInsertId();

            if ($stock_quantity > 0) {
                $inventoryStmt->execute([$product_id, $stock_quantity, 'Initial stock for new product size', $admin['id']]);
            }
        }
    }

    $pdo->commit();

    logAdminActivity('add_product', 'Added new product: ' . $name);

    $response['success'] = true;
    $response['message'] = 'Product added successfully';
    $response['product_id'] = $product_id;
    
    error_log('=== ADD PRODUCT COMPLETED SUCCESSFULLY ===');
    error_log('Product ID: ' . $product_id);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
    error_log('Error adding product: ' . $e->getMessage());
}

echo json_encode($response);
