<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

set_exception_handler(function ($exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
    exit();
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $message . ' in ' . $file . ' on line ' . $line]);
    exit();
});


// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Include database configuration
try {
    require_once __DIR__ . '/../../../database/config.php';
} catch (Exception $e) {
    $response['message'] = 'Config file error: ' . $e->getMessage();
    echo json_encode($response);
    exit();
}

try {
    require_once __DIR__ . '/../auth/check_session.php';
} catch (Exception $e) {
    $response['message'] = 'Session check file error: ' . $e->getMessage();
    echo json_encode($response);
    exit();
}

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
    $query = "SELECT p.*, o.order_number, u.first_name, u.last_name, u.email 
              FROM payments p
              LEFT JOIN orders o ON p.order_id = o.id
              LEFT JOIN users u ON p.user_id = u.id";
    $countQuery = "SELECT COUNT(*) FROM payments p LEFT JOIN orders o ON p.order_id = o.id LEFT JOIN users u ON p.user_id = u.id";
    $params = [];
    
    // Add WHERE conditions
    $conditions = [];
    
    if (!empty($status)) {
        $conditions[] = "p.status = ?";
        $params[] = $status;
    }
    
    if (!empty($date_from)) {
        $conditions[] = "DATE(p.payment_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions[] = "DATE(p.payment_date) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $conditions[] = "(p.transaction_id LIKE ? OR o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
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
    $query .= " ORDER BY p.payment_date DESC LIMIT :limit OFFSET :offset";
    $countParams = $params;
    
    // Execute count query
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalPayments = $countStmt->fetchColumn();
    
    // Execute main query
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = [
        'payments' => $payments,
        'pagination' => [
            'total' => $totalPayments,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalPayments / $limit)
        ]
    ];
    
    // Log admin activity
    logAdminActivity('view_payments', 'Viewed payments list');
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching payments: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error fetching payments: ' . $e->getMessage());
}

echo json_encode($response);
?>