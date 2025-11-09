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
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = (int)10; // Number of reviews per page
    $offset = (int)(($page - 1) * $limit);

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    $sql = "SELECT r.id, r.product_id, p.name AS product_name, r.user_id, u.first_name, u.last_name, r.rating, r.review_text, r.is_approved, r.created_at
            FROM reviews r
            JOIN products p ON r.product_id = p.id
            JOIN users u ON r.user_id = u.id";
    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $conditions[] = "(p.name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR r.review_text LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'sss';
    }

    if (!empty($status)) {
        if ($status === 'approved') {
            $conditions[] = "r.is_approved = ?";
            $params[] = 1;
            $types .= 'i';
        } elseif ($status === 'pending') {
            $conditions[] = "r.is_approved = ?";
            $params[] = 0;
            $types .= 'i';
        } elseif ($status === 'rejected') {
            $conditions[] = "r.is_approved = ?";
            $params[] = 2;
            $types .= 'i';
        }
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}";


    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM reviews r
                     JOIN products p ON r.product_id = p.id
                     JOIN users u ON r.user_id = u.id";
        $countConditions = array_slice($conditions, 0, count($conditions) - 2);
        $countParams = array_slice($params, 0, count($params) - 2);

        if (!empty($countConditions)) {
            $countSql .= " WHERE " . implode(' AND ', $countConditions);
        }
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $totalReviews = $countStmt->fetchColumn();

        $response = [
            'success' => true,
            'message' => 'Reviews fetched successfully.',
            'reviews' => $reviews,
            'total_reviews' => $totalReviews,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => ceil($totalReviews / $limit)
        ];

    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid request method.'];
}

echo json_encode($response);

$conn = null;
?>