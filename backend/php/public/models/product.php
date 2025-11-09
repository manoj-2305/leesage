<?php
// Product model for handling product-related database operations

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';

/**
 * Count all active products
 * @return int Total number of active products
 */
function countAllProducts() {
    $query = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
    $result = fetchOne($query);
    return $result ? (int)$result['total'] : 0;
}

/**
 * Count products in a category
 * @param int $categoryId Category ID
 * @return int Number of products in the category
 */
function countProductsByCategory($categoryId) {
    $query = "SELECT COUNT(*) as total 
              FROM products p 
              JOIN product_categories pc ON p.id = pc.product_id 
              WHERE pc.category_id = :category_id AND p.is_active = 1";
    $result = fetchOne($query, ['category_id' => $categoryId]);
    return $result ? (int)$result['total'] : 0;
}

/**
 * Count search results
 * @param string $searchTerm Search term
 * @return int Number of matching products
 */
function countSearchResults($searchTerm) {
    $searchTerm = "%$searchTerm%";
    $query = "SELECT COUNT(*) as total 
              FROM products 
              WHERE is_active = 1 AND (name LIKE :search_term OR description LIKE :search_term)";
    $result = fetchOne($query, ['search_term' => $searchTerm]);
    return $result ? (int)$result['total'] : 0;
}

/**
 * Get all active products
 * @param int $limit Maximum number of products to return
 * @param int $offset Offset for pagination
 * @param string $sort Sort order (newest, price_asc, price_desc, name_asc, name_desc)
 * @return array Products
 */
function getAllProducts($limit = null, $offset = 0, $sort = 'newest') {
    $query = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
             FROM products p 
             WHERE p.is_active = 1";
    
    // Add sorting based on the sort parameter
    switch ($sort) {
        case 'price_asc':
            $query .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY p.price DESC";
            break;
        case 'name_asc':
            $query .= " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $query .= " ORDER BY p.name DESC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY p.created_at DESC";
            break;
    }
    
    if ($limit !== null) {
        $query .= " LIMIT :limit OFFSET :offset";
        return fetchAll($query, ['limit' => $limit, 'offset' => $offset]);
    }
    
    return fetchAll($query);
}

/**
 * Get featured products
 * @param int $limit Maximum number of products to return
 * @return array Featured products
 */
function getFeaturedProducts($limit = 8) {
    $query = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
             FROM products p 
             WHERE p.is_active = 1 AND p.is_featured = 1 
             ORDER BY p.created_at DESC 
             LIMIT :limit";
    
    return fetchAll($query, ['limit' => $limit]);
}

/**
 * Get a product by ID
 * @param int $productId Product ID
 * @return array|null Product details or null if not found
 */
function getProductById($productId) {
    $query = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as image 
             FROM products p WHERE p.id = :id AND p.is_active = 1";
    $product = fetchOne($query, ['id' => $productId]);
    
    if ($product) {
        // Ensure price and discount_price are set, even if null in DB
        $product['price'] = $product['price'] ?? 0.00;
        $product['discount_price'] = $product['discount_price'] ?? null;

        // Get product images
        $imagesQuery = "SELECT * FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, display_order ASC";
        $product['images'] = fetchAll($imagesQuery, ['product_id' => $productId]);
        
        // Get product categories
        $categoriesQuery = "SELECT c.* FROM categories c 
                           JOIN product_categories pc ON c.id = pc.category_id 
                           WHERE pc.product_id = :product_id AND c.is_active = 1";
        $product['categories'] = fetchAll($categoriesQuery, ['product_id' => $productId]);

        // Get product sizes
        $sizesQuery = "SELECT id, size_name, stock_quantity FROM product_sizes WHERE product_id = :product_id AND is_active = 1 ORDER BY id ASC";
        $product['sizes'] = fetchAll($sizesQuery, ['product_id' => $productId]);
    }
    
    return $product;
}

/**
 * Get products by category
 * @param int $categoryId Category ID
 * @param int $limit Maximum number of products to return
 * @param int $offset Offset for pagination
 * @param string $sort Sort order (newest, price_asc, price_desc, name_asc, name_desc)
 * @return array Products in the category
 */
