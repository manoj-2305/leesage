<?php
// Enhanced product search and filtering API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/product.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

try {
    // Get search parameters
    $query = isset($_GET['query']) ? sanitizeInput($_GET['query']) : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $inStock = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : false;
    $onSale = isset($_GET['on_sale']) ? (bool)$_GET['on_sale'] : false;
    $sortBy = isset($_GET['sort_by']) ? sanitizeInput($_GET['sort_by']) : 'name';
    $sortOrder = isset($_GET['sort_order']) && strtolower($_GET['sort_order']) === 'desc' ? 'DESC' : 'ASC';
    
    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 12;
    $offset = ($page - 1) * $limit;
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'price', 'created_at', 'popularity'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'name';
    }
    
    // Search products with filters
    $searchParams = [
        'query' => $query,
        'category_id' => $category,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'in_stock' => $inStock,
        'on_sale' => $onSale,
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder,
        'limit' => $limit,
        'offset' => $offset
    ];
    
    $products = searchProductsWithFilters($searchParams);
    
    // Get total count for pagination
    $totalProducts = countSearchProductsWithFilters($searchParams);
    $totalPages = ceil($totalProducts / $limit);
    
    // Get price range for the current search (for filter UI)
    $priceRange = getProductPriceRange($searchParams);
    
    // Get categories with product counts for the current search (for filter UI)
    $categories = getCategoriesWithProductCounts($searchParams);
    
    sendSuccessResponse([
        'products' => $products,
        'filters' => [
            'price_range' => $priceRange,
            'categories' => $categories
        ],
        'pagination' => [
            'total' => $totalProducts,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $totalProducts)
        ]
    ]);
} catch (Exception $e) {
    logError('Product Search Error: ' . $e->getMessage());
    sendErrorResponse('Failed to search products');
}
?>