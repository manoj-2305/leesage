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
    $status    = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($messageId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
        exit;
    }
    if (!in_array($status, ['open', 'closed'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $sql = ($status === 'open')
        ? "UPDATE messages SET is_read = 1, replied = 0, updated_at = NOW() WHERE id = ?"
        : "UPDATE messages SET is_read = 1, replied = 1, updated_at = NOW() WHERE id = ?";

    /** @var PDOStatement $stmt */
    $stmt = $conn->prepare($sql);
    $stmt->execute([$messageId]);
    $rows = $stmt->rowCount();
    $stmt->closeCursor();

    if ($rows > 0) {
        /** @var PDOStatement $log */
        $log = $conn->prepare(
            "INSERT INTO admin_activity_logs (admin_id, action_type, action_description)
             VALUES (?, 'message_status_update', ?)"
        );
        $desc = "Updated message #{$messageId} status to {$status}";
        $log->execute([$_SESSION['admin_id'], $desc]);
        $log->closeCursor();

        echo json_encode(['success' => true, 'message' => 'Message status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found or no changes made']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
