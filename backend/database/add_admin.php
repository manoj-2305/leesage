<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $pdo = getDBConnection();

    // Check if the request is a POST request
// Allow both POST and GET for testing purposes. In production, only allow POST.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // For GET requests, we'll proceed with the sample data insertion
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only POST or GET requests are accepted for testing.']);
    exit();
}

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

// Sample admin data (for direct use or if POST data is empty)
$AdminData = [
    'username' => 'manoj_23',
    'email' => 'developer@leesage.com',
    'password' => '123456',
    'full_name' => 'Manoj M',
    'role' => 'admin'
];

// Use sample data if no POST data is provided or if it's invalid
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
    $input = $AdminData;
}

$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');
$fullName = trim($input['full_name'] ?? '');
$role = trim($input['role'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        throw new Exception('Username, email, password, and full name are required.');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }

    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute the SQL statement to insert a new admin user
    $stmt = $pdo->prepare(
        "INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([$username, $email, $passwordHash, $fullName, $role, 1]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Admin user added successfully.';
    } else {
        throw new Exception('Failed to add admin user.');
    }

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
} finally {
    echo json_encode($response);
}

?>