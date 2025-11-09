<?php
/**
 * Get cart and items from the database for a user
 * @param int $userId
 * @return array
 */
function getCartFromDb($userId) {
    $pdo = getConnection();
    $cart = [
        'items' => [],
        'total_items' => 0,
        'subtotal' => 0,
        'updated_at' => null
    ];
    $cartId = null;
    $stmt = $pdo->prepare('SELECT id, updated_at FROM carts WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $cartId = $result['id'];
        $cart['updated_at'] = $result['updated_at'];
    } else {
        return $cart;
    }

    $stmt = $pdo->prepare('SELECT id, product_id, quantity, size_id FROM cart_items WHERE cart_id = :cart_id');
    $stmt->execute([':cart_id' => $cartId]);
    $subtotal = 0;
    $totalItems = 0;
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productId = $item['product_id'];
        $quantity = $item['quantity'];
        $sizeId = $item['size_id'];

        $product = getProductById($productId);
        if ($product) {
            $price = $product['discount_price'] ?? $product['price'];
            $itemTotal = $price * $quantity;
            
            // Find the selected size object
            $selectedSize = null;
            foreach ($product['sizes'] as $size) {
                if ($size['id'] == $sizeId) {
                    $selectedSize = $size;
                    break;
                }
            }

            $cart['items'][] = [
                'id' => $item['id'],
                'product_id' => $productId,
                'name' => $product['name'],
                'price' => $price,
                'quantity' => $quantity,
                'total' => $itemTotal,
                'image' => $product['image'] ?? null,
                'size_id' => $sizeId,
                'size_name' => $selectedSize ? $selectedSize['size_name'] : 'N/A',
                'stock_quantity' => $selectedSize ? $selectedSize['stock_quantity'] : 0,
                'product_details' => $product // Add the full product details here
            ];
            $subtotal += $itemTotal;
            $totalItems += $quantity;
        }
    }
    $cart['subtotal'] = $subtotal;
    $cart['total_items'] = $totalItems;
    return $cart;
}
// Cart model for handling shopping cart operations

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/product.php';

/**
 * Initialize a cart in the session
 * @return array Empty cart
 */
function initializeCart() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [
            'items' => [],
            'total_items' => 0,
            'subtotal' => 0,
            'updated_at' => time()
        ];
    }
    
    return $_SESSION['cart'];
}

/**
 * Get the current cart
 * @return array Cart data
 */
function getCart() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        return getCartFromDb($_SESSION['user_id']);
    } else {
        initializeCart();
        return $_SESSION['cart'];
    }
}

/**
 * Add an item to the cart
 * @param int $productId Product ID
 * @param int $quantity Quantity to add
 * @return array Updated cart
 */
