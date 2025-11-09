<?php
session_start();
header('Content-Type: application/json');
include '../../../../backend/database/config.php';
require_once __DIR__ . '/../auth/check_session.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get transaction ID from request
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';

if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
    exit();
}

try {
    // Fetch payment details
    $stmt = $conn->prepare("
        SELECT
            p.id AS payment_id,
            p.order_id,
            p.transaction_id,
            p.payment_method,
            p.amount,
            p.currency,
            p.status AS payment_status,
            p.payment_gateway,
            p.created_at AS payment_created_at,
            p.notes,
            o.order_number,
            o.total_amount AS order_total_amount,
            o.order_date,
            u.id AS user_id,
            u.first_name,
            u.last_name,
            u.email,
            sa.address_line1 AS shipping_address_line1,
            sa.address_line2 AS shipping_address_line2,
            sa.city AS shipping_city,
            sa.state_province AS shipping_state,
            sa.postal_code AS shipping_postal_code,
            sa.country AS shipping_country,
            ba.address_line1 AS billing_address_line1,
            ba.address_line2 AS billing_address_line2,
            ba.city AS billing_city,
            ba.state_province AS billing_state,
            ba.postal_code AS billing_postal_code,
            ba.country AS billing_country
        FROM
            payments p
        JOIN
            orders o ON p.order_id = o.id
        JOIN
            users u ON o.user_id = u.id
        LEFT JOIN
            addresses sa ON o.shipping_address = sa.id -- Assuming shipping_address in orders is an address ID
        LEFT JOIN
            addresses ba ON o.billing_address = ba.id -- Assuming billing_address in orders is an address ID
        WHERE
            p.transaction_id = ?
    ");
    $stmt->bind_param("s", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment_details = $result->fetch_assoc();
    $stmt->close();

    if (!$payment_details) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit();
    }

    // Fetch order items
    $stmt_items = $conn->prepare("
        SELECT
            oi.quantity,
            oi.price,
            p.name AS product_name
        FROM
            order_items oi
        JOIN
            products p ON oi.product_id = p.id
        WHERE
            oi.order_id = ?
    ");
    $stmt_items->bind_param("i", $payment_details['order_id']);
    $stmt_items->execute();
    $order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    // Fetch payment history (assuming a simple log or status changes in payments table itself)
    // For a more detailed history, a separate payment_history table would be ideal.
    // For now, we'll just use the created_at and updated_at from the payments table as a basic history.
    $payment_history = [
        ['status' => $payment_details['payment_status'], 'timestamp' => $payment_details['payment_created_at'], 'notes' => $payment_details['notes']]
    ];

    // Structure the response
    $response_data = [
        'payment_id' => $payment_details['payment_id'],
        'order_id' => $payment_details['order_id'],
        'transaction_id' => $payment_details['transaction_id'],
        'payment_method' => $payment_details['payment_method'],
        'amount' => $payment_details['amount'],
        'currency' => $payment_details['currency'],
        'payment_status' => $payment_details['payment_status'],
        'payment_gateway' => $payment_details['payment_gateway'],
        'payment_created_at' => $payment_details['payment_created_at'],
        'notes' => $payment_details['notes'],
        'order_number' => $payment_details['order_number'],
        'order_total' => $payment_details['order_total_amount'],
        'order_date' => $payment_details['order_date'],
        'user_id' => $payment_details['user_id'],
        'first_name' => $payment_details['first_name'],
        'last_name' => $payment_details['last_name'],
        'email' => $payment_details['email'],
        'shipping_address' => [
            'address_line1' => $payment_details['shipping_address_line1'],
            'address_line2' => $payment_details['shipping_address_line2'],
            'city' => $payment_details['shipping_city'],
            'state' => $payment_details['shipping_state'],
            'postal_code' => $payment_details['shipping_postal_code'],
            'country' => $payment_details['shipping_country']
        ],
        'billing_address' => [
            'address_line1' => $payment_details['billing_address_line1'],
            'address_line2' => $payment_details['billing_address_line2'],
            'city' => $payment_details['billing_city'],
            'state' => $payment_details['billing_state'],
            'postal_code' => $payment_details['billing_postal_code'],
            'country' => $payment_details['billing_country']
        ],
        'order_items' => $order_items,
        'payment_history' => $payment_history
    ];

    echo json_encode(['success' => true, 'payment' => $response_data]);

} catch (Exception $e) {
    error_log("Error fetching payment details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch payment details. Please try again later.']);
}

$conn->close();
?>