<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API endpoint for fetching products

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../models/product.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start output buffering to catch any unexpected output
ob_start();

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
    // Get query parameters
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    
    switch ($action) {
        case 'list':
            // Get products list
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : PRODUCTS_PER_PAGE;
            $category = isset($_GET['category']) ? $_GET['category'] : null;
            $search = isset($_GET['search']) ? $_GET['search'] : null;
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
            
            // Validate and sanitize parameters
            $page = max(1, $page); // Ensure page is at least 1
            $limit = min(max(1, $limit), 50); // Limit between 1 and 50
            
            // Calculate offset for pagination
            $offset = ($page - 1) * $limit;
            
            // Get products based on parameters
            if ($category) {
                $products = getProductsByCategory($category, $limit, $offset, $sort);
                $total = countProductsByCategory($category);
            } elseif ($search) {
                $products = searchProducts($search, $limit, $offset, $sort);
                $total = countSearchResults($search);
            } else {
                $products = getAllProducts($limit, $offset, $sort);
                $total = countAllProducts();
            }
            
            // Calculate pagination info
            $totalPages = ceil($total / $limit);
            
            // Prepare response
            $response = [
                'products' => $products,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit
                ]
            ];
            
            sendSuccessResponse($response);
            break;
            
        case 'detail':
            // Get product details
            $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($productId <= 0) {
                sendErrorResponse('Invalid product ID', 400);
                return;
            }
            
            $product = getProductById($productId);
            
            if (!$product) {
                sendErrorResponse('Product not found', 404);
                return;
            }
            
            // Get related products
            $product['related_products'] = getRelatedProducts($productId, 4);
            
            // Get product reviews
            $product['reviews'] = getProductReviews($productId);
            
            sendSuccessResponse($product);
            break;
            
        case 'featured':
            // Get featured products
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
            $limit = min(max(1, $limit), 20); // Limit between 1 and 20
            
            $products = getFeaturedProducts($limit);
            sendSuccessResponse($products);
            break;
            
        case 'categories':
            // Get all categories
            $categories = getAllCategories();
            sendSuccessResponse($categories);
            break;
            
        case 'category':
            // Get category details
            $categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($categoryId <= 0) {
                sendErrorResponse('Invalid category ID', 400);
                return;
            }
            
            $category = getCategoryById($categoryId);
            
            if (!$category) {
                sendErrorResponse('Category not found', 404);
                return;
            }
            
            sendSuccessResponse($category);
            break;
            
        case 'size_guide':
            // Get size guide for a product
            $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
            
            if ($productId <= 0) {
                sendErrorResponse('Invalid product ID', 400);
                return;
            }
            
            $product = getProductById($productId);
            
            if (!$product) {
                sendErrorResponse('Product not found', 404);
                return;
            }
            
            // Get size guide data for the product
            $sizeGuide = getProductSizeGuide($productId);
            
            if (!$sizeGuide) {
                sendErrorResponse('Size guide not available for this product', 404);
                return;
            }
            
            sendSuccessResponse($sizeGuide);
            break;
            
        default:
            sendErrorResponse('Invalid action', 400);
            break;
    }
}
?>