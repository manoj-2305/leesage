<?php
session_start();
header('Content-Type: application/json');
include '../../../../backend/database/config.php';
require_once __DIR__ . '/../auth/check_session.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get data from request
$data = json_decode(file_get_contents('php://input'), true);
$transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : '';
$new_status = isset($data['status']) ? $data['status'] : '';

if (empty($transaction_id) || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID and new status are required']);
    exit();
}

// Validate new status
$allowed_statuses = ['pending', 'completed', 'failed', 'refunded'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment status']);
    exit();
}

try {
    // Update payment status
    $stmt = $conn->prepare("UPDATE payments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE transaction_id = ?");
    $stmt->bind_param("ss", $new_status, $transaction_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment not found or status already updated']);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Error updating payment status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update payment status. Please try again later.']);
}

$conn->close();
?>