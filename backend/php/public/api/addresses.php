<?php
// User addresses API endpoint

require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/common.php';
require_once __DIR__ . '/../utils/config.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/address.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require authentication for all address operations
if (!isAuthenticated()) {
    sendErrorResponse('Authentication required', 401);
    exit;
}

// Get current user ID
$userId = getCurrentUserId();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get user addresses
        try {
            // Check if specific address ID is requested
            if (isset($_GET['id']) && !empty($_GET['id'])) {
                $addressId = (int)$_GET['id'];
                $address = getUserAddressById($userId, $addressId);
                
                if (!$address) {
                    sendErrorResponse('Address not found', 404);
                    exit;
                }
                
                sendSuccessResponse($address);
            } else {
                // Get all addresses for current user
                $addresses = getUserAddresses($userId);
                sendSuccessResponse($addresses);
            }
        } catch (Exception $e) {
            logError('Address Get Error: ' . $e->getMessage());
            sendErrorResponse('Failed to retrieve addresses');
        }
        break;
        
    case 'POST':
        // Add new address
        try {
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            $requiredFields = ['address_line1', 'city', 'state_province', 'postal_code', 'country', 'phone_number'];
            $missingFields = validateRequiredFields($data, $requiredFields);
            logError('Missing fields after validation: ' . print_r($missingFields, true), 'DEBUG'); // Add this line
            if (!empty($missingFields)) {
                sendErrorResponse('Missing required fields', 400);
                exit;
            }

            // Prepare address data
            $addressData = [
                'user_id' => $userId,
                'address_line1' => sanitizeInput($data['address_line1']),
                'address_line2' => isset($data['address_line2']) ? sanitizeInput($data['address_line2']) : null,
                'city' => sanitizeInput($data['city']),
                'state_province' => sanitizeInput($data['state_province']),
                'postal_code' => sanitizeInput($data['postal_code']),
                'country' => sanitizeInput($data['country']),
                'phone_number' => sanitizeInput($data['phone_number']),
                'is_default' => isset($data['is_default']) ? (bool)$data['is_default'] : false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add address
            $addressId = addUserAddress($addressData);

            if ($addressId) {
                // Get the newly added address
                $newAddress = getUserAddressById($userId, $addressId);

                sendSuccessResponse([
                    'message' => 'Address added successfully',
                    'address' => $newAddress
                ]);
            } else {
                sendErrorResponse('Failed to add address');
            }
        } catch (Exception $e) {
            logError('Address Add Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while adding address');
        }
        break;
        
    case 'PUT':
        // Update address
        try {
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            if (!isset($data['id']) || empty($data['id'])) {
                sendErrorResponse('Address ID is required', 400);
                exit;
            }
            
            $addressId = (int)$data['id'];
            
            // Check if address exists and belongs to user
            $existingAddress = getUserAddressById($userId, $addressId);
            if (!$existingAddress) {
                sendErrorResponse('Address not found', 404);
                exit;
            }
            
            // Prepare update data
            $updateData = [];

            // Only update fields that are provided
            if (isset($data['address_line1'])) $updateData['address_line1'] = sanitizeInput($data['address_line1']);
            if (isset($data['address_line2'])) $updateData['address_line2'] = sanitizeInput($data['address_line2']);
            if (isset($data['city'])) $updateData['city'] = sanitizeInput($data['city']);
            if (isset($data['state_province'])) $updateData['state_province'] = sanitizeInput($data['state_province']);
            if (isset($data['postal_code'])) $updateData['postal_code'] = sanitizeInput($data['postal_code']);
            if (isset($data['country'])) $updateData['country'] = sanitizeInput($data['country']);
            if (isset($data['phone_number'])) $updateData['phone_number'] = sanitizeInput($data['phone_number']);
            if (isset($data['is_default'])) $updateData['is_default'] = (bool)$data['is_default'];
            $updateData['user_id'] = $userId;

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            // Update address
            $success = updateUserAddress($addressId, $updateData);

            if ($success) {
                // Get the updated address
                $updatedAddress = getUserAddressById($userId, $addressId);

                sendSuccessResponse([
                    'message' => 'Address updated successfully',
                    'address' => $updatedAddress
                ]);
            } else {
                sendErrorResponse('Failed to update address');
            }
        } catch (Exception $e) {
            logError('Address Update Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while updating address');
        }
        break;
        
    case 'DELETE':
        // Delete address
        try {
            // Get input data
            $data = getInputData();
            
            // Validate required fields
            if (!isset($data['id']) || empty($data['id'])) {
                sendErrorResponse('Address ID is required', 400);
                exit;
            }
            
            $addressId = (int)$data['id'];
            
            // Check if address exists and belongs to user
            $existingAddress = getUserAddressById($userId, $addressId);
            if (!$existingAddress) {
                sendErrorResponse('Address not found', 404);
                exit;
            }
            
            // Delete address
            $success = deleteUserAddress($addressId);
            
            if ($success) {
                sendSuccessResponse([
                    'message' => 'Address deleted successfully'
                ]);
            } else {
                sendErrorResponse('Failed to delete address');
            }
        } catch (Exception $e) {
            logError('Address Delete Error: ' . $e->getMessage());
            sendErrorResponse('An error occurred while deleting address');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
        break;
}
?>