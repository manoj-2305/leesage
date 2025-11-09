<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../auth/check_session.php';

// Only admin users can access this API
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Only administrators can perform this action.']);
    exit();
}

$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        break;
}

function handleGet($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM marketing_campaigns");
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $campaigns]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve campaigns: ' . $e->getMessage()]);
    }
}

function handlePost($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
        return;
    }

    $insertData = [
        'name' => $data['name'] ?? null,
        'type' => $data['type'] ?? null,
        'status' => $data['status'] ?? null,
        'start_date' => $data['start_date'] ?? null,
        'end_date' => $data['end_date'] ?? null,
        'description' => $data['description'] ?? null
    ];

    // Validate required fields
    if (empty($insertData['name']) || empty($insertData['type']) || empty($insertData['status']) || empty($insertData['start_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields. Name, Type, Status, and Start Date are required.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO marketing_campaigns (name, type, status, start_date, end_date, description) VALUES (:name, :type, :status, :start_date, :end_date, :description)");
        $stmt->execute($insertData);
        echo json_encode(['success' => true, 'message' => 'Campaign created successfully.', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create campaign: ' . $e->getMessage()]);
    }
}

function handlePut($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input or missing campaign ID.']);
        return;
    }

    $campaignId = $data['id'];
    $updateData = [
        'name' => $data['name'] ?? null,
        'type' => $data['type'] ?? null,
        'status' => $data['status'] ?? null,
        'start_date' => $data['start_date'] ?? null,
        'end_date' => $data['end_date'] ?? null,
        'description' => $data['description'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Remove null values to prevent overwriting with null if not provided
    $updateData = array_filter($updateData, function($value) { return $value !== null; });

    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data provided for update.']);
        return;
    }

    try {
        $setClause = [];
        $params = [];
        foreach ($updateData as $key => $value) {
            $setClause[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        $params[':id'] = $campaignId;

        $stmt = $pdo->prepare("UPDATE marketing_campaigns SET " . implode(', ', $setClause) . " WHERE id = :id");
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Campaign updated successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update campaign: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input or missing campaign ID.']);
        return;
    }

    $campaignId = $data['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM marketing_campaigns WHERE id = :id");
        $stmt->execute([':id' => $campaignId]);
        echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete campaign: ' . $e->getMessage()]);
    }
}
?>