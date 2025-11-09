<?php
// Product filtering API endpoint

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
    // Get filter parameters
    $category = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $inStock = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : false;
    $onSale = isset($_GET['on_sale']) ? (bool)$_GET['on_sale'] : false;
    $featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : false;
    $tags = isset($_GET['tags']) ? explode(',', sanitizeInput($_GET['tags'])) : [];
    $attributes = [];
    
    // Process dynamic attributes (e.g., color, size, material)
    foreach ($_GET as $key => $value) {
        if (strpos($key, 'attr_') === 0) {
            $attributeName = substr($key, 5); // Remove 'attr_' prefix
            $attributes[$attributeName] = sanitizeInput($value);
        }
    }
    
    // Sorting parameters
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
    
    // Filter products
    $filterParams = [
        'category_id' => $category,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'in_stock' => $inStock,
        'on_sale' => $onSale,
        'featured' => $featured,
        'tags' => $tags,
        'attributes' => $attributes,
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder,
        'limit' => $limit,
        'offset' => $offset
    ];
    
    $products = filterProducts($filterParams);
    
    // Get total count for pagination
    $totalProducts = countFilteredProducts($filterParams);
    $totalPages = ceil($totalProducts / $limit);
    
    // Get available filters for UI
    $availableFilters = getAvailableFilters($filterParams);
    
    sendSuccessResponse([
        'products' => $products,
        'filters' => $availableFilters,
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
    logError('Product Filter Error: ' . $e->getMessage());
    sendErrorResponse('Failed to filter products');
}
?>