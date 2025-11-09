function filterInventory() {
    const stockStatusFilter = document.getElementById('stockStatusFilter').value;
    const searchTerm = document.getElementById('inventorySearch').value;
    console.log('Filtering inventory:', { stockStatusFilter, searchTerm });
    loadInventory(stockStatusFilter, searchTerm);
}

async function loadInventory(stockStatusFilter = '', searchTerm = '') {
    const inventoryTableBody = document.querySelector('.inventory-table tbody');
    if (!inventoryTableBody) return;

    try {
        inventoryTableBody.innerHTML = '<tr><td colspan="7" class="loading">Loading inventory...</td></tr>';

        if (typeof productsAPI === 'undefined') {
            console.error('Products API not available');
            inventoryTableBody.innerHTML = '<tr><td colspan="7" class="error">Could not load inventory. API not available.</td></tr>';
            return;
        }

        // Pass stockStatusFilter as minStockStatus to the API
        const response = await productsAPI.getProducts(1, searchTerm, '', '', stockStatusFilter);

        if (response.success) {
            const products = response.data.products;

            if (products.length === 0) {
                inventoryTableBody.innerHTML = '<tr><td colspan="7" class="empty">No products found in inventory</td></tr>';
                return;
            }

            inventoryTableBody.innerHTML = '';

            products.forEach(product => {
                if (product.sizes && product.sizes.length > 0) {
                    product.sizes.forEach(size => {
                        const stockStatus = getStockStatus(size.stock_quantity, size.min_stock_level);
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${product.id}</td>
                            <td>${product.name}</td>
                            <td>${product.sku || 'N/A'}</td>
                            <td>${size.size_name}</td>
                            <td>${size.stock_quantity}</td>
                            <td>${size.min_stock_level}</td>
                            <td><span class="status-badge ${stockStatus.toLowerCase().replace(/ /g, '-')}" data-product-id="${product.id}" data-size-id="${size.id}">${stockStatus}</span></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="updateStock(${product.id}, ${size.id})">Update Stock</button>
                            </td>
                        `;
                        inventoryTableBody.appendChild(row);
                    });
                } else {
                    // Handle products without specific sizes, if necessary
                    const stockStatus = getStockStatus(product.stock_quantity, product.min_stock_level);
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${product.id}</td>
                        <td>${product.name}</td>
                        <td>${product.sku || 'N/A'}</td>
                        <td>N/A</td> <!-- No size -->
                        <td>${product.stock_quantity}</td>
                        <td>${product.min_stock_level}</td>
                        <td><span class="status-badge ${stockStatus.toLowerCase().replace(/ /g, '-')}" data-product-id="${product.id}">${stockStatus}</span></td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="updateStock(${product.id}, null)">Update Stock</button>
                        </td>
                    `;
                    inventoryTableBody.appendChild(row);
                }
            });
        } else {
            inventoryTableBody.innerHTML = `<tr><td colspan="7" class="error">${response.message || 'Failed to load inventory'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading inventory:', error);
        inventoryTableBody.innerHTML = '<tr><td colspan="7" class="error">An error occurred while loading inventory</td></tr>';
    }
}

function getStockStatus(currentStock, minStockLevel) {
    if (currentStock <= 0) {
        return 'Out of Stock';
    } else if (currentStock <= minStockLevel) {
        return 'Low Stock';
    } else {
        return 'In Stock';
    }
}

async function editInventory(productId, sizeId = null) {
    try {
        const response = await productsAPI.getProductDetails(productId);
        if (response.success && response.data && response.data.products && response.data.products.length > 0) {
            const product = response.data.products[0];
            document.getElementById('inventoryProductId').value = product.id;
            document.getElementById('inventoryProductName').value = product.name;
            document.getElementById('inventoryModalTitle').textContent = 'Edit Inventory for ' + product.name;

            let stockQuantity;
            let minStockLevel;
            let currentSizeId = sizeId;

            if (sizeId && product.sizes && product.sizes.length > 0) {
                const size = product.sizes.find(s => s.id === sizeId);
                if (size) {
                    stockQuantity = size.stock_quantity;
                    minStockLevel = size.min_stock_level;
                    document.getElementById('inventoryModalTitle').textContent += ` (Size: ${size.size_name})`;
                } else {
                    // If sizeId is provided but not found, fall back to product's general stock
                    stockQuantity = product.stock_quantity;
                    minStockLevel = product.min_stock_level;
                }
            } else {
                // If no sizeId is provided, use product's general stock
                stockQuantity = product.stock_quantity;
                minStockLevel = product.min_stock_level;
            }

            document.getElementById('inventoryCurrentStock').value = stockQuantity;
            document.getElementById('inventoryMinStockLevel').value = minStockLevel;
            
            // Add a hidden input for sizeId if it exists
            let inventorySizeIdInput = document.getElementById('inventorySizeId');
            if (!inventorySizeIdInput) {
                inventorySizeIdInput = document.createElement('input');
                inventorySizeIdInput.type = 'hidden';
                inventorySizeIdInput.id = 'inventorySizeId';
                inventorySizeIdInput.name = 'size_id';
                document.getElementById('inventoryForm').appendChild(inventorySizeIdInput);
            }
            inventorySizeIdInput.value = currentSizeId || '';

            document.getElementById('inventoryModal').style.display = 'block';
        } else {
            showNotification('Product not found.', 'error');
        }
    } catch (error) {
        console.error('Error fetching product for edit:', error);
        showNotification('Error loading product data.', 'error');
    }
}

async function updateStock(productId, sizeId = null) {
    editInventory(productId, sizeId);
}

function initInventoryPage() {
    loadInventory();
    const inventoryModal = document.getElementById('inventoryModal');
    const modalClose = inventoryModal.querySelector('.modal-close');
    const cancelInventory = document.getElementById('cancelInventory');
    const inventoryForm = document.getElementById('inventoryForm');

    function closeInventoryModal() {
        inventoryModal.style.display = 'none';
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeInventoryModal);
    }

    if (cancelInventory) {
        cancelInventory.addEventListener('click', closeInventoryModal);
    }

    window.addEventListener('click', function(event) {
        if (event.target == inventoryModal) {
            closeInventoryModal();
        }
    });

    if (inventoryForm) {
        inventoryForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const productId = document.getElementById('inventoryProductId').value;
            const currentStock = document.getElementById('inventoryCurrentStock').value;
            const minStockLevel = document.getElementById('inventoryMinStockLevel').value;
            const sizeId = document.getElementById('inventorySizeId').value || null;

            try {
                const updateData = {
                    stock_quantity: currentStock,
                    min_stock_level: minStockLevel
                };
                if (sizeId) {
                    updateData.size_id = sizeId;
                }

                const response = await productsAPI.updateProduct(productId, updateData);

                if (response.success) {
                    showNotification('Inventory updated successfully!', 'success');
                    closeInventoryModal();
                    loadInventory(); // Reload inventory to reflect changes
                } else {
                    showNotification(response.message || 'Failed to update inventory.', 'error');
                }
            } catch (error) {
                console.error('Error saving inventory changes:', error);
                showNotification('An error occurred while saving changes.', 'error');
            }
        });
    }
}

// Expose initInventoryPage to the global scope
window.initInventoryPage = initInventoryPage;