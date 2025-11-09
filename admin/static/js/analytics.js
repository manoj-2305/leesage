document.addEventListener('DOMContentLoaded', function() {
    // Fetch dashboard statistics from the backend
    fetchDashboardStats();
});

function initializeAnalyticsDashboard() {
    fetchDashboardStats();
}

async function fetchDashboardStats() {
    try {
        const response = await fetch('/Leesage/backend/php/admin/api/get_analytics.php');
        const data = await response.json();
        
        if (data.success) {
            // Update the main stats with null checks
            const totalSalesEl = document.getElementById('totalSales');
            const newCustomersEl = document.getElementById('newCustomersCount');
            const ordersPlacedEl = document.getElementById('ordersPlacedCount');
            const productsCountEl = document.getElementById('productsCount');
            
            if (totalSalesEl) {
                totalSalesEl.innerText = '₹' + parseFloat(data.data.totalSales || 0).toFixed(2);
            }
            if (newCustomersEl) {
                newCustomersEl.innerText = data.data.newCustomers || 0;
            }
            if (ordersPlacedEl) {
                ordersPlacedEl.innerText = data.data.totalOrders || 0;
            }
            if (productsCountEl) {
                productsCountEl.innerText = data.data.totalProducts || 0;
            }

            // Render charts with the available data
            renderSalesChart(data.data.salesOverTime); // Re-enabled for analytics page
            renderTopProductsChart(data.data.topSellingProducts);
            renderCustomerDemographicsChart(data.data.customerDemographics);
        } else {
            console.error('Error fetching dashboard stats:', data.message);
        }
    } catch (error) {
        console.error('Error fetching dashboard stats:', error);
    }
}

function renderSalesChart(salesData) {
    // This function is now responsible for rendering the sales chart on the analytics page.
    const salesCtx = document.getElementById('salesChart');
    if (!salesCtx) {
        console.warn('Sales chart canvas not found for analytics page');
        return;
    }
    
    if (salesData && salesData.length > 0) {
        const labels = salesData.map(item => item.order_day);
        const data = salesData.map(item => parseFloat(item.daily_sales || 0));
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales (₹)',
                    data: data,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

function renderTopProductsChart(productsData) {
    const topProductsCtx = document.getElementById('topProductsChart');
    if (!topProductsCtx) {
        // console.warn('Top products chart canvas not found'); // Removed warning as it's expected when not on analytics page
        return;
    }
    
    if (productsData && productsData.length > 0) {
        const labels = productsData.map(product => product.name);
        const data = productsData.map(product => parseInt(product.total_quantity_sold || 0));
        
        new Chart(topProductsCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Units Sold',
                    data: data,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

function renderCustomerDemographicsChart(demographicsData) {
    const customerDemographicsCtx = document.getElementById('customerDemographicsChart');
    if (!customerDemographicsCtx) {
        // console.warn('Customer demographics chart canvas not found'); // Removed warning as it's expected when not on analytics page
        return;
    }
    
    if (demographicsData && demographicsData.length > 0) {
        const labels = demographicsData.map(item => item.category);
        const data = demographicsData.map(item => parseInt(item.count || 0));
        
        new Chart(customerDemographicsCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Customer Distribution',
                    data: data,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Customer Demographics'
                    }
                }
            }
        });
    }
}
