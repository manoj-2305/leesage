<?php
// User wishlist API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../models/wishlist.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require authentication for all wishlist operations
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Get current user ID
$userId = getCurrentUserId();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get user wishlist
        try {
            // Get wishlist items with product details
            $wishlistItems = getWishlistWithProducts($userId);
            
            sendSuccessResponse([
                'items' => $wishlistItems,
                'count' => count($wishlistItems)
            ]);
        } catch (Exception $e) {
            logError('Wishlist Get Error: ' . $e->getMessage());
            sendErrorResponse('Failed to retrieve wishlist');
        }
        break;
        
    case 'POST':
        // Add product to wishlist
        try {
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            if (!isset($data['product_id']) || empty($data['product_id'])) {
                sendErrorResponse('Product ID is required', 400);
                exit;
            }
            
            $productId = (int)$data['product_id'];
            
            // Check if product exists
            $product = getProductById($productId);
            if (!$product) {
                sendErrorResponse('Product not found', 404);
                exit;
            }
            
            // Check if product is already in wishlist
            if (isProductInWishlist($userId, $productId)) {
                sendSuccessResponse([
                    'message' => 'Product is already in wishlist',
                    'in_wishlist' => true
                ]);
                exit;
            }
            
            // Add to wishlist
            $success = addToWishlist($userId, $productId);
            
            if ($success) {
                sendSuccessResponse([
                    'message' => 'Product added to wishlist',
                    'in_wishlist' => true
                ]);
            } else {
                sendErrorResponse('Failed to add product to wishlist');
            }
        } catch (Exception $e) {
            logError('Wishlist Add Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while adding to wishlist');
        }
        break;
        
    case 'DELETE':
        // Remove product from wishlist
        try {
            // Get input data
            $data = getInputData();
            
            // Check if we're removing a specific product or clearing the entire wishlist
            if (isset($data['product_id']) && !empty($data['product_id'])) {
                // Remove specific product
                $productId = (int)$data['product_id'];
                
                // Check if product is in wishlist
                if (!isProductInWishlist($userId, $productId)) {
                    sendErrorResponse('Product not found in wishlist', 404);
                    exit;
                }
                
                // Remove from wishlist
                $success = removeFromWishlist($userId, $productId);
                
                if ($success) {
                    sendSuccessResponse([
                        'message' => 'Product removed from wishlist',
                        'in_wishlist' => false
                    ]);
                } else {
                    sendErrorResponse('Failed to remove product from wishlist');
                }
            } else if (isset($data['clear']) && $data['clear'] === true) {
                // Clear entire wishlist
                $success = clearWishlist($userId);
                
                if ($success) {
                    sendSuccessResponse([
                        'message' => 'Wishlist cleared successfully'
                    ]);
                } else {
                    sendErrorResponse('Failed to clear wishlist');
                }
            } else {
                sendErrorResponse('Product ID or clear flag is required', 400);
            }
        } catch (Exception $e) {
            logError('Wishlist Remove Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while removing from wishlist');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>