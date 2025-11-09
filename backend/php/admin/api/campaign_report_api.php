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

$campaignId = $_GET['campaign_id'] ?? null;

if (!$campaignId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campaign ID is required.']);
    exit();
}

try {
    // In a real application, you would fetch detailed report data here
    // For now, we'll return some dummy data or basic campaign info
    $stmt = $pdo->prepare("SELECT id, name, type, status, start_date, end_date, description FROM marketing_campaigns WHERE id = :id");
    $stmt->execute([':id' => $campaignId]);
    $campaignData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($campaignData) {
        // Placeholder for more complex report data
        $reportData = [
            'campaign_info' => $campaignData,
            'metrics' => [
                'total_clicks' => rand(100, 5000),
                'total_views' => rand(5000, 50000),
                'conversion_rate' => round(rand(1, 100) / 10, 2) . '%',
                'revenue_generated' => rand(1000, 100000) / 100,
            ],
            'audience_demographics' => [
                'age_groups' => ['18-24' => '20%', '25-34' => '40%', '35-44' => '25%', '45+' => '15%'],
                'gender' => ['male' => '55%', 'female' => '45%'],
            ],
            'performance_over_time' => [
                ['date' => '2023-01-01', 'clicks' => 100, 'views' => 1000],
                ['date' => '2023-01-02', 'clicks' => 120, 'views' => 1200],
                ['date' => '2023-01-03', 'clicks' => 150, 'views' => 1500],
            ]
        ];
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $reportData]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Campaign not found.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

?>