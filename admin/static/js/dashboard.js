document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const toggleSidebarBtns = [document.getElementById('toggleSidebar'), document.getElementById('sidebarToggle')];
    const themeToggleBtn = document.getElementById('themeToggle');
    const notificationBtn = document.querySelector('.notification-btn');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const profileBtn = document.querySelector('.profile-btn');
    const profileDropdown = document.querySelector('.profile-dropdown');
    const dynamicContentArea = document.getElementById('dynamic-content-area');
    const navLinks = document.querySelectorAll('.nav-link[data-page]');
    const profileImage = document.getElementById('profileImage');
    const changeProfilePhotoBtn = document.getElementById('changeProfilePhotoBtn');
    const profileImageInput = document.getElementById('profileImageInput');

    // Utility functions (globally available)
    const adminUtils = {
        showNotification: (message, type) => {
            console.log(`Notification (${type}): ${message}`);
            // In a real application, you would display a toast, alert, or similar UI notification
            // For now, we'll just log it.
            alert(`${type.toUpperCase()}: ${message}`);
        },
        formatDate: (dateString) => {
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        },
        formatCurrency: (amount, currency = 'INR') => {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency,
            }).format(amount);
        }
    };

    /* Logout Functionality */
    const logoutLink = document.querySelector('.logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', async (e) => {
            e.preventDefault();
            await handleLogout();
        });
    }

    async function handleLogout() {
        try {
            const response = await fetch('../../backend/php/admin/auth/logout.php', {
                method: 'POST',
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear local storage
                localStorage.removeItem('adminRememberMe');
                localStorage.removeItem('adminUsername');
                sessionStorage.clear();
                
                // Redirect to login page
                window.location.href = '../index.html';
            } else {
                console.error('Logout failed:', data.message);
                alert('Logout failed. Please try again.');
            }
        } catch (error) {
            console.error('Logout error:', error);
            alert('An error occurred during logout. Please try again.');
        }
    }

    /* Sidebar Toggle */
    const toggleSidebar = (e) => {
        if (e.target.id === 'toggleSidebar') {
            sidebar.classList.toggle('collapsed');
            body.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        } else {
            sidebar.classList.toggle('active');
        }
    };
    toggleSidebarBtns.forEach(btn => btn?.addEventListener('click', toggleSidebar));

    /* Theme Toggle */
    const setTheme = (theme) => {
        body.classList.toggle('dark-mode', theme === 'dark');
        themeToggleBtn.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        localStorage.setItem('theme', theme);
    };
    themeToggleBtn?.addEventListener('click', () => {
        setTheme(body.classList.contains('dark-mode') ? 'light' : 'dark');
    });
    setTheme(localStorage.getItem('theme') || 'light');

    /* Dropdown Toggle */
    const toggleDropdown = (target, other) => {
        target.classList.toggle('active');
        other.classList.remove('active');
    };
    notificationBtn?.addEventListener('click', () => toggleDropdown(notificationDropdown, profileDropdown));
    profileBtn?.addEventListener('click', () => toggleDropdown(profileDropdown, notificationDropdown));

    /* Outside Click Close */
    document.addEventListener('click', (e) => {
        [[notificationBtn, notificationDropdown], [profileBtn, profileDropdown]]
            .forEach(([btn, dropdown]) => {
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.remove('active');
            });
        if (!sidebar.contains(e.target) && !toggleSidebarBtns.some(btn => btn?.contains(e.target))) {
            sidebar.classList.remove('active');
        }
    });

    /* Load Page Content */
    const loadPageContent = async (pageName) => {
        try {
            const url = pageName === 'dashboard'
                ? `../pages/dashboard.php`
                : `../pages/sections/${pageName}.html`;

            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            let contentContainer = doc.querySelector('#dashboard') ||
                doc.querySelector('[class$="-content"]') ||
                doc.body;

            // Destroy existing chart instance if dashboard content is being reloaded
            if (pageName === 'dashboard' && salesChartInstance) {
                salesChartInstance.destroy();
                salesChartInstance = null;
            }

            dynamicContentArea.innerHTML = contentContainer.innerHTML;
            dynamicContentArea.dispatchEvent(new CustomEvent('contentLoaded', { detail: { pageName } }));

            // Dynamically load page-specific scripts
            if (pageName === 'marketing' && typeof initMarketingPage === 'function') {
                initMarketingPage(dynamicContentArea);
            } else if (pageName === 'reviews' && typeof initializeReviewsPage === 'function') {
                initializeReviewsPage();
            } else if (pageName === 'analytics') {
                // Ensure analytics.js is loaded and then initialize
                const script = document.createElement('script');
                script.src = 'static/js/analytics.js';
                script.onload = () => {
                    if (typeof initializeAnalyticsDashboard === 'function') {
                        initializeAnalyticsDashboard();
                    }
                };
                dynamicContentArea.appendChild(script);
            } else if (pageName === 'payments') {
                const script = document.createElement('script');
                script.src = 'static/js/payments.js';
                dynamicContentArea.appendChild(script);
            }
        } catch (error) {
            console.error(`Could not load ${pageName} page:`, error);
            adminUtils.showNotification(`Could not load ${pageName} page: ${error.message}`, 'error');
            dynamicContentArea.innerHTML = `<div class="page-header"><h1>Error Loading Content</h1></div>`;
        }
    };

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            loadPageContent(link.dataset.page);
            navLinks.forEach(nav => nav.parentElement.classList.remove('active'));
            link.parentElement.classList.add('active');
            if (window.innerWidth <= 768) sidebar.classList.remove('active');
        });
    });

    /* Profile Fetch */
    const fetchAdminProfile = async () => {
        try {
            const response = await fetch('../../backend/php/admin/api/get_admin_profile.php');
            const data = await response.json();

            if (data.success) {
                const admin = data.data;
                
                // Get elements after profile section is loaded
                const profileUsername = document.getElementById('profile-username');
                const profileEmailInput = document.getElementById('profile-email-input');
                const profileFullnameInput = document.getElementById('profile-fullname-input');
                const profileRoleInput = document.getElementById('profile-role-input');
                const profileLastLoginInput = document.getElementById('profile-last-login-input');
                const profileFullname = document.getElementById('profile-fullname');
                const profileEmail = document.getElementById('profile-email');
                const profileRole = document.querySelector('.profile-role');
                const profileLastLogin = document.getElementById('profile-last-login');
                const profileImage = document.getElementById('profileImage');

                if (profileUsername) profileUsername.value = admin.username || '';
                if (profileEmailInput) profileEmailInput.value = admin.email || '';
                if (profileFullnameInput) profileFullnameInput.value = admin.full_name || '';
                if (profileRoleInput) profileRoleInput.value = admin.role || '';
                if (profileLastLoginInput) profileLastLoginInput.value = admin.last_login || 'N/A';
                if (profileFullname) profileFullname.textContent = admin.full_name || 'Admin';
                if (profileEmail) profileEmail.textContent = admin.email || '';
                if (profileRole) profileRole.textContent = admin.role || 'Admin';
                if (profileLastLogin) profileLastLogin.textContent = admin.last_login || 'N/A';
                
                if (profileImage) {
                    // Ensure the profile image path is correctly formatted
                    let profileImageUrl = admin.profile_image || 'https://via.placeholder.com/150';
                    if (profileImageUrl && !profileImageUrl.startsWith('http')) {
                        // Ensure it starts with /leesage/ for subdirectory
                        if (!profileImageUrl.startsWith('/leesage/')) {
                            profileImageUrl = '/leesage' + (profileImageUrl.startsWith('/') ? '' : '/') + profileImageUrl.replace(/^\/+/, '');
                        }
                    }
                    profileImage.src = profileImageUrl;
                }
            }
        } catch (error) {
            console.error('Error fetching admin profile:', error);
            adminUtils.showNotification('Error fetching admin profile: ' + error.message, 'error');
            const profileImage = document.getElementById('profileImage');
            if (profileImage) {
                profileImage.src = 'https://via.placeholder.com/150';
            }
        }
    };

    dynamicContentArea.addEventListener('contentLoaded', (event) => {
        console.log('Dashboard.js contentLoaded event fired for page:', event.detail.pageName);
        if (event.detail.pageName === 'profile') {
            fetchAdminProfile();
            setupProfileImageUpload();
        }
        if (event.detail.pageName === 'dashboard') loadDashboardStats();
        if (event.detail.pageName === 'analytics') {
            if (typeof initializeAnalyticsDashboard === 'function') {
                initializeAnalyticsDashboard();
            } else {
                adminUtils.showNotification('initializeAnalyticsDashboard function not found in analytics.js', 'error');
            }
        }
        if (event.detail.pageName === 'categories') {
            if (typeof initCategories === 'function') {
                initCategories();
            } else {
                adminUtils.showNotification('initCategories function not found in categories.js', 'error');
            }
        } else if (event.detail.pageName === 'orders') {
            if (typeof initOrders === 'function') {
                initOrders();
            } else {
                adminUtils.showNotification('initOrders function not found in orders.js', 'error');
            }
        } else if (event.detail.pageName === 'users') {
            if (typeof loadUsers === 'function') {
                loadUsers();
            } else {
                adminUtils.showNotification('loadUsers function not found in users.js', 'error');
            }
        } else if (event.detail.pageName === 'messages') {
            if (typeof initMessagesPage === 'function') {
                initMessagesPage();
            } else {
                adminUtils.showNotification('Error: initMessagesPage function not found.', 'error');
            }
        } else if (event.detail.pageName === 'marketing') {
            if (typeof initMarketingPage === 'function') {
                initMarketingPage(dynamicContentArea);
            } else {
                adminUtils.showNotification('Error: initMarketingPage function not found.', 'error');
            }
        } else if (event.detail.pageName === 'inventory') {
            if (typeof initInventoryPage === 'function') {
                initInventoryPage();
            } else {
                adminUtils.showNotification('Error: initInventoryPage function not found.', 'error');
            }
        } else if (event.detail.pageName === 'reviews') {
            if (typeof loadReviews === 'function') {
                loadReviews();
            } else {
                adminUtils.showNotification('Error: loadReviews function not found.', 'error');
            }
        }
        // Note: products page is handled in products.js - DO NOT add it here
    });

    function setupProfileImageUpload() {
        const changeProfilePhotoBtn = document.getElementById('changeProfilePhotoBtn');
        const profileImageInput = document.getElementById('profileImageInput');
        const profileImage = document.getElementById('profileImage');

        if (changeProfilePhotoBtn && profileImageInput) {
            changeProfilePhotoBtn.addEventListener('click', () => profileImageInput.click());
            
            profileImageInput.addEventListener('change', async () => {
                if (profileImageInput.files.length > 0) {
                    const file = profileImageInput.files[0];
                    const formData = new FormData();
                    formData.append('profile_image', file);

                    try {
                        const response = await fetch('../../backend/php/admin/api/upload_profile_image.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'include'
                        });
                        const data = await response.json();
                        
                        if (data.success) {
                            if (profileImage) {
                                profileImage.src = data.profile_image_url + '?' + new Date().getTime();
                            }
                            alert('Profile image updated successfully!');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error uploading profile image:', error);
                        alert('Error uploading profile image. Please try again.');
                    }
                }
            });
        }
    }

    /* Modals */
    const modalCloseBtns = document.querySelectorAll('.modal-close');
    modalCloseBtns.forEach(btn => btn.addEventListener('click', () => {
        const modal = btn.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }));



    /* Dashboard Stats and Charts */
    async function loadDashboardStats() {
        try {
            console.log('Loading dashboard stats...');
            const response = await fetch('../../backend/php/admin/api/get_dashboard_stats.php', {
                credentials: 'include' // Include cookies to maintain PHP session
            });
            const data = await response.json();
            console.log('Dashboard stats response:', data);
            
            if (data.success) {
                // Update stat cards
                const stats = data.data;
                // Default growth values if not provided by API
                const defaultGrowth = 0;
                
                updateStatCard('users', stats.counts.users, stats.total_users.growth);
                updateStatCard('orders', stats.counts.orders, stats.total_orders.growth);
                updateStatCard('revenue', stats.counts.revenue, stats.total_revenue.growth);
                updateStatCard('products', stats.counts.products, stats.products_in_stock.growth);
                
                // Update recent orders
                updateRecentOrders(stats.recent_orders);
                
                // Initialize charts with real data
                initializeDashboardCharts(stats.sales_chart.data, stats.order_counts_data);
            } else {
                console.error('Failed to load dashboard stats:', data.message);
                // If API call fails, still initialize charts with sample data
                initializeDashboardCharts();
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
            // If API call fails, still initialize charts with sample data
            initializeDashboardCharts();
        }
    }
    
    function updateStatCard(type, value, growth) {
        const valueElement = document.getElementById(`total${type.charAt(0).toUpperCase() + type.slice(1)}`);
        const growthElement = document.getElementById(`${type}Growth`);

        if (valueElement) {
            if (type === 'revenue') {
                valueElement.textContent = adminUtils.formatCurrency(value);
            } else {
                valueElement.textContent = value.toLocaleString();
            }
        }

        if (growthElement) {
            growthElement.textContent = `${growth}%`;
            if (growth > 0) {
                growthElement.parentElement.classList.remove('negative');
                growthElement.parentElement.classList.add('positive');
                growthElement.previousElementSibling.className = 'fas fa-arrow-up';
            } else if (growth < 0) {
                growthElement.parentElement.classList.remove('positive');
                growthElement.parentElement.classList.add('negative');
                growthElement.previousElementSibling.className = 'fas fa-arrow-down';
            } else {
                growthElement.parentElement.classList.remove('positive', 'negative');
                growthElement.previousElementSibling.className = 'fas fa-minus';
            }
        }
    }

    function updateRecentOrders(orders) {
        const recentOrdersList = document.getElementById('recentOrdersList');
        if (recentOrdersList) {
            recentOrdersList.innerHTML = ''; // Clear existing orders
            orders.forEach(order => {
                const orderItem = document.createElement('div');
                orderItem.classList.add('order-item');
                orderItem.innerHTML = `
                    <span class="order-id">#${order.id}</span>
                    <span class="order-customer">${order.user_name}</span>
                    <span class="order-amount">${adminUtils.formatCurrency(order.total_amount)}</span>
                    <span class="order-status ${order.status.toLowerCase().replace(' ', '-')}">${order.status}</span>
                `;
                recentOrdersList.appendChild(orderItem);
            });
        }
    }
    
    /* Dashboard Stats and Charts */
    let salesChartInstance = null; // Declare a variable to hold the chart instance

    function initializeDashboardCharts(salesData = null, orderCountsData = null) {
        if (typeof Chart === 'undefined') return;

        const salesCtx = document.getElementById('salesChart')?.getContext('2d');
        if (salesCtx) {
            // Destroy existing chart instance if it exists
            if (salesChartInstance) {
                salesChartInstance.destroy();
            }

            // Use real data if available, otherwise fallback to sample data
            const labels = salesData ? salesData.map(item => item.date) : [];
            const data = salesData ? salesData.map(item => item.revenue) : [];
            
            salesChartInstance = new Chart(salesCtx, { 
                type: 'line', 
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        label: 'Sales', 
                        data: data,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        tension: 0.3,
                        fill: true
                    }] 
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        const ordersCtx = document.getElementById('ordersChart')?.getContext('2d');
        if (ordersCtx) {
            new Chart(ordersCtx, { 
                type: 'bar', 
                data: { 
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 
                    datasets: [{ 
                        label: 'Orders', 
                        data: orderCountsData || [],
                        backgroundColor: '#4361ee'
                    }] 
                } 
            });
        }
    }

    loadPageContent('dashboard');
});