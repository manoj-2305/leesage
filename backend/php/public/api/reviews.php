<?php
// User reviews API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../models/review.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get reviews for a product or by a user
        try {
            // Check if product ID is provided
            if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
                $productId = (int)$_GET['product_id'];
                
                // Check if product exists
                $product = getProductById($productId);
                if (!$product) {
                    sendErrorResponse('Product not found', 404);
                    exit;
                }
                
                // Get pagination parameters
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $offset = ($page - 1) * $limit;
                
                // Get reviews for product
                $reviews = getProductReviews($productId, $limit, $offset);
                $totalReviews = countProductReviews($productId);
                $averageRating = getProductAverageRating($productId);
                $ratingDistribution = getProductRatingDistribution($productId);
                
                // Calculate pagination info
                $totalPages = ceil($totalReviews / $limit);
                
                sendSuccessResponse([
                    'reviews' => $reviews,
                    'total_reviews' => $totalReviews,
                    'average_rating' => $averageRating,
                    'rating_distribution' => $ratingDistribution,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'limit' => $limit,
                        'total_items' => $totalReviews
                    ]
                ]);
            } 
            // Check if user ID is provided and user is authenticated
            else if (isset($_GET['user_reviews']) && isAuthenticated()) {
                $userId = getCurrentUserId();
                
                // Get pagination parameters
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $offset = ($page - 1) * $limit;
                
                // Get reviews by user
                $reviews = getUserReviews($userId, $limit, $offset);
                $totalReviews = countUserReviews($userId);
                
                // Calculate pagination info
                $totalPages = ceil($totalReviews / $limit);
                
                sendSuccessResponse([
                    'reviews' => $reviews,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'limit' => $limit,
                        'total_items' => $totalReviews
                    ]
                ]);
            } else {
                sendErrorResponse('Product ID or user_reviews parameter is required', 400);
            }
        } catch (Exception $e) {
            logError('Reviews Get Error: ' . $e->getMessage());
            sendErrorResponse('Failed to retrieve reviews');
        }
        break;
        
    case 'POST':
        // Add a new review
        try {
            // Require authentication for adding reviews
            if (!isAuthenticated()) {
                sendErrorResponse('Authentication required', 401);
                exit;
            }
            
            $userId = getCurrentUserId();
            
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            $requiredFields = ['product_id', 'rating', 'title', 'content'];
            if (!validateRequiredFields($data, $requiredFields)) {
                sendErrorResponse('Missing required fields', 400);
                exit;
            }
            
            $productId = (int)$data['product_id'];
            $rating = (int)$data['rating'];
            $title = sanitizeInput($data['title']);
            $content = sanitizeInput($data['content']);
            
            // Validate rating (1-5)
            if ($rating < 1 || $rating > 5) {
                sendErrorResponse('Rating must be between 1 and 5', 400);
                exit;
            }
            
            // Check if product exists
            $product = getProductById($productId);
            if (!$product) {
                sendErrorResponse('Product not found', 404);
                exit;
            }
            
            // Check if user has already reviewed this product
            if (hasUserReviewedProduct($userId, $productId)) {
                sendErrorResponse('You have already reviewed this product', 400);
                exit;
            }
            
            // Check if user has purchased the product (optional validation)
            // Uncomment if you want to enforce this rule
            /*
            if (!hasUserPurchasedProduct($userId, $productId)) {
                sendErrorResponse('You can only review products you have purchased', 400);
                exit;
            }
            */
            
            // Add review
            $reviewData = [
                'user_id' => $userId,
                'product_id' => $productId,
                'rating' => $rating,
                'title' => $title,
                'content' => $content,
                'status' => 'pending', // Reviews can be pending, approved, or rejected
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $reviewId = addReview($reviewData);
            
            if ($reviewId) {
                // Get the newly added review
                $review = getReviewById($reviewId);
                
                sendSuccessResponse([
                    'message' => 'Review submitted successfully and pending approval',
                    'review' => $review
                ]);
            } else {
                sendErrorResponse('Failed to submit review');
            }
        } catch (Exception $e) {
            logError('Review Add Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while submitting review');
        }
        break;
        
    case 'PUT':
        // Update an existing review
        try {
            // Require authentication for updating reviews
            if (!isAuthenticated()) {
                sendErrorResponse('Authentication required', 401);
                exit;
            }
            
            $userId = getCurrentUserId();
            
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            if (!isset($data['review_id']) || empty($data['review_id'])) {
                sendErrorResponse('Review ID is required', 400);
                exit;
            }
            
            $reviewId = (int)$data['review_id'];
            
            // Get the review
            $review = getReviewById($reviewId);
            
            // Check if review exists and belongs to the user
            if (!$review || $review['user_id'] != $userId) {
                sendErrorResponse('Review not found or you do not have permission to update it', 404);
                exit;
            }
            
            // Prepare update data
            $updateData = [];
            
            // Only update fields that are provided
            if (isset($data['rating'])) {
                $rating = (int)$data['rating'];
                if ($rating < 1 || $rating > 5) {
                    sendErrorResponse('Rating must be between 1 and 5', 400);
                    exit;
                }
                $updateData['rating'] = $rating;
            }
            
            if (isset($data['title'])) {
                $updateData['title'] = sanitizeInput($data['title']);
            }
            
            if (isset($data['content'])) {
                $updateData['content'] = sanitizeInput($data['content']);
            }
            
            // Set review back to pending when updated
            $updateData['status'] = 'pending';
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // Update review
            $success = updateReview($reviewId, $updateData);
            
            if ($success) {
                // Get the updated review
                $updatedReview = getReviewById($reviewId);
                
                sendSuccessResponse([
                    'message' => 'Review updated successfully and pending approval',
                    'review' => $updatedReview
                ]);
            } else {
                sendErrorResponse('Failed to update review');
            }
        } catch (Exception $e) {
            logError('Review Update Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while updating review');
        }
        break;
        
    case 'DELETE':
        // Delete a review
        try {
            // Require authentication for deleting reviews
            if (!isAuthenticated()) {
                sendErrorResponse('Authentication required', 401);
                exit;
            }
            
            $userId = getCurrentUserId();
            
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            if (!isset($data['review_id']) || empty($data['review_id'])) {
                sendErrorResponse('Review ID is required', 400);
                exit;
            }
            
            $reviewId = (int)$data['review_id'];
            
            // Get the review
            $review = getReviewById($reviewId);
            
            // Check if review exists and belongs to the user
            if (!$review || $review['user_id'] != $userId) {
                sendErrorResponse('Review not found or you do not have permission to delete it', 404);
                exit;
            }
            
            // Delete review
            $success = deleteReview($reviewId);
            
            if ($success) {
                sendSuccessResponse([
                    'message' => 'Review deleted successfully'
                ]);
            } else {
                sendErrorResponse('Failed to delete review');
            }
        } catch (Exception $e) {
            logError('Review Delete Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while deleting review');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>