function getProductsByCategory($categoryId, $limit = null, $offset = 0, $sort = 'newest') {
    $query = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
             FROM products p 
             JOIN product_categories pc ON p.id = pc.product_id 
             WHERE pc.category_id = :category_id AND p.is_active = 1";
    
    // Add sorting based on the sort parameter
    switch ($sort) {
        case 'price_asc':
            $query .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY p.price DESC";
            break;
        case 'name_asc':
            $query .= " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $query .= " ORDER BY p.name DESC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY p.created_at DESC";
            break;
    }
    
    if ($limit !== null) {
        $query .= " LIMIT :limit OFFSET :offset";
        return fetchAll($query, [
            'category_id' => $categoryId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    return fetchAll($query, ['category_id' => $categoryId]);
}

/**
 * Search products
 * @param string $searchTerm Search term
 * @param int $limit Maximum number of products to return
 * @param int $offset Offset for pagination
 * @param string $sort Sort order (newest, price_asc, price_desc, name_asc, name_desc)
 * @return array Matching products
 */
function searchProducts($searchTerm, $limit = null, $offset = 0, $sort = 'newest') {
    $searchTerm = "%$searchTerm%";
    
    $query = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
             FROM products p 
             WHERE p.is_active = 1 AND (p.name LIKE :search_term OR p.description LIKE :search_term)";
    
    // Add sorting based on the sort parameter
    switch ($sort) {
        case 'price_asc':
            $query .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY p.price DESC";
            break;
        case 'name_asc':
            $query .= " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $query .= " ORDER BY p.name DESC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY p.created_at DESC";
            break;
    }
    
    if ($limit !== null) {
        $query .= " LIMIT :limit OFFSET :offset";
        return fetchAll($query, [
            'search_term' => $searchTerm,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    return fetchAll($query, ['search_term' => $searchTerm]);
}

/**
 * Get all categories
 * @param bool $activeOnly Only return active categories
 * @return array Categories
 */
function getAllCategories($activeOnly = true) {
    $query = "SELECT * FROM categories";
    
    if ($activeOnly) {
        $query .= " WHERE is_active = 1";
    }
    
    $query .= " ORDER BY name ASC";
    
    return fetchAll($query);
}

/**
 * Get a category by ID
 * @param int $categoryId Category ID
 * @return array|null Category details or null if not found
 */
function getCategoryById($categoryId) {
    $query = "SELECT * FROM categories WHERE id = :id";
    return fetchOne($query, ['id' => $categoryId]);
}

/**
 * Check if a product is in stock
 * @param int $productId Product ID
 * @param int $quantity Quantity to check
 * @param int|null $sizeId Optional size ID to check stock for
 * @return bool True if in stock, false otherwise
 */
function isProductInStock($productId, $quantity = 1, $sizeId = null) {
    $product = getProductById($productId);
    
    if (!$product) {
        return false; // Product not found
    }

    if ($sizeId !== null) {
        // Check stock for a specific size
        foreach ($product['sizes'] as $size) {
            if ($size['id'] == $sizeId) {
                return $size['stock_quantity'] >= $quantity;
            }
        }
        return false; // Size not found for this product
    } else {
        // Check overall product stock if no size is specified or product has no sizes
        return $product['stock_quantity'] >= $quantity;
    }
}

/**
 * Get product reviews
 * @param int $productId Product ID
 * @param bool $approvedOnly Only return approved reviews
 * @return array Reviews
 */
function getProductReviews($productId, $approvedOnly = true) {
    $query = "SELECT r.*, u.first_name, u.last_name 
             FROM reviews r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.product_id = :product_id";
    
    if ($approvedOnly) {
        $query .= " AND r.is_approved = 1";
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    return fetchAll($query, ['product_id' => $productId]);
}

/**
 * Add a product review
 * @param int $productId Product ID
 * @param int $userId User ID
 * @param int $rating Rating (1-5)
 * @param string $reviewText Review text
 * @return int|string Review ID
 */
function addProductReview($productId, $userId, $rating, $reviewText) {
    $data = [
        'product_id' => $productId,
        'user_id' => $userId,
        'rating' => $rating,
        'review_text' => $reviewText,
        'is_approved' => 0 // Require approval by default
    ];
    
    return insert('reviews', $data);
}

/**
 * Get related products
 * @param int $productId Product ID
 * @param int $limit Maximum number of products to return
 * @return array Related products
 */
function getRelatedProducts($productId, $limit = 4) {
    // Get categories for the product
    $categoriesQuery = "SELECT category_id FROM product_categories WHERE product_id = :product_id";
    $categories = fetchAll($categoriesQuery, ['product_id' => $productId]);
    
    if (empty($categories)) {
        return [];
    }
    
    $categoryIds = array_column($categories, 'category_id');
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    
    // Get products in the same categories
    $query = "SELECT DISTINCT p.*, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image 
             FROM products p 
             JOIN product_categories pc ON p.id = pc.product_id 
             WHERE p.id != ? AND p.is_active = 1 AND pc.category_id IN ($placeholders) 
             ORDER BY RAND() 
             LIMIT ?";
    
    $params = array_merge([$productId], $categoryIds, [$limit]);
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError('Error getting related products: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get size guide data for a product
 * @param int $productId Product ID
 * @return array|null Size guide data or null if not available
 */
function getProductSizeGuide($productId) {
    try {
        // First check if product exists and get its category
        $product = getProductById($productId);
        
        if (!$product) {
            return null;
        }
        
        $categoryId = $product['category_id'];
        
        // Query the size_guides table
        $query = "SELECT * FROM size_guides WHERE category_id = :category_id LIMIT 1";
        $sizeGuide = fetchOne($query, ['category_id' => $categoryId]);
        
        if (!$sizeGuide) {
            // If no specific size guide for this category, get the default one
            $query = "SELECT * FROM size_guides WHERE is_default = 1 LIMIT 1";
            $sizeGuide = fetchOne($query, []);
            
            if (!$sizeGuide) {
                return null;
            }
        }
        
        // Get the size guide details
        $query = "SELECT * FROM size_guide_details WHERE size_guide_id = :size_guide_id ORDER BY display_order";
        $details = fetchAll($query, ['size_guide_id' => $sizeGuide['id']]);
        
        // Format the response
        return [
            'title' => $sizeGuide['title'],
            'description' => $sizeGuide['description'],
            'details' => $details
        ];
    } catch (PDOException $e) {
        logError('Error getting size guide: ' . $e->getMessage());
        return null;
    }
}
?>