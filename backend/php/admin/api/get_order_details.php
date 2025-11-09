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

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    $response['message'] = 'Order ID is required';
    echo json_encode($response);
    exit();
}

$order_id = (int)$_GET['order_id'];

try {
    $pdo = getDBConnection();
    
    // Get order details
    $stmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email 
                          FROM orders o 
                          LEFT JOIN users u ON o.user_id = u.id 
                          WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $response['message'] = 'Order not found';
        echo json_encode($response);
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.sku, 
                            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as product_image 
                          FROM order_items oi 
                          LEFT JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order status history
    $stmt = $pdo->prepare("SELECT osh.*, au.username as admin_username 
                          FROM order_status_history osh 
                          LEFT JOIN admin_users au ON osh.created_by = au.id 
                          WHERE osh.order_id = ? 
                          ORDER BY osh.created_at DESC");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = [
        'order' => $order,
        'items' => $order_items,
        'status_history' => $status_history
    ];
    
    // Log admin activity
    logAdminActivity('view_order', 'Viewed order #' . $order['order_number']);
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching order details: ' . $e->getMessage());
}

echo json_encode($response);
?>
