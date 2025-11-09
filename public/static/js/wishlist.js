document.addEventListener('DOMContentLoaded', function() {
    // Initialize DOM elements
    const wishlistContainer = document.querySelector('.wishlist-container');
    
    // Only proceed if we're on the wishlist page
    if (!wishlistContainer) {
        // Export functions for use in other scripts even if not on wishlist page
        initializeWishlistFunctions();
        return;
    }
    
    const wishlistTableBody = document.querySelector('.wishlist-container tbody');
    const wishlistSummaryTotal = document.querySelector('.wishlist-summary strong');
    const emptyWishlistMessage = document.querySelector('.empty-wishlist');
    const shopButton = document.querySelector('.shop-btn');
    
    // Create loading and error elements
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator';
    loadingIndicator.innerHTML = '<div class="loading-spinner"></div><p>Loading your wishlist...</p>';
    
    const errorMessage = document.createElement('div');
    errorMessage.className = 'error-message';
    
    // Insert loading and error elements into the DOM
    wishlistContainer.insertBefore(loadingIndicator, wishlistContainer.firstChild.nextSibling);
    wishlistContainer.insertBefore(errorMessage, loadingIndicator.nextSibling);
    
    // Create login required notice
    const loginRequiredNotice = document.createElement('div');
    loginRequiredNotice.className = 'login-required-notice';
    loginRequiredNotice.innerHTML = '<p>Please <a href="login.html">log in</a> to use the wishlist feature.</p>';
    
    let wishlist = [];
    
    // Check if user is logged in
    let isLoggedIn = false;
    
    // Initialize wishlist
    showLoading();
    checkLoginStatus().then(() => {
        if (isLoggedIn) {
            fetchWishlist().then(() => {
                hideLoading();
            }).catch(error => {
                console.error('Error fetching wishlist:', error);
                showError('Failed to load your wishlist. Please try again.');
                hideLoading();
            });
        } else {
            // Show login required notice for guest users
            wishlistContainer.insertBefore(loginRequiredNotice, errorMessage.nextSibling);
            hideLoading();
            emptyWishlistMessage.style.display = 'none';
            if (wishlistTableBody) wishlistTableBody.parentElement.style.display = 'none';
            if (wishlistSummaryTotal) wishlistSummaryTotal.parentElement.style.display = 'none';
        }
    });
    
    // Check login status
    function checkLoginStatus() {
        return fetch('/leesage/backend/php/public/auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                isLoggedIn = data.logged_in;
                return isLoggedIn;
            })
            .catch(error => {
                console.error('Error checking login status:', error);
                isLoggedIn = false;
                return false;
            });
    }
    
    // Fetch wishlist data from API
    function fetchWishlist() {
        hideError();
        
        if (!isLoggedIn) {
            return Promise.resolve([]);
        }
        
        return fetch('/leesage/backend/php/public/api/wishlist.php', {
            method: 'GET',
            credentials: 'include'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch wishlist data');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                wishlist = data.items || [];
                renderWishlist(wishlist);
                return wishlist;
            } else {
                throw new Error(data.message || 'Failed to fetch wishlist');
            }
        })
        .catch(error => {
            console.error('Error fetching wishlist:', error);
            showError('Failed to load your wishlist. Please try again.');
            return [];
        });
    }
    
    // Add item to wishlist
    function addToWishlist(productId) {
        if (!isLoggedIn) {
            showLoginRequiredNotice();
            return Promise.resolve(false);
        }
        
        showLoading();
        hideError();
        
        const formData = new FormData();
        formData.append('product_id', productId);
        
        return fetch('/leesage/backend/php/public/api/wishlist.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                // Show success message
                showNotification('Product added to wishlist!');
                return fetchWishlist(); // Refresh wishlist data
            } else {
                showError(data.message || 'Failed to add item to wishlist');
                return false;
            }
        })
        .catch(error => {
            console.error('Error adding to wishlist:', error);
            hideLoading();
            showError('Error adding item to wishlist. Please try again.');
            return false;
        });
    }
    
    // Remove item from wishlist
    function removeFromWishlist(productId) {
        if (!isLoggedIn) {
            showLoginRequiredNotice();
            return Promise.resolve(false);
        }
        
        showLoading();
        hideError();
        
        const formData = new FormData();
        formData.append('product_id', productId);
        
        return fetch('/leesage/backend/php/public/api/wishlist.php', {
            method: 'DELETE',
            body: formData,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showNotification('Product removed from wishlist!');
                return fetchWishlist(); // Refresh wishlist data
            } else {
                showError(data.message || 'Failed to remove item from wishlist');
                return false;
            }
        })
        .catch(error => {
            console.error('Error removing from wishlist:', error);
            hideLoading();
            showError('Error removing item from wishlist. Please try again.');
            return false;
        });
    }
    
    // Add wishlist item to cart
    function addWishlistItemToCart(productId) {
        showLoading();
        hideError();
        
        // First check if we have a cart.js function to call
        if (typeof addToCart === 'function') {
            return addToCart(productId, 1).then(result => {
                hideLoading();
                if (result) {
                    showNotification('Product added to cart!');
                    return true;
                } else {
                    showError('Failed to add item to cart');
                    return false;
                }
            });
        } else {
            // Fallback if cart.js is not loaded or addToCart is not available
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            return fetch('/leesage/backend/php/public/api/cart.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification('Product added to cart!');
                    // Update cart icon if possible
                    if (typeof updateCartIcon === 'function') {
                        updateCartIcon(data.data.item_count);
                    }
                    return true;
                } else {
                    showError(data.message || 'Failed to add item to cart');
                    return false;
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                hideLoading();
                showError('Error adding item to cart. Please try again.');
                return false;
            });
        }
    }
    
    // Show loading indicator
    function showLoading() {
        loadingIndicator.classList.add('active');
        if (wishlistTableBody) wishlistTableBody.style.display = 'none';
        if (emptyWishlistMessage) emptyWishlistMessage.style.display = 'none';
        if (wishlistSummaryTotal && wishlistSummaryTotal.parentElement) {
            wishlistSummaryTotal.parentElement.style.display = 'none';
        }
        if (shopButton) shopButton.style.display = 'none';
    }
    
    // Hide loading indicator
    function hideLoading() {
        loadingIndicator.classList.remove('active');
        if (wishlistSummaryTotal && wishlistSummaryTotal.parentElement) {
            wishlistSummaryTotal.parentElement.style.display = 'block';
        }
        if (shopButton) shopButton.style.display = 'block';
    }
    
    // Show error message
    function showError(message) {
        errorMessage.textContent = message || 'An error occurred while loading your wishlist. Please try again.';
        errorMessage.classList.add('active');
    }
    
    // Hide error message
    function hideError() {
        errorMessage.classList.remove('active');
    }
    
    // Show login required notice
    function showLoginRequiredNotice() {
        if (!document.querySelector('.login-required-notice')) {
            wishlistContainer.insertBefore(loginRequiredNotice, errorMessage.nextSibling);
        }
        loginRequiredNotice.style.display = 'block';
    }
    
    // Show notification
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    }
    
    // Render wishlist
    function renderWishlist(items) {
        // Clear existing wishlist items
        if (wishlistTableBody) wishlistTableBody.innerHTML = '';
        
        const wishlistIsEmpty = !items || items.length === 0;

        if (!wishlistIsEmpty) {
            items.forEach(item => {
                const product = item;
                const productId = item.product_id;
                const price = product.sale_price || product.price;
                const stockStatus = product.in_stock ? 'In Stock' : 'Out of Stock';
                const stockClass = product.in_stock ? 'in-stock' : 'out-of-stock';

                const row = document.createElement('tr');
                row.setAttribute('data-product-id', productId);
                row.innerHTML = `
                    <td><img src="${product.image}" alt="${product.name}" class="product-image" onerror="this.onerror=null;this.src='/leesage/assets/images/placeholder.jpg';"></td>
                    <td><a href="product.html?id=${productId}">${product.name}</a></td>
                    <td>${product.on_sale ? 
                        `<span class="original-price">₹${product.price.toFixed(2)}</span> ₹${price.toFixed(2)}` :
        `₹${price.toFixed(2)}`}</td>
                    <td class="${stockClass}">${stockStatus}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="remove-btn">Remove</button>
                            <button class="add-to-cart-btn" ${!product.in_stock ? 'disabled' : ''}>Add to Cart</button>
                        </div>
                    </td>
                `;
                wishlistTableBody.appendChild(row);
            });
        }

        wishlistSummaryTotal.textContent = `Total Items: ${items.length}`;

        if (wishlistIsEmpty) {
            emptyWishlistMessage.style.display = 'block';
            wishlistTableBody.parentElement.style.display = 'none'; // Hide table
            wishlistSummaryTotal.parentElement.style.display = 'none'; // Hide summary
        } else {
            emptyWishlistMessage.style.display = 'none';
            wishlistTableBody.parentElement.style.display = 'table'; // Show table
            wishlistSummaryTotal.parentElement.style.display = 'block'; // Show summary
        }

        attachEventListeners();
    }

    function attachEventListeners() {
        // Add event listeners to remove buttons
        wishlistTableBody.querySelectorAll('.remove-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const productId = row.getAttribute('data-product-id');
                
                removeFromWishlist(productId).then(success => {
                    if (success) {
                        // Item removed, wishlist will be refreshed by removeFromWishlist
                    }
                });
            });
        });

        // Add event listeners to add-to-cart buttons
        wishlistTableBody.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const productId = row.getAttribute('data-product-id');
                
                addWishlistItemToCart(productId).then(success => {
                    if (success) {
                        // Optional: Ask if user wants to remove item from wishlist after adding to cart
                        if (confirm('Item added to cart. Would you like to remove it from your wishlist?')) {
                            removeFromWishlist(productId);
                        }
                    }
                });
            });
        });
    }

    // Export functions for use in other scripts
    initializeWishlistFunctions();
});

