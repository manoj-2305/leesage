<?php
// Coupons API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST method for coupon validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

try {
    // Get current user ID (optional for coupon validation)
    $userId = getCurrentUserId();

    // Get input data
    $data = getInputData();

    // Validate required fields
    if (!isset($data['code']) || empty(trim($data['code']))) {
        sendErrorResponse('Coupon code is required', 400);
        exit;
    }

    $couponCode = sanitizeInput($data['code']);

    // Query coupon from database
    $query = "SELECT * FROM coupons WHERE code = ? AND is_active = 1";
    $stmt = executeQuery($query, [$couponCode]);

    if ($stmt->rowCount() === 0) {
        sendErrorResponse('Invalid or expired coupon code', 400);
        exit;
    }

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if coupon is expired
    $currentDate = date('Y-m-d H:i:s');
    if ($coupon['end_date'] !== null && $coupon['end_date'] < $currentDate) {
        sendErrorResponse('Coupon code has expired', 400);
        exit;
    }

    // Return coupon details
    sendSuccessResponse([
        'code' => $coupon['code'],
        'discount_type' => $coupon['type'], // 'percentage' or 'fixed'
        'discount_value' => $coupon['value'],
        'min_order_amount' => $coupon['min_order_amount'] ?? 0,
    ]);

} catch (Exception $e) {
    logError('Coupon Validation Error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while validating coupon: ' . $e->getMessage());
}
?>
