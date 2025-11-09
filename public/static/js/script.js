/* JavaScript for Lee Sage E-Commerce Website */

// Product data without sample products
const products = [
];

document.addEventListener('DOMContentLoaded', () => {
  // Check login status and update navigation
  checkLoginStatus();

  // Function to check login status and update UI
  async function checkLoginStatus() {
    try {
      const response = await fetch('/leesage/backend/php/public/auth/check_session.php', {
        method: 'GET',
        credentials: 'include' // Include cookies to maintain PHP session
      });
      const data = await response.json();
      console.log('Login status data:', data);

      // Update localStorage based on the server response
      if (data.data.isLoggedIn) {
        localStorage.setItem('isLoggedIn', 'true');
        // Update profile icon with user data if available
        if (data.data.user) {
          updateProfileIcon(data.data.user);
        }
      } else {
        localStorage.removeItem('isLoggedIn');
      }

      // Update UI based on localStorage
      updateNavVisibility();

    } catch (error) {
      console.error('Error checking login status:', error);
      localStorage.removeItem('isLoggedIn'); // Assume not logged in on error
      updateNavVisibility(); // Update UI based on this assumption
    }
  }

  // New function to update navigation visibility based on localStorage
  function updateNavVisibility() {
    const navLogin = document.querySelector('.nav-login');
    const navProfile = document.querySelector('.nav-profile');
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    if (navLogin && navProfile) {
      if (isLoggedIn) {
        navLogin.style.display = 'none';
        navProfile.style.display = 'block';
      } else {
        navLogin.style.display = 'block';
        navProfile.style.display = 'none';
      }
    }
  }

  // Function to update profile icon with user's initial
  function updateProfileIcon(userData) {
    const profileAvatar = document.querySelector('.profile-avatar .avatar-text');
    if (profileAvatar && userData) {
      const userFirstName = userData.first_name || 'U';
      profileAvatar.textContent = userFirstName.charAt(0).toUpperCase();
      
      // Store user info in localStorage for consistency
      localStorage.setItem('userFirstName', userData.first_name || '');
      localStorage.setItem('userEmail', userData.email || '');
    }
  }

  // Mobile navigation toggle
  const hamburger = document.querySelector('.hamburger');
  const navMenu = document.querySelector('nav ul');

  if (hamburger && navMenu) {
    hamburger.addEventListener('click', () => {
      navMenu.classList.toggle('active');
    });
  }

  // Sticky header
  window.addEventListener('scroll', () => {
    const header = document.querySelector('header');
    if (window.scrollY > 50) {
      header.classList.add('sticky');
    } else {
      header.classList.remove('sticky');
    }
  });

  // Cart system
    updateCartCount();

  // Add to cart functionality on product pages
  const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
  addToCartButtons.forEach(button => {
    button.addEventListener('click', () => {
      const productId = button.getAttribute('data-product-id');
      const product = products.find(p => p.id == productId);
      if (product) {
        addToCart(product);
      }
    });
  });

  // Checkout form validation
  const checkoutForm = document.getElementById('checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const formData = new FormData(checkoutForm);
      const data = Object.fromEntries(formData.entries());
      
      // Simple validation
      if (!data.name || !data.address || !data.email || !data.phone) {
        document.getElementById('form-message').textContent = 'Please fill in all required fields.';
        return;
      }
      
      // Email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(data.email)) {
        document.getElementById('form-message').textContent = 'Please enter a valid email address.';
        return;
      }
      
      // Phone validation
      const phoneRegex = /^\d{10,15}$/;
      if (!phoneRegex.test(data.phone)) {
        document.getElementById('form-message').textContent = 'Please enter a valid phone number (10-15 digits).';
        return;
      }
      
      // Success message
      document.getElementById('form-message').textContent = 'Order placed successfully!';
      checkoutForm.reset();
      
      // Clear cart
      localStorage.removeItem('cart');
      updateCartCount();
    });
  }

  // Contact form validation
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const formData = new FormData(contactForm);
      const data = Object.fromEntries(formData.entries());
      
      // Simple validation
      if (!data.name || !data.email || !data.message) {
        document.getElementById('contact-message').textContent = 'Please fill in all required fields.';
        return;
      }
      
      // Email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(data.email)) {
        document.getElementById('contact-message').textContent = 'Please enter a valid email address.';
        return;
      }
      
      // Success message
      document.getElementById('contact-message').textContent = 'Message sent successfully!';
      contactForm.reset();
    });
  }

  // Newsletter subscription modal (basic implementation)
  // This would typically be triggered by a timer or scroll event
  // For now, we'll just show it on page load for demonstration
  // In a real implementation, this would be more sophisticated
  // and would include a cookie to prevent showing it again

  // Counter Animation for Interactive Stats
  const animateCounters = () => {
    const counters = document.querySelectorAll('.counter-number');
    const speed = 200; // The lower the slower

    counters.forEach(counter => {
      const target = +counter.getAttribute('data-target');
      const increment = target / speed;

      const updateCount = () => {
        const count = +counter.innerText;
        if (count < target) {
          counter.innerText = Math.ceil(count + increment);
          setTimeout(updateCount, 1);
        } else {
          counter.innerText = target;
        }
      };

      updateCount();
    });
  };

  // Trigger counter animation when section is in view
  const statsSection = document.querySelector('.interactive-stats');
  if (statsSection) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounters();
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    observer.observe(statsSection);
  }

  // Other JS functionalities will be added here

  // Product Carousel Functionality
  const productsScrollContainer = document.querySelector('.products-scroll-container');
  const prevButton = document.querySelector('.carousel-button.prev');
  const nextButton = document.querySelector('.carousel-button.next');

  if (productsScrollContainer && prevButton && nextButton) {
    const productCards = productsScrollContainer.querySelectorAll('.product-card');
    const cardWidth = productCards[0] ? productCards[0].offsetWidth + 32 : 0; // Card width + gap (2rem = 32px)
    let scrollPosition = 0;

    // Set initial --visible-products CSS variable
    const updateVisibleProducts = () => {
      const containerWidth = productsScrollContainer.offsetWidth;
      const visibleProducts = Math.floor(containerWidth / cardWidth);
      productsScrollContainer.style.setProperty('--visible-products', visibleProducts > 0 ? visibleProducts : 1);
    };

    updateVisibleProducts();
    window.addEventListener('resize', updateVisibleProducts);

    nextButton.addEventListener('click', () => {
      scrollPosition += cardWidth * parseInt(productsScrollContainer.style.getPropertyValue('--visible-products'));
      productsScrollContainer.scrollTo({ left: scrollPosition, behavior: 'smooth' });
    });

    prevButton.addEventListener('click', () => {
      scrollPosition -= cardWidth * parseInt(productsScrollContainer.style.getPropertyValue('--visible-products'));
      productsScrollContainer.scrollTo({ left: scrollPosition, behavior: 'smooth' });
    });
  }
});

function updateCartCount() {
  const cartIcon = document.querySelector('.cart-icon');
  if (!cartIcon) return;

  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  cartIcon.setAttribute('data-count', cart.length);
}

function renderProducts(productsArray) {
  const productGrid = document.querySelector('.product-grid');
  if (!productGrid) return;

  productGrid.innerHTML = '';
  productsArray.forEach(product => {
    const productCard = document.createElement('div');
    productCard.className = 'product-card';
    productCard.innerHTML = `
      <img src="${product.image}" alt="${product.name}">
      <div class="product-card-content">
        <h3>${product.name}</h3>
        <p>₹${product.price.toFixed(2)}</p>
      </div>
    `;
    productCard.addEventListener('click', () => {
      // Redirect to product detail page
      window.location.href = 'product.html';
    });
    productGrid.appendChild(productCard);
  });
}

function addToCart(product) {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  cart.push(product);
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartCount();
  
  // Show a simple alert or message
  alert(`${product.name} added to cart!`);
}

// Removed duplicate checkLoginStatus function
