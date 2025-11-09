<?php
// Order model for handling order operations

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/product.php';
require_once __DIR__ . '/cart.php';

/**
 * Create a new order
 * @param int $userId User ID
 * @param array $orderData Order data
 * @param array $cartItems Cart items
 * @return int|string Order ID or error message
 */
function createOrder($userId, $orderData, $cartItems) {
    // Validate cart before creating order
    $cartValidation = validateCart();
    if (!$cartValidation['valid']) {
        return ['error' => 'Some items in your cart are no longer available', 'invalid_items' => $cartValidation['invalid_items']];
    }
    
    // Calculate totals
    $cartTotals = calculateCartTotals();
    
    // Start transaction
    $pdo = beginTransaction();
    
    try {
        // Create order
        $orderNumber = generateOrderNumber();
        $orderDataToInsert = [
            'user_id' => $userId,
            'order_id' => $orderNumber,
            'status' => 'pending',
            'subtotal' => $orderData['subtotal'],
            'tax_amount' => $orderData['tax_amount'],
            'shipping_amount' => $orderData['shipping_amount'],
            'discount_amount' => $orderData['discount_amount'] ?? 0,
            'total_amount' => $orderData['total_amount'],
            'shipping_address' => is_array($orderData['shipping_address']) ? json_encode($orderData['shipping_address']) : $orderData['shipping_address'],
            'billing_address' => is_array($orderData['billing_address']) ? json_encode($orderData['billing_address']) : $orderData['billing_address'],
            'payment_method' => $orderData['payment_method'],
            'notes' => $orderData['notes'] ?? null
        ];
        
        $orderId = insert('orders', $orderDataToInsert);
        
        // Add order status history
        $statusData = [
            'order_id' => $orderId,
            'status' => 'pending',
            'comment' => 'Order created',
            'created_by' => $userId // Assuming the user placing the order is the creator
        ];
        
        insert('order_status_history', $statusData);
        
        // Add order items
        foreach ($cartItems as $item) {
            $orderItemData = [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'size_id' => $item['size_id'], // Added size_id
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total_price' => $item['total'] // Changed 'total' to 'total_price' to match schema
            ];
            
            insert('order_items', $orderItemData);
            // Update product stock
            updateProductStock($item['product_id'], $item['size_id'], -$item['quantity']);
        }
        
        // Commit transaction
        commitTransaction($pdo);
        
        // Clear cart after successful order
        clearCart();
        
        return $orderId;
    } catch (Exception $e) {
        // Rollback transaction on error
        rollbackTransaction($pdo);
        logError('Order creation failed: ' . $e->getMessage());
        return ['error' => 'Failed to create order. Please try again.'];
    }
}

/**
 * Get order by ID
 * @param int $orderId Order ID
 * @param int $userId User ID (for security check)
 * @return array|null Order details or null if not found
 */
function getOrderById($orderId, $userId = null) {
    $query = "SELECT * FROM orders WHERE id = :id";
    $params = ['id' => $orderId];
    
    // If user ID is provided, check if order belongs to user
    if ($userId) {
        $query .= " AND user_id = :user_id";
        $params['user_id'] = $userId;
    }
    
    $order = fetchOne($query, $params);
    
    if ($order) {
        // Get order items
        $order['items'] = getOrderItems($orderId);
        
        // Get order status history
        $order['status_history'] = getOrderStatusHistory($orderId);
    }
    
    return $order;
}

/**
 * Get order items
 * @param int $orderId Order ID
 * @return array Order items
 */
function getOrderItems($orderId) {
    $query = "SELECT oi.*, p.name, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = oi.product_id AND pi.is_primary = 1 LIMIT 1) as image 
             FROM order_items oi 
             JOIN products p ON oi.product_id = p.id 
             WHERE oi.order_id = :order_id";
    
    return fetchAll($query, ['order_id' => $orderId]);
}

/**
 * Get order status history
 * @param int $orderId Order ID
 * @return array Order status history
 */