// Initialize wishlist functions for use in other scripts
function initializeWishlistFunctions() {
    // Check if user is logged in
    let isLoggedIn = false;
    
    // Check login status function
    function checkLoginStatus() {
        return fetch('/leesage/backend/php/public/auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                isLoggedIn = data.logged_in;
                return isLoggedIn;
            })
            .catch(error => {
                console.error('Error checking wishlist status:', error);
                isLoggedIn = false;
                return false;
            });
    }
    
    // Add to wishlist function
    function addToWishlist(productId) {
        return checkLoginStatus().then(() => {
            if (!isLoggedIn) {
                alert('Please log in to add items to your wishlist');
                return Promise.resolve(false);
            }
            
            const formData = new FormData();
            formData.append('product_id', productId);
            
            return fetch('/leesage/backend/php/public/api/wishlist.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showNotification('Product added to wishlist!');
                    return true;
                } else {
                    alert(data.message || 'Failed to add item to wishlist');
                    return false;
                }
            })
            .catch(error => {
                console.error('Error adding to wishlist:', error);
                alert('Error adding item to wishlist. Please try again.');
                return false;
            });
        });
    }
    
    // Remove from wishlist function
    function removeFromWishlist(productId) {
        return checkLoginStatus().then(() => {
            if (!isLoggedIn) {
                alert('Please log in to manage your wishlist');
                return Promise.resolve(false);
            }
            
            const formData = new FormData();
            formData.append('product_id', productId);
            
            return fetch('/leesage/backend/php/public/api/wishlist.php', {
                method: 'DELETE',
                body: formData,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Product removed from wishlist!');
                    return true;
                } else {
                    alert(data.message || 'Failed to remove item from wishlist');
                    return false;
                }
            })
            .catch(error => {
                console.error('Error removing from wishlist:', error);
                alert('Error removing item from wishlist. Please try again.');
                return false;
            });
        });
    }
    
    // Check wishlist status function
    function checkWishlistStatus(productId) {
        return checkLoginStatus().then(() => {
            if (!isLoggedIn) return false;
            
            return fetch(`/leesage/backend/php/public/api/wishlist.php?check=${productId}`, {
                method: 'GET',
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                return data.success && data.in_wishlist;
            })
            .catch(error => {
                console.error('Error checking wishlist status:', error);
                return false;
            });
        });
    }
    
    // Show notification function
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    }
    
    // Export functions to window object
    window.wishlistFunctions = {
        addToWishlist,
        removeFromWishlist,
        checkWishlistStatus
    };
}