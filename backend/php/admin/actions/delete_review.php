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

    if ($reviewId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid review ID.'];
        echo json_encode($response);
        exit();
    }

    try {
        $sql = "DELETE FROM reviews WHERE id = ?";
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");

        if ($stmt->execute([$reviewId])) {
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Review deleted successfully.'];
            } else {
                $response = ['success' => false, 'message' => 'Review not found.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Failed to delete review.'];
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