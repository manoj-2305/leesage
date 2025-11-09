<?php
// API endpoint for fetching product details

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../models/product.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle different request methods
switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}

/**
 * Handle GET requests
 */
function handleGetRequest() {
    // Get product ID from query parameters
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($productId <= 0) {
        sendErrorResponse('Invalid product ID', 400);
        return;
    }
    
    // Get product details
    $product = getProductById($productId);
    
    if (!$product) {
        sendErrorResponse('Product not found', 404);
        return;
    }
    
    // Get related products
    $product['related_products'] = getRelatedProducts($productId, 4);
    
    // Get product reviews
    $product['reviews'] = getProductReviews($productId);
    
    // Send response
    sendSuccessResponse($product);
}
?>