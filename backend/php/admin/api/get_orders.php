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
    if ($page < 1) {
        $page = 1;
    }
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Base query
    $query = "SELECT o.*, u.first_name, u.last_name, u.email,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.id";
    $countQuery = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id";
    $params = [];
    
    // Add WHERE conditions
    $conditions = [];
    
    if (!empty($status)) {
        $conditions[] = "o.status = ?";
        $params[] = $status;
    }
    
    if (!empty($date_from)) {
        $conditions[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $conditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
        $countQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Add ORDER BY and LIMIT
    $query .= " ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
    $countParams = $params;
    
    // Execute count query
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalOrders = $countStmt->fetchColumn();
    
    // Execute main query
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = [
        'orders' => $orders,
        'pagination' => [
            'total' => $totalOrders,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalOrders / $limit)
        ]
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching orders: ' . $e->getMessage());
}

echo json_encode($response);
?>
