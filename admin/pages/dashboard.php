<!DOCTYPE html>
<?php
require_once '../../backend/php/admin/auth/check_session.php';

// Redirect to login page if not logged in
if (!isAdminLoggedIn()) {
    header('Location: ../index.html'); // Assuming index.html is the login page
    exit();
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leesage Admin Panel</title>
    <link rel="stylesheet" href="../static/css/dashboard.css">
    <link rel="stylesheet" href="../static/css/sections.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="light-mode">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-store"></i>
                <span>Leesage Admin</span>
            </div>
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item active">
                    <a href="#dashboard" class="nav-link" data-page="dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#orders" class="nav-link" data-page="orders">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#products" class="nav-link" data-page="products">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#categories" class="nav-link" data-page="categories">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#users" class="nav-link" data-page="users">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#inventory" class="nav-link" data-page="inventory">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventory</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#analytics" class="nav-link" data-page="analytics">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#marketing" class="nav-link" data-page="marketing">
                        <i class="fas fa-bullhorn"></i>
                        <span>Marketing</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#reviews" class="nav-link" data-page="reviews">
                        <i class="fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#messages" class="nav-link" data-page="messages">
                        <i class="fas fa-envelope"></i>
                        <span>Messages</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#payments" class="nav-link" data-page="payments">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#security" class="nav-link" data-page="security">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search orders, products, users...">
                </div>
            </div>
            
            <div class="header-right">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="notifications">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </button>
                    <div class="notification-dropdown">
                        <div class="notification-item">
                            <i class="fas fa-shopping-cart"></i>
                            <span>New order #1234</span>
                        </div>
                        <div class="notification-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Low stock alert: Product XYZ</span>
                        </div>
                        <div class="notification-item">
                            <i class="fas fa-user"></i>
                            <span>New user registration</span>
                        </div>
                    </div>
                </div>
                <div class="profile">
                    <button class="profile-btn">
                        <span>Admin User</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="profile-dropdown">
                        <a href="#profile" class="nav-link" data-page="profile"><i class="fas fa-user"></i> Profile</a>
                        <a href="#settings" class="nav-link" data-page="settings"><i class="fas fa-cog"></i> Settings</a>
                        <a href="#logout" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content" id="pageContent">
            <div id="dynamic-content-area">
                <div class="page" id="analytics">
                    <!-- Content will be loaded dynamically here -->
                </div>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    // Load analytics content dynamically
                    fetch('sections/analytics.html')
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('analytics').innerHTML = html;
                        })
                        .catch(err => console.error('Failed to load analytics section:', err));
                </script>
                <!-- Dashboard Page -->
                <div class="page active" id="dashboard">
                    <div class="page-header">
                        <h1>Dashboard</h1>
                        <p>Welcome back, Admin! Here's what's happening with your store today.</p>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalUsers"></h3>
                                <p>Total Users</p>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                <span id="usersGrowth"></span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalOrders"></h3>
                                <p>Total Orders</p>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                <span id="ordersGrowth"></span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalRevenue"></h3>
                                <p>Total Revenue</p>
                            </div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                <span id="revenueGrowth"></span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="productsInStock"></h3>
                                <p>Products in Stock</p>
                            </div>
                            <div class="stat-change negative">
                                <i class="fas fa-arrow-down"></i>
                                <span id="productsGrowth"></span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="chart-container" style="height: 300px;">
                            <h3>Sales Trends</h3>
                            <canvas id="salesChart"></canvas>
                        </div>
                        <div class="recent-orders">
                            <h3>Recent Orders</h3>
                            <div class="order-list" id="recentOrdersList">
                                <!-- Recent orders will be loaded dynamically here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../static/js/admin-api.js"></script>
    <script src="../static/js/modals.js"></script>
    <script src="../static/js/dashboard.js"></script>
    <script src="../static/js/products.js"></script>
    <script src="../static/js/orders.js"></script>
    <script src="../static/js/categories.js"></script>
    <script src="../static/js/users.js"></script>
    <script src="../static/js/inventory.js"></script>
    <script src="../static/js/analytics.js"></script>
    <script src="../static/js/messages.js"></script>
    <script src="../static/js/marketing.js"></script>
    <script src="../static/js/reviews.js"></script>
</body>
</html>
