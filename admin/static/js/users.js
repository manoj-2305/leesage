// Global function to load users - called from dashboard.js
function loadUsers(page = 1, search = '', role = '', status = '') {
    const usersTableBody = document.querySelector('.users-table tbody');
    if (!usersTableBody) {
        console.error('Users table body not found');
        return;
    }
    
    // Load users data
    fetchUsersData(page, search, role, status);
}

// Function to fetch users from API
async function fetchUsersData(page = 1, search = '', role = '', status = '') {
    const usersTableBody = document.querySelector('.users-table tbody');
    if (!usersTableBody) return;
    
    try {
        usersTableBody.innerHTML = '<tr><td colspan="6" class="loading">Loading users...</td></tr>';
        
        const params = new URLSearchParams({
            page,
            limit: 10,
            search,
            status: status === 'active' ? 'active' : status === 'inactive' ? 'inactive' : ''
        });
        
        const response = await fetch(`../../backend/php/admin/api/get_users.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const users = data.data.users;
            
            if (users.length === 0) {
                usersTableBody.innerHTML = '<tr><td colspan="6" class="empty">No users found</td></tr>';
                return;
            }
            
            usersTableBody.innerHTML = '';
            
            users.forEach(user => {
                const row = document.createElement('tr');
                const fullName = `${user.first_name} ${user.last_name}`.trim();
                row.innerHTML = `
                    <td>${user.id}</td>
                    <td>${fullName}</td>
                    <td>${user.email}</td>
                    <td>customer</td>
                    <td><span class="status-badge ${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info edit-user" data-id="${user.id}" onclick="editUserHandler(${user.id})">Edit</button>
                        <button class="btn btn-sm btn-danger delete-user" data-id="${user.id}" onclick="deleteUserHandler(${user.id})">Delete</button>
                    </td>
                `;
                
                usersTableBody.appendChild(row);
            });
            
            // Initialize modal and button handlers
            initializeModalHandlers();
        } else {
            usersTableBody.innerHTML = `<tr><td colspan="6" class="error">${data.message || 'Failed to load users'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading users:', error);
        usersTableBody.innerHTML = '<tr><td colspan="6" class="error">An error occurred while loading users</td></tr>';
    }
}

// Global handler functions for inline onclick
function editUserHandler(userId) {
    loadUserForEdit(userId);
}

function deleteUserHandler(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
        deleteUser(userId);
    }
}

// Initialize modal and button handlers
function initializeModalHandlers() {
    const addUserBtn = document.getElementById('addUserBtn');
    const userModal = document.getElementById('userModal');
    const userModalTitle = document.getElementById('userModalTitle');
    const userForm = document.getElementById('userForm');
    const modalClose = document.querySelector('.modal-close');
    const cancelUser = document.getElementById('cancelUser');

    // Add user button
    if (addUserBtn) {
        addUserBtn.onclick = () => openUserModal('Add New User');
    }

    // Modal close handlers
    if (modalClose) {
        modalClose.onclick = closeUserModal;
    }
    
    if (cancelUser) {
        cancelUser.onclick = closeUserModal;
    }
    
    if (userModal) {
        window.onclick = function(event) {
            if (event.target === userModal) {
                closeUserModal();
            }
        };
    }

    // Form submission
    if (userForm) {
        userForm.onsubmit = function(event) {
            event.preventDefault();
            
            const userId = document.getElementById('userId').value;
            const userName = document.getElementById('userName').value;
            const userEmail = document.getElementById('userEmail').value;
            const userStatus = document.getElementById('userStatus').value;
            const userPassword = document.getElementById('userPassword').value;
            
            const formData = new FormData();
            formData.append('name', userName);
            formData.append('email', userEmail);
            formData.append('status', userStatus === 'active' ? 1 : 0);
            
            if (userPassword) {
                formData.append('password', userPassword);
            }
            
            if (userId) {
                formData.append('user_id', userId);
                editUser(formData);
            } else {
                addUser(formData);
            }
        };
    }
}

// Function to open user modal
function openUserModal(title, user = null) {
    const userModal = document.getElementById('userModal');
    const userModalTitle = document.getElementById('userModalTitle');
    
    if (!userModal || !userModalTitle) {
        console.error('Modal elements not found');
        return;
    }
    
    userModalTitle.textContent = title;
    
    if (user) {
        document.getElementById('userId').value = user.id;
        document.getElementById('userName').value = `${user.first_name} ${user.last_name}`.trim();
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userStatus').value = user.status || 'active';
        document.getElementById('userPassword').value = '';
    } else {
        const userForm = document.getElementById('userForm');
        if (userForm) {
            userForm.reset();
        }
        document.getElementById('userId').value = '';
    }
    
    userModal.style.display = 'block';
}

// Function to close user modal
function closeUserModal() {
    const userModal = document.getElementById('userModal');
    if (userModal) {
        userModal.style.display = 'none';
    }
}

// Function to load user data for editing
async function loadUserForEdit(userId) {
    try {
        // Create a more targeted API call for getting a single user
        const response = await fetch(`../../backend/php/admin/api/get_users.php?user_id=${userId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.data.users.length > 0) {
            // Find the exact user by ID
            const user = data.data.users.find(u => u.id == userId);
            if (user) {
                const userData = {
                    id: user.id,
                    first_name: user.first_name,
                    last_name: user.last_name,
                    email: user.email,
                    is_active: user.is_active
                };
                openUserModal('Edit User', userData);
            } else {
                showNotification('User not found', 'error');
            }
        } else {
            showNotification('Failed to load user details', 'error');
        }
    } catch (error) {
        console.error('Error loading user details:', error);
        showNotification('An error occurred while loading user details', 'error');
    }
}

// Function to add a new user
async function addUser(formData) {
    try {
        const response = await fetch('../../backend/php/admin/actions/add_user.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('User added successfully!', 'success');
            closeUserModal();
            loadUsers();
        } else {
            showNotification(data.message || 'Failed to add user', 'error');
        }
    } catch (error) {
        console.error('Error adding user:', error);
        showNotification('An error occurred while adding the user', 'error');
    }
}

// Function to edit an existing user
async function editUser(formData) {
    try {
        const response = await fetch('../../backend/php/admin/actions/edit_user.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('User updated successfully!', 'success');
            closeUserModal();
            loadUsers();
        } else {
            showNotification(data.message || 'Failed to update user', 'error');
        }
    } catch (error) {
        console.error('Error updating user:', error);
        showNotification('An error occurred while updating the user', 'error');
    }
}

// Function to delete a user
async function deleteUser(userId) {
    try {
        const formData = new FormData();
        formData.append('user_id', userId);
        
        const response = await fetch('../../backend/php/admin/actions/delete_user.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('User deleted successfully!', 'success');
            loadUsers();
        } else {
            showNotification(data.message || 'Failed to delete user', 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showNotification('An error occurred while deleting the user', 'error');
    }
}

// Function to show notifications
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close"><i class="fas fa-times"></i></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('active'), 10);
    
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.classList.remove('active');
        setTimeout(() => notification.remove(), 300);
    });
    
    setTimeout(() => {
        if (document.body.contains(notification)) {
            notification.classList.remove('active');
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Ensure the users section is loaded before initializing
    const checkUsersSection = setInterval(() => {
        const usersTableBody = document.querySelector('.users-table tbody');
        const addUserBtn = document.getElementById('addUserBtn');
        
        if (usersTableBody && addUserBtn) {
            clearInterval(checkUsersSection);
            loadUsers();
        }
    }, 100);
});

// Make functions globally available
window.loadUsers = loadUsers;
window.openUserModal = openUserModal;
window.closeUserModal = closeUserModal;
window.editUserHandler = editUserHandler;
window.deleteUserHandler = deleteUserHandler;
