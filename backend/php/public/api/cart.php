<?php
// Cart management API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/cart.php';
require_once __DIR__ . '/../models/product.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if it doesn't exist
initializeCart();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get cart contents
        try {
            $cart = getCart();
            $cartWithDetails = [];
            
            // Get product details for each cart item
            foreach ($cart['items'] as $item) {
                $product = getProductById($item['product_id']);
                if ($product) {
                    $selectedSizeId = $item['size_id'] ?? $item['size'] ?? null;
                    $selectedSize = null;
                    if ($selectedSizeId !== null && is_array($product['sizes'])) {
                        foreach ($product['sizes'] as $pSize) {
                            if ($pSize['id'] == $selectedSizeId) {
                                $selectedSize = $pSize;
                                break;
                            }
                        }
                    }

                    $cartWithDetails[] = [
                        'id' => $item['id'],
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'size' => $selectedSize['size_name'] ?? null,
                        'product' => [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'price' => (float)$product['price'],
                            'sale_price' => (float)$product['discount_price'],
                            'image' => $product['image'] ? $product['image'] : '/leesage/assets/images/placeholder.jpg',
                            'stock' => $selectedSize['stock_quantity'] ?? $product['stock_quantity']
                        ]
                    ];
                }
            }
            
            // Calculate cart totals
            $cartTotals = calculateCartTotals();
            
            sendSuccessResponse([
                'items' => $cartWithDetails,
                'item_count' => count($cartWithDetails),
                'subtotal' => $cartTotals['subtotal'],
                'tax' => $cartTotals['tax_amount'],
                'shipping' => $cartTotals['shipping_cost'],
                'total' => $cartTotals['total']
            ]);
        } catch (Exception $e) {
            logError('Cart Get Error: ' . $e->getMessage());
            sendErrorResponse('Failed to retrieve cart');
        }
        break;
        
    case 'POST':
        // Add item to cart
        try {
            // Get input data
            $data = getInputData();
            
            // Debug: log received data
            error_log('Cart POST data: ' . print_r($data, true));

            // Get optional size (handle both 'size' and 'selected_size' for backward compatibility)
            $size = null;
            if (isset($data['selected_size']) && !empty($data['selected_size'])) {
                $size = (int)sanitizeInput($data['selected_size']);
            } elseif (isset($data['size']) && !empty($data['size'])) {
                $size = (int)sanitizeInput($data['size']);
            }
            
            // Validate required fields
            $requiredFields = ['product_id', 'quantity'];
            $missingFields = validateRequiredFields($data, $requiredFields);
            if (!empty($missingFields)) {
                error_log('Missing required fields: ' . implode(', ', $missingFields));
                sendErrorResponse('Missing required fields: ' . implode(', ', $missingFields), 400);
                exit;
            }

            // Validate product ID
            $productId = (int)$data['product_id'];
            $product = getProductById($productId);
            if (!$product) {
                sendErrorResponse('Product not found', 404);
                exit;
            }
            
            // Validate quantity
            $quantity = (int)$data['quantity'];
            if ($quantity <= 0) {
                sendErrorResponse('Quantity must be greater than zero', 400);
                exit;
            }
            
            // Check stock availability for the selected size
            $selectedSizeId = $size; // $size already contains the selected_size_id
            $availableStock = 0;
            if ($selectedSizeId && isset($product['sizes'])) {
                foreach ($product['sizes'] as $productSize) {
                    if ($productSize['id'] == $selectedSizeId) {
                        $availableStock = $productSize['stock_quantity'];
                        break;
                    }
                }
            } else {
                // If no size is selected or product has no sizes, use main product stock
                $availableStock = $product['stock_quantity'];
            }

            if ($quantity > $availableStock) {
                sendErrorResponse('Not enough stock available for the selected size', 400);
                exit;
            }
            
            // Add to cart
            $result = addToCart($productId, $quantity, $size);
            
            if ($result && !isset($result['error'])) {
                // Get updated cart
                $cart = getCart();
                $cartTotals = calculateCartTotals();
                
                sendSuccessResponse([
                    'message' => 'Product added to cart',
                    'item_count' => count($cart['items']),
                    'subtotal' => $cartTotals['subtotal'],
                    'total' => $cartTotals['total']
                ]);
            } else {
                sendErrorResponse($result['error'] ?? 'Failed to add product to cart');
            }
        } catch (Exception $e) {
            logError('Cart Add Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while adding to cart');
        }
        break;
        
    case 'PUT':
        // Update cart item
        try {
            // Get input data
            $data = getInputData();

            // Validate required fields
            $requiredFields = ['item_id', 'quantity'];
            $missingFields = validateRequiredFields($data, $requiredFields);
            if (!empty($missingFields)) {
                sendErrorResponse('Missing required fields: ' . implode(', ', $missingFields), 400);
                exit;
            }

            // Get optional size
            $size = null;
            if (isset($data['size_id']) && !empty($data['size_id'])) {
                $size = (int)sanitizeInput($data['size_id']);
            }

            // Validate item ID
            $itemId = (int)$data['item_id'];

            // Validate quantity
            $quantity = (int)$data['quantity'];
            if ($quantity < 0) { // Quantity can be 0 to remove item
                sendErrorResponse('Quantity cannot be negative', 400);
                exit;
            }

            // Update cart item
            $result = updateCartItem($itemId, $quantity, $size);

            if ($result && !isset($result['error'])) {
                // Get updated cart
                $cart = getCart();
                $cartTotals = calculateCartTotals();

                sendSuccessResponse([
                    'message' => 'Cart updated successfully',
                    'item_count' => count($cart['items']),
                    'subtotal' => $cartTotals['subtotal'],
                    'total' => $cartTotals['total']
                ]);
            } else {
                sendErrorResponse($result['error'] ?? 'Failed to update cart');
            }
        } catch (Exception $e) {
            logError('Cart Update Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while updating cart');
        }
        break;
        
    case 'DELETE':
        // Remove item from cart
        try {
            // Get input data
            $data = getInputData();

            // Check if we're clearing the entire cart
            if (isset($data['clear_cart']) && $data['clear_cart'] === true) {
                clearCart();
                sendSuccessResponse([
                    'message' => 'Cart cleared successfully',
                    'item_count' => 0,
                    'subtotal' => 0,
                    'total' => 0
                ]);
                exit;
            }

            // Validate required fields for item removal
            if (!isset($data['item_id']) || empty($data['item_id'])) {
                sendErrorResponse('Item ID is required', 400);
                exit;
            }

            $itemId = (int)$data['item_id'];
            $size = null;
            if (isset($data['size_id']) && !empty($data['size_id'])) {
                $size = (int)sanitizeInput($data['size_id']);
            }

            // Remove from cart
            $result = removeFromCart($itemId, $size);

            if ($result && !isset($result['error'])) {
                // Get updated cart
                $cart = getCart();
                $cartTotals = calculateCartTotals();

                sendSuccessResponse([
                    'message' => 'Item removed from cart',
                    'item_count' => count($cart['items']),
                    'subtotal' => $cartTotals['subtotal'],
                    'total' => $cartTotals['total']
                ]);
            } else {
                sendErrorResponse($result['error'] ?? 'Failed to remove item from cart');
            }
        } catch (Exception $e) {
            logError('Cart Remove Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while removing from cart');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>