// Checkout JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadCheckoutData();
    setupCheckoutEventListeners();
});

function setupCheckoutEventListeners() {
    // Apply coupon button
    document.getElementById('apply-coupon-btn').addEventListener('click', applyCoupon);

    // Place order button
    document.getElementById('place-order-btn').addEventListener('click', placeOrder);
}

function loadCheckoutData() {
    // Load cart items
    loadCartItems();

    // Load selected address
    loadSelectedAddress();

    // Load personal details
    loadPersonalDetails();
    loadPaymentMethods();

    // Load initial totals
    updateTotals();
}

function loadCartItems() {
    fetch('/leesage/backend/php/public/api/cart.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayCartItems(data.data.items);
        } else {
            showMessage('Failed to load cart items', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading cart items:', error);
        showMessage('Error loading cart items', 'error');
    });
}

function displayCartItems(items) {
    const tbody = document.getElementById('checkout-items-body');
    tbody.innerHTML = '';

    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5">Your cart is empty</td></tr>';
        return;
    }

    items.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><img src="../../${item.product.image || 'placeholder.jpg'}" alt="${item.product.name}" class="product-image"></td>
            <td>${item.product.name}</td>
            <td>₹${item.product.price.toFixed(2)}</td>
            <td>${item.quantity}</td>
            <td class="subtotal">₹${(item.product.price * item.quantity).toFixed(2)}</td>
        `;
        tbody.appendChild(row);
    });
}

function loadSelectedAddress() {
    const selectedAddressId = localStorage.getItem('selectedAddressId');
    if (!selectedAddressId) {
        document.getElementById('selected-address').innerHTML = '<p>No address selected. Please go back and select an address.</p>';
        return;
    }

    fetch(`/leesage/backend/php/public/api/addresses.php?id=${selectedAddressId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySelectedAddress(data.data);
        } else {
            showMessage('Failed to load address', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading address:', error);
        showMessage('Error loading address', 'error');
    });
}

function displaySelectedAddress(address) {
    const addressDiv = document.getElementById('selected-address');
    addressDiv.innerHTML = `
        <p><strong>${address.address_line1}</strong></p>
        ${address.address_line2 ? `<p>${address.address_line2}</p>` : ''}
        <p>${address.city}, ${address.state_province} ${address.postal_code}</p>
        <p>${address.country}</p>
        <p>Phone: ${address.phone_number}</p>
    `;
}

function loadPersonalDetails() {
    const userFirstName = localStorage.getItem('userFirstName');
    const userEmail = localStorage.getItem('userEmail');

    if (userFirstName && userEmail) {
        displayPersonalDetails({ first_name: userFirstName, email: userEmail });
    } else {
        // Fallback or handle case where user details are not in localStorage
        document.getElementById('personal-details').innerHTML = '<p>Personal details not available. Please log in.</p>';
    }
}

function displayPersonalDetails(user) {
    const personalDetailsDiv = document.getElementById('personal-details');
    personalDetailsDiv.innerHTML = `
        <p><strong>Name:</strong> ${user.first_name}</p>
        <p><strong>Email:</strong> ${user.email}</p>
    `;
}

function applyCoupon() {
    const couponCode = document.getElementById('coupon-code').value.trim();
    if (!couponCode) {
        showMessage('Please enter a coupon code', 'error');
        return;
    }

    fetch('/leesage/backend/php/public/api/coupons.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ code: couponCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Coupon applied successfully', 'success');
            const appliedCouponData = {
                code: data.data.code,
                type: data.data.discount_type,
                value: data.data.discount_value,
                min_order_amount: data.data.min_order_amount,
                discount_amount: 0 // This will be calculated in updateTotals
            };
            localStorage.setItem('appliedCoupon', JSON.stringify(appliedCouponData));
            updateTotals();
        } else {
            showMessage(data.message, 'error');
            localStorage.removeItem('appliedCoupon');
            updateTotals();
        }
    })
    .catch(error => {
        console.error('Error applying coupon:', error);
        showMessage('Error applying coupon', 'error');
    });
}

