<?php
// User wishlist model functions

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';

/**
 * Get all wishlist items for a user
 * 
 * @param int $userId User ID
 * @return array Array of wishlist items
 */
function getWishlistItems($userId) {
    $query = "SELECT * FROM user_wishlist WHERE user_id = :user_id ORDER BY created_at DESC";
    return fetchAll($query, ['user_id' => $userId]);
}

/**
 * Get wishlist items with product details
 * 
 * @param int $userId User ID
 * @return array Array of wishlist items with product details
 */
function getWishlistWithProducts($userId) {
    $query = "SELECT w.id, w.user_id, w.product_id, w.created_at, 
                     p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.sku
              FROM user_wishlist w
              JOIN products p ON w.product_id = p.id
              WHERE w.user_id = :user_id
              ORDER BY w.created_at DESC";
    
    $items = fetchAll($query, ['user_id' => $userId]);
    
    // Process each item
    foreach ($items as &$row) {
        // Format prices as floats
        $row['price'] = (float)$row['price'];
        $row['sale_price'] = $row['sale_price'] !== null ? (float)$row['sale_price'] : null;
        
        // Calculate if product is on sale
        $row['on_sale'] = ($row['sale_price'] !== null && $row['sale_price'] < $row['price']);
        
        // Calculate if product is in stock
        $row['in_stock'] = ($row['stock_quantity'] > 0);
    }
    
    return $items;
}

/**
 * Check if a product is in the user's wishlist
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @return bool True if product is in wishlist, false otherwise
 */
function isProductInWishlist($userId, $productId) {
    $query = "SELECT COUNT(*) as count FROM user_wishlist WHERE user_id = :user_id AND product_id = :product_id";
    $result = fetchOne($query, ['user_id' => $userId, 'product_id' => $productId]);
    
    return $result && $result['count'] > 0;
}

/**
 * Add a product to the user's wishlist
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @return bool Success or failure
 */
function addToWishlist($userId, $productId) {
    try {
        $data = [
            'user_id' => $userId,
            'product_id' => $productId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        insert('user_wishlist', $data);
        return true;
    } catch (Exception $e) {
        logError('Add to wishlist error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remove a product from the user's wishlist
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @return bool Success or failure
 */
function removeFromWishlist($userId, $productId) {
    try {
        $whereClause = "user_id = :user_id AND product_id = :product_id";
        $params = ['user_id' => $userId, 'product_id' => $productId];
        
        delete('user_wishlist', $whereClause, $params);
        return true;
    } catch (Exception $e) {
        logError('Remove from wishlist error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clear all items from the user's wishlist
 * 
 * @param int $userId User ID
 * @return bool Success or failure
 */
function clearWishlist($userId) {
    try {
        $whereClause = "user_id = :user_id";
        $params = ['user_id' => $userId];
        
        delete('user_wishlist', $whereClause, $params);
        return true;
    } catch (Exception $e) {
        logError('Clear wishlist error: ' . $e->getMessage());
        return false;
    }
}
?>