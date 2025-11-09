<?php
// Contact form submission handler

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
    exit;
}

try {
    // Get input data
    $data = getInputData();
    
    // Validate required fields
    $requiredFields = ['name', 'email', 'subject', 'message'];
    if (!validateRequiredFields($data, $requiredFields)) {
        sendErrorResponse('Missing required fields', 400);
        exit;
    }
    
    // Validate email format
    if (!isValidEmail($data['email'])) {
        sendErrorResponse('Invalid email format', 400);
        exit;
    }
    
    // Sanitize input
    $name = sanitizeInput($data['name']);
    $email = sanitizeInput($data['email']);
    $subject = sanitizeInput($data['subject']);
    $message = sanitizeInput($data['message']);
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : null;
    
    // Store contact submission in database
    $conn = getConnection();
    
    $sql = "INSERT INTO contact_submissions (name, email, phone, subject, message, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $stmt->bind_param("ssssss", $name, $email, $phone, $subject, $message, $ipAddress);
    $result = $stmt->execute();
    
    if ($result) {
        $submissionId = $conn->insert_id;
        
        // Send email notification (in a real application)
        // This is a simplified example - in production, use a proper email library
        
        // Prepare email content
        $emailTo = ADMIN_EMAIL;
        $emailSubject = 'New Contact Form Submission: ' . $subject;
        $emailBody = "Name: $name\n";
        $emailBody .= "Email: $email\n";
        if ($phone) {
            $emailBody .= "Phone: $phone\n";
        }
        $emailBody .= "Subject: $subject\n\n";
        $emailBody .= "Message:\n$message\n\n";
        $emailBody .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
        $emailBody .= "IP Address: $ipAddress\n";
        
        $headers = "From: " . SITE_NAME . " <" . NOREPLY_EMAIL . ">\r\n";
        $headers .= "Reply-To: $name <$email>\r\n";
        
        // Log email for development purposes
        logError('Contact Form Email: ' . $emailBody);
        
        // In development, don't actually send the email
        if (ENVIRONMENT !== 'development') {
            mail($emailTo, $emailSubject, $emailBody, $headers);
        }
        
        sendSuccessResponse([
            'message' => 'Thank you for your message. We will get back to you soon!',
            'submission_id' => $submissionId
        ]);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    logError('Contact Form Error: ' . $e->getMessage());
    sendErrorResponse('An error occurred while processing your message. Please try again later.');
}
?>