document.addEventListener('DOMContentLoaded', function() {
    const dynamicContentArea = document.getElementById('dynamic-content-area');

    function addSizeField(type = 'add') {
        const containerId = type === 'edit' ? 'edit_product_sizes_container' : 'product_sizes_container';
        const container = document.getElementById(containerId);
        if (!container) return;

        const sizeInputGroup = document.createElement('div');
        sizeInputGroup.classList.add('size-input-group');
        sizeInputGroup.innerHTML = `
            ${type === 'edit' ? '<input type="hidden" name="size_ids[]" value="">' : ''}
            <input type="text" name="sizes[]" placeholder="Size (e.g., S, M, L)" required>
            <input type="number" name="stock_quantities[]" placeholder="Stock Quantity" min="0" required>
            <input type="number" name="min_stock_levels[]" placeholder="Min Stock Level" min="0" value="0">
            <button type="button" class="btn btn-danger remove-size">Remove</button>
        `;
        container.appendChild(sizeInputGroup);
    }

    dynamicContentArea.addEventListener('contentLoaded', function(event) {
        console.log('Products.js contentLoaded event fired for page:', event.detail.pageName);
        if (event.detail.pageName === 'products') {
            console.log('Initializing products page from products.js');
            initializeProductsPage();
        }
    });

    function initializeProductsPage() {
        console.log('=== INITIALIZE PRODUCTSPAGE CALLED ===');
        console.log('Current timestamp:', Date.now());
        const addProductBtn = document.getElementById('addProductBtn');
        const modalClose = document.getElementById('modalClose');
        const cancelAddProduct = document.getElementById('cancelAddProduct');
        const addProductForm = document.getElementById('addProductForm');
        const editModalClose = document.getElementById('editModalClose');
        const cancelEditProduct = document.getElementById('cancelEditProduct');
        const editProductForm = document.getElementById('editProductForm');
        const productsTableBody = document.querySelector('.products-table tbody');

        // Ensure API dependencies are loaded
        function ensureAPIsLoaded() {
            if (typeof productsAPI === 'undefined') {
                console.warn('Products API not loaded, attempting to load admin-api.js');
                const script = document.createElement('script');
                script.src = '../../static/js/admin-api.js';
                script.onload = function() {
                    console.log('APIs loaded successfully');
                    initializeProducts();
                };
                script.onerror = function() {
                    console.error('Failed to load admin-api.js');
                    showError('Could not load required APIs. Please refresh the page.');
                };
                document.head.appendChild(script);
            } else {
                initializeProducts();
            }
        }

        function initializeProducts() {
            console.log('=== INITIALIZE PRODUCTS CALLED ===');
            console.log('Current timestamp:', Date.now());
            loadProducts();
            setupEventListeners();
        }

        function setupEventListeners() {
            console.log('=== SETUP EVENT LISTENERS CALLED ===');
            console.log('Current timestamp:', Date.now());
            console.log('addProductForm exists:', !!addProductForm);
            console.log('editProductForm exists:', !!editProductForm);
            // Add Product button
            if (addProductBtn) {
                addProductBtn.addEventListener('click', function() {
                    console.log('Add Product button clicked');
                    openAddProductModal();
                });
            }

            // Modal close buttons
            if (modalClose) {
                modalClose.addEventListener('click', closeAddProductModal);
            }

            if (cancelAddProduct) {
                cancelAddProduct.addEventListener('click', closeAddProductModal);
            }

            if (editModalClose) {
                editModalClose.addEventListener('click', closeEditProductModal);
            }

            if (cancelEditProduct) {
                cancelEditProduct.addEventListener('click', closeEditProductModal);
            }

            // Close modal when clicking outside
            const addProductModal = document.getElementById('addProductModal');
            if (addProductModal) {
                addProductModal.addEventListener('click', function(e) {
                    if (e.target === addProductModal) {
                        closeAddProductModal();
                    }
                });
            }

            const editProductModal = document.getElementById('editProductModal');
            if (editProductModal) {
                editProductModal.addEventListener('click', function(e) {
                    if (e.target === editProductModal) {
                        closeEditProductModal();
                    }
                });
            }

            // Form submissions
            if (addProductForm) {
                addProductForm.addEventListener('submit', handleAddFormSubmit);
            }

            if (editProductForm) {
                editProductForm.addEventListener('submit', handleEditFormSubmit);
            }

            // Add size field button
            const addSizeFieldBtn = document.getElementById('add_size_field');
            if (addSizeFieldBtn) {
                addSizeFieldBtn.addEventListener('click', addSizeField);
            }

            const editAddSizeFieldBtn = document.getElementById('edit_add_size_field');
            if (editAddSizeFieldBtn) {
                editAddSizeFieldBtn.addEventListener('click', function() {
                    addSizeField('edit');
                });
            }

            // Event delegation for removing size fields
            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-size')) {
                    e.target.closest('.size-input-group').remove();
                }
            });
        }

        function openAddProductModal() {
            addProductForm.reset();
            const addModalTitle = document.querySelector('#addProductModal h3');
            if (addModalTitle) addModalTitle.textContent = 'Add New Product';
            const addSubmitBtn = document.querySelector('#addProductForm button[type="submit"]');
            if (addSubmitBtn) addSubmitBtn.textContent = 'Add Product';
            const addProductModal = document.getElementById('addProductModal');
            if (addProductModal) addProductModal.style.display = 'flex';
            
            // Load categories for add product
            loadCategories('category_id');

            // Clear and add initial size field
            const productSizesContainer = document.getElementById('product_sizes_container');
            if (productSizesContainer) {
                productSizesContainer.innerHTML = '';
                addSizeField();
            }
        }

        function closeAddProductModal() {
            const addProductModal = document.getElementById('addProductModal');
            if (addProductModal) addProductModal.style.display = 'none';
        }

        function closeEditProductModal() {
            const editProductModal = document.getElementById('editProductModal');
            if (editProductModal) editProductModal.style.display = 'none';
        }

    async function loadCategories(selectId = 'category_id') {
        const categorySelect = document.getElementById(selectId);
        if (!categorySelect) return;

        try {
            if (typeof categoriesAPI === 'undefined') {
                categorySelect.innerHTML = '<option value="">API not available</option>';
                return;
            }

            const response = await categoriesAPI.getCategories();
            if (response.success) {
                categorySelect.innerHTML = '<option value="">Select Category</option>';
                response.data.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id || category.category_id;
                    option.textContent = category.name;
                    categorySelect.appendChild(option);
                });
            } else {
                categorySelect.innerHTML = '<option value="">Failed to load</option>';
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            categorySelect.innerHTML = '<option value="">Error loading</option>';
        }
    }

    async function loadCategoriesForEdit(selectId = 'edit_categories') {
        const categorySelect = document.getElementById(selectId);
        if (!categorySelect) return;

        try {
            if (typeof categoriesAPI === 'undefined') {
                categorySelect.innerHTML = '<option value="">API not available</option>';
                return;
            }

            const response = await categoriesAPI.getCategories();
            if (response.success) {
                categorySelect.innerHTML = '';
                response.data.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id || category.category_id;
                    option.textContent = category.name;
                    categorySelect.appendChild(option);
                });
            } else {
                categorySelect.innerHTML = '<option value="">Failed to load</option>';
            }
        } catch (error) {
            console.error('Error loading categories for edit:', error);
            categorySelect.innerHTML = '<option value="">Error loading</option>';
        }
    }

        async function loadProducts() {
            if (!productsTableBody) return;

            try {
                productsTableBody.innerHTML = '<tr><td colspan="6" class="loading">Loading products...</td></tr>';

                if (typeof productsAPI === 'undefined') {
                    productsTableBody.innerHTML = '<tr><td colspan="6" class="error">API not available</td></tr>';
                    return;
                }

                const response = await productsAPI.getProducts();
                if (response.success) {
                    const products = response.data.products || [];
                    
                    if (products.length === 0) {
                        productsTableBody.innerHTML = '<tr><td colspan="6" class="empty">No products found</td></tr>';
                        return;
                    }

                    productsTableBody.innerHTML = '';
                    products.forEach(product => {
                        const row = createProductRow(product);
                        productsTableBody.appendChild(row);
                    });
                } else {
                    productsTableBody.innerHTML = `<tr><td colspan="6" class="error">${response.message || 'Failed to load products'}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading products:', error);
                productsTableBody.innerHTML = '<tr><td colspan="6" class="error">Error loading products</td></tr>';
            }
        }

        function createProductRow(product) {
            const row = document.createElement('tr');
            let imageUrl = product.primary_image || 'https://via.placeholder.com/50';
            
            // Fix image URL path - ensure it uses absolute path from root including /Leesage/
            if (imageUrl && !imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
                imageUrl = '/Leesage/' + imageUrl;
            } else if (imageUrl && imageUrl.startsWith('/') && !imageUrl.startsWith('/Leesage/')) {
                imageUrl = '/Leesage' + imageUrl;
            }
            
            const status = product.is_active == 1 ? 'Active' : 'Inactive';
            const statusClass = product.is_active == 1 ? 'active' : 'inactive';

            row.innerHTML = `
                <td><img src="${imageUrl}" alt="${product.name}" style="width: 50px; height: 50px; object-fit: cover;"></td>
                <td>${product.name}</td>
                <td>₹${parseFloat(product.price).toFixed(2)}</td>
                <td>₹${product.discount_price ? parseFloat(product.discount_price).toFixed(2) : 'N/A'}</td>
                <td>${product.sku || 'N/A'}</td>
                <td>
                            ${product.sizes && product.sizes.length > 0 ? 
                                product.sizes.map(size => `<div>${size.size_name}: ${size.stock_quantity}</div>`).join('') :
                                'N/A'
                            }
                        </td>
                <td>${product.is_featured == 1 ? 'Yes' : 'No'}</td>
                <td><span class="status-badge ${statusClass}">${status}</span></td>
                <td>
                    <button class="btn btn-sm btn-info edit-product" data-id="${product.id || product.product_id}">Edit</button>
                    <button class="btn btn-sm btn-danger delete-product" data-id="${product.id || product.product_id}">Delete</button>
                    <button class="btn btn-sm btn-warning toggle-product" data-id="${product.id || product.product_id}" data-active="${product.is_active}">
                        ${product.is_active == 1 ? 'Hide' : 'Show'}
                    </button>
                </td>
            `;

            // Add event listeners
            row.querySelector('.edit-product').addEventListener('click', () => editProduct(product.id || product.product_id));
            row.querySelector('.delete-product').addEventListener('click', () => deleteProduct(product.id || product.product_id));
            row.querySelector('.toggle-product').addEventListener('click', () => toggleProductStatus(product.id || product.product_id, product.is_active));

            return row;
        }

        async function editProduct(productId) {
            try {
                const response = await productsAPI.getProductDetails(productId);
                if (response.success && response.data.products && response.data.products.length > 0) {
                    const product = response.data.products[0];
                    
                    // Populate edit form with null checks
                    const editProductId = document.getElementById('edit_product_id');
                    if (editProductId) editProductId.value = product.id || product.product_id;
                    
                    const editProductName = document.getElementById('edit_product_name');
                    if (editProductName) editProductName.value = product.name;
                    
                    const editDescription = document.getElementById('edit_description');
                    if (editDescription) editDescription.value = product.description || '';
                    
                    const editPrice = document.getElementById('edit_price');
                    if (editPrice) editPrice.value = product.price;
                    
                    const editDiscountPrice = document.getElementById('edit_discount_price');
                    if (editDiscountPrice) editDiscountPrice.value = product.discount_price || '';
                    
                    const editSku = document.getElementById('edit_sku');
                    if (editSku) editSku.value = product.sku || '';
                    

            
            // Load product sizes for editing
            const editProductSizesContainer = document.getElementById('edit_product_sizes_container');
            if (editProductSizesContainer) {
                editProductSizesContainer.innerHTML = ''; // Clear existing size fields
                if (product.sizes && product.sizes.length > 0) {
                    product.sizes.forEach(size => {
                        const sizeInputGroup = document.createElement('div');
                        sizeInputGroup.classList.add('size-input-group');
                        sizeInputGroup.innerHTML = `
                            <input type="hidden" name="size_ids[]" value="${size.id}">
                            <input type="text" name="sizes[]" placeholder="Size (e.g., S, M, L)" value="${size.size_name}" required>
                            <input type="number" name="stock_quantities[]" placeholder="Stock Quantity" value="${size.stock_quantity}" min="0" required>
                            <input type="number" name="min_stock_levels[]" placeholder="Min Stock Level" value="${size.min_stock_level || 0}" min="0">
                            <button type="button" class="btn btn-danger remove-size">Remove</button>
                        `;
                        editProductSizesContainer.appendChild(sizeInputGroup);
                    });
                } else {
                    addSizeField('edit'); // Add a default size field if no sizes exist
                }
            }
                    

                    
                    const editIsFeatured = document.getElementById('edit_is_featured');
                    if (editIsFeatured) editIsFeatured.checked = product.is_featured == 1;
                    
                    const editIsActive = document.getElementById('edit_is_active');
                    if (editIsActive) editIsActive.checked = product.is_active == 1;

                    // Load categories for edit form and set selected categories
                    await loadCategoriesForEdit('edit_categories');
                    
                    // Set selected categories based on product categories
                    if (product.categories && product.categories.length > 0) {
                        const categorySelect = document.getElementById('edit_categories');
                        if (categorySelect) {
                            const categoryIds = product.categories.map(cat => cat.id || cat.category_id);
                            Array.from(categorySelect.options).forEach(option => {
                                option.selected = categoryIds.includes(parseInt(option.value));
                            });
                        }
                    }
                    
                    // Load and display current product images
                    await loadProductImages(product.id || product.product_id);
                    
                    // Update edit modal
                    const editModalTitle = document.querySelector('#editProductModal h3');
                    if (editModalTitle) editModalTitle.textContent = 'Edit Product';
                    
                    const editModal = document.getElementById('editProductModal');
                    if (editModal) editModal.style.display = 'flex';
                    
                    const editSubmitBtn = document.querySelector('#editProductForm button[type="submit"]');
                    if (editSubmitBtn) editSubmitBtn.textContent = 'Update Product';
                } else {
                    showError('Product not found');
                }
            } catch (error) {
                console.error('Error loading product for edit:', error);
                showError('Failed to load product details');
            }
        }

        async function loadProductImages(productId) {
            try {
                const response = await fetch(`../../backend/php/admin/api/get_products.php?product_id=${productId}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success && data.data.products && data.data.products.length > 0) {
                    const product = data.data.products[0];
                    displayCurrentImages(product.images || []);
                }
            } catch (error) {
                console.error('Error loading product images:', error);
            }
        }

        function displayCurrentImages(images) {
            const container = document.getElementById('current_images_preview');
            if (!container) return;
            
            container.innerHTML = '<h4>Current Images</h4>';
            
            if (images.length === 0) {
                container.innerHTML += '<p>No images uploaded yet.</p>';
                return;
            }
            
            const imagesContainer = document.createElement('div');
            imagesContainer.className = 'current-images-grid';
            imagesContainer.style.display = 'flex';
            imagesContainer.style.flexWrap = 'wrap';
            imagesContainer.style.gap = '10px';
            imagesContainer.style.marginTop = '10px';
            
            images.forEach((image, index) => {
                const imageWrapper = document.createElement('div');
                imageWrapper.style.position = 'relative';
                imageWrapper.style.display = 'inline-block';
                
                const img = document.createElement('img');
                img.src = '/Leesage/' + image.image_path;
                img.alt = 'Product Image';
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.style.border = image.is_primary ? '2px solid #007bff' : '1px solid #ddd';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'delete_images[]';
                checkbox.value = image.id;
                checkbox.style.position = 'absolute';
                checkbox.style.top = '5px';
                checkbox.style.right = '5px';
                
                const primaryRadio = document.createElement('input');
                primaryRadio.type = 'radio';
                primaryRadio.name = 'primary_image_id';
                primaryRadio.value = image.id;
                primaryRadio.checked = image.is_primary;
                primaryRadio.style.position = 'absolute';
                primaryRadio.style.bottom = '5px';
                primaryRadio.style.left = '5px';
                
                imageWrapper.appendChild(img);
                imageWrapper.appendChild(checkbox);
                imageWrapper.appendChild(primaryRadio);
                imagesContainer.appendChild(imageWrapper);
            });
            
            container.appendChild(imagesContainer);
        }

        async function deleteProduct(productId) {
            if (!confirm('Are you sure you want to delete this product?')) return;

            try {
                const response = await productsAPI.deleteProduct(productId);
                if (response.success) {
                    showSuccess('Product deleted successfully');
                    loadProducts();
                } else {
                    showError(response.message || 'Failed to delete product');
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                showError('Error deleting product');
            }
        }

        async function toggleProductStatus(productId, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const action = newStatus == 1 ? 'activate' : 'deactivate';

            // Update button label
            const button = document.querySelector(`.toggle-product[data-id="${productId}"]`);
            if (button) {
                button.textContent = newStatus == 1 ? 'Hide' : 'Show';
            }

            if (!confirm(`Are you sure you want to ${action} this product?`)) return;

            try {
                const response = await productsAPI.updateProductStatus(productId, newStatus);
                if (response.success) {
                    showSuccess(`Product ${action}d successfully`);
                    loadProducts();
                } else {
                    showError(response.message || `Failed to ${action} product`);
                }
            } catch (error) {
                console.error('Error toggling product status:', error);
                showError('Error updating product status');
            }
        }

        async function handleAddFormSubmit(event) {
            event.preventDefault();
            event.stopImmediatePropagation(); // Prevent any other handlers
            
            // Check if already submitting
            if (event.target.dataset.submitting === 'true') {
                return;
            }
            event.target.dataset.submitting = 'true';
            
            console.log('=== ADD FORM SUBMIT STARTED ==='); // Debug log
            
            // Disable submit button to prevent double submission
            const submitBtn = event.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Adding...';
            }
            
            const formData = new FormData(addProductForm);
            formData.append('category_id', addProductForm.category_id.value);
            formData.append('action', 'add');
            formData.append('request_timestamp', Date.now().toString()); // Add unique timestamp
            formData.append('request_id', Math.random().toString(36).substr(2, 9)); // Add unique ID

            // Append product sizes as a JSON string
            const productSizes = [];
            const sizeInputs = document.querySelectorAll('#product_sizes_container input[name="sizes[]"]');
            const stockQuantityInputs = document.querySelectorAll('#product_sizes_container input[name="stock_quantities[]"]');
            const minStockLevelInputs = document.querySelectorAll('#product_sizes_container input[name="min_stock_levels[]"]');

            sizeInputs.forEach((input, index) => {
                productSizes.push({
                    size: input.value,
                    stock_quantity: stockQuantityInputs[index].value,
                    min_stock_level: minStockLevelInputs[index]?.value || 0
                });
            });
            formData.append('sizes', JSON.stringify(productSizes));

            // Append product images if selected
            const productImages = document.getElementById('product_images').files;
            console.log('Number of images to upload:', productImages.length); // Debug log
            for (let i = 0; i < productImages.length; i++) {
                formData.append('product_images[]', productImages[i]);
                console.log('Appending image:', productImages[i].name); // Debug log
            }
            
            try {
                if (typeof productsAPI === 'undefined') {
                    showError('Products API not available');
                    return;
                }

                console.log('Calling productsAPI.addProduct...'); // Debug log
                const response = await productsAPI.addProduct(formData);
                console.log('API Response:', response); // Debug log

                if (response.success) {
                    showSuccess('Product added successfully!');
                    closeAddProductModal();
                    addProductForm.reset();
                    loadProducts();
                } else {
                    showError(response.message || 'Failed to add product');
                }
            } catch (error) {
                console.error('Error adding product:', error);
                showError('An error occurred while adding the product');
            } finally {
                // Re-enable submit button and clear submitting flag
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Add Product';
                }
                event.target.dataset.submitting = 'false';
            }
        }

        async function handleEditFormSubmit(event) {
            event.preventDefault();
            event.stopImmediatePropagation(); // Prevent any other handlers
            
            // Check if already submitting
            if (event.target.dataset.submitting === 'true') {
                return;
            }
            event.target.dataset.submitting = 'true';
            
            // Disable submit button to prevent double submission
            const submitBtn = event.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
            }
            
            const formData = new FormData(editProductForm);
            formData.append('product_id', editProductForm.edit_product_id.value);
            formData.append('action', 'update');

            // Append product sizes as a JSON string
            const productSizes = [];
            const editSizeInputs = document.querySelectorAll('#edit_product_sizes_container input[name="sizes[]"]');
            const editStockQuantityInputs = document.querySelectorAll('#edit_product_sizes_container input[name="stock_quantities[]"]');
            const editMinStockLevelInputs = document.querySelectorAll('#edit_product_sizes_container input[name="min_stock_levels[]"]');
            const editSizeIds = document.querySelectorAll('#edit_product_sizes_container input[name="size_ids[]"]');

            editSizeInputs.forEach((input, index) => {
                productSizes.push({
                    size: input.value,
                    stock_quantity: editStockQuantityInputs[index].value,
                    min_stock_level: editMinStockLevelInputs[index]?.value || 0
                });
            });
            formData.append('sizes', JSON.stringify(productSizes));
            
            // Append size_ids separately
            editSizeIds.forEach(sizeId => {
                formData.append('size_ids[]', sizeId.value);
            });

            // Append product images if selected
            const productImages = document.getElementById('edit_product_images').files;
            for (let i = 0; i < productImages.length; i++) {
                formData.append('product_images[]', productImages[i]);
            }
            
            try {
                if (typeof productsAPI === 'undefined') {
                    showError('Products API not available');
                    return;
                }

                const response = await productsAPI.editProduct(formData);

                if (response.success) {
                    showSuccess('Product updated successfully!');
                    closeEditProductModal();
                    loadProducts();
                } else {
                    console.error('Edit product response:', response);
                    showError(response.message || 'Failed to update product');
                }
            } catch (error) {
                console.error('Error updating product:', error);
                showError('An error occurred while updating the product');
            } finally {
                // Re-enable submit button and clear submitting flag
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Product';
                }
                event.target.dataset.submitting = 'false';
            }
        }

        function showSuccess(message) {
            if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
                adminUtils.showNotification(message, 'success');
            } else {
                alert(message);
            }
        }

        function showError(message) {
            if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
                adminUtils.showNotification(message, 'error');
            } else {
                alert(message);
            }
        }

        // Initialize
        ensureAPIsLoaded();
    }
});