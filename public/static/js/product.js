// Professional Product Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Get product ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');
    
    if (!productId) {
        showError('Product ID is missing');
        return;
    }
    
    // Fetch product details
    fetchProductDetails(productId);
    // Initialize all product page features
    initSizeSelection();
    initQuantityControls();
    initWishlist();
    initImageZoom();
    initResponsiveFeatures();
    initTabs();

    // Fetch product details from API
function fetchProductDetails(productId) {
    // Show loading state
    document.querySelector('.product-container').classList.add('loading');
    
    fetch(`/leesage/backend/php/public/api/product-details.php?id=${productId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Product not found');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update product details on the page
                updateProductDetails(data.data);
                // Initialize gallery after images are loaded
                initImageGallery();
            } else {
                showError(data.message || 'Failed to load product details');
            }
        })
        .catch(error => {
            console.error('Error fetching product details:', error);
            showError('Error loading product details. Please try again later.');
        })
        .finally(() => {
            document.querySelector('.product-container').classList.remove('loading');
        });
}

// Update product details on the page
function updateProductDetails(product) {
    // Update product name
    document.getElementById('productName').textContent = product.name;
    
    // Update product ID
    document.getElementById('productId').value = product.id;
    
    // Update product price
    document.getElementById('productPrice').textContent = `₹${parseFloat(product.price).toFixed(2)}`;
    
    // Update discount price if available
    const discountPriceElement = document.getElementById('productDiscountPrice');
    if (product.discount_price && parseFloat(product.discount_price) > 0) {
        discountPriceElement.textContent = `₹${parseFloat(product.discount_price).toFixed(2)}`;
        document.getElementById('productPrice').classList.add('original-price');
        discountPriceElement.style.display = 'inline-block';
    } else {
        discountPriceElement.style.display = 'none';
    }
    
    // Update product description
    document.getElementById('productDescription').innerHTML = product.description.substring(0, 200) + '...';
    document.getElementById('fullDescription').innerHTML = product.description;
    
    // Update product meta information
    document.getElementById('productSku').textContent = product.sku || 'N/A';
    
    // Update product category
    if (product.categories && product.categories.length > 0) {
        document.getElementById('productCategory').textContent = product.categories[0].name;
    } else {
        document.getElementById('productCategory').textContent = 'Uncategorized';
    }
    
    // Update product availability
    document.getElementById('productAvailability').textContent = 
        product.stock_quantity > 0 ? 'In Stock' : 'Out of Stock';
    
    // Update product images
    updateProductImages(product.images);

    // Update product sizes
    const sizeOptionsContainer = document.getElementById('sizeOptions');
    if (sizeOptionsContainer) {
        sizeOptionsContainer.innerHTML = ''; // Clear existing options
        if (product.sizes && product.sizes.length > 0) {
            product.sizes.forEach(size => {
                const sizeOption = document.createElement('span');
                sizeOption.classList.add('size-option');
                sizeOption.textContent = size.size_name;
                sizeOption.dataset.sizeId = size.id; // Store size ID
                sizeOption.dataset.stockQuantity = size.stock_quantity; // Store stock quantity
                sizeOptionsContainer.appendChild(sizeOption);
            });
            initSizeSelection(); // Re-initialize size selection event listeners
        } else {
            sizeOptionsContainer.innerHTML = '<p>No sizes available</p>';
        }
    }
    
    // Update related products if available
    if (product.related_products && product.related_products.length > 0) {
        updateRelatedProducts(product.related_products);
    }
    
    // Update reviews if available
    if (product.reviews) {
        updateProductReviews(product.reviews);
    }
}

// Update product images
function updateProductImages(images) {
    if (!images || images.length === 0) return;
    
    const mainImage = document.getElementById('mainImage');
    const thumbnailsContainer = document.getElementById('thumbnailsContainer');
    
    // Clear existing thumbnails
    thumbnailsContainer.innerHTML = '';
    
    // Set main image to first image
    mainImage.src = '/leesage/' + images[0].image_path;
    mainImage.alt = 'Product Image';
    
    // Create thumbnails
    images.forEach((image, index) => {
        const thumbnail = document.createElement('div');
        thumbnail.className = 'thumbnail' + (index === 0 ? ' active' : '');
        thumbnail.dataset.image = image.image_path;
        
        const img = document.createElement('img');
        img.src = '/leesage/' + image.image_path;
        img.alt = 'Product Thumbnail';
        
        thumbnail.appendChild(img);
        thumbnailsContainer.appendChild(thumbnail);
    });
}

// Update related products
function updateRelatedProducts(relatedProducts) {
    const relatedProductsContainer = document.getElementById('relatedProducts');
    if (!relatedProductsContainer) return;
    
    relatedProductsContainer.innerHTML = '';
    
    relatedProducts.forEach(product => {
        const productCard = document.createElement('div');
        productCard.className = 'product-card';
        
        const imageUrl = product.primary_image || 'assets/images/placeholder.jpg';
        
        productCard.innerHTML = `
            <div class="product-image">
                <img src="/leesage/${imageUrl}" alt="${product.name}">
            </div>
            <div class="product-info">
                <h3>${product.name}</h3>
                <div class="product-price">
                    ${product.discount_price ?
                    `<span class="original-price">₹${parseFloat(product.price).toFixed(2)}</span>
                    <span class="discount-price">₹${parseFloat(product.discount_price).toFixed(2)}</span>` :
                    `<span>₹${parseFloat(product.price).toFixed(2)}</span>`
                    }
                </div>
            </div>
            <a href="product.html?id=${product.id}" class="product-link"></a>
        `;
        
        relatedProductsContainer.appendChild(productCard);
    });
}

// Update product reviews
function updateProductReviews(reviews) {
    // Calculate average rating
    let totalRating = 0;
    reviews.forEach(review => {
        totalRating += review.rating;
    });
    
    const averageRating = reviews.length > 0 ? totalRating / reviews.length : 0;
    
    // Update average rating display
    document.getElementById('averageRating').textContent = averageRating.toFixed(1);
    document.getElementById('totalReviews').textContent = `Based on ${reviews.length} reviews`;
    
    // Update rating stars
    updateRatingStars('averageRatingStars', averageRating);
    updateRatingStars('productRating', averageRating);
    
    // Update review count
    document.getElementById('reviewCount').textContent = `(${reviews.length} reviews)`;
    
    // Update rating distribution
    updateRatingDistribution(reviews);
    
    // Update reviews list
    updateReviewsList(reviews);
}

// Update rating stars
function updateRatingStars(elementId, rating) {
    const starsContainer = document.getElementById(elementId);
    if (!starsContainer) return;
    
    starsContainer.innerHTML = '';
    
    // Create 5 stars
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement('i');
        
        if (i <= Math.floor(rating)) {
            // Full star
            star.className = 'fas fa-star';
        } else if (i - 0.5 <= rating) {
            // Half star
            star.className = 'fas fa-star-half-alt';
        } else {
            // Empty star
            star.className = 'far fa-star';
        }
        
        starsContainer.appendChild(star);
    }
}

// Update rating distribution
function updateRatingDistribution(reviews) {
    const distributionContainer = document.getElementById('ratingDistribution');
    if (!distributionContainer) return;
    
    // Count ratings by star level
    const ratingCounts = [0, 0, 0, 0, 0]; // 5 stars, 4 stars, 3 stars, 2 stars, 1 star
    
    reviews.forEach(review => {
        const rating = Math.min(Math.max(Math.floor(review.rating), 1), 5);
        ratingCounts[5 - rating]++;
    });
    
    // Clear container
    distributionContainer.innerHTML = '';
    
    // Create distribution bars
    for (let i = 5; i >= 1; i--) {
        const count = ratingCounts[5 - i];
        const percentage = reviews.length > 0 ? (count / reviews.length) * 100 : 0;
        
        const distributionItem = document.createElement('div');
        distributionItem.className = 'rating-bar';
        
        distributionItem.innerHTML = `
            <div class="rating-label">${i} <i class="fas fa-star"></i></div>
            <div class="rating-bar-container">
                <div class="rating-bar-fill" style="width: ${percentage}%"></div>
            </div>
            <div class="rating-count">${count}</div>
        `;
        
        distributionContainer.appendChild(distributionItem);
    }
}

// Update reviews list
function updateReviewsList(reviews) {
    const reviewsListContainer = document.getElementById('reviewsList');
    if (!reviewsListContainer) return;
    
    // Clear container
    reviewsListContainer.innerHTML = '';
    
    if (reviews.length === 0) {
        reviewsListContainer.innerHTML = '<div class="no-reviews">No reviews yet. Be the first to review this product!</div>';
        return;
    }
    
    // Sort reviews by date (newest first)
    const sortedReviews = [...reviews].sort((a, b) => {
        return new Date(b.created_at) - new Date(a.created_at);
    });
    
    // Display reviews (limit to 5 for now)
    const reviewsToShow = sortedReviews.slice(0, 5);
    
    reviewsToShow.forEach(review => {
        const reviewItem = document.createElement('div');
        reviewItem.className = 'review-item';
        
        const reviewDate = new Date(review.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        reviewItem.innerHTML = `
            <div class="review-header">
                <div class="review-rating">
                    ${getStarIcons(review.rating)}
                </div>
                <div class="review-author">${review.user_name || 'Anonymous'}</div>
                <div class="review-date">${reviewDate}</div>
            </div>
            <div class="review-title">${review.title || ''}</div>
            <div class="review-content">${review.content}</div>
        `;
        
        reviewsListContainer.appendChild(reviewItem);
    });
    
    // Add pagination if needed
    if (reviews.length > 5) {
        const paginationContainer = document.getElementById('reviewPagination');
        if (paginationContainer) {
            paginationContainer.innerHTML = `
                <button class="pagination-btn">Show More Reviews</button>
            `;
        }
    }
}

// Helper function to generate star icons
function getStarIcons(rating) {
    let stars = '';
    
    for (let i = 1; i <= 5; i++) {
        if (i <= Math.floor(rating)) {
            // Full star
            stars += '<i class="fas fa-star"></i>';
        } else if (i - 0.5 <= rating) {
            // Half star
            stars += '<i class="fas fa-star-half-alt"></i>';
        } else {
            // Empty star
            stars += '<i class="far fa-star"></i>';
        }
    }
    
    return stars;
}

// Show error message
function showError(message) {
    // You can implement a more sophisticated error display
    console.error(message);
    alert(message);
}

// Add to Cart AJAX handler
const addToCartForm = document.getElementById('add-to-cart-form');
if (addToCartForm) {
    addToCartForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const productId = this.querySelector('input[name="product_id"]').value;
        const quantity = this.querySelector('input[name="quantity"]').value;
        const selectedSizeId = this.querySelector('#selected_size').value; // Get the ID from the hidden input
        const selectedSizeName = this.querySelector('#selected_size_name').value; // Get the name from the hidden input

        // Check if user is logged in
        fetch('/leesage/backend/php/public/auth/check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.data.isLoggedIn) {
                // User is logged in, add to cart via API
                addToCartAPI(productId, quantity, selectedSizeId, selectedSizeName);
            } else {
                // User is not logged in, redirect to login page with product data
                // Pass product data directly as URL parameters
                const redirectUrl = `/leesage/public/pages/login.html?redirect=product&action=add_to_cart&id=${productId}&quantity=${quantity}&size=${selectedSizeId}`;
                window.location.href = redirectUrl;

            }
        })
        .catch(error => {
            console.error('Error checking login status:', error);
            // Show error and redirect to login
            showNotification('Please login to add items to cart', 'error');
            setTimeout(() => {
                window.location.href = '/leesage/public/pages/login.html?redirect=product';
            }, 2000);
        });
    });
}

// Add to cart via API (for logged-in users)
function addToCartAPI(productId, quantity, selectedSizeId, selectedSizeName) {
    const formData = new FormData();
    formData.append('product_id', parseInt(productId, 10));
    formData.append('quantity', parseInt(quantity, 10));
    if (selectedSizeId) {
        formData.append('selected_size', selectedSizeId); // Send the size ID
    }
    
    console.log('Sending to cart API:', {
        product_id: productId,
        quantity: quantity,
        selected_size: selectedSizeId,
        selected_size_name: selectedSizeName
    });
    
    fetch('/leesage/backend/php/public/api/cart.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(response => {
        console.log('Cart API response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Cart API response data:', data);
        if (data.success) {
            showNotification('Product added to cart!');
            updateCartCount(data.data.total_items || 0);
            
            // Check if we should redirect to checkout (Buy Now button)
            const buyNowButton = document.querySelector('.btn-buy-now');
            if (buyNowButton && buyNowButton.classList.contains('btn-buy-now')) {
                // Redirect to checkout page
                window.location.href = '/leesage/public/pages/checkout.html';
            }
        } else {
            showError(data.message || 'Failed to add product to cart');
        }
    })
    .catch(error => {
        console.error('Error adding product to cart:', error);
        showError('Error adding product to cart. Please try again.');
    });
}

// Add to cart via local storage (for guests)
function addToCartLocal(productId, quantity, selectedSize) {
    // Get existing cart
    let cart = JSON.parse(localStorage.getItem('cart')) || {};
    
    // Add or update product in cart
    if (!cart[productId]) {
        cart[productId] = {
            quantity: parseInt(quantity),
            size: selectedSize || null
        };
    } else {
        // If product already exists, update quantity
        cart[productId].quantity = (cart[productId].quantity || 0) + parseInt(quantity);
        // Update size if provided
        if (selectedSize) {
            cart[productId].size = selectedSize;
        }
    }
    
    // Save cart to local storage
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Show notification
    showNotification('Product added to cart!');
    
    // Update cart count
    updateCartCount(Object.keys(cart).length);
    
    if (clickedButton && clickedButton.classList.contains('btn-buy-now')) {
        // Redirect to checkout page
        window.location.href = '/leesage/public/pages/checkout.html';
    }
}

// Show notification
function showNotification(message) {
    // You can implement a more sophisticated notification
    alert(message);
}

// Update cart count
function updateCartCount(count) {
    const cartIcon = document.querySelector('.cart-icon');
    if (cartIcon) {
        cartIcon.dataset.count = count;
    }
}
});

// Image Gallery Functionality
function initImageGallery() {
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.querySelector('.main-image');
    
    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            const newImage = this.dataset.image;
            if (mainImage && newImage) {
                // Add fade transition
                mainImage.style.opacity = '0.7';
                setTimeout(() => {
                    mainImage.src = '/leesage/' + newImage;
                    mainImage.style.opacity = '1';
                }, 150);
                
                // Update active thumbnail
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
}

// Size Selection
function initSizeSelection() {
    console.log('initSizeSelection called');
    const sizeOptionsContainer = document.getElementById('sizeOptions');
    if (!sizeOptionsContainer) {
        console.log('sizeOptionsContainer not found');
        return;
    }

    // Ensure selected_size and selected_size_name hidden inputs exist
    let selectedSizeInput = document.getElementById('selected_size');
    if (!selectedSizeInput) {
        selectedSizeInput = document.createElement('input');
        selectedSizeInput.type = 'hidden';
        selectedSizeInput.id = 'selected_size';
        selectedSizeInput.name = 'selected_size';
        document.getElementById('add-to-cart-form').appendChild(selectedSizeInput);
    }

    let selectedSizeNameInput = document.getElementById('selected_size_name');
    if (!selectedSizeNameInput) {
        selectedSizeNameInput = document.createElement('input');
        selectedSizeNameInput.type = 'hidden';
        selectedSizeNameInput.id = 'selected_size_name';
        selectedSizeNameInput.name = 'selected_size_name';
        document.getElementById('add-to-cart-form').appendChild(selectedSizeNameInput);
    }

    sizeOptionsContainer.addEventListener('click', function(event) {
        const target = event.target;
        if (target.classList.contains('size-option')) {
            console.log('Size option clicked:', target.textContent);
            // Remove 'active' from all size options
            sizeOptionsContainer.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('active'));

            // Add 'active' to the clicked size option
            target.classList.add('active');
            console.log('Active class added to:', target.textContent);

            // Update hidden input with selected size ID and name
            const sizeId = target.dataset.sizeId;
            const sizeName = target.textContent.trim(); // Get the size name from the clicked element
            selectedSizeInput.value = sizeId;
            selectedSizeNameInput.value = sizeName;

            // Update product availability display
            const stockQuantity = parseInt(target.dataset.stockQuantity, 10);
            const productAvailabilityElement = document.getElementById('productAvailability');
            if (productAvailabilityElement) {
                productAvailabilityElement.textContent = stockQuantity > 0 ? 'In Stock' : 'Out of Stock';
                productAvailabilityElement.className = stockQuantity > 0 ? 'in-stock' : 'out-of-stock';
                console.log('Product availability updated to:', productAvailabilityElement.textContent);
            }
        }
    });

    // Automatically select the first available size or the first size if all are out of stock
    const firstAvailableSize = sizeOptionsContainer.querySelector('.size-option[data-stock-quantity]:not([data-stock-quantity="0"]):not(.active)');
    const firstSize = sizeOptionsContainer.querySelector('.size-option:not(.active)');

    if (firstAvailableSize) {
        console.log('Attempting to auto-select first available size:', firstAvailableSize.textContent);
        firstAvailableSize.click();
    } else if (firstSize) {
        console.log('Attempting to auto-select first size (all out of stock):', firstSize.textContent);
        firstSize.click();
    }
}

// Quantity Controls
function initQuantityControls() {
    const minusBtn = document.querySelector('.quantity-btn[data-action="decrease"]');
    const plusBtn = document.querySelector('.quantity-btn[data-action="increase"]');
    const quantityInput = document.querySelector('.quantity-input');
    
    if (minusBtn && plusBtn && quantityInput) {
        minusBtn.addEventListener('click', () => updateQuantity(-1));
        plusBtn.addEventListener('click', () => updateQuantity(1));
        
        quantityInput.addEventListener('change', validateQuantity);
    }
}

function updateQuantity(change) {
    const input = document.querySelector('.quantity-input');
    const maxStock = parseInt(input.max) || 999;
    let newValue = parseInt(input.value) + change;
    
    newValue = Math.max(1, Math.min(newValue, maxStock));
    input.value = newValue;
    
    // Update all quantity inputs
    document.querySelectorAll('input[name="quantity"]').forEach(qty => {
        qty.value = newValue;
    });
}

function validateQuantity() {
    const input = document.querySelector('.quantity-input');
    const maxStock = parseInt(input.max) || 999;
    let value = parseInt(input.value);
    
    if (isNaN(value) || value < 1) {
        value = 1;
    } else if (value > maxStock) {
        value = maxStock;
    }
    
    input.value = value;
}

// Wishlist Functionality
function initWishlist() {
    const wishlistBtn = document.querySelector('.btn-wishlist');
    
    if (wishlistBtn) {
        const productId = wishlistBtn.dataset.productId;
        
        // Check if user is logged in and if product is in wishlist
        fetch('/leesage/backend/php/public/auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                if (data.logged_in) {
                    // Check if product is in wishlist
                    if (window.wishlistFunctions && typeof window.wishlistFunctions.checkWishlistStatus === 'function') {
                        window.wishlistFunctions.checkWishlistStatus(productId)
                            .then(inWishlist => {
                                if (inWishlist) {
                                    wishlistBtn.classList.add('active');
                                    wishlistBtn.innerHTML = '♥ Remove from Wishlist';
                                } else {
                                    wishlistBtn.classList.remove('active');
                                    wishlistBtn.innerHTML = '♡ Add to Wishlist';
                                }
                            });
                    }
                }
            });
        
        wishlistBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const productId = this.dataset.productId;
            const isActive = this.classList.contains('active');
            
            // Check if wishlistFunctions is available from wishlist.js
            if (window.wishlistFunctions) {
                if (isActive) {
                    // Remove from wishlist
                    window.wishlistFunctions.removeFromWishlist(productId)
                        .then(success => {
                            if (success) {
                                this.classList.remove('active');
                                this.innerHTML = '♡ Add to Wishlist';
                            }
                        });
                } else {
                    // Add to wishlist
                    window.wishlistFunctions.addToWishlist(productId)
                        .then(success => {
                            if (success) {
                                this.classList.add('active');
                                this.innerHTML = '♥ Remove from Wishlist';
                            }
                        });
                }
            } else {
                // Fallback if wishlist.js is not loaded
                console.log('Wishlist functionality not available. Please include wishlist.js');
                alert('Wishlist functionality is not available at the moment. Please try again later.');
            }
        });
    }
}

// Image Zoom on Hover (Desktop)
function initImageZoom() {
    const mainImage = document.querySelector('.main-image');
    
    if (mainImage && window.innerWidth > 768) {
        mainImage.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            
            this.style.transformOrigin = `${x}% ${y}%`;
            this.style.transform = 'scale(1.5)';
        });
        
        mainImage.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.transformOrigin = 'center';
        });
    }
}

// Tab switching functionality
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');
    
    tabButtons.forEach(button => {
        // Handle click events
        button.addEventListener('click', function() {
            switchTab(this.dataset.tab);
        });
        
        // Handle hover events
        button.addEventListener('mouseenter', function() {
            switchTab(this.dataset.tab);
        });
        
        // Optional: handle mouse leave to go back to first tab
        button.addEventListener('mouseleave', function() {
            // Uncomment the line below if you want to return to description tab on mouse leave
            // switchTab('description');
        });
    });
    
    function switchTab(targetTab) {
        // Remove active class from all buttons and panels
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabPanels.forEach(panel => panel.classList.remove('active'));
        
        // Add active class to target button and corresponding panel
        const targetButton = document.querySelector(`[data-tab="${targetTab}"]`);
        const targetPanel = document.getElementById(targetTab + 'Tab');
        
        if (targetButton && targetPanel) {
            targetButton.classList.add('active');
            targetPanel.classList.add('active');
        }
    }
}

// Responsive Features
function initResponsiveFeatures() {
    // Handle mobile image gallery
    if (window.innerWidth <= 768) {
        setupMobileGallery();
    }
    
    // Handle window resize
    window.addEventListener('resize', debounce(() => {
        if (window.innerWidth <= 768) {
            setupMobileGallery();
        }
    }, 250));
}

function setupMobileGallery() {
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.querySelector('.main-image');
    
    if (thumbnails.length > 0 && mainImage) {
        // Ensure thumbnails are in a horizontal scroll container
        const gallery = document.querySelector('.thumbnail-gallery');
        if (gallery) {
            gallery.style.display = 'flex';
            gallery.style.overflowX = 'auto';
            gallery.style.scrollSnapType = 'x mandatory';
        }
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add to Cart Animation
function addToCartAnimation(button) {
    button.style.transform = 'scale(0.95)';
    button.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">✓</span> Adding...';
    
    setTimeout(() => {
        button.style.transform = 'scale(1)';
        button.innerHTML = '✓ Added to Cart';
        button.style.background = '#27ae60';
        
        setTimeout(() => {
            button.innerHTML = 'Add to Cart';
            button.style.background = '';
        }, 2000);
    }, 1000);
}

// Size Guide Modal
function showSizeGuide() {
    const modal = document.createElement('div');
    modal.className = 'size-guide-modal';
    
    // Create loading content first
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Size Guide</h2>
            <div class="loading-indicator">
                <div class="spinner"></div>
                <p>Loading size guide...</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal functionality
    modal.querySelector('.close').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Fetch size guide data from API
    fetch('/leesage/backend/php/public/api/products.php?action=size_guide&product_id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.size_guide) {
                const modalContent = modal.querySelector('.modal-content');
                const loadingIndicator = modal.querySelector('.loading-indicator');
                
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
                
                // Create table with the fetched data
                const table = document.createElement('table');
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Chest (inches)</th>
                            <th>Length (inches)</th>
                            <th>Sleeve (inches)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.size_guide.map(item => `
                            <tr>
                                <td>${item.size}</td>
                                <td>${item.chest}</td>
                                <td>${item.length}</td>
                                <td>${item.sleeve}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                `;
                
                modalContent.appendChild(table);
            } else {
                // Fallback to generic size guide if API fails
                const modalContent = modal.querySelector('.modal-content');
                const loadingIndicator = modal.querySelector('.loading-indicator');
                
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
                
                const errorMessage = document.createElement('p');
                errorMessage.className = 'error-message';
                errorMessage.textContent = 'Size guide information is not available for this product. Please contact customer service for assistance.';
                modalContent.appendChild(errorMessage);
            }
        })
        .catch(error => {
            console.error('Error fetching size guide:', error);
            const modalContent = modal.querySelector('.modal-content');
            const loadingIndicator = modal.querySelector('.loading-indicator');
            
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            
            const errorMessage = document.createElement('p');
            errorMessage.className = 'error-message';
            errorMessage.textContent = 'Unable to load size guide. Please try again later.';
            modalContent.appendChild(errorMessage);
        });
}

// Social Sharing
function shareProduct(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.querySelector('.product-title').textContent);
    
    const shareUrls = {
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
        twitter: `https://twitter.com/intent/tweet?url=${url}&text=${title}`,
        pinterest: `https://pinterest.com/pin/create/button/?url=${url}&description=${title}`,
        linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`
    };
    
    if (shareUrls[platform]) {
        window.open(shareUrls[platform], '_blank', 'width=600,height=400');
    }
}

// Loading States
function showLoadingState() {
    const container = document.querySelector('.product-container');
    if (container) {
        container.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-image"></div>
                <div class="skeleton-content">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-price"></div>
                    <div class="skeleton-description"></div>
                    <div class="skeleton-actions"></div>
                </div>
            </div>
        `;
    }
}

// Check if we have a success message from login redirect
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('cart_added') === 'true') {
    // Show success notification
    const notification = document.createElement('div');
    notification.className = 'notification success';
    notification.textContent = 'Product successfully added to cart after login!';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #27ae60;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 5000);
    
    // Remove the parameter from URL without reloading
    const newUrl = window.location.pathname + '?id=' + urlParams.get('id');
    window.history.replaceState({}, '', newUrl);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .loading-skeleton {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
        }
        
        .skeleton-image {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            height: 400px;
            border-radius: 8px;
        }
        
        .skeleton-content > div {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .skeleton-title { height: 2rem; width: 80%; }
        .skeleton-price { height: 1.5rem; width: 40%; }
        .skeleton-description { height: 4rem; width: 100%; }
        .skeleton-actions { height: 3rem; width: 60%; }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .size-guide-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        th, td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
    `;
    document.head.appendChild(style);
});
