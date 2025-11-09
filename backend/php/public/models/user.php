<?php
// User model for handling user-related database operations

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';

// Set timezone to Kolkata (Asia/Kolkata)
date_default_timezone_set('Asia/Kolkata');

/**
 * Get a user by ID
 * @param int $userId User ID
 * @return array|null User details or null if not found
 */
function getUserById($userId) {
    $query = "SELECT id, first_name, last_name, email, profile_image, created_at, updated_at, last_login 
             FROM users 
             WHERE id = :id AND is_active = 1";
    
    return fetchOne($query, ['id' => $userId]);
}

/**
 * Get a user by email
 * @param string $email User email
 * @return array|null User details or null if not found
 */
function getUserByEmail($email) {
    $query = "SELECT * FROM users WHERE email = :email";
    return fetchOne($query, ['email' => $email]);
}

/**
 * Create a new user
 * @param string $firstName First name
 * @param string $lastName Last name
 * @param string $email Email
 * @param string $password Plain text password
 * @return int|string User ID
 */
function createUser($firstName, $lastName, $email, $password) {
    $passwordHash = password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
    
    $data = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => $passwordHash,
        'is_active' => 1
    ];
    
    return insert('users', $data);
}

/**
 * Update user profile
 * @param int $userId User ID
 * @param array $data User data to update
 * @return int Number of affected rows
 */
function updateUserProfile($userId, $data) {
    $allowedFields = ['first_name', 'last_name', 'profile_image'];
    $updateData = array_intersect_key($data, array_flip($allowedFields));
    
    return update('users', $updateData, 'id = :id', ['id' => $userId]);
}

/**
 * Change user password
 * @param int $userId User ID
 * @param string $newPassword New password
 * @return int Number of affected rows
 */
function changeUserPassword($userId, $newPassword) {
    $passwordHash = password_hash($newPassword, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
    
    return update('users', ['password_hash' => $passwordHash], 'id = :id', ['id' => $userId]);
}

/**
 * Verify user password
 * @param string $password Plain text password
 * @param string $hash Password hash from database
 * @return bool True if password is correct, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Create a new session for a user
 * @param int $userId User ID
 * @param string $ipAddress IP address
 * @param string $userAgent User agent
 * @return string Session token
 */
function createUserSession($userId, $ipAddress = null, $userAgent = null) {
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $data = [
        'user_id' => $userId,
        'session_token' => $token,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'expires_at' => $expiresAt
    ];
    
    insert('user_sessions', $data);
    
    return $token;
}

/**
 * Validate a session token
 * @param int $userId User ID
 * @param string $token Session token
 * @return bool True if valid, false otherwise
 */
function validateSessionToken($userId, $token) {
    $query = "SELECT COUNT(*) FROM user_sessions 
             WHERE user_id = :user_id AND session_token = :token AND expires_at > NOW()";
    
    $params = [
        'user_id' => $userId,
        'token' => $token
    ];
    
    $stmt = executeQuery($query, $params);
    return $stmt->fetchColumn() > 0;
}

/**
 * Delete a user session
 * @param string $token Session token
 * @return int Number of affected rows
 */
function deleteUserSession($token) {
    return delete('user_sessions', 'session_token = :token', ['token' => $token]);
}

/**
 * Delete all sessions for a user
 * @param int $userId User ID
 * @return int Number of affected rows
 */
function deleteAllUserSessions($userId) {
    return delete('user_sessions', 'user_id = :user_id', ['user_id' => $userId]);
}

/**
 * Update user's last login time
 * @param int $userId User ID
 * @return int Number of affected rows
 */
function updateLastLogin($userId) {
    return update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
}

/**
 * Get user's orders with pagination
 * @param int $userId User ID
 * @param int $limit Number of orders per page
 * @param int $offset Offset for pagination
 * @return array Orders
 */
function getUserOrders($userId, $limit = 10, $offset = 0) {
    $query = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    return fetchAll($query, ['user_id' => $userId, 'limit' => $limit, 'offset' => $offset]);
}

/**
 * Get count of user's orders
 * @param int $userId User ID
 * @return int Count of orders
 */
function getUserOrdersCount($userId) {
    $query = "SELECT COUNT(*) as count FROM orders WHERE user_id = :user_id";
    $result = fetchOne($query, ['user_id' => $userId]);
    return (int)$result['count'];
}

/**
 * Get user's reviews
 * @param int $userId User ID
 * @return array Reviews
 */
function getUserReviews($userId) {
    $query = "SELECT r.*, p.name as product_name, 
                    (SELECT pi.image_path FROM product_images pi 
                     WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as product_image 
             FROM reviews r 
             JOIN products p ON r.product_id = p.id 
             WHERE r.user_id = :user_id 
             ORDER BY r.created_at DESC";
    
    return fetchAll($query, ['user_id' => $userId]);
}
?>