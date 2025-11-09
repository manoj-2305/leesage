<?php
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../../../database/config.php';

$response = ['success' => false, 'message' => ''];

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'Admin not logged in.';
    echo json_encode($response);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Check if file was uploaded without errors
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_image']['tmp_name'];
    $fileName = $_FILES['profile_image']['name'];
    $fileSize = $_FILES['profile_image']['size'];
    $fileType = $_FILES['profile_image']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
    if (in_array($fileExtension, $allowedfileExtensions)) {
        // Generate a unique file name based on admin ID and a timestamp
        $newFileName = 'admin_' . $admin_id . '_' . uniqid() . '.' . $fileExtension;
        $uploadFileDir = __DIR__ . '/../../../../assets/profiles/admin/';
        $dest_path = $uploadFileDir . $newFileName;

        // Create directory if it doesn't exist
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0777, true);
        }

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            try {
                $pdo = getDBConnection();
                // Get current profile image to delete it if it's not the default
                $stmt = $pdo->prepare("SELECT profile_image FROM admin_users WHERE id = ?");
                $stmt->execute([$admin_id]);
                $currentImage = $stmt->fetchColumn();

                // Delete old image if it exists and is not the placeholder
                if ($currentImage && strpos($currentImage, 'placeholder.com') === false) {
                    $oldImagePath = __DIR__ . '/../../../' . $currentImage;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // Update database with new image path - include subdirectory with proper slash
                $profile_image_url = '/leesage/assets/profiles/admin/' . $newFileName;
                $stmt = $pdo->prepare("UPDATE admin_users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$profile_image_url, $admin_id]);

                $response['success'] = true;
                $response['message'] = 'Profile image uploaded successfully.';
                $response['profile_image_url'] = $profile_image_url;
            } catch (PDOException $e) {
                $response['message'] = 'Database error: ' . $e->getMessage();
                error_log('Profile image update database error: ' . $e->getMessage());
            }
        } else {
            $response['message'] = 'There was an error moving the uploaded file.';
        }
    } else {
        $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.';
    }
} else {
    $response['message'] = 'No file uploaded or there was an upload error.';
}

echo json_encode($response);
?>
