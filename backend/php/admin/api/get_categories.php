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
    $parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
    
    // Prepare query
    $query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) as product_count,
                    (SELECT COUNT(*) FROM categories c2 WHERE c2.parent_id = c.id) as subcategory_count
              FROM categories c";
    $params = [];
    
    // Filter by parent_id if provided
    if ($parent_id !== null) {
        $query .= " WHERE c.parent_id = ?";
        $params[] = $parent_id;
    } else {
        $query .= " WHERE c.parent_id IS NULL OR c.parent_id = 0";
    }
    
    $query .= " ORDER BY c.name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = $categories;
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching categories: ' . $e->getMessage());
}

echo json_encode($response);
?>
