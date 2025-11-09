<?php
// User notifications API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/notification.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require authentication for all notification operations
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
        // Get user notifications
        try {
            // Get pagination parameters
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
            $offset = ($page - 1) * $limit;
            
            // Get filter parameters
            $readStatus = isset($_GET['read_status']) ? $_GET['read_status'] : null; // 'read', 'unread', or null for all
            
            // Get notifications
            $notifications = getUserNotifications($userId, $readStatus, $limit, $offset);
            $totalNotifications = countUserNotifications($userId, $readStatus);
            $unreadCount = countUserNotifications($userId, 'unread');
            
            // Calculate pagination info
            $totalPages = ceil($totalNotifications / $limit);
            
            sendSuccessResponse([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'limit' => $limit,
                    'total_items' => $totalNotifications
                ]
            ]);
        } catch (Exception $e) {
            logError('Notifications Get Error: ' . $e->getMessage());
            sendErrorResponse('Failed to retrieve notifications');
        }
        break;
        
    case 'PUT':
        // Mark notification(s) as read
        try {
            // Get input data
            $data = getInputData();
            
            // Check if marking all as read or specific notification
            if (isset($data['mark_all_read']) && $data['mark_all_read'] === true) {
                // Mark all notifications as read
                $success = markAllNotificationsAsRead($userId);
                
                if ($success) {
                    sendSuccessResponse([
                        'message' => 'All notifications marked as read',
                        'unread_count' => 0
                    ]);
                } else {
                    sendErrorResponse('Failed to mark notifications as read');
                }
            } else if (isset($data['notification_id']) && !empty($data['notification_id'])) {
                // Mark specific notification as read
                $notificationId = (int)$data['notification_id'];
                
                // Check if notification exists and belongs to user
                $notification = getNotificationById($notificationId);
                if (!$notification || $notification['user_id'] != $userId) {
                    sendErrorResponse('Notification not found', 404);
                    exit;
                }
                
                // Mark as read
                $success = markNotificationAsRead($notificationId);
                
                if ($success) {
                    // Get updated unread count
                    $unreadCount = countUserNotifications($userId, 'unread');
                    
                    sendSuccessResponse([
                        'message' => 'Notification marked as read',
                        'unread_count' => $unreadCount
                    ]);
                } else {
                    sendErrorResponse('Failed to mark notification as read');
                }
            } else {
                sendErrorResponse('Notification ID or mark_all_read flag is required', 400);
            }
        } catch (Exception $e) {
            logError('Notification Update Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while updating notification');
        }
        break;
        
    case 'DELETE':
        // Delete notification(s)
        try {
            // Get input data
            $data = getInputData();
            
            // Check if deleting all or specific notification
            if (isset($data['delete_all']) && $data['delete_all'] === true) {
                // Delete all notifications
                $success = deleteAllUserNotifications($userId);
                
                if ($success) {
                    sendSuccessResponse([
                        'message' => 'All notifications deleted'
                    ]);
                } else {
                    sendErrorResponse('Failed to delete notifications');
                }
            } else if (isset($data['notification_id']) && !empty($data['notification_id'])) {
                // Delete specific notification
                $notificationId = (int)$data['notification_id'];
                
                // Check if notification exists and belongs to user
                $notification = getNotificationById($notificationId);
                if (!$notification || $notification['user_id'] != $userId) {
                    sendErrorResponse('Notification not found', 404);
                    exit;
                }
                
                // Delete notification
                $success = deleteNotification($notificationId);
                
                if ($success) {
                    sendSuccessResponse([
                        'message' => 'Notification deleted successfully'
                    ]);
                } else {
                    sendErrorResponse('Failed to delete notification');
                }
            } else {
                sendErrorResponse('Notification ID or delete_all flag is required', 400);
            }
        } catch (Exception $e) {
            logError('Notification Delete Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while deleting notification');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>