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
    
    // Get total products count
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt->fetchColumn();
    
    // Get active products count
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
    $active_products = $stmt->fetchColumn();
    
    // Get total categories count
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    $total_categories = $stmt->fetchColumn();
    
    // Get total users count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    
    // Get new customers (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $new_customers = $stmt->fetchColumn();

    // Get total orders count
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();
    
    // Get orders by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $orders_by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get total revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status != 'cancelled'");
    $total_revenue = $stmt->fetchColumn() ?: 0;
    
    // Get recent orders (last 5)
    $stmt = $pdo->query("SELECT o.id, o.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name, o.total_amount, o.status, o.created_at 
                         FROM orders o 
                         LEFT JOIN users u ON o.user_id = u.id 
                         ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates for recent orders
    foreach ($recent_orders as &$order) {
        $order['created_at'] = date('Y-m-d H:i:s', strtotime($order['created_at']));
    }
    
    // Get low stock products (stock <= 10)
    $stmt = $pdo->query("SELECT p.id, p.name, ps.stock_quantity 
                         FROM products p 
                         JOIN product_sizes ps ON p.id = ps.product_id 
                         WHERE ps.stock_quantity <= 10 
                         ORDER BY ps.stock_quantity ASC 
                         LIMIT 5");
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sales data for chart (last 7 days)
    $sales_data = [];
    $order_counts_data = [];
    $dates = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $display_date = date('M d', strtotime($date));
        $dates[] = $display_date;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue 
                              FROM orders 
                              WHERE DATE(created_at) = ? AND status != 'cancelled'");
        $stmt->execute([$date]);
        $day_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sales_data[] = [
            'date' => $display_date,
            'orders' => (int)$day_data['order_count'],
            'revenue' => (float)$day_data['revenue']
        ];
        $order_counts_data[] = (int)$day_data['order_count'];
    }

    // Get top selling products (top 5 by units sold)
    $stmt = $pdo->query("SELECT p.name, SUM(oi.quantity) as units_sold 
                         FROM order_items oi 
                         JOIN products p ON oi.product_id = p.id 
                         GROUP BY p.id 
                         ORDER BY units_sold DESC 
                         LIMIT 5");
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get customer demographics (example: users by registration month/year)
    // This is a simplified example, real demographics would be more complex
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as category, COUNT(*) as count 
                         FROM users 
                         GROUP BY category 
                         ORDER BY category ASC 
                         LIMIT 5");
    $customer_demographics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log admin activity
    logAdminActivity('view_dashboard', 'Viewed dashboard statistics');
    
    $response['success'] = true;
    $response['data'] = [
        'counts' => [
            'products' => $total_products,
            'active_products' => $active_products,
            'categories' => $total_categories,
            'users' => $total_users,
            'new_customers' => $new_customers,
            'orders' => $total_orders,
            'revenue' => $total_revenue,
            'page_views' => 0 // Placeholder, as page views are not tracked in DB
        ],
        'total_users' => [
            'value' => $total_users,
            'growth' => 15 // Placeholder for growth
        ],
        'total_orders' => [
            'value' => $total_orders,
            'growth' => 10 // Placeholder for growth
        ],
        'total_revenue' => [
            'value' => $total_revenue,
            'growth' => 20 // Placeholder for growth
        ],
        'products_in_stock' => [
            'value' => $total_products, // Assuming total_products represents products in stock for now
            'growth' => 5 // Placeholder for growth
        ],
        'orders_by_status' => $orders_by_status,
        'recent_orders' => $recent_orders,
        'low_stock_products' => $low_stock_products,
        'sales_chart' => [
            'labels' => $dates,
            'data' => $sales_data
        ],
        'order_counts_data' => $order_counts_data,
        'top_products' => $top_products,
        'customer_demographics' => $customer_demographics
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error fetching dashboard statistics: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error fetching dashboard statistics: ' . $e->getMessage());
}

echo json_encode($response);
?>
