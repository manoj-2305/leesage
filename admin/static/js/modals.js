// Modal Management System for Admin Sections
// This file handles all modal functionality across admin sections

// Global modal management functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore background scrolling
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
});

// Close modal when pressing Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Form submission handlers for each modal
document.addEventListener('DOMContentLoaded', function() {
    // Analytics Modal
    const analyticsForm = document.getElementById('analyticsForm');
    if (analyticsForm) {
        analyticsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Analytics form submitted');
            // Implement analytics report generation
            closeModal('analyticsModal');
        });
    }

    // Marketing Modal
    const marketingForm = document.getElementById('marketingForm');
    if (marketingForm) {
        marketingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const campaignData = {
                id: document.getElementById('campaignId').value,
                name: document.getElementById('campaignName').value,
                type: document.getElementById('campaignType').value,
                description: document.getElementById('campaignDescription').value,
                startDate: document.getElementById('campaignStartDate').value,
                endDate: document.getElementById('campaignEndDate').value,
                budget: document.getElementById('campaignBudget').value,
                status: document.getElementById('campaignStatus').value
            };
            console.log('Marketing campaign data:', campaignData);
            // Implement campaign creation/update
            closeModal('marketingModal');
        });
    }

    // Messages Modal
    const messagesForm = document.getElementById('messagesForm');
    if (messagesForm) {
        messagesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageData = {
                id: document.getElementById('messageId').value,
                subject: document.getElementById('messageSubject').value,
                content: document.getElementById('messageContent').value,
                status: document.getElementById('messageStatus').value
            };
            console.log('Message data:', messageData);
            // Implement message creation/update
            closeModal('messagesModal');
        });
    }

    // Payments Modal
    const paymentsForm = document.getElementById('paymentsForm');
    if (paymentsForm) {
        paymentsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const paymentData = {
                id: document.getElementById('paymentId').value,
                amount: document.getElementById('paymentAmount').value,
                method: document.getElementById('paymentMethod').value,
                status: document.getElementById('paymentStatus').value,
                date: document.getElementById('paymentDate').value,
                description: document.getElementById('paymentDescription').value
            };
            console.log('Payment data:', paymentData);
            // Implement payment processing
            closeModal('paymentsModal');
        });
    }

    // Security Modal
    const securityForm = document.getElementById('securityForm');
    if (securityForm) {
        securityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const securityData = {
                id: document.getElementById('securityId').value,
                settingName: document.getElementById('securitySettingName').value,
                settingValue: document.getElementById('securitySettingValue').value,
                description: document.getElementById('securitySettingDescription').value
            };
            console.log('Security settings:', securityData);
            // Implement security settings update
            closeModal('securityModal');
        });
    }

    // Reviews Modal
    const reviewsForm = document.getElementById('reviewsForm');
    if (reviewsForm) {
        reviewsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const reviewData = {
                id: document.getElementById('reviewId').value,
                product: document.getElementById('reviewProduct').value,
                rating: document.getElementById('reviewRating').value,
                title: document.getElementById('reviewTitle').value,
                content: document.getElementById('reviewContent').value,
                status: document.getElementById('reviewStatus').value
            };
            console.log('Review data:', reviewData);
            // Implement review creation/update
            closeModal('reviewsModal');
        });
    }
});

