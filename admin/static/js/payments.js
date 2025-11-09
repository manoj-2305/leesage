let currentPage = 1;
let currentLimit = 10;

async function loadPayments(page = 1, limit = 10, status = '', dateFrom = '', dateTo = '', searchTerm = '') {
    try {
        const queryParams = new URLSearchParams({
            page: page,
            limit: limit,
            status: status,
            date_from: dateFrom,
            date_to: dateTo,
            search: searchTerm
        });
        const response = await fetch(`../../backend/php/admin/api/get_payments.php?${queryParams.toString()}`);
        const result = await response.json();

        if (result.success) {
            populatePaymentsTable(result.data.payments);
            renderPagination(result.data.pagination);
        } else {
            console.error('Error loading payments:', result.message);
            // Optionally show a notification to the user
        }
    } catch (error) {
        console.error('Fetch error:', error);
        // Optionally show a notification to the user
    }
}

function populatePaymentsTable(payments) {
    const tableBody = document.querySelector('.payments-table tbody');
    tableBody.innerHTML = ''; // Clear existing rows

    if (payments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center">No payments found.</td></tr>';
        return;
    }

    payments.forEach(payment => {
        const row = `
            <tr>
                <td>${payment.transaction_id}</td>
                <td>${payment.order_number || 'N/A'}</td>
                <td>${payment.first_name} ${payment.last_name}</td>
                <td>₹${parseFloat(payment.amount).toFixed(2)}</td>
                <td>${payment.payment_method}</td>
                <td>${payment.payment_date}</td>
                <td><span class="status-badge ${payment.status.toLowerCase().replace(' ', '-')}">${payment.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewTransaction('${payment.transaction_id}')">View</button>
                    ${payment.status === 'completed' ? `<button class="btn btn-sm btn-warning" onclick="processRefund('${payment.transaction_id}')">Refund</button>` : ''}
                    ${payment.status === 'pending' ? `<button class="btn btn-sm btn-success" onclick="markAsCompleted('${payment.transaction_id}')">Mark Completed</button>` : ''}
                </td>
            </tr>
        `;
        tableBody.innerHTML += row;
    });
}

function renderPagination(pagination) {
    const paginationContainer = document.querySelector('.pagination'); // Assuming a .pagination div exists
    if (!paginationContainer) return;

    paginationContainer.innerHTML = '';

    if (pagination.total_pages <= 1) {
        return;
    }

    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.classList.add('pagination-btn');
    prevBtn.innerHTML = '&laquo; Previous';
    prevBtn.disabled = pagination.page === 1;
    prevBtn.addEventListener('click', () => {
        if (pagination.page > 1) {
            currentPage = pagination.page - 1;
            loadPayments(currentPage, currentLimit, 
                document.getElementById('paymentStatusFilter').value,
                document.getElementById('paymentDateFrom').value,
                document.getElementById('paymentDateTo').value,
                document.getElementById('paymentSearch').value
            );
        }
    });
    paginationContainer.appendChild(prevBtn);

    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, pagination.page - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(pagination.total_pages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.classList.add('pagination-btn');
        if (i === pagination.page) {
            pageBtn.classList.add('active');
        }
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => {
            currentPage = i;
            loadPayments(currentPage, currentLimit,
                document.getElementById('paymentStatusFilter').value,
                document.getElementById('paymentDateFrom').value,
                document.getElementById('paymentDateTo').value,
                document.getElementById('paymentSearch').value
            );
        });
        paginationContainer.appendChild(pageBtn);
    }

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.classList.add('pagination-btn');
    nextBtn.innerHTML = 'Next &raquo;';
    nextBtn.disabled = pagination.page === pagination.total_pages;
    nextBtn.addEventListener('click', () => {
        if (pagination.page < pagination.total_pages) {
            currentPage = pagination.page + 1;
            loadPayments(currentPage, currentLimit,
                document.getElementById('paymentStatusFilter').value,
                document.getElementById('paymentDateFrom').value,
                document.getElementById('paymentDateTo').value,
                document.getElementById('paymentSearch').value
            );
        }
    });
    paginationContainer.appendChild(nextBtn);
}

function filterPayments() {
    currentPage = 1; // Reset to first page on filter
    const status = document.getElementById('paymentStatusFilter').value;
    const fromDate = document.getElementById('paymentDateFrom').value;
    const toDate = document.getElementById('paymentDateTo').value;
    const searchTerm = document.getElementById('paymentSearch').value;
    loadPayments(currentPage, currentLimit, status, fromDate, toDate, searchTerm);
}

