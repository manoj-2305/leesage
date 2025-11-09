<?php
// Configuration settings for the public website

// Site settings
define('SITE_NAME', 'Lee Sage');
define('SITE_URL', '/leesage');
define('ASSETS_URL', SITE_URL . '/assets');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'leesage_session');

// Security settings
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_OPTIONS', ['cost' => 12]);

// Email settings
define('ADMIN_EMAIL', 'admin@leesage.com');
define('CONTACT_EMAIL', 'contact@leesage.com');

// Pagination settings
define('PRODUCTS_PER_PAGE', 12);

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_DIR', __DIR__ . '/../../../../assets/uploads/');

// Cart settings
define('CART_COOKIE_NAME', 'leesage_cart');
define('CART_COOKIE_LIFETIME', 30 * 24 * 60 * 60); // 30 days

// Order settings
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPED', 'shipped');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// Payment settings
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PAID', 'paid');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

// Tax and shipping settings
define('DEFAULT_TAX_RATE', 0.10); // 10%
define('FREE_SHIPPING_THRESHOLD', 100.00); // Free shipping for orders over ₹100
define('DEFAULT_SHIPPING_COST', 10.00); // ₹10 shipping fee

// API settings
define('API_RATE_LIMIT', 100); // 100 requests per hour
define('API_RESPONSE_CACHE_TIME', 300); // 5 minutes
ini_set('display_errors', 'Off');
?>