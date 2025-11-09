// Address management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadAddresses();
    setupEventListeners();
    updateCheckoutButtonState(); // Initialize button state on page load

    const proceedToCheckoutBtn = document.getElementById('proceed-to-checkout-btn');
    if (proceedToCheckoutBtn) {
        proceedToCheckoutBtn.addEventListener('click', () => {
            const selectedAddressId = localStorage.getItem('selectedAddressId');
            if (selectedAddressId) {
                window.location.href = `checkout.html?addressId=${selectedAddressId}`;
            } else {
                showMessage('Please select an address to proceed to checkout.', 'error');
            }
        });
    }
});

function setupEventListeners() {
    // Add address button
    document.getElementById('add-address-btn').addEventListener('click', function() {
        showAddressForm(null);
    });

    // Cancel button
    document.getElementById('cancel-btn').addEventListener('click', hideAddressForm);

    // Close modal when clicking outside the form
    document.getElementById('address-modal').addEventListener('click', function(event) {
        if (event.target === this) {
            hideAddressForm();
        }
    });

    // Address form submission
    document.getElementById('address-form-element').addEventListener('submit', handleAddressSubmit);
}

function loadAddresses() {
    fetch('/leesage/backend/php/public/api/addresses.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAddresses(data.data);
        } else {
            showMessage('Failed to load addresses', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading addresses:', error);
        showMessage('Error loading addresses', 'error');
    });
}

function displayAddresses(addresses) {
    const addressList = document.getElementById('address-list');
    addressList.innerHTML = '';

    if (addresses.length === 0) {
        addressList.innerHTML = '<p>No addresses found. Add your first address below.</p>';
        localStorage.removeItem('selectedAddressId');
        return;
    }

    let selectedAddressId = localStorage.getItem('selectedAddressId');

    addresses.forEach(address => {
        const addressCard = createAddressCard(address);
        addressList.appendChild(addressCard);
        if (selectedAddressId && address.id == selectedAddressId) {
            addressCard.classList.add('selected');
        }
        addressCard.addEventListener('click', () => selectAddress(address.id));
    });
}

function createAddressCard(address) {
    const card = document.createElement('div');
    card.className = 'address-card';
    card.dataset.id = address.id;

    const addressHtml = `
        <div class="address-content">
            <div class="address-details">
                <p><strong>${address.address_line1}</strong></p>
                ${address.address_line2 ? `<p>${address.address_line2}</p>` : ''}
                <p>${address.city}, ${address.state_province} ${address.postal_code}</p>
                <p>${address.country}</p>
                <p>${address.phone_number}</p>
                ${address.is_default ? '<span class="default-badge">Default</span>' : ''}
            </div>
            <div class="address-actions">
                <button class="edit-btn" data-id="${address.id}" onclick="editAddress(${address.id})">Edit</button>
                <button class="delete-btn" data-id="${address.id}" onclick="deleteAddress(${address.id})">Delete</button>
            </div>
        </div>
    `;

    card.innerHTML = addressHtml;
    return card;
}

function selectAddress(addressId) {
    // Remove selected class from all cards
    document.querySelectorAll('.address-card').forEach(card => {
        card.classList.remove('selected');
    });

    // Add selected class to clicked card
    const selectedCard = document.querySelector(`.address-card[data-id="${addressId}"]`);
    selectedCard.classList.add('selected');

    // Store selected address ID
    localStorage.setItem('selectedAddressId', addressId);
    updateCheckoutButtonState();
}

function updateCheckoutButtonState() {
    const proceedToCheckoutBtn = document.getElementById('proceed-to-checkout-btn');
    const selectedAddressId = localStorage.getItem('selectedAddressId');
    if (proceedToCheckoutBtn) {
        if (selectedAddressId) {
            proceedToCheckoutBtn.disabled = false;
        } else {
            proceedToCheckoutBtn.disabled = true;
        }
    }
}

function showAddressForm(addressId = null) {
    const modal = document.getElementById('address-modal');
    const formTitle = modal.querySelector('#form-title');
    const formElement = modal.querySelector('#address-form-element');

    if (addressId) {
        formTitle.textContent = 'Edit Address';
        loadAddressForEdit(addressId);
    } else {
        formTitle.textContent = 'Add Address';
        formElement.reset();
        modal.querySelector('#address-id').value = '';
    }

    modal.classList.add('active');
}

function hideAddressForm() {
    const modal = document.getElementById('address-modal');
    modal.classList.remove('active');
    modal.querySelector('#address-form-element').reset();
}

function loadAddressForEdit(addressId) {
    fetch(`/leesage/backend/php/public/api/addresses.php?id=${addressId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateForm(data.data);
        } else {
            showMessage('Failed to load address', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading address:', error);
        showMessage('Error loading address', 'error');
    });
}

function populateForm(address) {
    const modal = document.getElementById('address-modal');
    modal.querySelector('#address-id').value = address.id;
    modal.querySelector('#address_line1').value = address.address_line1;
    modal.querySelector('#address_line2').value = address.address_line2 || '';
    modal.querySelector('#city').value = address.city;
    modal.querySelector('#state_province').value = address.state_province;
    modal.querySelector('#postal_code').value = address.postal_code;
    modal.querySelector('#country').value = address.country;
    modal.querySelector('#phone_number').value = address.phone_number || '';
    modal.querySelector('#is_default').checked = address.is_default;
}

function handleAddressSubmit(event) {
    event.preventDefault();

    const modal = document.getElementById('address-modal');
    const addressId = modal.querySelector('#address-id').value;
    const addressData = {
        address_line1: modal.querySelector('#address_line1').value,
        address_line2: modal.querySelector('#address_line2').value,
        city: modal.querySelector('#city').value,
        state_province: modal.querySelector('#state_province').value,
        postal_code: modal.querySelector('#postal_code').value,
        country: modal.querySelector('#country').value,
        phone_number: modal.querySelector('#phone_number').value,
        is_default: modal.querySelector('#is_default').checked
    };

    // Client-side validation for required fields
    const requiredFields = ['address_line1', 'city', 'state_province', 'postal_code', 'country', 'phone_number'];
    const missingFields = requiredFields.filter(field => !addressData[field] || addressData[field].trim() === '');

    if (missingFields.length > 0) {
        alert(`Please fill in all required fields: ${missingFields.join(', ')}`);
        return;
    }

    const method = addressId ? 'PUT' : 'POST';
    const url = '/leesage/backend/php/public/api/addresses.php';

    if (method === 'PUT') {
        addressData.id = addressId;
    }

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(addressData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            hideAddressForm();
            loadAddresses();
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving address:', error);
        showMessage('Error saving address', 'error');
    });
}

function editAddress(addressId) {
    console.log('Editing address with ID:', addressId);
    showAddressForm(addressId);
}

function deleteAddress(addressId) {
    if (!confirm('Are you sure you want to delete this address?')) {
        return;
    }

    const deleteData = { id: addressId };

    fetch('/leesage/backend/php/public/api/addresses.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(deleteData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            loadAddresses();
            // After deleting, re-check if selected address still exists
            const selectedAddressId = localStorage.getItem('selectedAddressId');
            if (selectedAddressId == addressId) {
                localStorage.removeItem('selectedAddressId');
            }
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting address:', error);
        showMessage('Error deleting address', 'error');
    });
}

function showMessage(message, type) {
    // Simple alert for now, can be enhanced with toast notifications
    alert(message);
}
