<?php
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../../../database/config.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'Admin not logged in.';
    echo json_encode($response);
    exit();
}

$admin_id = $_SESSION['admin_id'];
error_log('get_admin_profile.php: admin_id from session: ' . $admin_id);

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT username, email, full_name, role, profile_image, last_login FROM admin_users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin_data) {
        $response['success'] = true;
        $response['data'] = $admin_data;
        error_log('get_admin_profile.php: Admin data fetched: ' . json_encode($admin_data));
    } else {
        $response['message'] = 'Admin data not found.';
        error_log('get_admin_profile.php: Admin data not found for admin_id: ' . $admin_id);
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Admin profile fetch error: ' . $e->getMessage());
}

echo json_encode($response);
?>