// Generic Confirmation Modal
function showConfirmationModal(message, onConfirm, onCancel) {
    let confirmModal = document.getElementById('confirmationModal');
    if (!confirmModal) {
        confirmModal = document.createElement('div');
        confirmModal.id = 'confirmationModal';
        confirmModal.className = 'modal';
        confirmModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="confirmationModalTitle">Confirm Action</h3>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="confirmationMessage"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                    <button class="btn btn-secondary" id="cancelActionBtn">Cancel</button>
                </div>
            </div>
        `;
        document.body.appendChild(confirmModal);

        confirmModal.querySelector('.modal-close').addEventListener('click', () => {
            closeModal('confirmationModal');
            if (onCancel) onCancel();
        });
        confirmModal.querySelector('#cancelActionBtn').addEventListener('click', () => {
            closeModal('confirmationModal');
            if (onCancel) onCancel();
        });
        confirmModal.querySelector('#confirmActionBtn').addEventListener('click', () => {
            closeModal('confirmationModal');
            if (onConfirm) onConfirm();
        });

        // Close when clicking outside
        confirmModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal('confirmationModal');
                if (onCancel) onCancel();
            }
        });
    }

    document.getElementById('confirmationMessage').textContent = message;
    openModal('confirmationModal');
}

// Generic Message Modal
function showMessageModal(title, message, type) {
    let messageModal = document.getElementById('messageModal');
    if (!messageModal) {
        messageModal = document.createElement('div');
        messageModal.id = 'messageModal';
        messageModal.className = 'modal';
        messageModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="messageModalTitle"></h3>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="messageModalContent"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" id="messageModalCloseBtn">OK</button>
                </div>
            </div>
        `;
        document.body.appendChild(messageModal);

        messageModal.querySelector('.modal-close').addEventListener('click', () => {
            closeModal('messageModal');
        });
        messageModal.querySelector('#messageModalCloseBtn').addEventListener('click', () => {
            closeModal('messageModal');
        });

        // Close when clicking outside
        messageModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal('messageModal');
            }
        });
    }

    document.getElementById('messageModalTitle').textContent = title;
    document.getElementById('messageModalContent').textContent = message;
    
    // Apply type-specific styling (optional, can be done with CSS classes)
    const modalHeader = messageModal.querySelector('.modal-header');
    if (type === 'success') {
        modalHeader.style.backgroundColor = '#4CAF50';
    } else if (type === 'error') {
        modalHeader.style.backgroundColor = '#dc3545';
    } else {
        modalHeader.style.backgroundColor = '#007bff'; // Default blue
    }

    openModal('messageModal');
}

// Utility functions for modal operations
function populateModal(modalId, data) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    // Populate form fields based on modal type
    switch(modalId) {
        case 'marketingModal':
            document.getElementById('campaignId').value = data.id || '';
            document.getElementById('campaignName').value = data.name || '';
            document.getElementById('campaignType').value = data.type || '';
            document.getElementById('campaignDescription').value = data.description || '';
            document.getElementById('campaignStartDate').value = data.startDate || '';
            document.getElementById('campaignEndDate').value = data.endDate || '';
            document.getElementById('campaignBudget').value = data.budget || '';
            document.getElementById('campaignStatus').value = data.status || 'draft';
            document.getElementById('marketingModalTitle').textContent = data.id ? 'Edit Campaign' : 'Create Campaign';
            break;
            
        case 'messagesModal':
            document.getElementById('messageId').value = data.id || '';
            document.getElementById('messageSubject').value = data.subject || '';
            document.getElementById('messageContent').value = data.content || '';
            document.getElementById('messageStatus').value = data.status || 'active';
            document.getElementById('messagesModalTitle').textContent = data.id ? 'Edit Message' : 'Create Message';
            break;
            
        case 'paymentsModal':
            document.getElementById('paymentId').value = data.id || '';
            document.getElementById('paymentAmount').value = data.amount || '';
            document.getElementById('paymentMethod').value = data.method || '';
            document.getElementById('paymentStatus').value = data.status || 'pending';
            document.getElementById('paymentDate').value = data.date || new Date().toISOString().split('T')[0];
            document.getElementById('paymentDescription').value = data.description || '';
            document.getElementById('paymentsModalTitle').textContent = data.id ? 'Edit Payment' : 'Process Payment';
            break;
            
        case 'securityModal':
            document.getElementById('securityId').value = data.id || '';
            document.getElementById('securitySettingName').value = data.settingName || '';
            document.getElementById('securitySettingValue').value = data.settingValue || '';
            document.getElementById('securitySettingDescription').value = data.description || '';
            document.getElementById('securityModalTitle').textContent = data.id ? 'Edit Security Setting' : 'Add Security Setting';
            break;
            
        case 'reviewsModal':
            document.getElementById('reviewId').value = data.id || '';
            document.getElementById('reviewProduct').value = data.product || '';
            document.getElementById('reviewRating').value = data.rating || '5';
            document.getElementById('reviewTitle').value = data.title || '';
            document.getElementById('reviewContent').value = data.content || '';
            document.getElementById('reviewStatus').value = data.status || 'pending';
            document.getElementById('reviewsModalTitle').textContent = data.id ? 'Edit Review' : 'Add Review';
            break;
    }
}

// Validation functions
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });

    return isValid;
}

// AJAX helper functions
function sendData(endpoint, data, callback) {
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (callback) callback(data);
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
