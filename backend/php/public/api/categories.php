<?php
// API endpoint for fetching product categories

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
    // Get query parameters
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    
    switch ($action) {
        case 'list':
            // Get all categories
            $categories = getAllCategories();
            sendSuccessResponse($categories);
            break;
            
        case 'detail':
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
            
            // Get products in this category
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : PRODUCTS_PER_PAGE;
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
            
            // Validate and sanitize parameters
            $page = max(1, $page); // Ensure page is at least 1
            $limit = min(max(1, $limit), 50); // Limit between 1 and 50
            
            $products = getProductsByCategory($categoryId, $page, $limit, $sort);
            $total = countProductsByCategory($categoryId);
            
            // Calculate pagination info
            $totalPages = ceil($total / $limit);
            
            // Prepare response
            $response = [
                'category' => $category,
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
            
        default:
            sendErrorResponse('Invalid action', 400);
            break;
    }
}
?>