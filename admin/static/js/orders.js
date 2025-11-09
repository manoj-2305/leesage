function initOrders() {
    const statusFilter = document.getElementById('orderStatusFilter');
    const dateFromFilter = document.getElementById('orderDateFrom');
    const dateToFilter = document.getElementById('orderDateTo');
    const ordersTableBody = document.querySelector('.orders-table tbody');
    const orderDetailsModal = document.getElementById('orderDetailsModal');

    // Load orders when the page loads
    loadOrders();

    // Filter orders
    window.filterOrders = function () {
        const status = statusFilter ? statusFilter.value : '';
        const fromDate = dateFromFilter ? dateFromFilter.value : '';
        const toDate = dateToFilter ? dateToFilter.value : '';
        loadOrders(status, fromDate, toDate);
    };

    // Export orders
    window.exportOrders = function () {
        const status = statusFilter ? statusFilter.value : '';
        const fromDate = dateFromFilter ? dateFromFilter.value : '';
        const toDate = dateToFilter ? dateToFilter.value : '';
        exportOrdersToCSV(status, fromDate, toDate);
    };

    // View order details
    window.viewOrder = function (orderId) {
        try {
            orderDetailsModal.style.display = 'block';

            if (typeof ordersAPI === 'undefined') {
                console.error('Orders API not available');
                return;
            }

            ordersAPI.getOrderDetails(orderId)
                .then(response => {
                    if (response.success) {
                        updateOrderDetailsModal(response.data);
                    } else {
                        console.error('Failed to load order details:', response.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading order details:', error);
                });
        } catch (error) {
            console.error('Error viewing order:', error);
        }
    };

    // Update order status
    window.updateOrderStatus = function (orderId, status) {
        try {
            if (typeof ordersAPI === 'undefined') {
                console.error('Orders API not available');
                return;
            }

            ordersAPI.updateOrderStatus(orderId, status)
                .then(response => {
                    if (response.success) {
                        alert(`Order #${orderId} status updated to ${status}`);
                        loadOrders(); // Reload the orders list
                    } else {
                        alert(response.message || 'Failed to update order status');
                    }
                })
                .catch(error => {
                    console.error('Error updating order status:', error);
                    alert('An error occurred while updating the order status');
                });
        } catch (error) {
            console.error('Error updating order status:', error);
        }
    };

    // Cancel order
    window.cancelOrder = function (orderId) {
        if (confirm('Are you sure you want to cancel this order?')) {
            updateOrderStatus(orderId, 'cancelled');
        }
    };

    // Mark as delivered
    window.markAsDelivered = function (orderId) {
        updateOrderStatus(orderId, 'delivered');
    };

    // Load orders from API
    async function loadOrders(status = '', fromDate = '', toDate = '') {
        if (!ordersTableBody) return;

        try {
            ordersTableBody.innerHTML = '<tr><td colspan="7" class="loading">Loading orders...</td></tr>';

            if (typeof ordersAPI === 'undefined') {
                console.error('Orders API not available');
                ordersTableBody.innerHTML = '<tr><td colspan="7" class="error">Could not load orders. API not available.</td></tr>';
                return;
            }

            const response = await ordersAPI.getOrders(status, fromDate, toDate);

            if (response.success) {
                const orders = response.data.orders;

                if (orders.length === 0) {
                    ordersTableBody.innerHTML = '<tr><td colspan="7" class="empty">No orders found</td></tr>';
                    return;
                }

                ordersTableBody.innerHTML = '';

                orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>#${order.order_id}</td>
                        <td>${order.customer_name}<br><small>${order.customer_email}</small></td>
                        <td>${order.order_date}</td>
                        <td>${order.items_count} items</td>
                        <td>₹${order.total_amount}</td>
                        <td><span class="status-badge ${order.status.toLowerCase()}">${order.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewOrder(${order.order_id})">View</button>
                            <button class="btn btn-sm btn-warning" onclick="updateOrderStatus(${order.order_id}, '${getNextStatus(order.status)}')">Update</button>
                            ${order.status !== 'cancelled' && order.status !== 'delivered'
                        ? `<button class="btn btn-sm btn-danger" onclick="cancelOrder(${order.order_id})">Cancel</button>`
                        : ''}
                        </td>
                    `;
                    ordersTableBody.appendChild(row);
                });
            } else {
                ordersTableBody.innerHTML = `<tr><td colspan="7" class="error">${response.message || 'Failed to load orders'}</td></tr>`;
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            ordersTableBody.innerHTML = '<tr><td colspan="7" class="error">An error occurred while loading orders</td></tr>';
        }
    }

    // Get next status
    function getNextStatus(currentStatus) {
        const statusFlow = {
            'pending': 'processing',
            'processing': 'shipped',
            'shipped': 'delivered',
            'delivered': 'delivered',
            'cancelled': 'cancelled'
        };
        return statusFlow[currentStatus.toLowerCase()] || 'processing';
    }

    // Update order details modal
    function updateOrderDetailsModal(order) {
        document.getElementById('modal-order-id').textContent = `#${order.order.id}`;
        document.getElementById('modal-customer-name').textContent = order.order.first_name + ' ' + order.order.last_name;
        document.getElementById('modal-customer-email').textContent = order.order.email;
        document.getElementById('modal-customer-phone').textContent = order.order.customer_phone || 'N/A';
        document.getElementById('modal-shipping-address').textContent = order.order.shipping_address || 'N/A';
        document.getElementById('modal-order-date').textContent = order.order.created_at;

        const modalStatusBadge = document.getElementById('modal-order-status');
        modalStatusBadge.className = `status-badge ${order.order.status.toLowerCase()}`;
        modalStatusBadge.textContent = order.order.status;

        const itemsTableBody = document.getElementById('modal-order-items-body');
        itemsTableBody.innerHTML = '';
        order.items.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.product_name}</td>
                <td>${item.quantity}</td>
                <td>₹${parseFloat(item.unit_price).toFixed(2)}</td>
                <td>₹${(parseFloat(item.quantity) * parseFloat(item.unit_price)).toFixed(2)}</td>
            `;
            itemsTableBody.appendChild(row);
        });

        document.getElementById('modal-subtotal').textContent = `₹${parseFloat(order.order.subtotal).toFixed(2)}`;
        document.getElementById('modal-shipping').textContent = `₹${parseFloat(order.order.shipping_cost).toFixed(2)}`;
        document.getElementById('modal-tax').textContent = `₹${parseFloat(order.order.tax_amount).toFixed(2)}`;
        document.getElementById('modal-total').textContent = `₹${parseFloat(order.order.total_amount).toFixed(2)}`;

        const actionButtons = document.getElementById('modal-order-actions');
        actionButtons.innerHTML = '';

        if (order.order.status === 'pending') {
            actionButtons.innerHTML += `<button class="btn btn-primary" onclick="updateOrderStatus(${order.order.id}, 'processing')">Mark as Processing</button>`;
        }

        if (order.order.status === 'processing') {
            actionButtons.innerHTML += `<button class="btn btn-success" onclick="updateOrderStatus(${order.order.id}, 'shipped')">Mark as Shipped</button>`;
        }

        if (order.order.status === 'shipped') {
            actionButtons.innerHTML += `<button class="btn btn-warning" onclick="updateOrderStatus(${order.order.id}, 'delivered')">Mark as Delivered</button>`;
        }

        if (order.order.status !== 'cancelled' && order.order.status !== 'delivered') {
            actionButtons.innerHTML += `<button class="btn btn-danger" onclick="cancelOrder(${order.order.id})">Cancel Order</button>`;
        }
    }

    // Export orders to CSV
    function exportOrdersToCSV(status = '', fromDate = '', toDate = '') {
        try {
            if (typeof ordersAPI === 'undefined') {
                console.error('Orders API not available');
                alert('Could not export orders. API not available.');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/api/export-orders';
            form.target = '_blank';

            if (status) {
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                form.appendChild(statusInput);
            }

            if (fromDate) {
                const fromInput = document.createElement('input');
                fromInput.type = 'hidden';
                fromInput.name = 'from_date';
                fromInput.value = fromDate;
                form.appendChild(fromInput);
            }

            if (toDate) {
                const toInput = document.createElement('input');
                toInput.type = 'hidden';
                toInput.name = 'to_date';
                toInput.value = toDate;
                form.appendChild(toInput);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            alert('Exporting orders...');
        } catch (error) {
            console.error('Error exporting orders:', error);
            alert('An error occurred while exporting orders');
        }
    }
}
