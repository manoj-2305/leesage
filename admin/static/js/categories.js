/**
 * Categories Management JavaScript
 * Handles all category-related functionality in the admin panel
 */

// Global variables
let categories = [];
let currentCategoryId = null;

// Initialize when DOM is loaded


// Function to initialize categories when loaded dynamically
window.initCategories = function() {
    loadCategories();
    setupEventListeners();
}

/**
 * Set up all event listeners
 */
function setupEventListeners() {
    // Add category button
    document.getElementById('addCategoryBtn').addEventListener('click', openAddCategoryModal);
    
    // Modal close buttons
    document.querySelector('.modal-close').addEventListener('click', closeCategoryModal);
    document.getElementById('cancelCategory').addEventListener('click', closeCategoryModal);
    
    // Form submission
    document.getElementById('categoryForm').addEventListener('submit', handleCategorySubmit);
    
    // Close modal when clicking outside
    document.getElementById('categoryModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCategoryModal();
        }
    });
}

function initCategories() {
    loadCategories();
    setupEventListeners();
}

// Function to load categories and render the table
async function loadCategories() {
    try {
        const response = await fetch('/leesage/backend/php/admin/api/get_categories.php');
        const data = await response.json();
        
        if (data.success) {
            categories = data.data;
            console.log('Categories loaded:', categories);
            renderCategoriesTable();
        } else {
            showError('Failed to load categories: ' + data.message);
        }
    } catch (error) {
        console.error('Error loading categories:', error);
        showError('Failed to load categories. Please try again.');
    }
}

/**
 * Render categories in the table
 */
function renderCategoriesTable() {
    const tbody = document.querySelector('.categories-table tbody');
    tbody.innerHTML = '';
    
    if (categories.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 20px;">
                    No categories found. Add your first category to get started.
                </td>
            </tr>
        `;
        return;
    }
    
    categories.forEach(category => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>CAT${String(category.id).padStart(3, '0')}</td>
            <td>${escapeHtml(category.name)}</td>
            <td>${escapeHtml(category.description || 'No description')}</td>
            <td>
                <button class="btn btn-sm btn-info" onclick="editCategory(${category.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteCategory(${category.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

/**
 * Open modal for adding a new category
 */
function openAddCategoryModal() {
    currentCategoryId = null;
    document.getElementById('categoryModalTitle').textContent = 'Add New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryModal').style.display = 'flex';
}

/**
 * Open modal for editing a category
 */
function editCategory(categoryId) {
    const category = categories.find(c => c.id === categoryId);
    if (!category) {
        showError('Category not found');
        return;
    }
    
    currentCategoryId = categoryId;
    document.getElementById('categoryModalTitle').textContent = 'Edit Category';
    document.getElementById('categoryId').value = categoryId;
    document.getElementById('categoryName').value = category.name;
    document.getElementById('categoryDescription').value = category.description || '';
    document.getElementById('categoryModal').style.display = 'flex';
}

/**
 * Close the category modal
 */
function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
    document.getElementById('categoryForm').reset();
    currentCategoryId = null;
}

/**
 * Handle form submission for adding/editing categories
 */
async function handleCategorySubmit(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const name = document.getElementById('categoryName').value.trim();
    const description = document.getElementById('categoryDescription').value.trim();
    
    if (!name) {
        showError('Category name is required');
        return;
    }
    
    formData.append('name', name);
    formData.append('description', description);
    
    if (currentCategoryId) {
        formData.append('category_id', currentCategoryId);
    }
    
    try {
        const endpoint = currentCategoryId 
            ? '/leesage/backend/php/admin/actions/edit_category.php'
            : '/leesage/backend/php/admin/actions/add_category.php';
            
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            closeCategoryModal();
            loadCategories();
        } else {
            showError(data.message || 'Operation failed');
        }
    } catch (error) {
        console.error('Error saving category:', error);
        showError('Failed to save category. Please try again.');
    }
}

/**
 * Delete a category
 */
async function deleteCategory(categoryId) {

    const category = categories.find(c => c.id === categoryId);
    if (!category) return;
    
    showConfirmationModal(`Are you sure you want to delete the category "${category.name}"?`, async () => {
        const formData = new FormData();
        formData.append('category_id', categoryId);
        // force_delete will be handled by the backend based on initial check
        formData.append('force_delete', 0); // Default to 0, backend will prompt if needed
        console.log('Attempting to delete category with ID:', categoryId, 'Force delete:', 0);

        try {
            const response = await fetch('/leesage/backend/php/admin/actions/delete_category.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showSuccess(data.message);
                loadCategories();
            } else {
                showError(data.message || 'Failed to delete category');
            }
        } catch (error) {
            console.error('Error deleting category:', error);
            showError('Failed to delete category. Please try again.');
        }
    });
    return;
    
    try {
        const response = await fetch('/leesage/backend/php/admin/actions/delete_category.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            loadCategories();
        } else if (data.has_products || data.has_subcategories) {
            const confirmationMessage = 
                data.message + '<br><br>' +
                'Products: ' + (data.product_count || 0) + '<br>' +
                'Subcategories: ' + (data.subcategory_count || 0) + '<br><br>' +
                'Do you want to force delete anyway?';

            showConfirmationModal(confirmationMessage, async () => {
                formData.set('force_delete', 1);
                try {
                    const forceResponse = await fetch('/leesage/backend/php/admin/actions/delete_category.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const forceData = await forceResponse.json();
                    if (forceData.success) {
                        showSuccess(forceData.message);
                        loadCategories();
                    } else {
                        showError(forceData.message || 'Failed to delete category');
                    }
                } catch (forceError) {
                    console.error('Error during force delete:', forceError);
                    showError('Failed to force delete category. Please try again.');
                }
            });
        } else {
            showError(data.message || 'Failed to delete category');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        showError('Failed to delete category. Please try again.');
    }
}

/**
 * Show success message
 */
function showSuccess(message) {
    showMessageModal('Success', message, 'success');
}

/**
 * Show error message
 */
function showError(message) {
    showMessageModal('Error', message, 'error');
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
