function filterReviews() {
    const status = document.getElementById('reviewStatusFilter').value;
    const searchTerm = document.getElementById('reviewSearch').value;
    loadReviews(1, searchTerm, status);
}

async function loadReviews(page = 1, search = '', status = '') {
    showLoadingIndicator();
    const response = await reviewsAPI.getReviews(page, search, status);
    hideLoadingIndicator();

    if (response.success) {
        renderReviews(response.reviews);
        renderPagination(response.total_pages, response.current_page, search, status);
    } else {
        displayErrorMessage('Failed to load reviews: ' + response.message);
        document.getElementById('reviewsTableBody').innerHTML = '<tr><td colspan="8" class="text-center">' + (response.message || 'No reviews found.') + '</td></tr>';
        document.getElementById('reviewsPagination').innerHTML = '';
    }
}

function renderReviews(reviews) {
    const tbody = document.getElementById('reviewsTableBody');
    tbody.innerHTML = '';
    if (reviews.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No reviews found.</td></tr>';
        return;
    }

    reviews.forEach(review => {

        const row = `
            <tr>
                <td>${review.id}</td>
                <td>${review.product_name}</td>
                <td>${review.first_name} ${review.last_name}</td>
                <td>${review.rating} <i class="fas fa-star"></i></td>
                <td>${review.review_text.substring(0, 50)}${review.review_text.length > 50 ? '...' : ''}</td>
                <td>${new Date(review.created_at).toLocaleDateString()}</td>
                <td><span class="status-badge ${getReviewStatusClass(review.is_approved)}">${getReviewStatusText(review.is_approved)}</span></td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewReview(${review.id})">View</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteReview(${review.id})">Delete</button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function getReviewStatusText(isApproved) {
    if (isApproved == 1) return 'Approved';
    if (isApproved == 2) return 'Rejected';
    return 'Pending';
}

function getReviewStatusClass(isApproved) {
    if (isApproved == 1) return 'success';
    if (isApproved == 2) return 'danger';
    return 'warning';
}

function renderPagination(totalPages, currentPage, search, status) {
    const paginationContainer = document.getElementById('reviewsPagination');
    paginationContainer.innerHTML = '';

    if (totalPages <= 1) return;

    let paginationHtml = `<ul class="pagination justify-content-center">`;

    // Previous button
    paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="loadReviews(${currentPage - 1}, '${search}', '${status}')">Previous</a>
                        </li>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        paginationHtml += `<li class="page-item ${currentPage === i ? 'active' : ''}">
                                <a class="page-link" href="#" onclick="loadReviews(${i}, '${search}', '${status}')">${i}</a>
                            </li>`;
    }

    // Next button
    paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="loadReviews(${currentPage + 1}, '${search}', '${status}')">Next</a>
                        </li>`;

    paginationHtml += `</ul>`;
    paginationContainer.innerHTML = paginationHtml;
}

async function viewReview(reviewId) {
    showLoadingIndicator();
    const response = await reviewsAPI.getReviewDetails(reviewId);
    hideLoadingIndicator();

    if (response.success) {
        const review = response.review;
        document.getElementById('modalReviewId').textContent = review.id;
        document.getElementById('modalReviewProduct').textContent = review.product_name;
        document.getElementById('modalReviewCustomer').textContent = `${review.first_name} ${review.last_name}`;
        document.getElementById('modalReviewRating').innerHTML = `${review.rating} <i class="fas fa-star"></i>`;
        document.getElementById('modalReviewComment').textContent = review.review_text;
        document.getElementById('modalReviewDate').textContent = new Date(review.created_at).toLocaleDateString();
        document.getElementById('modalReviewStatus').innerHTML = `<span class="status-badge ${getReviewStatusClass(review.is_approved)}">${getReviewStatusText(review.is_approved)}</span>`;
        
        // Set data attributes for update status buttons
        document.getElementById('modalApproveBtn').dataset.reviewId = review.id;
        document.getElementById('modalPendingBtn').dataset.reviewId = review.id;
        document.getElementById('modalRejectBtn').dataset.reviewId = review.id;

        document.getElementById('reviewDetailsModal').style.display = 'block';
    } else {
        displayErrorMessage('Failed to load review details: ' + response.message);
    }
}

function closeReviewModal() {
    document.getElementById('reviewDetailsModal').style.display = 'none';
}

async function updateReviewStatus(reviewId, status) {
    showLoadingIndicator();
    const response = await reviewsAPI.updateReviewStatus(reviewId, status);
    hideLoadingIndicator();

    if (response.success) {
        displaySuccessMessage(response.message);
        closeReviewModal();
        loadReviews(); // Reload reviews to reflect changes
    } else {
        displayErrorMessage('Failed to update review status: ' + response.message);
    }
}

async function deleteReview(reviewId) {
    if (confirm('Are you sure you want to delete this review?')) {
        showLoadingIndicator();
        const response = await reviewsAPI.deleteReview(reviewId);
        hideLoadingIndicator();

        if (response.success) {
            displaySuccessMessage(response.message);
            loadReviews(); // Reload reviews to reflect changes
        } else {
            displayErrorMessage('Failed to delete review: ' + response.message);
        }
    }
}

function initializeReviewsPage() {
    const reviewDetailsModal = document.getElementById('reviewDetailsModal');
    const modalClose = reviewDetailsModal.querySelector('.modal-close');

    if (modalClose) {
        modalClose.addEventListener('click', closeReviewModal);
    }

    window.addEventListener('click', function(event) {
        if (event.target == reviewDetailsModal) {
            closeReviewModal();
        }
    });

    document.getElementById('modalApproveBtn').addEventListener('click', (e) => updateReviewStatus(e.target.dataset.reviewId, 'approved'));
    document.getElementById('modalPendingBtn').addEventListener('click', (e) => updateReviewStatus(e.target.dataset.reviewId, 'pending'));
    document.getElementById('modalRejectBtn').addEventListener('click', (e) => updateReviewStatus(e.target.dataset.reviewId, 'rejected'));

    document.getElementById('reviewSearch').addEventListener('input', debounce(filterReviews, 300));
    document.getElementById('reviewStatusFilter').addEventListener('change', filterReviews);

    loadReviews(); // Initial load of reviews
}

// Utility functions for messages and loading (assuming they are defined elsewhere or will be added)
function showLoadingIndicator() {
    // Implement showing a loading spinner or similar
    console.log('Showing loading indicator...');
}

function hideLoadingIndicator() {
    // Implement hiding the loading spinner
    console.log('Hiding loading indicator...');
}

function displaySuccessMessage(message) {
    // Implement displaying a success toast or alert
    console.log('Success:', message);
}

function displayErrorMessage(message) {
    // Implement displaying an error toast or alert
    console.log('Error:', message);
}

function debounce(func, delay) {
    let timeout;
    return function(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), delay);
    };
}