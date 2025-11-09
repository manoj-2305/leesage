<?php
// Product reviews model functions

/**
 * Get reviews for a specific product with pagination
 * 
 * @param int $productId Product ID
 * @param int $limit Number of reviews per page
 * @param int $offset Offset for pagination
 * @return array Array of reviews
 */
function getProductReviews($productId, $limit = 10, $offset = 0) {
    try {
        $query = "SELECT r.*, u.first_name, u.last_name 
                FROM product_reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.product_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params = [$productId, $limit, $offset];
        $reviews = [];
        $rows = fetchAll($query, $params);
        
        foreach ($rows as $row) {
            // Mask user's last name for privacy
            if ($row['last_name']) {
                $row['last_name'] = substr($row['last_name'], 0, 1) . '.'; 
            }
            
            // Create user display name
            $row['user_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            
            // Remove sensitive fields
            unset($row['first_name']);
            unset($row['last_name']);
            
            $reviews[] = $row;
        }
    
        return $reviews;
    } catch (Exception $e) {
        logError('Error getting product reviews: ' . $e->getMessage());
        return [];
    }
}

/**
 * Count total number of approved reviews for a product
 * 
 * @param int $productId Product ID
 * @return int Total number of reviews
 */
function countProductReviews($productId) {
    try {
        $query = "SELECT COUNT(*) as count FROM product_reviews WHERE product_id = ? AND status = 'approved'";
        $row = fetchOne($query, [$productId]);
        
        return $row ? (int)$row['count'] : 0;
    } catch (Exception $e) {
        logError('Error counting product reviews: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get average rating for a product
 * 
 * @param int $productId Product ID
 * @return float Average rating
 */
function getProductAverageRating($productId) {
    try {
        $query = "SELECT AVG(rating) as average FROM product_reviews WHERE product_id = ? AND status = 'approved'";
        $row = fetchOne($query, [$productId]);
        
        return $row && $row['average'] ? round((float)$row['average'], 1) : 0;
    } catch (Exception $e) {
        logError('Error getting product average rating: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get rating distribution for a product (count of 1-5 star ratings)
 * 
 * @param int $productId Product ID
 * @return array Rating distribution
 */
function getProductRatingDistribution($productId) {
    try {
        $query = "SELECT rating, COUNT(*) as count 
                FROM product_reviews 
                WHERE product_id = ? AND status = 'approved' 
                GROUP BY rating 
                ORDER BY rating DESC";
        
        $rows = fetchAll($query, [$productId]);
        
        // Initialize distribution with zeros
        $distribution = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        ];
        
        // Fill in actual counts
        foreach ($rows as $row) {
            $distribution[$row['rating']] = (int)$row['count'];
        }
        
        return $distribution;
    } catch (Exception $e) {
        logError('Error getting product rating distribution: ' . $e->getMessage());
        return [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        ];
    }
}

/**
 * Get reviews by a specific user with pagination
 * 
 * @param int $userId User ID
 * @param int $limit Number of reviews per page
 * @param int $offset Offset for pagination
 * @return array Array of reviews
 */
function getUserReviews($userId, $limit = 10, $offset = 0) {
    try {
        $query = "SELECT r.*, p.name as product_name, p.image as product_image 
                FROM product_reviews r
                JOIN products p ON r.product_id = p.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params = [$userId, $limit, $offset];
        return fetchAll($query, $params);
    } catch (Exception $e) {
        logError('Error getting user reviews: ' . $e->getMessage());
        return [];
    }
}

/**
 * Count total number of reviews by a user
 * 
 * @param int $userId User ID
 * @return int Total number of reviews
 */
function countUserReviews($userId) {
    try {
        $query = "SELECT COUNT(*) as count FROM product_reviews WHERE user_id = ?";
        $row = fetchOne($query, [$userId]);
        
        return $row ? (int)$row['count'] : 0;
    } catch (Exception $e) {
        logError('Error counting user reviews: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Check if a user has already reviewed a product
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @return bool True if user has reviewed the product, false otherwise
 */
function hasUserReviewedProduct($userId, $productId) {
    try {
        $query = "SELECT COUNT(*) as count FROM product_reviews WHERE user_id = ? AND product_id = ?";
        $row = fetchOne($query, [$userId, $productId]);
        
        return $row && (int)$row['count'] > 0;
    } catch (Exception $e) {
        logError('Error checking if user reviewed product: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if a user has purchased a product (for review validation)
 * 
 * @param int $userId User ID
 * @param int $productId Product ID
 * @return bool True if user has purchased the product, false otherwise
 */
function hasUserPurchasedProduct($userId, $productId) {
    try {
        $query = "SELECT COUNT(*) as count 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.user_id = ? AND oi.product_id = ? AND o.status != 'cancelled'";
        
        $row = fetchOne($query, [$userId, $productId]);
        
        return $row && (int)$row['count'] > 0;
    } catch (Exception $e) {
        logError('Error checking if user purchased product: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add a new product review
 * 
 * @param array $reviewData Review data
 * @return int|false The new review ID or false on failure
 */
function addReview($reviewData) {
    try {
        $data = [
            'user_id' => $reviewData['user_id'],
            'product_id' => $reviewData['product_id'],
            'rating' => $reviewData['rating'],
            'title' => $reviewData['title'],
            'content' => $reviewData['content'],
            'status' => $reviewData['status'],
            'created_at' => $reviewData['created_at'],
            'updated_at' => $reviewData['updated_at']
        ];
        
        return insert('product_reviews', $data);
    } catch (Exception $e) {
        logError('Error adding review: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get a specific review by ID
 * 
 * @param int $reviewId Review ID
 * @return array|null Review data or null if not found
 */
function getReviewById($reviewId) {
    try {
        $query = "SELECT * FROM product_reviews WHERE id = ?";
        return fetchOne($query, [$reviewId]);
    } catch (Exception $e) {
        logError('Error getting review by ID: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update an existing review
 * 
 * @param int $reviewId Review ID
 * @param array $updateData Data to update
 * @return bool Success or failure
 */
function updateReview($reviewId, $updateData) {
    try {
        // Add the ID to the update data for the where clause
        $where = ['id' => $reviewId];
        
        return update('product_reviews', $updateData, $where);
    } catch (Exception $e) {
        logError('Error updating review: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a review
 * 
 * @param int $reviewId Review ID
 * @return bool Success or failure
 */
function deleteReview($reviewId) {
    try {
        $where = ['id' => $reviewId];
        return delete('product_reviews', $where);
    } catch (Exception $e) {
        logError('Error deleting review: ' . $e->getMessage());
        return false;
    }
}
?>