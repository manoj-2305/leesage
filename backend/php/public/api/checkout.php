<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/error.log');

// Checkout API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/order.php';
require_once __DIR__ . '/../models/cart.php';
require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/address.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require authentication for checkout
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

try {
    // Get current user ID
    $userId = getCurrentUserId();

    // Get input data
    $data = getInputData();

    // Validate required fields
    $requiredFields = ['address_id', 'payment_method'];
    $missingFields = validateRequiredFields($data, $requiredFields);
    if (!empty($missingFields)) {
        sendErrorResponse('Missing required fields', 400);
        exit;
    }

    $addressId = (int)$data['address_id'];
    $paymentMethod = sanitizeInput($data['payment_method']);
    $couponAvailed = isset($data['coupon_availed']) ? (int)$data['coupon_availed'] : 0;
    $couponDetails = isset($data['coupon_details']) ? $data['coupon_details'] : null;

    // Validate payment method
    if (!in_array($paymentMethod, ['cod', 'razorpay'])) {
        sendErrorResponse('Invalid payment method', 400);
        exit;
    }

    // Get and validate address
    $address = getUserAddressById($userId, $addressId);
    if (!$address) {
        sendErrorResponse('Invalid address', 400);
        exit;
    }

    // Get cart
    $cart = getCart();
    if (empty($cart['items'])) {
        sendErrorResponse('Your cart is empty', 400);
        exit;
    }

    // Validate cart items stock
    $validationResult = validateCart();
    if (!$validationResult['valid']) {
        sendErrorResponse('Some items are out of stock', 400);
        exit;
    }

    // Calculate totals
    $cartTotals = calculateCartTotals();

    // Apply coupon if provided
    $discount = 0;
    if ($couponAvailed && $couponDetails) {
        // Frontend has already validated the coupon and sent details
        if ($cartTotals['subtotal'] >= $couponDetails['min_order_amount']) {
            if ($couponDetails['type'] === 'percentage') {
                $discount = $cartTotals['subtotal'] * ($couponDetails['value'] / 100);
            } else {
                $discount = min($couponDetails['value'], $cartTotals['subtotal']);
            }
        } else {
            sendErrorResponse('Minimum order amount not met for coupon', 400);
            exit;
        }
    }

    // Set shipping cost based on payment method
    $shippingCost = ($paymentMethod === 'cod') ? 49.00 : $cartTotals['shipping_cost'];

    // Calculate final totals
    $subtotal = $cartTotals['subtotal'];
    $taxAmount = $cartTotals['tax_amount'];
    $total = $subtotal + $taxAmount + $shippingCost - $discount;

    // Serialize address for storage
    $shippingAddressJson = json_encode($address);

    // Prepare order data
    $orderData = [
        'user_id' => $userId,
        'order_id' => generateOrderNumber(),
        'status' => 'pending',
        'subtotal' => $subtotal,
        'tax_amount' => $taxAmount,
        'shipping_amount' => $shippingCost,
        'discount_amount' => $discount,
        'total_amount' => $total,
        'shipping_address' => $shippingAddressJson,
        'billing_address' => $shippingAddressJson, // Same as shipping for now
        'payment_method' => $paymentMethod,
        'coupon_availed' => $couponAvailed,
        'notes' => isset($data['notes']) ? sanitizeInput($data['notes']) : null
    ];

    // Create order
    $orderId = createOrder($userId, $orderData, $cart['items']);

    if (!$orderId) {
        sendErrorResponse('Failed to create order', 500);
        exit;
    }

    // If coupon was availed, insert details into ordered_coupon table
    if ($couponAvailed && $couponDetails) {
        $insertCouponQuery = "INSERT INTO ordered_coupon (order_id, user_id, coupon_code, coupon_type, coupon_value, discount_amount, applied_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        executeQuery($insertCouponQuery, [
            $orderId,
            $userId,
            $couponDetails['code'],
            $couponDetails['type'],
            $couponDetails['value'],
            $couponDetails['discount_amount'] // Use the discount_amount from couponDetails
        ]);
    }

    // Clear cart after successful order
    clearCart();

    $transactionId = null;
    // Insert into payments table only if not COD
    if ($paymentMethod !== 'cod') {
        $transactionId = 'TRN-' . time() . '-' . strtoupper(bin2hex(random_bytes(4)));
        $paymentData = [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'amount' => $total,
            'currency' => 'INR',
            'payment_method' => $paymentMethod,
            'status' => 'completed', // Non-COD payments are completed
            'payment_gateway' => $paymentMethod, // Assuming payment method is also the gateway for now
            'created_at' => date('Y-m-d H:i:s')
        ];
        insert('payments', $paymentData);
    }

    // Send response
    $responseData = [
        'message' => 'Order placed successfully',
        'order_id' => $orderId,
        'order_number' => $orderData['order_id'],
        'total' => $total
    ];

    if ($transactionId) {
        $responseData['transaction_id'] = $transactionId;
    }

    sendSuccessResponse($responseData);

} catch (Exception $e) {
    logError('Checkout Error: ' . $e->getMessage());
    sendErrorResponse('An error occurred during checkout');
}
?>