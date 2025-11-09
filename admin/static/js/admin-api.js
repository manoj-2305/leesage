/**
 * Leesage Admin Panel API Functions
 * This file contains all the JavaScript functions to interact with the backend API
 */

// Products API Functions
const productsAPI = {
    // Get all products with optional filters
    getProducts: async (page = 1, search = '', category = '', status = '', minStockStatus = '') => {
        try {
            const params = new URLSearchParams();
            params.append('page', page);
            params.append('search', search);
            params.append('category', category);
            params.append('status', status);
            if (minStockStatus) {
                params.append('min_stock_status', minStockStatus);
            }
            
            const response = await fetch(`../../backend/php/admin/api/get_products.php?${params}`, {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching products:', error);
            return { success: false, message: 'Failed to fetch products' };
        }
    },

    // Get details for a specific product
    getProductDetails: async (productId) => {
        try {
            const response = await fetch(`../../backend/php/admin/api/get_products.php?product_id=${productId}`, {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching product details:', error);
            return { success: false, message: 'Failed to fetch product details' };
        }
    },
    // Update an existing product
    updateProduct: async (productId, productData) => {
        try {
            const response = await fetch(`../../backend/php/admin/api/update_product.php?id=${productId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(productData),
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating product:', error);
            return { success: false, message: 'Error updating product.' };
        }
    },
    
    // Add a new product
    addProduct: async (formData) => {
        try {
            const response = await fetch('../../backend/php/admin/actions/add_product.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error adding product:', error);
            return { success: false, message: 'Failed to add product' };
        }
    },
    

    
    // Edit an existing product
    editProduct: async (formData) => {
        try {
            const response = await fetch('../../backend/php/admin/actions/edit_product.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            
            // Check if response is ok
            if (!response.ok) {
                console.error('HTTP error:', response.status, response.statusText);
                return { success: false, message: 'Server error: ' + response.status };
            }
            
            // Get response text first to check if it's valid JSON
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            try {
                return JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Invalid JSON response:', responseText);
                return { success: false, message: 'Invalid server response. Check server logs.' };
            }
        } catch (error) {
            console.error('Error editing product:', error);
            return { success: false, message: 'Failed to edit product: ' + error.message };
        }
    },
    
    // Delete a product
    deleteProduct: async (productId) => {
        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            
            const response = await fetch('../../backend/php/admin/actions/delete_product.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting product:', error);
            return { success: false, message: 'Failed to delete product' };
        }
    },
    
    // Update product status (active/inactive)
    updateProductStatus: async (productId, isActive) => {
        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('is_active', isActive ? 1 : 0);
            
            const response = await fetch('../../backend/php/admin/actions/update_product_status.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating product status:', error);
            return { success: false, message: 'Failed to update product status' };
        }
    }
};

// Reviews API Functions
const reviewsAPI = {
    getReviews: async (page = 1, search = '', status = '') => {
        try {
            const params = new URLSearchParams();
            params.append('page', page);
            params.append('search', search);
            params.append('status', status);
            const response = await fetch(`../../backend/php/admin/api/get_reviews.php?${params}`, {
                credentials: 'include'
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching reviews:', error);
            return { success: false, message: 'Failed to fetch reviews' };
        }
    },

    getReviewDetails: async (reviewId) => {
        try {
            const response = await fetch(`../../backend/php/admin/api/get_review_details.php?review_id=${reviewId}`, {
                credentials: 'include'
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching review details:', error);
            return { success: false, message: 'Failed to fetch review details' };
        }
    },

    updateReviewStatus: async (reviewId, status) => {
        try {
            const formData = new FormData();
            formData.append('review_id', reviewId);
            formData.append('status', status);
            const response = await fetch('../../backend/php/admin/actions/update_review_status.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating review status:', error);
            return { success: false, message: 'Failed to update review status' };
        }
    },

    deleteReview: async (reviewId) => {
        try {
            const formData = new FormData();
            formData.append('review_id', reviewId);
            const response = await fetch('../../backend/php/admin/actions/delete_review.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting review:', error);
            return { success: false, message: 'Failed to delete review' };
        }
    }
};

// Categories API Functions
const categoriesAPI = {
    // Get all categories
    getCategories: async (parentId = '') => {
        try {
            const params = new URLSearchParams();
            if (parentId !== '') {
                params.append('parent_id', parentId);
            }
            const response = await fetch(`../../backend/php/admin/api/get_categories.php?${params}`, {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching categories:', error);
            return { success: false, message: 'Failed to fetch categories' };
        }
    },
    
    // Add a new category
    addCategory: async (formData) => {
        try {
            const response = await fetch('../../backend/php/admin/actions/add_category.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error adding category:', error);
            return { success: false, message: 'Failed to add category' };
        }
    },
    
    // Edit an existing category
    editCategory: async (formData) => {
        try {
            const response = await fetch('../../backend/php/admin/actions/edit_category.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error editing category:', error);
            return { success: false, message: 'Failed to edit category' };
        }
    },
    
    // Delete a category
    deleteCategory: async (categoryId, forceDelete = false) => {
        try {
            const formData = new FormData();
            formData.append('category_id', categoryId);
            if (forceDelete) {
                formData.append('force_delete', 1);
            }
            
            const response = await fetch('../../backend/php/admin/actions/delete_category.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting category:', error);
            return { success: false, message: 'Failed to delete category' };
        }
    }
};

// Orders API Functions
const ordersAPI = {
    // Get all orders with optional filters
    getOrders: async (page = 1, search = '', status = '', dateFrom = '', dateTo = '') => {
        try {
            const params = new URLSearchParams({
                page,
                search,
                status,
                date_from: dateFrom,
                date_to: dateTo
            });
            const response = await fetch(`../../backend/php/admin/api/get_orders.php?${params}`, {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching orders:', error);
            return { success: false, message: 'Failed to fetch orders' };
        }
    },
    
    // Get details for a specific order
    getOrderDetails: async (orderId) => {
        try {
            const response = await fetch(`../../backend/php/admin/api/get_order_details.php?order_id=${orderId}`, {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching order details:', error);
            return { success: false, message: 'Failed to fetch order details' };
        }
    },
    
    // Update order status
    updateOrderStatus: async (orderId, status, notes = '') => {
        try {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', status);
            formData.append('notes', notes);
            
            const response = await fetch('../../backend/php/admin/actions/update_order_status.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating order status:', error);
            return { success: false, message: 'Failed to update order status' };
        }
    }
};

// Users API Functions
const usersAPI = {
    // Get all users with optional filters
    getUsers: async (page = 1, search = '', status = '') => {
        try {
            const params = new URLSearchParams({
                page,
                search,
                status
            });
            const response = await fetch(`../../backend/php/admin/api/get_users.php?${params}`, {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching users:', error);
            return { success: false, message: 'Failed to fetch users' };
        }
    },
    
    // Add a new user
    addUser: async (formData) => {
        try {
            const response = await fetch('../../backend/php/admin/actions/add_user.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error adding user:', error);
            return { success: false, message: 'Failed to add user' };
        }
    },
    
    // Edit an existing user
    editUser: async (formData) => {
        try {
            const response = await fetch('../../backend/php/admin/actions/edit_user.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error editing user:', error);
            return { success: false, message: 'Failed to edit user' };
        }
    },
    
    // Delete a user
    deleteUser: async (userId, forceDelete = false) => {
        try {
            const formData = new FormData();
            formData.append('user_id', userId);
            if (forceDelete) {
                formData.append('force_delete', 1);
            }
            
            const response = await fetch('../../backend/php/admin/actions/delete_user.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Include cookies to maintain PHP session
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting user:', error);
            return { success: false, message: 'Failed to delete user' };
        }
    }
};

// Utility Functions
const adminUtils = {
    // Show notification
    showNotification: (message, type = 'success') => {
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
        
        // Add active class after a small delay for animation
        setTimeout(() => notification.classList.add('active'), 10);
        
        // Close button functionality
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.remove('active');
            setTimeout(() => notification.remove(), 300); // Wait for animation to complete
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (document.body.contains(notification)) {
                notification.classList.remove('active');
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    },
    
    // Format currency
    formatCurrency: (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    },
    
    // Format date
    formatDate: (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
};