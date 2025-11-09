// Forgot Password JavaScript
(function() {
    'use strict';

    // DOM elements
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('email');
    const resetSuccess = document.getElementById('resetSuccess');
    const resetError = document.getElementById('resetError');
    const resetErrorMessage = document.getElementById('resetErrorMessage');

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initializeForgotPassword();
    });

    function initializeForgotPassword() {
        // Set up event listeners
        setupEventListeners();
        
        // Focus on email field
        if (emailInput) {
            emailInput.focus();
        }
    }

    function setupEventListeners() {
        // Form submission
        if (forgotPasswordForm) {
            forgotPasswordForm.addEventListener('submit', handlePasswordReset);
        }

        // Real-time validation
        if (emailInput) {
            emailInput.addEventListener('input', clearError);
            emailInput.addEventListener('blur', validateEmail);
        }

        // Enter key support
        if (emailInput) {
            emailInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    handlePasswordReset(e);
                }
            });
        }
    }

    function validateEmail() {
        const email = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email) {
            showError('Please enter your email address');
            return false;
        }
        
        if (!emailRegex.test(email)) {
            showError('Please enter a valid email address');
            return false;
        }
        
        return true;
    }

    async function handlePasswordReset(e) {
        e.preventDefault();
        
        // Clear any existing errors
        clearError();
        
        // Validate email
        if (!validateEmail()) {
            return;
        }

        // Get form data
        const formData = {
            email: emailInput.value.trim()
        };

        // Show loading state
        setLoadingState(true);

        try {
            // Simulate API call - replace with actual endpoint
            const response = await simulatePasswordReset(formData);
            
            if (response.success) {
                handleSuccessfulReset(formData);
            } else {
                showError(response.message || 'Failed to send reset instructions. Please try again.');
            }
        } catch (error) {
            console.error('Password reset error:', error);
            showError('An error occurred. Please try again later.');
        } finally {
            setLoadingState(false);
        }
    }

    async function simulatePasswordReset(formData) {
        // Simulate API delay
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        // Make actual API call to backend
        try {
            const response = await fetch('../api/forgot-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: formData.email })
            });
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error:', error);
            return { success: false, message: 'An error occurred. Please try again later.' };
        }
    }

    function handleSuccessfulReset(formData) {
        // Show success message
        if (resetSuccess) {
            resetSuccess.style.display = 'flex';
            forgotPasswordForm.style.display = 'none';
        }
        
        // Log for demo purposes
        console.log('Password reset email sent to:', formData.email);
    }

    function showError(message) {
        if (resetError && resetErrorMessage) {
            resetErrorMessage.textContent = message;
            resetError.style.display = 'flex';
            
            // Auto-hide after 5 seconds
            setTimeout(clearError, 5000);
        }
    }

    function clearError() {
        if (resetError) {
            resetError.style.display = 'none';
        }
    }

    function setLoadingState(loading) {
        const resetBtn = forgotPasswordForm?.querySelector('.reset-btn');
        if (resetBtn) {
            if (loading) {
                resetBtn.disabled = true;
                resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            } else {
                resetBtn.disabled = false;
                resetBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Instructions';
            }
        }
    }

    // Utility functions
    function sanitizeInput(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }

    // Security: Clear sensitive data on page unload
    window.addEventListener('beforeunload', function() {
        emailInput.value = '';
    });

    // Handle browser back button after reset
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || window.performance?.navigation?.type === 2) {
            // Reset form when returning via back button
            if (forgotPasswordForm) {
                forgotPasswordForm.reset();
            }
            clearError();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (forgotPasswordForm) {
                handlePasswordReset(e);
            }
        }
        
        // Escape to clear error
        if (e.key === 'Escape') {
            clearError();
        }
    });

    // Console security
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        console.log = console.warn = console.error = function() {};
    }
})();

// Export for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { handlePasswordReset: window.handlePasswordReset };
}
