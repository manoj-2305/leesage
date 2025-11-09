<?php
require_once __DIR__ . '/../auth/check_session.php';
require_once __DIR__ . '/../../../database/config.php';

$conn = null;
try {
    $conn = getDBConnection();
} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    echo json_encode($response);
    exit();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewId = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($reviewId <= 0 || !in_array($status, ['approved', 'pending', 'rejected'])) {
        $response = ['success' => false, 'message' => 'Invalid review ID or status.'];
        echo json_encode($response);
        exit();
    }

    $isApproved = 0;
    if ($status === 'approved') {
        $isApproved = 1;
    } else if ($status === 'rejected') {
        $isApproved = 2; // Assuming 2 for rejected, 0 for pending
    }

    try {
        $sql = "UPDATE reviews SET is_approved = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([$isApproved, $reviewId])) {
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Review status updated successfully.'];
            } else {
                $response = ['success' => false, 'message' => 'Review not found or status already the same.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Failed to update review status.'];
        }

    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid request method.'];
}

echo json_encode($response);

$conn = null;
?>