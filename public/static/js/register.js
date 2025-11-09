document.addEventListener('DOMContentLoaded', function() {
  const registerForm = document.getElementById('register-form');
  const firstNameInput = document.getElementById('first-name');
  const lastNameInput = document.getElementById('last-name');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirm-password');
  const termsCheckbox = document.getElementById('terms');
  const registerErrorDiv = document.getElementById('register-error');
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
    registerErrorDiv.textContent = '';
    registerErrorDiv.style.display = 'none';
    hideMessage();
  }

  // Basic client-side validation
  function validateForm() {
    clearErrors();
    let isValid = true;

    if (firstNameInput.value.trim() === '') {
      displayError(firstNameInput, 'First Name is required.');
      isValid = false;
    }

    if (lastNameInput.value.trim() === '') {
      displayError(lastNameInput, 'Last Name is required.');
      isValid = false;
    }

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
    } else if (passwordInput.value.length < 8) {
      displayError(passwordInput, 'Password must be at least 8 characters long.');
      isValid = false;
    }

    if (confirmPasswordInput.value.trim() === '') {
      displayError(confirmPasswordInput, 'Confirm Password is required.');
      isValid = false;
    } else if (passwordInput.value !== confirmPasswordInput.value) {
      displayError(confirmPasswordInput, 'Passwords do not match.');
      isValid = false;
    }

    if (!termsCheckbox.checked) {
      displayError(termsCheckbox, 'You must accept the terms and conditions.');
      isValid = false;
    }

    return isValid;
  }

  registerForm.addEventListener('submit', async function(event) {
    event.preventDefault();
    if (validateForm()) {
      const formData = {
        first_name: firstNameInput.value.trim(),
        last_name: lastNameInput.value.trim(),
        email: emailInput.value.trim(),
        password: passwordInput.value.trim(),
        confirm_password: confirmPasswordInput.value.trim(),
        terms_accepted: termsCheckbox.checked
      };

      try {
        const response = await fetch('../../backend/php/public/auth/register.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
          showMessage(data.message, 'success');
          registerForm.reset();
          setTimeout(() => {
            window.location.href = 'home.html';
          }, 1000); // Redirect after 1 second
        } else {
          showMessage(data.message, 'error');
        }
      } catch (error) {
        console.error('Error during registration:', error);
        showMessage('An error occurred during registration. Please try again.', 'error');
      }
    } else {
      showMessage('Please correct the errors above.', 'error');
    }
  });
});
