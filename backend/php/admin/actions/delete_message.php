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
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    if ($messageId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
        exit;
    }

    /** @var PDOStatement $stmt */
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $rows = $stmt->rowCount();
    $stmt->closeCursor();

    if ($rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
