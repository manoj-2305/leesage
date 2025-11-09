<?php
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../auth/check_session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/** @var PDO $conn */
$conn = getDBConnection();

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $query = "SELECT m.*, u.first_name, u.last_name
              FROM messages m
              LEFT JOIN users u ON m.user_id = u.id
              WHERE 1=1";
    $params = [];
    $types = '';

    if ($search !== '') {
        $query .= " AND (m.name LIKE ? OR m.email LIKE ? OR m.subject LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $types .= 'sss';
    }

    if ($status !== '') {
        if ($status === 'new') {
            $query .= " AND m.is_read = 0";
        } elseif ($status === 'open') {
            $query .= " AND m.is_read = 1 AND m.replied = 0";
        } elseif ($status === 'closed') {
            $query .= " AND m.replied = 1";
        }
    }

    $countQuery = str_replace('m.*, u.first_name, u.last_name', 'COUNT(*) AS total', $query);

    /** @var PDOStatement $stmt */
    $stmt = $conn->prepare($countQuery);
    if ($types !== '') $stmt->execute($params);
    else $stmt->execute();
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();

    $query .= " ORDER BY m.created_at DESC LIMIT {$limit} OFFSET {$offset}";

    /** @var PDOStatement $stmt */
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $res = $stmt;

    $messages = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $messages[] = [
            'id'         => (int)$row['id'],
            'name'       => $row['name'],
            'email'      => $row['email'],
            'subject'    => $row['subject'],
            'message'    => $row['message'],
            'is_read'    => (bool)$row['is_read'],
            'replied'    => (bool)$row['replied'],
            'user_id'    => $row['user_id'] ? (int)$row['user_id'] : null,
            'user_name'  => $row['first_name'] ? ($row['first_name'] . ' ' . $row['last_name']) : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->closeCursor();

    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $messages,
            'pagination' => [
                'current_page' => $page,
                'total_pages'  => max(1, (int)ceil($total / $limit)),
                'total'        => $total,
                'per_page'     => $limit
            ]
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
