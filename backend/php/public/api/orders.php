<?php
// Orders API endpoint

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

// Require authentication for all order operations
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Get current user ID
$userId = getCurrentUserId();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Check if specific order ID is requested
        if (isset($_GET['id']) && !empty($_GET['id'])) {
            // Get specific order details
            try {
                $orderId = (int)$_GET['id'];
                $order = getOrderById($orderId);
                
                // Verify order belongs to current user
                if (!$order || $order['user_id'] != $userId) {
                    sendErrorResponse('Order not found', 404);
                    exit;
                }
                
                // Get order items
                $orderItems = getOrderItems($orderId);
                
                // Get order status history
                $statusHistory = getOrderStatusHistory($orderId);
                
                // Prepare response
                $orderDetails = [
                    'order' => $order,
                    'items' => $orderItems,
                    'status_history' => $statusHistory
                ];
                
                sendSuccessResponse($orderDetails);
            } catch (Exception $e) {
                logError('Order Details Error: ' . $e->getMessage());
                sendErrorResponse('Failed to retrieve order details');
            }
        } else {
            // Get all orders for current user
            try {
                // Pagination parameters
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $offset = ($page - 1) * $limit;
                
                // Get orders
                $orders = getUserOrders($userId, $limit, $offset);
                
                // Get total count for pagination
                $totalOrders = getUserOrdersCount($userId);
                $totalPages = ceil($totalOrders / $limit);
                
                sendSuccessResponse([
                    'orders' => $orders,
                    'pagination' => [
                        'total' => $totalOrders,
                        'per_page' => $limit,
                        'current_page' => $page,
                        'last_page' => $totalPages,
                        'from' => $offset + 1,
                        'to' => min($offset + $limit, $totalOrders)
                    ]
                ]);
            } catch (Exception $e) {
                logError('Orders List Error: ' . $e->getMessage());
                sendErrorResponse('Failed to retrieve orders');
            }
        }
        break;
        
    case 'POST':
        // Cancel order (simplified for this example)
        try {
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            if (!isset($data['order_id']) || empty($data['order_id'])) {
                sendErrorResponse('Order ID is required', 400);
                exit;
            }
            
            $orderId = (int)$data['order_id'];
            $order = getOrderById($orderId);
            
            // Verify order belongs to current user
            if (!$order || $order['user_id'] != $userId) {
                sendErrorResponse('Order not found', 404);
                exit;
            }
            
            // Check if order can be cancelled
            if (!in_array($order['status'], ['pending', 'processing'])) {
                sendErrorResponse('This order cannot be cancelled', 400);
                exit;
            }
            
            // Begin transaction
            beginTransaction();
            
            try {
                // Update order status
                $statusUpdated = updateOrderStatus($orderId, 'cancelled');
                
                if (!$statusUpdated) {
                    throw new Exception('Failed to update order status');
                }
                
                // Add order status history
                $statusData = [
                    'order_id' => $orderId,
                    'status' => 'cancelled',
                    'comment' => 'Order cancelled by customer',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $statusHistoryId = addOrderStatusHistory($statusData);
                
                if (!$statusHistoryId) {
                    throw new Exception('Failed to add order status history');
                }
                
                // Restore product stock
                $orderItems = getOrderItems($orderId);
                foreach ($orderItems as $item) {
                    $stockUpdated = restoreProductStock($item['product_id'], $item['quantity']);
                    
                    if (!$stockUpdated) {
                        throw new Exception('Failed to restore product stock');
                    }
                }
                
                // Commit transaction
                commitTransaction();
                
                sendSuccessResponse([
                    'message' => 'Order cancelled successfully'
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                rollbackTransaction();
                throw $e;
            }
        } catch (Exception $e) {
            logError('Order Cancel Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while cancelling the order');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>