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
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    // Base query
    $query = "SELECT ih.id, ih.product_id, p.name as product_name, ih.change_type, ih.quantity, ih.notes, 
              ih.created_by, CONCAT(au.username, ' (', au.full_name, ')') as admin_name, ih.created_at 
              FROM inventory_history ih 
              LEFT JOIN products p ON ih.product_id = p.id 
              LEFT JOIN admin_users au ON ih.created_by = au.id";
    
    $countQuery = "SELECT COUNT(*) FROM inventory_history ih";
    $params = [];
    
    // Add product filter if specified
    if ($product_id) {
        $query .= " WHERE ih.product_id = ?";
        $countQuery .= " WHERE ih.product_id = ?";
        $params[] = $product_id;
    }
    
    // Add sorting
    $query .= " ORDER BY ih.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countParams = $product_id ? [$product_id] : [];
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    // Format dates and other data
    foreach ($history as &$entry) {
        $entry['created_at'] = date('Y-m-d H:i:s', strtotime($entry['created_at']));
    }
    
    // Log admin activity
    logAdminActivity('view_inventory_history', 'Viewed inventory history' . ($product_id ? ' for product ID: ' . $product_id : ''));
    
    $response['success'] = true;
    $response['data'] = [
        'history' => $history,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching inventory history: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error fetching inventory history: ' . $e->getMessage());
}

echo json_encode($response);
?>
