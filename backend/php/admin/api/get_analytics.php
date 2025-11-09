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
    
    // Fetch total sales (completed orders only)
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total_sales FROM orders WHERE status = 'completed'");
    $totalSales = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    // Fetch total orders
    $stmt = $pdo->query("SELECT COUNT(*) AS total_orders FROM orders");
    $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

    // Fetch total products
    $stmt = $pdo->query("SELECT COUNT(*) AS total_products FROM products");
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;

    // Fetch total users
    $stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;

    // Fetch new customers (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) AS new_customers FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $newCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['new_customers'] ?? 0;

    // Fetch sales over time (last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as order_day, SUM(total_amount) as daily_sales 
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        AND status = 'completed'
        GROUP BY DATE(created_at) 
        ORDER BY order_day ASC
    ");
    $salesOverTime = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no sales data, create sample data for the last 7 days
    if (empty($salesOverTime)) {
        $salesOverTime = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $salesOverTime[] = [
                'order_day' => $date,
                'daily_sales' => 0
            ];
        }
    }

    // Fetch top selling products
    $stmt = $pdo->query("
        SELECT p.name, SUM(oi.quantity) as total_quantity_sold 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        GROUP BY p.id, p.name 
        ORDER BY total_quantity_sold DESC 
        LIMIT 5
    ");
    $topSellingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no products sold, get top products by stock
    if (empty($topSellingProducts)) {
        $stmt = $pdo->query("
            SELECT p.name, ps.stock_quantity as total_quantity_sold
            FROM products p
            JOIN product_sizes ps ON p.id = ps.product_id
            ORDER BY ps.stock_quantity DESC
            LIMIT 5
        ");
        $topSellingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch customer demographics by registration month
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as category, COUNT(*) as count 
        FROM users 
        GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
        ORDER BY category DESC 
        LIMIT 6
    ");
    $customerDemographics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no demographics data, create sample
    if (empty($customerDemographics)) {
        $customerDemographics = [
            ['category' => 'This Month', 'count' => 0],
            ['category' => 'Last Month', 'count' => 0]
        ];
    }

    // Fetch order status distribution
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $orderStatusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch revenue by category
    $stmt = $pdo->query("
        SELECT c.name as category, SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN product_categories pc ON p.id = pc.product_id
        JOIN categories c ON pc.category_id = c.id
        GROUP BY c.id, c.name
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $revenueByCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = [
        'totalSales' => floatval($totalSales),
        'totalOrders' => intval($totalOrders),
        'totalProducts' => intval($totalProducts),
        'totalUsers' => intval($totalUsers),
        'newCustomers' => intval($newCustomers),
        'salesOverTime' => $salesOverTime,
        'topSellingProducts' => $topSellingProducts,
        'customerDemographics' => $customerDemographics,
        'orderStatusDistribution' => $orderStatusDistribution,
        'revenueByCategory' => $revenueByCategory
    ];

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Database error in get_analytics.php: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
    error_log('Error in get_analytics.php: ' . $e->getMessage());
}

echo json_encode($response);
?>
