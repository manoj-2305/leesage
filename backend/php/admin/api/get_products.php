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

$response = ['success' => false, 'message' => '', 'data' => []];

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $minStockStatus = isset($_GET['min_stock_status']) ? $_GET['min_stock_status'] : '';
    $size_id = isset($_GET['size_id']) ? (int)$_GET['size_id'] : 0;
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    
    // Base query
    $query = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
             FROM products p";
    $countQuery = "SELECT COUNT(*) FROM products p";
    $params = [];
    $countParams = [];
    
    // Add WHERE conditions
    $conditions = [];
    
    if ($product_id > 0) {
        $conditions[] = "p.id = ?";
        $params[] = $product_id;
        $countParams[] = $product_id;
    }
    
    if (!empty($search)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $countParams[] = $searchParam;
        $countParams[] = $searchParam;
        $countParams[] = $searchParam;
    }
    
    if ($category > 0) {
        $query .= " JOIN product_categories pc ON p.id = pc.product_id";
        $countQuery .= " JOIN product_categories pc ON p.id = pc.product_id";
        $conditions[] = "pc.category_id = ?";
        $params[] = $category;
        $countParams[] = $category;
    }
    
    if ($status === 'active') {
        $conditions[] = "p.is_active = 1";
    } elseif ($status === 'inactive') {
        $conditions[] = "p.is_active = 0";
    }
    
    if ($minStockStatus === 'low-stock') {
        $query .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $countQuery .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $conditions[] = 'ps.stock_quantity <= ps.min_stock_level AND ps.stock_quantity > 0';
    } elseif ($minStockStatus === 'out-of-stock') {
        $query .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $countQuery .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $conditions[] = 'ps.stock_quantity <= 0';
    } elseif ($minStockStatus === 'in-stock') {
        $query .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $countQuery .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $conditions[] = 'ps.stock_quantity > ps.min_stock_level AND ps.stock_quantity > 0';
    }

    if ($size_id > 0) {
        $query .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $countQuery .= " JOIN product_sizes ps ON p.id = ps.product_id";
        $conditions[] = "ps.id = ?";
        $params[] = $size_id;
        $countParams[] = $size_id;
    }
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
        $countQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Add ORDER BY and LIMIT
    if ($product_id <= 0) {
        $query .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
    }
    

    
    // Execute count query
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalProducts = $countStmt->fetchColumn();
    
    // Execute main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories and images for each product
    foreach ($products as &$product) {
        // Get categories
        $categoryStmt = $pdo->prepare("SELECT c.id, c.name FROM categories c 
                                     JOIN product_categories pc ON c.id = pc.category_id 
                                     WHERE pc.product_id = ?");
        $categoryStmt->execute([$product['id']]);
        $product['categories'] = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get images
        $imageStmt = $pdo->prepare("SELECT id, image_path, is_primary, display_order 
                                   FROM product_images 
                                   WHERE product_id = ? 
                                   ORDER BY is_primary DESC, display_order ASC");
        $imageStmt->execute([$product['id']]);
        $product['images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get sizes
        $sizeStmt = $pdo->prepare("SELECT id, size_name, stock_quantity, min_stock_level, is_active FROM product_sizes WHERE product_id = ? ORDER BY size_name ASC");
        $sizeStmt->execute([$product['id']]);
        $product['sizes'] = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $response['success'] = true;
    $response['data'] = [
        'products' => $products,
        'pagination' => [
            'total' => $totalProducts,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalProducts / $limit)
        ]
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching products: ' . $e->getMessage());
}

echo json_encode($response);
?>