function addToCart($productId, $quantity = 1, $size = null) {
    // Use DB for logged-in users
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $product = getProductById($productId);
    if (!$product) {
        return ['error' => 'Product not found'];
    }

    // Check if product is in stock
    if (!isProductInStock($productId, $quantity, $size)) {
        return ['error' => 'Product is out of stock or size not available'];
    }

    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $pdo = getConnection();
        // Find or create cart for user
        $cart = fetchOne('SELECT id FROM carts WHERE user_id = :user_id', [':user_id' => $userId]);
        if ($cart) {
            $cartId = $cart['id'];
        } else {
            $cartId = insert('carts', ['user_id' => $userId]);
        }
        // Check if item already in cart (by product_id and size_id)
        $item = fetchOne('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND size_id = :size_id', [':cart_id' => $cartId, ':product_id' => $productId, ':size_id' => $size]);
        if ($item) {
            $newQty = $item['quantity'] + $quantity;
            update('cart_items', ['quantity' => $newQty], 'id = :id', [':id' => $item['id']]);
        } else {
            insert('cart_items', [
                'cart_id' => $cartId,
                'product_id' => $productId,
                'size_id' => $size,
                'quantity' => $quantity,
                'added_at' => date('Y-m-d H:i:s')
            ]);
        }
        // Optionally, update cart updated_at
        update('carts', ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $cartId]);
        return getCartFromDb($userId);
    } else {
        // Session fallback for guests
        initializeCart();
        $cartItems = &$_SESSION['cart']['items'];

        $itemFound = false;
        foreach ($cartItems as &$item) {
            if ($item['product_id'] == $productId && $item['size_id'] == $size) {
                $item['quantity'] += $quantity;
                $item['total'] = $item['quantity'] * $item['price'];
                $itemFound = true;
                break;
            }
        }

        if (!$itemFound) {
            $price = $product['discount_price'] ?? $product['price'];
            // Find the selected size object
            $selectedSize = null;
            foreach ($product['sizes'] as $s) {
                if ($s['id'] == $size) {
                    $selectedSize = $s;
                    break;
                }
            }

            $cartItems[] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'price' => $price,
                'quantity' => $quantity,
                'total' => $quantity * $price,
                'image' => $product['image'] ?? null,
                'size_id' => $size,
                'size_name' => $selectedSize ? $selectedSize['name'] : 'N/A',
                'stock_quantity' => $selectedSize ? $selectedSize['stock_quantity'] : 0
            ];
        }
        updateCartTotals();
        return $_SESSION['cart'];
    }
}

/**
 * Update cart item quantity
 * @param int $itemId Cart Item ID
 * @param int $quantity New quantity
 * @param int $size Size ID (optional)
 * @return array Updated cart
 */
function updateCartItem($itemId, $quantity, $size = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        return ['error' => 'User not logged in'];
    }
    
    // If quantity is 0 or negative, remove item
    if ($quantity <= 0) {
        return removeFromCart($itemId, $size);
    }
    
    $userId = $_SESSION['user_id'];
    $pdo = getConnection();
    
    error_log("updateCartItem: itemId=" . $itemId . ", userId=" . $userId . ", quantity=" . $quantity);
    // Get cart item details to check product and stock, and ensure it belongs to the user's cart
    $item = fetchOne('SELECT ci.product_id, ci.size_id FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE ci.id = :item_id AND c.user_id = :user_id', [':item_id' => $itemId, ':user_id' => $userId]);
    error_log("updateCartItem: fetchOne result=" . print_r($item, true));
    if (!$item) {
        return ['error' => 'Item not found in cart or does not belong to user'];
    }
    
    $productId = $item['product_id'];
    $itemSizeId = $item['size_id'];
    
    // Check if product is in stock
    if (!isProductInStock($productId, $quantity, $itemSizeId)) {
        return ['error' => 'Not enough stock available'];
    }
    
    // Update item quantity
    $stmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :item_id');
    $stmt->execute([
        ':quantity' => $quantity,
        ':item_id' => $itemId
    ]);
    $affected = $stmt->rowCount();

    if ($affected === 0) {
        return ['error' => 'Failed to update item quantity'];
    }
    
    // Update cart updated_at
    $cart = fetchOne('SELECT cart_id FROM cart_items WHERE id = :item_id', [':item_id' => $itemId]);
    if ($cart) {
        $stmt = $pdo->prepare('UPDATE carts SET updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $cart['cart_id']]);
    }

    return getCartFromDb($userId);
}

/**
 * Remove an item from the cart
 * @param int $itemId Cart Item ID
 * @param int $size Size ID (optional, kept for backward compatibility)
 * @return array Updated cart
 */
