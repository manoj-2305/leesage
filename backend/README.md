# Leesage E-commerce Admin Panel Backend

This directory contains the backend code for the Leesage E-commerce Admin Panel. The backend is built with PHP and MySQL, providing a robust API for managing products, categories, orders, users, and more.

## Directory Structure

```
backend/
├── database/
│   ├── config.php              # Database connection configuration
│   ├── structure.sql           # Main database structure
│   └── ecommerce_tables.sql    # E-commerce specific tables
└── php/
    ├── admin/                  # Admin panel backend
    │   ├── actions/            # Action handlers (add, edit, delete operations)
    │   ├── api/                # API endpoints (get data operations)
    │   └── auth/               # Authentication handlers
    └── public/                 # Public website backend
```

## API Endpoints

### Authentication

- `auth/login.php` - Admin login
- `auth/check_session.php` - Verify admin session

### Data Retrieval (API)

- `api/get_admin_profile.php` - Get admin user profile
- `api/get_products.php` - Get products with pagination and filters
- `api/get_categories.php` - Get categories with product counts
- `api/get_orders.php` - Get orders with pagination and filters
- `api/get_order_details.php` - Get detailed information for a specific order
- `api/get_users.php` - Get users with pagination and filters
- `api/get_inventory_history.php` - Get product inventory history
- `api/get_dashboard_stats.php` - Get statistics for the dashboard

### Data Modification (Actions)

- `actions/add_product.php` - Add a new product
- `actions/edit_product.php` - Edit an existing product
- `actions/delete_product.php` - Delete a product
- `actions/update_product_status.php` - Update product status (active/inactive)
- `actions/add_category.php` - Add a new category
- `actions/edit_category.php` - Edit an existing category
- `actions/delete_category.php` - Delete a category
- `actions/add_user.php` - Add a new user
- `actions/edit_user.php` - Edit an existing user
- `actions/delete_user.php` - Delete a user
- `actions/update_order_status.php` - Update order status

## Database Schema

### Admin Tables

- `admin_users` - Admin user accounts
- `admin_sessions` - Admin session management
- `admin_activity_logs` - Admin activity tracking

### E-commerce Tables

- `users` - Customer accounts
- `user_sessions` - Customer session management
- `categories` - Product categories
- `products` - Product information
- `product_categories` - Product-category relationships (many-to-many)
- `product_images` - Product images
- `orders` - Order information
- `order_items` - Items within orders
- `order_status_history` - Order status change history
- `inventory_history` - Product inventory change history
- `reviews` - Product reviews
- `messages` - Customer messages/inquiries

## Authentication

The admin panel uses session-based authentication. The `check_session.php` file provides the following functions:

- `isAdminLoggedIn()` - Check if an admin is logged in
- `getCurrentAdmin()` - Get the current admin's data
- `logAdminActivity()` - Log admin actions

## Frontend Integration

The frontend JavaScript in `admin/static/js/admin-api.js` provides a clean interface for interacting with these backend endpoints.

## Security Features

- Session-based authentication
- Password hashing
- Prepared statements for all database queries
- Input validation and sanitization
- Activity logging
- CSRF protection

## Setup

1. Import `database/structure.sql` to create the main database structure
2. Import `database/ecommerce_tables.sql` to create the e-commerce tables
3. Configure database connection in `database/config.php`
4. Ensure proper file permissions for image upload directories