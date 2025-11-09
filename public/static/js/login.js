document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.getElementById('login-form');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const loginErrorDiv = document.getElementById('login-error');
  const messageBox = document.getElementById('message-box');

  // Function to display error messages
  function displayError(element, message) {
    const errorDiv = document.getElementById(`${element.id}-error`);
    if (errorDiv) {
      errorDiv.textContent = message;
      errorDiv.style.display = message ? 'block' : 'none';
    }
  }

  function showMessage(message, type) {
    messageBox.textContent = message;
    messageBox.className = `message-box ${type}`;
    messageBox.style.display = 'block';
  }

  function hideMessage() {
    messageBox.textContent = '';
    messageBox.className = 'message-box';
    messageBox.style.display = 'none';
  }

  // Function to clear all error messages
  function clearErrors() {
    document.querySelectorAll('.error-message').forEach(div => {
      div.textContent = '';
      div.style.display = 'none';
    });
    loginErrorDiv.textContent = '';
    loginErrorDiv.style.display = 'none';
    hideMessage();
  }

  // Basic client-side validation
  function validateForm() {
    clearErrors();
    let isValid = true;

    if (emailInput.value.trim() === '') {
      displayError(emailInput, 'Email is required.');
      isValid = false;
    } else if (!/^[\w-]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$/.test(emailInput.value)) {
      displayError(emailInput, 'Invalid email format.');
      isValid = false;
    }

    if (passwordInput.value.trim() === '') {
      displayError(passwordInput, 'Password is required.');
      isValid = false;
    }

    return isValid;
  }

    loginForm.addEventListener('submit', async function(event) {
    event.preventDefault();
    if (validateForm()) {
      const formData = {
        email: emailInput.value.trim(),
        password: passwordInput.value.trim()
      };

      try {
        const response = await fetch('../../backend/php/public/auth/login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        });

        if (!response.ok) {
          const errorText = await response.text();
          console.error('Server response not OK:', response.status, errorText);
          try {
            const errorData = JSON.parse(errorText);
            showMessage(errorData.message || 'Server error occurred.', 'error');
          } catch (e) {
            showMessage(`Server error: ${response.status} - ${errorText}`, 'error');
          }
          return;
        }

        const data = await response.json();

        if (data.success && data.data && data.data.user) {
          // Store user data in localStorage
          localStorage.setItem('userFirstName', data.data.user.first_name);
          localStorage.setItem('userLastName', data.data.user.last_name);
          localStorage.setItem('userEmail', data.data.user.email);
          localStorage.setItem('isLoggedIn', 'true');
          
          showMessage(data.message, 'success');
          
          // Check if there's product data in URL parameters to add to cart after login
          const urlParams = new URLSearchParams(window.location.search);
          const productId = urlParams.get('id');
          const quantity = urlParams.get('quantity');
          const size = urlParams.get('size');
          const action = urlParams.get('action');

          if (productId && quantity && action === 'add_to_cart') {
            // Add the item to cart via API
            fetch('/leesage/backend/php/public/api/cart.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                size: size
              })
            })
            .then(response => response.json())
            .then(cartData => {
              if (cartData.success) {
                // Redirect back to product page with success message
                setTimeout(() => {
                  window.location.href = `/leesage/public/pages/product.html?id=${productId}&cart_added=true`;
                }, 1000);
              } else {
                // If cart addition fails, still redirect but show error
                console.error('Failed to add item to cart:', cartData.message);
                setTimeout(() => {
                  window.location.href = 'home.html';
                }, 1000);
              }
            })
            .catch(error => {
              console.error('Error adding item to cart:', error);
              setTimeout(() => {
                window.location.href = 'home.html';
              }, 1000);
            });
          } else {
            // No pending cart item, redirect normally
            setTimeout(() => {
              window.location.href = 'home.html';
            }, 1000);
          }
        } else {
          showMessage(data.message, 'error');
        }
      } catch (error) {
        console.error('Network or parsing error during login:', error);
        showMessage('A network error occurred. Please check your connection and try again.', 'error');
      }
    } else {
      showMessage('Please correct the errors above.', 'error');
    }
  });
});