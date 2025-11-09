<?php
// Order details API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/order.php';
require_once __DIR__ . '/../models/user.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require authentication
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

// Get current user ID
$userId = getCurrentUserId();

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    sendErrorResponse('Order ID is required', 400);
    exit;
}

try {
    $orderId = (int)$_GET['id'];
    $order = getOrderById($orderId);
    
    // Verify order belongs to current user
    if (!$order || $order['user_id'] != $userId) {
        sendErrorResponse('Order not found', 404);
        exit;
    }
    
    // Get order items with product details
    $orderItems = getOrderItemsWithProductDetails($orderId);
    
    // Get order status history
    $statusHistory = getOrderStatusHistory($orderId);
    
    // Prepare response
    $orderDetails = [
        'order' => [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'order_date' => $order['order_date'],
            'status' => $order['status'],
            'subtotal' => $order['subtotal'],
            'tax' => $order['tax'],
            'shipping' => $order['shipping'],
            'total' => $order['total'],
            'shipping_address' => $order['shipping_address'],
            'shipping_city' => $order['shipping_city'],
            'shipping_state' => $order['shipping_state'],
            'shipping_zip' => $order['shipping_zip'],
            'shipping_country' => $order['shipping_country'],
            'payment_method' => $order['payment_method'],
            'created_at' => $order['created_at']
        ],
        'items' => $orderItems,
        'status_history' => $statusHistory
    ];
    
    sendSuccessResponse($orderDetails);
} catch (Exception $e) {
    logError('Order Details Error: ' . $e->getMessage());
    sendErrorResponse('Failed to retrieve order details');
}
?>