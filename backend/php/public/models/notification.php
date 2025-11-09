<?php
// User notifications model functions

/**
 * Get notifications for a user with pagination and filtering
 * 
 * @param int $userId User ID
 * @param string|null $readStatus Filter by read status ('read', 'unread', or null for all)
 * @param int $limit Number of notifications per page
 * @param int $offset Offset for pagination
 * @return array Array of notifications
 */
function getUserNotifications($userId, $readStatus = null, $limit = 20, $offset = 0) {
    $db = getDBConnection();
    
    $query = "SELECT * FROM user_notifications WHERE user_id = ?";
    $params = [$userId];
    $types = 'i';
    
    // Add read status filter if specified
    if ($readStatus === 'read') {
        $query .= " AND is_read = 1";
    } else if ($readStatus === 'unread') {
        $query .= " AND is_read = 0";
    }
    
    // Add ordering and pagination
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    
    // Bind parameters dynamically
    $bindParams = array_merge([$types], $params);
    $bindParamsRef = [];
    foreach ($bindParams as $key => $value) {
        $bindParamsRef[$key] = &$bindParams[$key];
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bindParamsRef);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        // Convert boolean fields
        $row['is_read'] = (bool)$row['is_read'];
        
        // Parse data if it's JSON
        if ($row['data'] && isJson($row['data'])) {
            $row['data'] = json_decode($row['data'], true);
        }
        
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Count total number of notifications for a user with optional filtering
 * 
 * @param int $userId User ID
 * @param string|null $readStatus Filter by read status ('read', 'unread', or null for all)
 * @return int Total number of notifications
 */
function countUserNotifications($userId, $readStatus = null) {
    $db = getDBConnection();
    
    $query = "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ?";
    $params = [$userId];
    $types = 'i';
    
    // Add read status filter if specified
    if ($readStatus === 'read') {
        $query .= " AND is_read = 1";
    } else if ($readStatus === 'unread') {
        $query .= " AND is_read = 0";
    }
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    $bindParams = array_merge([$types], $params);
    $bindParamsRef = [];
    foreach ($bindParams as $key => $value) {
        $bindParamsRef[$key] = &$bindParams[$key];
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bindParamsRef);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return (int)$row['count'];
}

/**
 * Get a specific notification by ID
 * 
 * @param int $notificationId Notification ID
 * @return array|null Notification data or null if not found
 */
function getNotificationById($notificationId) {
    $db = getDBConnection();
    
    $query = "SELECT * FROM user_notifications WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $notificationId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $notification = $result->fetch_assoc();
    
    // Convert boolean fields
    $notification['is_read'] = (bool)$notification['is_read'];
    
    // Parse data if it's JSON
    if ($notification['data'] && isJson($notification['data'])) {
        $notification['data'] = json_decode($notification['data'], true);
    }
    
    return $notification;
}

/**
 * Add a new notification for a user
 * 
 * @param array $notificationData Notification data
 * @return int|false The new notification ID or false on failure
 */
function addNotification($notificationData) {
    $db = getDBConnection();
    
    // Convert data to JSON if it's an array
    if (isset($notificationData['data']) && is_array($notificationData['data'])) {
        $notificationData['data'] = json_encode($notificationData['data']);
    }
    
    $query = "INSERT INTO user_notifications (user_id, type, title, message, data, is_read, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param(
        'issssss',
        $notificationData['user_id'],
        $notificationData['type'],
        $notificationData['title'],
        $notificationData['message'],
        $notificationData['data'],
        $notificationData['is_read'],
        $notificationData['created_at']
    );
    
    if ($stmt->execute()) {
        return $db->insert_id;
    }
    
    return false;
}

/**
 * Mark a notification as read
 * 
 * @param int $notificationId Notification ID
 * @return bool Success or failure
 */
function markNotificationAsRead($notificationId) {
    $db = getDBConnection();
    
    $query = "UPDATE user_notifications SET is_read = 1 WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $notificationId);
    
    return $stmt->execute();
}

/**
 * Mark all notifications for a user as read
 * 
 * @param int $userId User ID
 * @return bool Success or failure
 */