async function viewTransaction(transactionId) {
    console.log('Viewing transaction:', transactionId);
    try {
        const response = await fetch(`../../backend/php/admin/api/get_payment_details.php?transaction_id=${transactionId}`);
        const result = await response.json();

        if (result.success) {
            const payment = result.payment;
            document.getElementById('modalTransactionId').textContent = payment.transaction_id;
            document.getElementById('modalOrderId').textContent = payment.order_number || 'N/A';
            document.getElementById('modalCustomerName').textContent = `${payment.first_name} ${payment.last_name}`;
            document.getElementById('modalCustomerEmail').textContent = payment.email || 'N/A';
            document.getElementById('modalAmount').textContent = `₹${parseFloat(payment.amount).toFixed(2)}`;
            document.getElementById('modalPaymentMethod').textContent = payment.payment_method;
            document.getElementById('modalTransactionDate').textContent = new Date(payment.payment_created_at).toLocaleString();
            document.getElementById('modalTransactionStatus').textContent = payment.payment_status;
            document.getElementById('modalOrderDate').textContent = new Date(payment.order_date).toLocaleString();
            document.getElementById('modalAdditionalDetails').textContent = payment.notes || 'No additional details.';

            // Populate User Details
            document.getElementById('modalUserId').textContent = payment.user_id || 'N/A';
            document.getElementById('modalUserEmail').textContent = payment.email || 'N/A';

            // Populate Order Details
            document.getElementById('modalOrderTotal').textContent = `₹${parseFloat(payment.order_total).toFixed(2)}` || 'N/A';
            
            // Populate Shipping Address
            document.getElementById('modalShippingAddress').innerHTML = formatAddress(payment.shipping_address);

            // Populate Billing Address
            document.getElementById('modalBillingAddress').innerHTML = formatAddress(payment.billing_address);

            // Populate Order Items
            renderOrderItems(payment.order_items || []);

            // Populate Payment History
            renderPaymentHistory(payment.payment_history || []);

            // Update refund button visibility and action
            const modalRefundBtn = document.getElementById('modalRefundBtn');
            if (payment.payment_status === 'completed') {
                modalRefundBtn.style.display = 'inline-block';
                modalRefundBtn.onclick = () => processRefund(payment.transaction_id);
            } else {
                modalRefundBtn.style.display = 'none';
            }

            // Show the modal
            $('#transactionDetailsModal').modal('show');
        } else {
            console.error('Error fetching transaction details:', result.message);
            // Optionally show a notification
        }
    } catch (error) {
        console.error('Fetch error:', error);
    }
}

function formatAddress(address) {
    if (!address || !address.address_line1) return 'N/A';
    let formatted = address.address_line1;
    if (address.address_line2) formatted += `<br>${address.address_line2}`;
    if (address.city) formatted += `<br>${address.city}`;
    if (address.state) formatted += `, ${address.state}`;
    if (address.postal_code) formatted += ` ${address.postal_code}`;
    if (address.country) formatted += `<br>${address.country}`;
    return formatted;
}

function renderOrderItems(items) {
    const orderItemsContainer = document.getElementById('modalOrderItems');
    orderItemsContainer.innerHTML = '';
    if (items.length === 0) {
        orderItemsContainer.innerHTML = '<p>No order items found.</p>';
        return;
    }
    const ul = document.createElement('ul');
    ul.classList.add('list-group');
    items.forEach(item => {
        const li = document.createElement('li');
        li.classList.add('list-group-item');
        li.textContent = `${item.product_name} (x${item.quantity}) - ₹${parseFloat(item.price).toFixed(2)}`;
        ul.appendChild(li);
    });
    orderItemsContainer.appendChild(ul);
}

function renderPaymentHistory(history) {
    const paymentHistoryContainer = document.getElementById('modalPaymentHistory');
    paymentHistoryContainer.innerHTML = '';
    if (history.length === 0) {
        paymentHistoryContainer.innerHTML = '<p>No payment history found.</p>';
        return;
    }
    const ul = document.createElement('ul');
    ul.classList.add('list-group');
    history.forEach(record => {
        const li = document.createElement('li');
        li.classList.add('list-group-item');
        li.textContent = `Status: ${record.status} on ${new Date(record.timestamp).toLocaleString()} - Notes: ${record.notes || 'N/A'}`;
        ul.appendChild(li);
    });
    paymentHistoryContainer.appendChild(ul);
}

async function processRefund(transactionId) {
    if (confirm('Are you sure you want to process a refund for transaction ' + transactionId + '?')) {
        try {
            const response = await fetch('../../backend/php/admin/api/update_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ transaction_id: transactionId, status: 'refunded' })
            });
            const result = await response.json();

            if (result.success) {
                console.log('Refund processed successfully:', result.message);
                // Close the modal if it's open
                $('#transactionDetailsModal').modal('hide');
                loadPayments(currentPage, currentLimit, 
                    document.getElementById('paymentStatusFilter').value,
                    document.getElementById('paymentDateFrom').value,
                    document.getElementById('paymentDateTo').value,
                    document.getElementById('paymentSearch').value
                );
            } else {
                console.error('Error processing refund:', result.message);
                alert('Failed to process refund: ' + result.message);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('An error occurred while processing refund.');
        }
    }
}

async function markAsCompleted(transactionId) {
    if (confirm('Are you sure you want to mark transaction ' + transactionId + ' as completed?')) {
        try {
            const response = await fetch('../../backend/php/admin/api/update_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ transaction_id: transactionId, status: 'completed' })
            });
            const result = await response.json();

            if (result.success) {
                console.log('Transaction marked as completed successfully:', result.message);
                // Close the modal if it's open
                $('#transactionDetailsModal').modal('hide');
                loadPayments(currentPage, currentLimit, 
                    document.getElementById('paymentStatusFilter').value,
                    document.getElementById('paymentDateFrom').value,
                    document.getElementById('paymentDateTo').value,
                    document.getElementById('paymentSearch').value
                );
            } else {
                console.error('Error marking transaction as completed:', result.message);
                alert('Failed to mark transaction as completed: ' + result.message);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('An error occurred while marking transaction as completed.');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Event listeners for filters
    document.getElementById('paymentStatusFilter').addEventListener('change', filterPayments);
    document.getElementById('paymentDateFrom').addEventListener('change', filterPayments);
    document.getElementById('paymentDateTo').addEventListener('change', filterPayments);
    document.getElementById('paymentSearch').addEventListener('keyup', filterPayments);
    document.querySelector('.payment-filters .btn-primary').addEventListener('click', filterPayments);

    // Initial load of payments
    loadPayments();
});