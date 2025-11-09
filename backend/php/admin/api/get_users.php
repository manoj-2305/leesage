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
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    // Base query
    $query = "SELECT id, first_name, last_name, email, profile_image, last_login, is_active, created_at 
              FROM users";
    $countQuery = "SELECT COUNT(*) FROM users";
    $params = [];
    
    // Add WHERE conditions
    $conditions = [];
    
    if ($user_id > 0) {
        $conditions[] = "id = ?";
        $params[] = $user_id;
    } else if (!empty($search)) {
        $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($status === 'active') {
        $conditions[] = "is_active = 1";
    } elseif ($status === 'inactive') {
        $conditions[] = "is_active = 0";
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
        $countQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Add ORDER BY and LIMIT
    $query .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $countParams = $params;
    
    // Execute count query
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalUsers = $countStmt->fetchColumn();
    
    // Execute main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order count for each user
    foreach ($users as &$user) {
        $orderStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $orderStmt->execute([$user['id']]);
        $user['order_count'] = $orderStmt->fetchColumn();
    }
    
    $response['success'] = true;
    $response['data'] = [
        'users' => $users,
        'pagination' => [
            'total' => $totalUsers,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalUsers / $limit)
        ]
    ];
    
    // Log admin activity
    logAdminActivity('view_users', 'Viewed users list');
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching users: ' . $e->getMessage());
}

echo json_encode($response);
