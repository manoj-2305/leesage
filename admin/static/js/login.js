// Admin Login JavaScript
(function() {
    'use strict';

    // DOM elements
    const loginForm = document.getElementById('adminLoginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const rememberMeCheckbox = document.getElementById('rememberMe');
    const loginError = document.getElementById('loginError');
    const errorMessage = document.getElementById('errorMessage');

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initializeLogin();
    });

    function initializeLogin() {
        // Load remembered user preference
        loadRememberedUser();
        
        // Set up event listeners
        setupEventListeners();
        
        // Focus on username field
        if (usernameInput) {
            usernameInput.focus();
        }
    }

    function setupEventListeners() {
        // Form submission
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }

        // Real-time validation
        if (usernameInput) {
            usernameInput.addEventListener('input', clearError);
            usernameInput.addEventListener('blur', validateUsername);
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', clearError);
            passwordInput.addEventListener('blur', validatePassword);
        }

        // Enter key support
        [usernameInput, passwordInput].forEach(input => {
            if (input) {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        handleLogin(e);
                    }
                });
            }
        });
    }

    function loadRememberedUser() {
        const remembered = localStorage.getItem('adminRememberMe');
        if (remembered === 'true' && rememberMeCheckbox) {
            rememberMeCheckbox.checked = true;
            
            // Optionally pre-fill username if stored
            const savedUsername = localStorage.getItem('adminUsername');
            if (savedUsername && usernameInput) {
                usernameInput.value = savedUsername;
                passwordInput?.focus();
            }
        }
    }

    function validateUsername() {
        const username = usernameInput.value.trim();
        if (!username) {
            showError('Please enter your username or email');
            return false;
        }
        return true;
    }

    function validatePassword() {
        const password = passwordInput.value;
        if (!password) {
            showError('Please enter your password');
            return false;
        }
        if (password.length < 6) {
            showError('Password must be at least 6 characters');
            return false;
        }
        return true;
    }

    async function handleLogin(e) {
        e.preventDefault();
        
        // Clear any existing errors
        clearError();
        
        // Validate inputs
        if (!validateUsername() || !validatePassword()) {
            return;
        }

        // Get form data
        const formData = {
            username: usernameInput.value.trim(),
            password: passwordInput.value,
            rememberMe: rememberMeCheckbox ? rememberMeCheckbox.checked : false
        };

        console.log('Sending login data:', formData); // Added console log

        // Show loading state
        setLoadingState(true);

        try {
            // Make actual API call to PHP backend
            const response = await fetch('/leesage/backend/php/admin/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData),
                credentials: 'include' // Include cookies in the request and store cookies from the response
            });

            const data = await response.json();
            console.log('Received response data:', data);
            console.log('Response status:', response.status);
            console.log('Response statusText:', response.statusText);
            
            if (!response.ok) {
                // If response is not OK (e.g., 401, 500), throw an error with the backend message
                throw new Error(data.message || `HTTP error! status: ${response.status}`);
            }

            if (data.success) {
                handleSuccessfulLogin(formData, data.admin);
            } else {
                // This case should ideally be caught by !response.ok, but as a fallback
                showError(data.message || 'Login failed. Please try again.');
            }
        } catch (error) {
            console.error('Login error caught:', error);
            if (error instanceof SyntaxError) {
                console.error('JSON parsing error:', error.message);
                showError('Server returned invalid JSON. Please check server logs.');
            } else {
                showError(error.message || 'An error occurred. Please try again later.');
            }

        } finally {
            setLoadingState(false);
        }
    }

    function handleSuccessfulLogin(formData, adminData) {
        // Store user preference
        if (formData.rememberMe) {
            localStorage.setItem('adminRememberMe', 'true');
            localStorage.setItem('adminUsername', formData.username);
        } else {
            localStorage.removeItem('adminRememberMe');
            localStorage.removeItem('adminUsername');
        }

        // Store session data
        sessionStorage.setItem('adminToken', 'authenticated');
        sessionStorage.setItem('adminUsername', adminData.username);
        sessionStorage.setItem('adminFullName', adminData.fullName);
        sessionStorage.setItem('adminRole', adminData.role);
        
        // The PHP session is maintained by cookies automatically set by PHP
        // We don't need to manually set cookies for the PHP session

        // Redirect to dashboard
        window.location.href = 'pages/dashboard.php';
    }

    function showError(message) {
        if (loginError && errorMessage) {
            errorMessage.textContent = message;
            loginError.style.display = 'flex';
            
            // Auto-hide after 5 seconds
            setTimeout(clearError, 5000);
        }
    }

    function clearError() {
        if (loginError) {
            loginError.style.display = 'none';
        }
    }

    function setLoadingState(loading) {
        const loginBtn = loginForm?.querySelector('.login-btn');
        if (loginBtn) {
            if (loading) {
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            } else {
                loginBtn.disabled = false;
                loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
            }
        }
    }

    // Password visibility toggle
    window.togglePassword = function() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.toggle-password i');
        
        if (passwordInput && icon) {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    };

    // Utility functions
    function sanitizeInput(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }

    // Security: Clear sensitive data on page unload
    window.addEventListener('beforeunload', function() {
        if (!rememberMeCheckbox?.checked) {
            passwordInput.value = '';
        }
    });

    // Handle browser back button after logout
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || window.performance?.navigation?.type === 2) {
            // Clear form when returning via back button
            if (loginForm) {
                loginForm.reset();
            }
            clearError();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (loginForm) {
                handleLogin(e);
            }
        }
        
        // Escape to clear error
        if (e.key === 'Escape') {
            clearError();
        }
    });

})();

// Export for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { handleLogin: window.handleLogin };
}
