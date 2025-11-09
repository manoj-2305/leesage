let cartTableBody = document.querySelector('.cart-container tbody');
let cartSummary = document.querySelector('.cart-summary strong');
let emptyCartMessage = document.querySelector('.empty-cart');
let checkoutButton = document.querySelector('.checkout-btn');

// Create loading and error elements globally
const loadingIndicator = document.createElement('div');
loadingIndicator.className = 'loading-indicator';
loadingIndicator.innerHTML = '<div class="loading-spinner"></div><p>Loading your cart...</p>';

const errorMessage = document.createElement('div');
errorMessage.className = 'error-message';

document.addEventListener('DOMContentLoaded', function() {
    console.log('cart.js script started.');
    
    // Insert loading and error elements into the DOM
    const cartContainer = document.querySelector('.cart-container');
    if (cartContainer) {
        cartContainer.insertBefore(loadingIndicator, cartContainer.firstChild.nextSibling);
        cartContainer.insertBefore(errorMessage, loadingIndicator.nextSibling);
    }
    
    // Create guest user notice
    const guestUserNotice = document.createElement('div');
    guestUserNotice.className = 'guest-user-notice';
    guestUserNotice.innerHTML = '<p>You are shopping as a guest. <a href="login.html">Log in</a> to sync your cart across devices.</p>';
    
    let cart = {};
    
    // Check if user is logged in
    let isLoggedIn = false;
    
    // Initialize cart
    showLoading();
    checkLoginStatus().then(async () => {
        try {
            const cartData = await fetchCart();
            hideLoading();
        } catch (error) {
            console.error('Error fetching cart:', error);
            showError('Failed to load your cart. Please try again.');
            hideLoading();
        }
    });
    
    // Check login status
    function checkLoginStatus() {
        return fetch('/leesage/backend/php/public/auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                isLoggedIn = data.data.isLoggedIn;
                return isLoggedIn;
            })
            .catch(error => {
                console.error('Error checking login status:', error);
                isLoggedIn = false;
                return false;
            });
    }
    
    // Fetch cart data from API or localStorage
    async function fetchCart() {
        hideError();
        let cartData = {};

        try {
            const loggedIn = await checkLoginStatus();

            if (loggedIn) {
                // Fetch cart from API for logged-in users
                const response = await fetch('/leesage/backend/php/public/api/cart.php', {
                    credentials: 'include'
                });
                if (!response.ok) {
                    throw new Error('Failed to fetch cart data');
                }
                const data = await response.json();
                if (data.success) {
                    console.log('Cart data from API:', data.data);
                    cartData = data.data;
                } else {
                    throw new Error(data.message || 'Failed to fetch cart');
                }
            } else {
                // Use localStorage for guest users
                if (!document.querySelector('.guest-user-notice')) {
                    cartContainer.insertBefore(guestUserNotice, errorMessage.nextSibling);
                }
                const localCart = getLocalCart();
                
                // If localCart has items, fetch product details for them
                if (localCart && Object.keys(localCart).length > 0) {
                    const productIds = Object.keys(localCart);
                    const fetchPromises = productIds.map(async productId => {
                        const response = await fetch(`/leesage/backend/php/public/api/products.php?action=detail&id=${productId}`);
                        if (!response.ok) {
                            throw new Error(`Failed to fetch product details for ID ${productId}`);
                        }
                        const productDetails = await response.json();
                        if (productDetails.success) {
                            return {
                                id: `local-${productId}`, // Assign a unique ID for local cart items
                                product_id: productId,
                                quantity: localCart[productId],
                                product: productDetails.data,
                                size: null // Assuming no size information stored in localStorage for simplicity
                            };
                        } else {
                            console.error(`Error fetching details for product ${productId}:`, productDetails.message);
                            return null;
                        }
                    });

                    const fetchedItems = await Promise.all(fetchPromises);
                    cartData = {
                        items: fetchedItems.filter(item => item !== null),
                        item_count: fetchedItems.filter(item => item !== null).length,
                        subtotal: 0, // Will be calculated by renderCart
                        tax: 0,
                        shipping: 0,
                        total: 0 // Will be calculated by renderCart
                    };
                } else {
                    cartData = { items: [], item_count: 0, subtotal: 0, tax: 0, shipping: 0, total: 0 };
                }
            }
        } catch (error) {
            console.error('Error in fetchCart:', error);
            showError('Failed to load your cart. Please try again.');
            // Fallback to empty cart data on error
            cartData = { items: [], item_count: 0, subtotal: 0, tax: 0, shipping: 0, total: 0 };
        }

        // Render cart after all data is fetched and processed
        await renderCart(cartData);
        return cartData;
    }
    
    // Get cart from localStorage
    function getLocalCart() {
        try {
            const savedCart = localStorage.getItem('cart');
            if (savedCart) {
                return JSON.parse(savedCart);
            } else {
                return {};
            }
        } catch (error) {
            console.error('Error parsing local cart:', error);
            return {};
        }
    }
    // Create loading indicator
    function showLoading() {
        loadingIndicator.classList.add('active');
        if (cartTableBody) cartTableBody.style.display = 'none';
        if (emptyCartMessage) emptyCartMessage.style.display = 'none';
        if (document.querySelector('.cart-summary')) document.querySelector('.cart-summary').style.display = 'none';
        if (checkoutButton) checkoutButton.style.display = 'none';
    }
    
    // Hide loading indicator
    function hideLoading() {
        loadingIndicator.classList.remove('active');
        if (document.querySelector('.cart-summary')) document.querySelector('.cart-summary').style.display = 'block';
    }
    
    // Show error message
    function showError(message) {
        errorMessage.textContent = message || 'An error occurred while loading your cart. Please try again.';
        errorMessage.classList.add('active');
    }
    
    // Hide error message
    function hideError() {
        errorMessage.classList.remove('active');
    }
    
    // Add item to cart
    function addToCart(productId, quantity = 1) {
        showLoading();
        hideError();
        
        return checkLoginStatus().then(loggedIn => {
            if (loggedIn) {
                // Add to cart via API
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('quantity', quantity);
                
                return fetch('/leesage/backend/php/public/api/cart.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        // Show success message
                        alert('Product added to cart!');
                        updateCartIcon(data.data.item_count);
                        return fetchCart(); // Refresh cart data
                    } else {
                        showError(data.message || 'Failed to add item to cart');
                        return null;
                    }
                })
                .catch(error => {
                    console.error('Error adding to cart:', error);
                    hideLoading();
                    showError('Error adding item to cart. Please try again.');
                    return null;
                });
            } else {
                // Add to localStorage for guest users
                cart[productId] = (cart[productId] || 0) + quantity;
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartIcon();
                hideLoading();
                // Show success message
                alert('Product added to cart!');
                return fetchCart(); // Refresh cart data
            }
        });
    }
    
    // Update cart item quantity
    function updateCartItem(itemId, quantity, sizeId = null) {
        showLoading();
        hideError();

        return checkLoginStatus().then(loggedIn => {
            if (loggedIn) {
                // Update cart via API
                const requestData = {
                    item_id: itemId,
                    quantity: quantity
                };
                if (sizeId) {
                    requestData.size_id = sizeId;
                }

                return fetch('/leesage/backend/php/public/api/cart.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData),
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        updateCartIcon(data.data.item_count);
                        return fetchCart(); // Refresh cart data
                    } else {
                        showError(data.message || 'Failed to update cart');
                        return null;
                    }
                })
                .catch(error => {
                    console.error('Error updating cart:', error);
                    hideLoading();
                    showError('Error updating cart. Please try again.');
                    return null;
                });
            } else {
                // Update localStorage for guest users
                let cart = JSON.parse(localStorage.getItem('cart') || '{}');
                // For guest users, itemId is 'local-productId', so extract productId
                const productId = itemId.replace('local-', '');
                if (cart[productId] !== undefined) {
                    if (quantity <= 0) {
                        delete cart[productId];
                    } else {
                        cart[productId] = quantity;
                    }
                } else {
                    console.warn('Attempted to update a non-existent item in guest cart:', itemId);
                }
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartIcon();
                hideLoading();
                return fetchCart(); // Refresh cart data
            }
        });
    }
    
    // Remove item from cart
    function removeFromCart(itemId, sizeId = null) {
        showLoading();
        hideError();

        return checkLoginStatus().then(loggedIn => {
            if (loggedIn) {
                // Remove from cart via API
                const requestData = {
                    item_id: itemId
                };
                if (sizeId) {
                    requestData.size_id = sizeId;
                }

                return fetch('/leesage/backend/php/public/api/cart.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData),
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        updateCartIcon(data.data.item_count);
                        return fetchCart(); // Refresh cart data
                    } else {
                        showError(data.message || 'Failed to remove item from cart');
                        return null;
                    }
                })
                .catch(error => {
                    console.error('Error removing from cart:', error);
                    hideLoading();
                    showError('Error removing item from cart. Please try again.');
                    return null;
                });
            } else {
                // Remove from localStorage for guest users
                let cart = JSON.parse(localStorage.getItem('cart') || '{}');
                // For guest users, itemId is 'local-productId', so extract productId
                const productId = itemId.replace('local-', '');
                if (cart[productId] !== undefined) {
                    delete cart[productId];
                } else {
                    console.warn('Attempted to remove a non-existent item from guest cart:', itemId);
                }
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartIcon();
                hideLoading();
                return fetchCart(); // Refresh cart data
            }
        });
    }

    async function renderCart(cartData) {
        console.log('Rendering cart with data:', cartData);
        // Clear existing cart items
        if (cartTableBody) {
            cartTableBody.innerHTML = '';
            console.log('cartTableBody found and cleared.');
        } else {
            console.error('cartTableBody not found!');
        }
        
        let cartItems = [];
        let cartTotal = 0;
        
        if (cartData && cartData.items && Array.isArray(cartData.items)) {
            cartItems = cartData.items;
            cartTotal = cartData.total || 0;
        }
        
        console.log('Cart items to render:', cartItems);
        let cartIsEmpty = !cartItems || cartItems.length === 0;

        let subtotal = 0;
        cartItems.forEach(item => {
            const price = parseFloat(item.product.sale_price) || parseFloat(item.product.price);
            subtotal += price * item.quantity;
        });

        const taxRate = 0.10; // 10% tax rate
        const tax = subtotal * taxRate;
        const shipping = 0; // As per your requirement
        const finalTotal = subtotal + tax + shipping;

        if (!cartIsEmpty) {
            cartItems.forEach(item => {
                const product = item.product;
                const quantity = item.quantity;
                const productId = item.product_id;
                const price = parseFloat(product.sale_price) || parseFloat(product.price);
                const itemSubtotal = price * quantity;

                const row = document.createElement('tr');
                row.setAttribute('data-product-id', productId);
                row.setAttribute('data-size-id', item.size_id);
                row.setAttribute('data-item-id', item.id); // Add this line
                row.innerHTML = `
                    <td><img src="../../${product.image}" alt="${product.name}" class="img-fluid rounded-3" style="width: 120px;"></td>
                    <td>${product.name}</td>
                    <td>₹${price.toFixed(2)}</td>
                    <td>
                        <div class="quantity-controls">
                            <button class="qty-minus">-</button>
                            <input type="number" class="qty-input" value="${quantity}" min="1" max="${item.stock_quantity || 99}">
                            <button class="qty-plus">+</button>
                        </div>
                    </td>
                    <td>₹${itemSubtotal.toFixed(2)}</td>
                    <td><button class="remove-btn">Remove</button></td>
                `;
                cartTableBody.appendChild(row);
            });
        }

        cartSummary.innerHTML = `
            <p>Subtotal: ₹${subtotal.toFixed(2)}</p>
            <p>Shipping: ₹${shipping.toFixed(2)}</p>
            <p>Tax (10%): ₹${tax.toFixed(2)}</p>
            <h4>Total: ₹${finalTotal.toFixed(2)}</h4>
        `;

        if (cartIsEmpty) {
            emptyCartMessage.style.display = 'block';
            cartTableBody.style.display = 'none'; // Hide cart items body
            cartSummary.style.display = 'none'; // Hide summary
            checkoutButton.style.display = 'none'; // Hide checkout button
        } else {
            emptyCartMessage.style.display = 'none';
            cartTableBody.style.display = 'table-row-group'; // Show cart items body
            cartSummary.style.display = 'block'; // Show summary
            checkoutButton.style.display = 'block'; // Show checkout button
        }
    }
    
    // Update cart icon (e.g., number of items)
    function updateCartIcon(itemCount = 0) {
        const cartIcon = document.querySelector('.cart-icon span');
        if (cartIcon) {
            cartIcon.textContent = itemCount;
        }
    }

    // Event Listeners for quantity and remove buttons
    if (cartTableBody) {
        cartTableBody.addEventListener('click', function(event) {
            const target = event.target;
            const row = target.closest('tr[data-product-id]');
            if (!row) return;

            const productId = row.dataset.productId;
            const sizeId = row.dataset.sizeId; // Get sizeId
            const itemId = row.dataset.itemId; // Get itemId
            const qtyInput = row.querySelector('.qty-input');
            let currentQuantity = parseInt(qtyInput.value);

            if (target.classList.contains('qty-minus')) {
                if (currentQuantity > 1) {
                    updateCartItem(itemId, currentQuantity - 1, sizeId);
                }
            } else if (target.classList.contains('qty-plus')) {
                updateCartItem(itemId, currentQuantity + 1, sizeId);
            } else if (target.classList.contains('remove-btn')) {
                removeFromCart(itemId, sizeId);
            }
        });
    }
});