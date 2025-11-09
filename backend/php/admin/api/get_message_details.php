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
    $id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
        exit;
    }

    /** @var PDOStatement $stmt */
    $stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }

    if ((int)$row['is_read'] === 0) {
        /** @var PDOStatement $up */
        $up = $conn->prepare("UPDATE messages SET is_read = 1, updated_at = NOW() WHERE id = ?");
        $up->execute([$id]);
        $up->closeCursor();
        $row['is_read'] = 1;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id'         => (int)$row['id'],
            'name'       => $row['name'],
            'email'      => $row['email'],
            'subject'    => $row['subject'],
            'message'    => $row['message'],
            'is_read'    => (bool)$row['is_read'],
            'replied'    => (bool)$row['replied'],
            'user_id'    => $row['user_id'] ? (int)$row['user_id'] : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
