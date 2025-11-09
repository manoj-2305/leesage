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

$response = ['success' => false, 'message' => ''];

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

// Check if required fields are provided
if (!isset($_POST['order_id']) || empty($_POST['order_id']) || 
    !isset($_POST['status']) || empty($_POST['status'])) {
    $response['message'] = 'Order ID and status are required';
    echo json_encode($response);
    exit();
}

$order_id = (int)$_POST['order_id'];
$status = $_POST['status'];
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

// Get current admin
$admin = getCurrentAdmin();

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Check if order exists
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $response['message'] = 'Order not found';
        echo json_encode($response);
        exit();
    }
    
    $current_status = $order['status'];
    
    // Validate status transition
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    if (!in_array($status, $valid_statuses)) {
        $response['message'] = 'Invalid status';
        echo json_encode($response);
        exit();
    }
    
    // Prevent certain status transitions
    $invalid_transitions = [
        'delivered' => ['pending', 'processing'],
        'cancelled' => ['delivered', 'refunded'],
        'refunded' => ['pending']
    ];
    
    if (isset($invalid_transitions[$status]) && in_array($current_status, $invalid_transitions[$status])) {
        $response['message'] = "Cannot change order from '{$current_status}' to '{$status}'";
        echo json_encode($response);
        exit();
    }
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    // Add status history entry
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$order_id, $status, $notes, $admin['id']]);
    
    // If status is cancelled or refunded, handle inventory updates
    if (($status == 'cancelled' || $status == 'refunded') && 
        ($current_status != 'cancelled' && $current_status != 'refunded')) {
        
        // Get order items
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return items to inventory
        foreach ($items as $item) {
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Log inventory change
            $stmt = $pdo->prepare("INSERT INTO inventory_history (product_id, change_type, quantity, notes, created_by, created_at) 
                                 VALUES (?, 'increase', ?, ?, ?, NOW())");
            $stmt->execute([
                $item['product_id'],
                $item['quantity'],
                "Order #{$order_id} {$status}",
                $admin['id']
            ]);
        }
    }
    
    $pdo->commit();
    
    // Log admin activity
    logAdminActivity('update_order_status', "Updated order #{$order_id} status from {$current_status} to {$status}");
    
    $response['success'] = true;
    $response['message'] = 'Order status updated successfully';
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error updating order status: ' . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error updating order status: ' . $e->getMessage());
}

echo json_encode($response);
?>