function markAllNotificationsAsRead($userId) {
    $db = getDBConnection();
    
    $query = "UPDATE user_notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $userId);
    
    return $stmt->execute();
}

/**
 * Delete a notification
 * 
 * @param int $notificationId Notification ID
 * @return bool Success or failure
 */
function deleteNotification($notificationId) {
    $db = getDatabaseConnection();
    
    $query = "DELETE FROM user_notifications WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $notificationId);
    
    return $stmt->execute();
}

/**
 * Delete all notifications for a user
 * 
 * @param int $userId User ID
 * @return bool Success or failure
 */
function deleteAllUserNotifications($userId) {
    $db = getDatabaseConnection();
    
    $query = "DELETE FROM user_notifications WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $userId);
    
    return $stmt->execute();
}

/**
 * Helper function to check if a string is valid JSON
 * 
 * @param string $string String to check
 * @return bool True if valid JSON, false otherwise
 */
function isJson($string) {
    if (!is_string($string)) {
        return false;
    }
    
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Create a notification for order status change
 * 
 * @param int $userId User ID
 * @param int $orderId Order ID
 * @param string $orderNumber Order number
 * @param string $newStatus New order status
 * @return int|false The new notification ID or false on failure
 */
function createOrderStatusNotification($userId, $orderId, $orderNumber, $newStatus) {
    $statusMessages = [
        'processing' => 'Your order is now being processed.',
        'shipped' => 'Your order has been shipped!',
        'delivered' => 'Your order has been delivered. Enjoy!',
        'cancelled' => 'Your order has been cancelled.',
        'refunded' => 'Your order has been refunded.'
    ];
    
    $message = isset($statusMessages[$newStatus]) ? $statusMessages[$newStatus] : "Your order status has been updated to {$newStatus}.";
    
    $notificationData = [
        'user_id' => $userId,
        'type' => 'order_status',
        'title' => "Order #{$orderNumber} Update",
        'message' => $message,
        'data' => json_encode([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'status' => $newStatus
        ]),
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return addNotification($notificationData);
}

/**
 * Create a notification for review approval
 * 
 * @param int $userId User ID
 * @param int $reviewId Review ID
 * @param int $productId Product ID
 * @param string $productName Product name
 * @return int|false The new notification ID or false on failure
 */
function createReviewApprovalNotification($userId, $reviewId, $productId, $productName) {
    $notificationData = [
        'user_id' => $userId,
        'type' => 'review_approved',
        'title' => 'Review Approved',
        'message' => "Your review for {$productName} has been approved and published.",
        'data' => json_encode([
            'review_id' => $reviewId,
            'product_id' => $productId,
            'product_name' => $productName
        ]),
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return addNotification($notificationData);
}

/**
 * Create a notification for price drop on wishlist item
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @param string $productName Product name
 * @param float $oldPrice Old price
 * @param float $newPrice New price
 * @return int|false The new notification ID or false on failure
 */
function createPriceDropNotification($userId, $productId, $productName, $oldPrice, $newPrice) {
    $percentOff = round((($oldPrice - $newPrice) / $oldPrice) * 100);
    
    $notificationData = [
        'user_id' => $userId,
        'type' => 'price_drop',
        'title' => 'Price Drop Alert',
        'message' => "{$productName} is now {$percentOff}% off! Price dropped from \${$oldPrice} to \${$newPrice}.",
        'data' => json_encode([
            'product_id' => $productId,
            'product_name' => $productName,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'percent_off' => $percentOff
        ]),
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return addNotification($notificationData);
}

/**
 * Create a notification for back in stock item
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @param string $productName Product name
 * @return int|false The new notification ID or false on failure
 */
function createBackInStockNotification($userId, $productId, $productName) {
    $notificationData = [
        'user_id' => $userId,
        'type' => 'back_in_stock',
        'title' => 'Back in Stock',
        'message' => "{$productName} is back in stock! Get it while supplies last.",
        'data' => json_encode([
            'product_id' => $productId,
            'product_name' => $productName
        ]),
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return addNotification($notificationData);
}
?>