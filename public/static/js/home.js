// Initialize any home page specific functionality
document.addEventListener('DOMContentLoaded', function() {
  const video = document.getElementById('background-video');
  if (video) {
    // Ensure video plays automatically on load
    video.play().catch(error => {
      console.error('Video play failed:', error);
    });
  }
  
  // Update cart count
  updateCartCount();
  
  // Mobile navigation toggle
  const hamburger = document.querySelector('.hamburger');
  const navMenu = document.querySelector('nav ul');

  if (hamburger && navMenu) {
    hamburger.addEventListener('click', () => {
      navMenu.classList.toggle('active');
    });
  }
  
  // Load featured products
  loadFeaturedProducts();
});

/**
 * Load featured products from API
 */
function loadFeaturedProducts() {
  const featuredProductsContainer = document.getElementById('featured-products');
  if (!featuredProductsContainer) return;
  
  // Show loading indicator
  featuredProductsContainer.innerHTML = `
    <div class="loading-indicator">
      <div class="spinner"></div>
      <p>Loading featured products...</p>
    </div>
  `;
  
  // Fetch featured products from API
  fetch('/leesage/backend/php/public/api/products.php?action=featured&limit=4')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.data && data.data.length > 0) {
        // Clear loading indicator
        featuredProductsContainer.innerHTML = '';
        
        // Render featured products
        data.data.forEach(product => {
          const productCard = createProductCard(product);
          featuredProductsContainer.appendChild(productCard);
        });
      } else {
        throw new Error(data.message || 'No featured products found');
      }
    })
    .catch(error => {
      console.error('Error loading featured products:', error);
      featuredProductsContainer.innerHTML = `
        <div class="error-message">
          <p>Failed to load featured products. Please try again later.</p>
        </div>
      `;
    });
}

/**
 * Create a product card element
 * @param {Object} product - Product data
 * @returns {HTMLElement} Product card element
 */
function createProductCard(product) {
  const productCard = document.createElement('div');
  productCard.className = 'product-card';
  
  // Format price
  const price = parseFloat(product.price).toFixed(2);
  const discountPrice = product.discount_price ? parseFloat(product.discount_price).toFixed(2) : null;
  
  // Set image path (use placeholder if no image)
  const imagePath = product.primary_image ? `/leesage/${product.primary_image}` : '/leesage/assets/images/placeholder.jpg';
  
  // Create product card HTML
  productCard.innerHTML = `
    <a href="product.html?id=${product.id}">
      <img src="${imagePath}" alt="${product.name}" class="product-image">
      <div class="product-info">
        <h3 class="product-title">${product.name}</h3>
        ${discountPrice ?
            `<p class="product-price"><span class="original-price">₹${price}</span> <span class="discount-price">₹${discountPrice}</span></p>` :
            `<p class="product-price">₹${price}</p>`
        }
      </div>
    </a>
  `;
  
  return productCard;
}