function updateTotals() {
    fetch('/leesage/backend/php/public/api/cart.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cart = data.data;
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            const appliedCoupon = JSON.parse(localStorage.getItem('appliedCoupon') || 'null');

            let subtotal = cart.subtotal;
            let tax = subtotal * 0.10; // 10% tax
            let shipping = (paymentMethod === 'cod') ? 49.00 : (subtotal >= 100 ? 0 : 10.00);
            let discount = 0;

            if (appliedCoupon) {
                if (appliedCoupon.type === 'percentage') {
                    discount = subtotal * (appliedCoupon.value / 100);
                } else {
                    discount = Math.min(appliedCoupon.value, subtotal);
                }
                appliedCoupon.discount_amount = discount; // Store calculated discount
                localStorage.setItem('appliedCoupon', JSON.stringify(appliedCoupon));
            }

            let total = subtotal + tax + shipping - discount;

            displayTotals(subtotal, tax, shipping, discount, total);
        }
    })
    .catch(error => {
        console.error('Error updating totals:', error);
    });
}

function displayTotals(subtotal, tax, shipping, discount, total) {
    const totalsDiv = document.getElementById('order-totals');
    totalsDiv.innerHTML = `
        <p>Subtotal: ₹${subtotal.toFixed(2)}</p>
        <p>Tax (10%): ₹${tax.toFixed(2)}</p>
        <p>Shipping: ₹${shipping.toFixed(2)}</p>
        ${discount > 0 ? `<p>Discount: -₹${discount.toFixed(2)}</p>` : ''}
        <p><strong>Total: ₹${total.toFixed(2)}</strong></p>
    `;
}

function placeOrder() {
    const selectedAddressId = localStorage.getItem('selectedAddressId');
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const appliedCoupon = JSON.parse(localStorage.getItem('appliedCoupon') || 'null');

    if (!selectedAddressId) {
        showMessage('Please select an address', 'error');
        return;
    }

    if (!paymentMethod) {
        showMessage('Please select a payment method', 'error');
        return;
    }

    const orderData = {
        address_id: selectedAddressId,
        payment_method: paymentMethod,
        coupon_availed: appliedCoupon ? 1 : 0,
        coupon_details: appliedCoupon ? appliedCoupon : null
    };

    // Disable button to prevent double submission
    const placeOrderBtn = document.getElementById('place-order-btn');
    placeOrderBtn.disabled = true;
    placeOrderBtn.textContent = 'Processing...';

    fetch('/leesage/backend/php/public/api/checkout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Order placed successfully!', 'success');
            localStorage.removeItem('selectedAddressId');
            localStorage.removeItem('appliedCoupon');
            // Redirect to order confirmation page or orders page
            setTimeout(() => {
                window.location.href = 'orders.html'; // Assuming orders page exists
            }, 2000);
        } else {
            showMessage(data.message, 'error');
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'Place Order';
        }
    })
    .catch(error => {
        console.error('Error placing order:', error);
        showMessage('Error placing order', 'error');
        placeOrderBtn.disabled = false;
        placeOrderBtn.textContent = 'Place Order';
    });
}

function showMessage(message, type) {
    // Simple alert for now, can be enhanced with toast notifications
    alert(message);
}

function loadPaymentMethods() {
    // For now, we'll use dummy payment methods. In a real application, these would come from an API.
    const paymentMethods = [
        { id: 'cod', name: 'Cash on Delivery (COD)', description: 'Additional ₹49 shipping' },
        { id: 'razorpay', name: 'Razorpay', description: 'Pay securely online' }
    ];
    displayPaymentMethods(paymentMethods);
}

function displayPaymentMethods(paymentMethods) {
    const container = document.getElementById('payment-methods-container');
    container.innerHTML = ''; // Clear previous content

    paymentMethods.forEach(method => {
        const div = document.createElement('div');
        div.classList.add('payment-method-option');
        div.innerHTML = `
            <input type="radio" id="${method.id}" name="payment_method" value="${method.id}" required>
            <label for="${method.id}">
                <strong>${method.name}</strong><br>
                <span>${method.description}</span>
            </label>
        `;
        container.appendChild(div);
    });

    // Select the first payment method by default if none is selected
    const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedPaymentMethod && paymentMethods.length > 0) {
        document.getElementById(paymentMethods[0].id).checked = true;
    }

    // Add event listener to update totals when payment method changes
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', updateTotals);
    });
}