function removeFromCart($itemId, $size = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        return ['error' => 'User not logged in'];
    }
    
    $userId = $_SESSION['user_id'];
    $pdo = getConnection();
    
    error_log("removeFromCart: itemId=" . $itemId . ", userId=" . $userId);
    // Get cart item details to find cart_id and ensure it belongs to the user's cart
    $item = fetchOne('SELECT ci.cart_id FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE ci.id = :item_id AND c.user_id = :user_id', [':item_id' => $itemId, ':user_id' => $userId]);
    error_log("removeFromCart: fetchOne result=" . print_r($item, true));
    if (!$item) {
        return ['error' => 'Item not found in cart or does not belong to user'];
    }
    
    $cartId = $item['cart_id'];
    
    // Remove item
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :item_id');
    $stmt->execute([':item_id' => $itemId]);
    
    // Update cart updated_at
    $stmt = $pdo->prepare('UPDATE carts SET updated_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $cartId]);
    
    return getCartFromDb($userId);
}

/**
 * Clear the cart
 * @return array Empty cart
 */
function clearCart() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        return ['error' => 'User not logged in'];
    }
    $userId = $_SESSION['user_id'];
    $pdo = getConnection();
    // Find cart
    $cart = fetchOne('SELECT id FROM carts WHERE user_id = :user_id', [':user_id' => $userId]);
    if (!$cart) {
        return ['error' => 'Cart not found'];
    }
    $cartId = $cart['id'];
    // Delete all items
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
    $stmt->execute([':cart_id' => $cartId]);
    // Optionally, update cart updated_at
    $stmt = $pdo->prepare('UPDATE carts SET updated_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $cartId]);
    return getCartFromDb($userId);
}

/**
 * Update cart totals
 */
function updateCartTotals() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $pdo = getConnection();
        $cart = fetchOne('SELECT id FROM carts WHERE user_id = :user_id', [':user_id' => $userId]);
        if ($cart) {
            $cartId = $cart['id'];
            $stmt = $pdo->prepare('UPDATE carts SET updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $cartId]);
        }
    } else {
        // Session fallback for guests
        $items = &$_SESSION['cart']['items'];
        $totalItems = 0;
        $subtotal = 0;
        foreach ($items as $item) {
            $totalItems += $item['quantity'];
            $subtotal += $item['total'];
        }
        $_SESSION['cart']['total_items'] = $totalItems;
        $_SESSION['cart']['subtotal'] = $subtotal;
        $_SESSION['cart']['updated_at'] = time();
    }
}

/**
 * Calculate cart totals with shipping and tax
 * @param float $shippingCost Shipping cost
 * @param float $taxRate Tax rate (0.1 for 10%)
 * @return array Cart totals
 */
function calculateCartTotals($shippingCost = null, $taxRate = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        $cart = getCartFromDb($_SESSION['user_id']);
    } else {
        initializeCart();
        $cart = $_SESSION['cart'];
    }
    $subtotal = $cart['subtotal'];
    $shippingCost = $shippingCost ?? DEFAULT_SHIPPING_COST;
    $taxRate = $taxRate ?? DEFAULT_TAX_RATE;
    if ($subtotal >= FREE_SHIPPING_THRESHOLD) {
        $shippingCost = 0;
    }
    $taxAmount = $subtotal * $taxRate;
    $total = $subtotal + $taxAmount + $shippingCost;
    return [
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'shipping_cost' => $shippingCost,
        'total' => $total
    ];
}

/**
 * Validate cart items against current stock
 * @return array Validation results
 */
function validateCart() {
    // Initialize cart if not exists
    initializeCart();
    
    $items = $_SESSION['cart']['items'];
    $invalidItems = [];
    $valid = true;
    
    foreach ($items as $item) {
        $productId = $item['product_id'];
        $quantity = $item['quantity'];
        
        // Check if product exists and is in stock
        $product = getProductById($productId);
        
        if (!$product || !isProductInStock($productId, $quantity)) {
            $invalidItems[] = [
                'product_id' => $productId,
                'name' => $item['name'],
                'requested_quantity' => $quantity,
                'available_quantity' => $product ? $product['stock_quantity'] : 0
            ];
            $valid = false;
        }
    }
    
    return [
        'valid' => $valid,
        'invalid_items' => $invalidItems
    ];
}

?>