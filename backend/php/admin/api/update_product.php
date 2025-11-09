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

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit();
}

// Get product ID from query parameter
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    $response['message'] = 'Invalid product ID.';
    echo json_encode($response);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON input.';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getDBConnection();

    $updateFields = [];
    $params = [':id' => $productId];

    // Check for stock_quantity
    if (isset($data['stock_quantity']) && is_numeric($data['stock_quantity'])) {
        $updateFields[] = 'stock_quantity = :stock_quantity';
        $params[':stock_quantity'] = (int)$data['stock_quantity'];
    }

    // Check for min_stock_level
    if (isset($data['min_stock_level']) && is_numeric($data['min_stock_level'])) {
        $updateFields[] = 'min_stock_level = :min_stock_level';
        $params[':min_stock_level'] = (int)$data['min_stock_level'];
    }

    if (empty($updateFields)) {
        $response['message'] = 'No valid fields to update.';
        echo json_encode($response);
        exit();
    }

    $sizeId = isset($data['size_id']) ? (int)$data['size_id'] : 0;

    if ($sizeId > 0) {
        // Update product_sizes table
        $query = "UPDATE product_sizes SET " . implode(', ', $updateFields) . " WHERE product_id = :id AND id = :size_id";
        $params[':size_id'] = $sizeId;
    } else {
        // Update products table (original behavior)
        $query = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = :id";
    }

    $stmt = $pdo->prepare($query);

    if ($stmt->execute($params)) {
        $response['success'] = true;
        $response['message'] = 'Product updated successfully.';
    } else {
        $response['message'] = 'Failed to update product.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error updating product: ' . $e->getMessage());
}

echo json_encode($response);
?>