function getOrderStatusHistory($orderId) {
    $query = "SELECT * FROM order_status_history 
             WHERE order_id = :order_id 
             ORDER BY created_at ASC";
    
    return fetchAll($query, ['order_id' => $orderId]);
}

/**
 * Update order status
 * @param int $orderId Order ID
 * @param string $status New status
 * @param string $notes Notes
 * @return bool Success status
 */
function updateOrderStatus($orderId, $status, $notes = '') {
    // Start transaction
    $pdo = beginTransaction();
    
    try {
        // Update order status
        update('orders', ['status' => $status], 'id = :id', ['id' => $orderId]);
        
        // Add status history
        $statusData = [
            'order_id' => $orderId,
            'status' => $status,
            'comment' => $notes,
            'created_by' => $_SESSION['user_id'] ?? null // Assuming admin or system if no user session
        ];
        
        insert('order_status_history', $statusData);
        
        // Commit transaction
        commitTransaction($pdo);
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        rollbackTransaction($pdo);
        logError('Order status update failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get orders by user ID
 * @param int $userId User ID
 * @param int $limit Limit
 * @param int $offset Offset
 * @return array Orders
 */
function getOrdersByUserId($userId, $limit = 10, $offset = 0) {
    $query = "SELECT * FROM orders 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :limit OFFSET :offset";
    
    $params = [
        'user_id' => $userId,
        'limit' => $limit,
        'offset' => $offset
    ];
    
    return fetchAll($query, $params);
}

/**
 * Count orders by user ID
 * @param int $userId User ID
 * @return int Order count
 */
function countOrdersByUserId($userId) {
    $query = "SELECT COUNT(*) as count FROM orders WHERE user_id = :user_id";
    $result = fetchOne($query, ['user_id' => $userId]);
    return (int)$result['count'];
}

/**
 * Update product stock
 * @param int $productId Product ID
 * @param int $quantity Quantity change (negative for decrease)
 * @return bool Success status
 */
function updateProductStock($productId, $sizeId, $quantity) {
    // Get current stock from product_sizes table
    $productSize = fetchOne("SELECT stock_quantity FROM product_sizes WHERE product_id = :product_id AND id = :size_id", ['product_id' => $productId, 'size_id' => $sizeId]);
    
    if (!$productSize) {
        return false;
    }
    
    $newStock = $productSize['stock_quantity'] + $quantity;
    
    // Ensure stock doesn't go below 0
    if ($newStock < 0) {
        $newStock = 0;
    }
    
    // Update stock in product_sizes table
    update('product_sizes', ['stock_quantity' => $newStock], 'product_id = :product_id AND id = :size_id', ['product_id' => $productId, 'size_id' => $sizeId]);
    
    // Add inventory history
    $inventoryData = [
        'product_id' => $productId,
        'size_id' => $sizeId, // Add size_id to inventory history
        'quantity_change' => $quantity,
        'new_quantity' => $newStock,
        'notes' => $quantity < 0 ? 'Order placement' : 'Stock update'
    ];
    
    insert('inventory_history', $inventoryData);
    
    return true;
}

/**
 * Get order items with product details
 * @param int $orderId Order ID
 * @return array Order items with product details
 */
function getOrderItemsWithProductDetails($orderId) {
    $query = "SELECT oi.*, p.name, p.sku, p.description, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = oi.product_id AND pi.is_primary = 1 LIMIT 1) as image 
             FROM order_items oi 
             JOIN products p ON oi.product_id = p.id 
             WHERE oi.order_id = :order_id";
    
    return fetchAll($query, ['order_id' => $orderId]);
}

/**
 * Restore product stock when order is cancelled
 * @param int $productId Product ID
 * @param int $sizeId Size ID
 * @param int $quantity Quantity to restore
 * @return bool Success status
 */
function restoreProductStock($productId, $sizeId, $quantity) {
    // We're restoring stock, so quantity should be positive
    return updateProductStock($productId, $sizeId, abs($quantity));
}
?>