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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reviewId = isset($_GET['review_id']) ? (int)$_GET['review_id'] : 0;

    if ($reviewId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid review ID.'];
        echo json_encode($response);
        exit();
    }

    try {
        $sql = "SELECT r.id, r.product_id, p.name AS product_name, r.user_id, u.first_name, u.last_name, r.rating, r.review_text, r.is_approved, r.created_at, r.updated_at
                FROM reviews r
                JOIN products p ON r.product_id = p.id
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?";
        $stmt = $conn->prepare($sql);
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($review) {
        $response = ['success' => true, 'message' => 'Review details fetched successfully.', 'review' => $review];
    } else {
        $response = ['success' => false, 'message' => 'Review not found.'];
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