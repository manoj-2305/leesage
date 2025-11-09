<?php
// User address model functions

/**
 * Get all addresses for a user
 * 
 * @param int $userId User ID
 * @return array Array of user addresses
 */
function getUserAddresses($userId) {
    $query = "SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC";
    $params = ['user_id' => $userId];

    $addresses = fetchAll($query, $params);

    // Convert boolean fields from integers
    foreach ($addresses as &$address) {
        $address['is_default'] = (bool)$address['is_default'];
    }

    return $addresses;
}

/**
 * Get a specific address by ID for a user
 * 
 * @param int $userId User ID
 * @param int $addressId Address ID
 * @return array|null Address data or null if not found
 */
function getUserAddressById($userId, $addressId) {
    $query = "SELECT * FROM addresses WHERE id = :id AND user_id = :user_id";
    $params = ['id' => $addressId, 'user_id' => $userId];

    $address = fetchOne($query, $params);

    if ($address) {
        // Convert boolean fields from integers
        $address['is_default'] = (bool)$address['is_default'];
    }

    return $address;
}

/**
 * Add a new address for a user
 * 
 * @param array $addressData Address data
 * @return int|false The new address ID or false on failure
 */
function addUserAddress($addressData) {
    // If setting this as default, unset default for other addresses of the user
    if (isset($addressData['is_default']) && $addressData['is_default']) {
        $updateQuery = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id";
        $updateParams = ['user_id' => $addressData['user_id']];
        update('addresses', ['is_default' => 0], 'user_id = :user_id', $updateParams);
    }

    $query = "INSERT INTO addresses (user_id, address_line1, address_line2, city, state_province, postal_code, country, phone_number, is_default, created_at, updated_at) 
              VALUES (:user_id, :address_line1, :address_line2, :city, :state_province, :postal_code, :country, :phone_number, :is_default, :created_at, :updated_at)";

    $params = [
        'user_id' => $addressData['user_id'],
        'address_line1' => $addressData['address_line1'],
        'address_line2' => $addressData['address_line2'],
        'city' => $addressData['city'],
        'state_province' => $addressData['state_province'],
        'postal_code' => $addressData['postal_code'],
        'country' => $addressData['country'],
        'phone_number' => $addressData['phone_number'],
        'is_default' => $addressData['is_default'],
        'created_at' => $addressData['created_at'],
        'updated_at' => $addressData['updated_at']
    ];

    return insert('addresses', $params);
}

/**
 * Update an existing address
 * 
 * @param int $addressId Address ID
 * @param array $updateData Data to update
 * @return bool Success or failure
 */
function updateUserAddress($addressId, $updateData) {
    // If setting this as default, unset default for other addresses of the user
    if (isset($updateData['is_default']) && $updateData['is_default']) {
        // Ensure user_id is present in updateData for this logic
        if (!isset($updateData['user_id'])) {
            // Fetch user_id if not provided, assuming addressId is valid
            $existingAddress = getUserAddressById(null, $addressId); // user_id can be null here if not used in getUserAddressById for fetching
            if ($existingAddress) {
                $updateData['user_id'] = $existingAddress['user_id'];
            } else {
                return false; // Address not found
            }
        }
        $updateQuery = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id AND id != :id";
        $updateParams = ['user_id' => $updateData['user_id'], 'id' => $addressId];
        update('addresses', ['is_default' => 0], 'user_id = :user_id AND id != :id', ['user_id' => $updateData['user_id'], 'id' => $addressId]);
    }

    // Prepare update data
    $updateFields = [];
    $params = [];

    foreach ($updateData as $field => $value) {
        // Skip user_id as it's used in the WHERE clause or handled above
        if ($field === 'user_id') {
            continue;
        }

        // Map old column names to new ones if they exist in updateData
        if ($field === 'state') {
            $field = 'state_province';
        } elseif ($field === 'zip_code') {
            $field = 'postal_code';
        }

        // Skip fields that are no longer in the table or handled differently
        if (in_array($field, ['phone', 'is_default_shipping', 'is_default_billing'])) {
            continue;
        }

        $updateFields[$field] = $value;
        $params[$field] = $value;
    }

    $params['id'] = $addressId;

    return update('addresses', $updateFields, 'id = :id', $params);
}

/**
 * Delete an address
 * 
 * @param int $addressId Address ID
 * @return bool Success or failure
 */
function deleteUserAddress($addressId) {
    return delete('addresses', 'id = :id', ['id' => $addressId]);
}

// Removed updateDefaultShippingAddress and updateDefaultBillingAddress
// as they are replaced by the single is_default column logic.

?>