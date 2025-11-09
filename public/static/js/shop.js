document.addEventListener('DOMContentLoaded', function() {
    // Store products data globally
    let products = [];
    let currentCategory = 'all';
    let currentPage = 1;
    let totalPages = 1;
    const productsPerPage = 12;
    
    // Fetch products from API
    function fetchProducts(category = 'all', page = 1) {
        // Show loading indicator
        const productGrid = document.getElementById('productGrid');
        productGrid.innerHTML = '<div class="loading">Loading products...</div>';
        
        // Build API URL
        let apiUrl = '/leesage/backend/php/public/api/products.php?action=list&page=' + page + '&limit=' + productsPerPage;
        
        // Add category filter if not 'all'
        if (category !== 'all') {
            apiUrl += '&category=' + category;
        }
        
        // Fetch products from API
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data); // Debug log
                if (data.success) {
                    products = data.data.products; // Fixed: access data.products instead of data.products
                    totalPages = data.data.pagination.total_pages; // Fixed: access data.pagination
                    currentPage = data.data.pagination.current_page; // Fixed: access data.pagination
                    
                    // Render products
                    renderProducts(products);
                    
                    // Render pagination
                    renderPagination();
                } else {
                    throw new Error(data.message || 'Failed to fetch products');
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                productGrid.innerHTML = `<div class="error">Failed to load products. ${error.message}</div>`;
            });
    }

    const productGrid = document.getElementById('productGrid');
    const filterButtons = document.querySelectorAll('.filter-btn');

    // Function to render products
    function renderProducts(productsList) {
        productGrid.innerHTML = '';
        
        if (productsList.length === 0) {
            productGrid.innerHTML = '<div class="no-products">No products found</div>';
            return;
        }
        
        productsList.forEach(product => {
            // Get image path or use placeholder
            const imagePath = product.primary_image 
                ? `/leesage/${product.primary_image}` 
                : '../../assets/images/placeholder.jpg';
                
            // Get product price - use discount_price if available
            const displayPrice = product.discount_price && parseFloat(product.discount_price) > 0
                ? `<span class="original-price">₹${parseFloat(product.price).toFixed(2)}</span> ₹${parseFloat(product.discount_price).toFixed(2)}`
                : `₹${parseFloat(product.price).toFixed(2)}`;
                
            const productCard = `
                <div class="product-card" data-category="${product.category_id || ''}">
                    <a href="product.html?id=${product.id}">
                        <div class="image-container">
                            <img src="${imagePath}" alt="${product.name}" class="product-image">
                        </div>
                        <div class="product-info">
                            <h3 class="product-title">${product.name}</h3>
                            <p class="product-price">${displayPrice}</p>
                        </div>
                    </a>
                    <div class="product-actions">
                        <a href="product.html?id=${product.id}" class="btn btn-buy-now">Buy Now</a>
                        <button type="button" class="btn btn-add-cart" onclick="addToCart(${product.id}, '${product.name}', ${product.discount_price || product.price}, '${imagePath}')">Add to Cart</button>
                    </div>
                </div>
            `;
            productGrid.insertAdjacentHTML('beforeend', productCard);
        });
    }
    
    // Function to render pagination
    function renderPagination() {
        const paginationContainer = document.getElementById('pagination') || createPaginationContainer();
        paginationContainer.innerHTML = '';
        
        if (totalPages <= 1) {
            return;
        }
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.classList.add('pagination-btn');
        prevBtn.innerHTML = '&laquo; Previous';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                fetchProducts(currentCategory, currentPage - 1);
            }
        });
        paginationContainer.appendChild(prevBtn);
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.classList.add('pagination-btn');
            if (i === currentPage) {
                pageBtn.classList.add('active');
            }
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                if (i !== currentPage) {
                    fetchProducts(currentCategory, i);
                }
            });
            paginationContainer.appendChild(pageBtn);
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.classList.add('pagination-btn');
        nextBtn.innerHTML = 'Next &raquo;';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                fetchProducts(currentCategory, currentPage + 1);
            }
        });
        paginationContainer.appendChild(nextBtn);
    }
    
    // Create pagination container if it doesn't exist
    function createPaginationContainer() {
        const container = document.createElement('div');
        container.id = 'pagination';
        container.classList.add('pagination');
        productGrid.parentNode.insertBefore(container, productGrid.nextSibling);
        return container;
    }

    // Filter products by category
    function filterProducts(category) {
        currentCategory = category;
        currentPage = 1; // Reset to first page when changing category
        fetchProducts(category, 1);
    }

    // Add click event listeners to filter buttons
    filterButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            button.classList.add('active');
            // Filter products
            filterProducts(button.getAttribute('data-category'));
        });
    });

    // Cart functionality
    window.addToCart = function(productId, productName, productPrice, productImage) {
        // Check if user is logged in
        fetch('/leesage/backend/php/public/auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                if (data.data.isLoggedIn) {
                    // User is logged in, add to cart via API
                    addToCartAPI(productId, 1);
                } else {
                    // User is not logged in, redirect to login page with product data
                    const productData = {
                        id: productId,
                        name: productName,
                        price: productPrice,
                        image: productImage,
                        action: 'add_to_cart'
                    };
                    
                    // Store product data in sessionStorage for after login
                    sessionStorage.setItem('pending_cart_item', JSON.stringify(productData));
                    
                    // Redirect to login page
                    window.location.href = '/leesage/public/pages/login.html?redirect=shop&action=add_to_cart';
                }
            })
            .catch(error => {
                console.error('Error checking login status:', error);
                // Show error and redirect to login
                showNotification('Please login to add items to cart', 'error');
                setTimeout(() => {
                    window.location.href = '/leesage/public/pages/login.html?redirect=shop';
                }, 2000);
            });
    };
    
    // Add to cart via API (for logged in users)
    function addToCartAPI(productId, quantity) {
        fetch('/leesage/backend/php/public/api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity
            }),
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.data.item_count);
                showNotification('Product added to cart!');
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            showNotification('Failed to add product to cart', 'error');
        });
    }
    
    // Add to cart via localStorage (for guests)
    function addToCartLocal(productId, productName, productPrice, productImage) {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        
        // Check if product already exists in cart
        const existingProductIndex = cart.findIndex(item => item.id === productId);
        
        if (existingProductIndex >= 0) {
            // Product exists, increase quantity
            cart[existingProductIndex].quantity += 1;
        } else {
            // Product doesn't exist, add new item
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                image: productImage,
                quantity: 1
            });
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartCount(cart.reduce((total, item) => total + item.quantity, 0));
        showNotification('Product added to cart!');
    }
    
    // Update cart count in header
    function updateCartCount(count) {
        const cartIcon = document.querySelector('.cart-icon');
        if (!cartIcon) return;
        
        if (count !== undefined) {
            cartIcon.setAttribute('data-count', count);
            return;
        }
        
        // If count not provided, fetch from API for logged-in users
        fetch('/leesage/backend/php/public/auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                if (data.data.isLoggedIn) {
                    // Fetch cart count from API for logged-in users
                    fetch('/leesage/backend/php/public/api/cart.php')
                        .then(response => response.json())
                        .then(cartData => {
                            if (cartData.success) {
                                cartIcon.setAttribute('data-count', cartData.data.item_count || 0);
                            } else {
                                cartIcon.setAttribute('data-count', 0);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching cart count:', error);
                            cartIcon.setAttribute('data-count', 0);
                        });
                } else {
                    // For guest users, use localStorage
                    const cart = JSON.parse(localStorage.getItem('cart')) || {};
                    let itemCount = 0;
                    for (const productId in cart) {
                        if (cart.hasOwnProperty(productId)) {
                            itemCount += cart[productId].quantity || 0;
                        }
                    }
                    cartIcon.setAttribute('data-count', itemCount);
                }
            })
            .catch(error => {
                console.error('Error checking login status:', error);
                cartIcon.setAttribute('data-count', 0);
            });
    }
    
    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('hide');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 500);
        }, 3000);
    }

    // Check if we have a success message from login redirect
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('cart_added') === 'true') {
        showNotification('Product successfully added to cart after login!', 'success');
        // Remove the parameter from URL without reloading
        const newUrl = window.location.pathname;
        window.history.replaceState({}, '', newUrl);
    }

    // Initial load
    fetchProducts('all', 1);
    updateCartCount();
});
