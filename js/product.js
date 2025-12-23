/**
 * Product Management JavaScript
 * Handles form validation, AJAX operations, and user feedback for product management
 * Requirements: 6.1, 6.2, 6.4, 6.5
 */

$(document).ready(function() {
    // Regular expressions for validation
    const productTitleRegex = /^[a-zA-Z0-9\s\-_&().,!@#$%^*+=|\\:;"'<>?/]{1,255}$/;
    const priceRegex = /^\d+(\.\d{1,2})?$/;
    const keywordsRegex = /^[a-zA-Z0-9\s\-_,]{0,255}$/;
    
    // Global variables for modal management
    let currentEditProductId = null;
    let currentDeleteProductId = null;
    let categoriesData = [];
    let brandsData = [];

    // Initialize the page
    initializePage();

    /**
     * Initialize page functionality
     */
    function initializePage() {
        loadCategories();
        loadBrands();
        loadProducts();
        bindEventHandlers();
    }

    /**
     * Bind all event handlers
     */
    function bindEventHandlers() {
        // Add product form submission
        $('#add-product-form').submit(handleAddProduct);
        
        // Edit product form submission
        $('#edit-product-form').submit(handleEditProduct);
        
        // Category change handler for brand filtering
        $('#category_id, #edit_category_id').change(handleCategoryChange);
        
        // Refresh products button
        $('#refresh-products').click(function(e) {
            e.preventDefault();
            loadProducts();
        });
        
        // Modal close handlers
        $('#close-edit-modal, #cancel-edit').click(closeEditModal);
        $('#close-delete-modal, #cancel-delete').click(closeDeleteModal);
        
        // Delete confirmation
        $('#confirm-delete-btn').click(handleDeleteProduct);
        
        // Close modals when clicking outside
        $(window).click(function(e) {
            if ($(e.target).hasClass('modal')) {
                closeEditModal();
                closeDeleteModal();
            }
        });
        
        // Form reset handler
        $('#add-product-form').on('reset', function() {
            // Clear any validation messages
            $('.form-input').removeClass('error');
            $('#brand_id').empty().append('<option value="">Select a brand...</option>');
        });
    }

    /**
     * Validate product form data
     * @param {object} formData - The form data to validate
     * @returns {object} Validation result with isValid and message properties
     */
    function validateProductForm(formData) {
        // Check for empty required fields
        if (!formData.product_title || formData.product_title.trim() === '') {
            return {
                isValid: false,
                message: 'Product title is required!',
                field: 'product_title'
            };
        }

        if (!formData.product_price || formData.product_price.trim() === '') {
            return {
                isValid: false,
                message: 'Product price is required!',
                field: 'product_price'
            };
        }

        if (!formData.category_id || formData.category_id === '') {
            return {
                isValid: false,
                message: 'Please select a category!',
                field: 'category_id'
            };
        }

        if (!formData.brand_id || formData.brand_id === '') {
            return {
                isValid: false,
                message: 'Please select a brand!',
                field: 'brand_id'
            };
        }

        // Trim values
        const title = formData.product_title.trim();
        const price = formData.product_price.trim();
        const keywords = formData.product_keywords ? formData.product_keywords.trim() : '';

        // Validate title length and format
        if (title.length > 255) {
            return {
                isValid: false,
                message: 'Product title must be 255 characters or less!',
                field: 'product_title'
            };
        }

        if (!productTitleRegex.test(title)) {
            return {
                isValid: false,
                message: 'Product title contains invalid characters!',
                field: 'product_title'
            };
        }

        // Validate price format
        if (!priceRegex.test(price)) {
            return {
                isValid: false,
                message: 'Please enter a valid price (e.g., 29.99)!',
                field: 'product_price'
            };
        }

        // Validate price is positive
        const priceValue = parseFloat(price);
        if (priceValue < 0) {
            return {
                isValid: false,
                message: 'Product price must be a positive number!',
                field: 'product_price'
            };
        }

        // Validate keywords if provided
        if (keywords && !keywordsRegex.test(keywords)) {
            return {
                isValid: false,
                message: 'Keywords contain invalid characters!',
                field: 'product_keywords'
            };
        }

        if (keywords && keywords.length > 255) {
            return {
                isValid: false,
                message: 'Keywords must be 255 characters or less!',
                field: 'product_keywords'
            };
        }

        // Validate category and brand selections are numeric
        if (!isNumeric(formData.category_id)) {
            return {
                isValid: false,
                message: 'Invalid category selection!',
                field: 'category_id'
            };
        }

        if (!isNumeric(formData.brand_id)) {
            return {
                isValid: false,
                message: 'Invalid brand selection!',
                field: 'brand_id'
            };
        }

        return {
            isValid: true,
            message: 'Valid'
        };
    }

    /**
     * Check if a value is numeric
     * @param {string} value - Value to check
     * @returns {boolean} True if numeric
     */
    function isNumeric(value) {
        return !isNaN(value) && !isNaN(parseFloat(value)) && isFinite(value);
    }

    /**
     * Load categories for dropdown population
     */
    function loadCategories() {
        $.ajax({
            url: '../actions/fetch_category_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.data && response.data.categories) {
                    categoriesData = response.data.categories;
                    populateCategoryDropdown(response.data.categories);
                } else {
                    showError('Error', 'Failed to load categories. Please refresh the page.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading categories:', status, error);
                showError('Connection Error', 'Failed to load categories. Please check your connection and try again.');
            }
        });
    }

    /**
     * Load brands for dropdown population
     */
    function loadBrands() {
        $.ajax({
            url: '../actions/fetch_brand_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.data && response.data.brands) {
                    brandsData = response.data.brands;
                } else {
                    console.warn('No brands loaded or error occurred');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading brands:', status, error);
                showError('Connection Error', 'Failed to load brands. Please check your connection and try again.');
            }
        });
    }

    /**
     * Populate category dropdown with options
     * @param {Array} categories - Array of category objects
     */
    function populateCategoryDropdown(categories) {
        const categorySelects = $('#category_id, #edit_category_id');
        categorySelects.empty();
        categorySelects.append('<option value="">Select a category...</option>');
        
        categories.forEach(category => {
            categorySelects.append(`<option value="${category.cat_id}">${escapeHtml(category.cat_name)}</option>`);
        });
    }

    /**
     * Handle category change to filter brands
     */
    function handleCategoryChange(e) {
        const categoryId = $(this).val();
        const isEditForm = $(this).attr('id') === 'edit_category_id';
        const brandSelect = isEditForm ? $('#edit_brand_id') : $('#brand_id');
        
        // Clear brand dropdown
        brandSelect.empty();
        brandSelect.append('<option value="">Select a brand...</option>');
        
        if (categoryId && brandsData.length > 0) {
            // Filter brands by category
            const filteredBrands = brandsData.filter(brand => 
                brand.category_id == categoryId
            );
            
            filteredBrands.forEach(brand => {
                brandSelect.append(`<option value="${brand.brand_id}">${escapeHtml(brand.brand_name)}</option>`);
            });
            
            if (filteredBrands.length === 0) {
                brandSelect.append('<option value="" disabled>No brands available for this category</option>');
            }
        }
    }

    /**
     * Handle add product form submission
     */
    function handleAddProduct(e) {
        e.preventDefault();

        // Collect form data
        const formData = {
            product_title: $('#product_title').val(),
            product_price: $('#product_price').val(),
            product_description: $('#product_description').val(),
            product_keywords: $('#product_keywords').val(),
            category_id: $('#category_id').val(),
            brand_id: $('#brand_id').val()
        };
        
        // Validate input
        const validation = validateProductForm(formData);
        if (!validation.isValid) {
            showError('Validation Error', validation.message);
            highlightField(validation.field);
            return;
        }

        // Clear any previous field highlights
        clearFieldHighlights();

        // Show loading state
        setAddButtonLoading(true);

        // First add the product without image, then upload image if provided
        const imageFile = $('#product_image')[0].files[0];
        addProduct(formData, imageFile);
    }

    /**
     * Upload image for a specific product (used after product creation)
     * @param {number} productId - The product ID
     * @param {File} imageFile - Image file to upload
     * @param {function} callback - Callback function with success boolean
     */
    function uploadImageForProduct(productId, imageFile, callback) {
        const uploadFormData = new FormData();
        uploadFormData.append('product_image', imageFile);
        uploadFormData.append('product_id', productId);
        uploadFormData.append('csrf_token', $('input[name="csrf_token"]').val());

        $.ajax({
            url: '../actions/upload_product_image_action.php',
            type: 'POST',
            data: uploadFormData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Image uploaded successfully, now update the product with the image path
                    updateProductImage(productId, response.data.relative_path, callback);
                } else {
                    console.error('Image upload failed:', response.message);
                    callback(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Image upload error:', status, error);
                callback(false);
            }
        });
    }

    /**
     * Update product with image path
     * @param {number} productId - The product ID
     * @param {string} imagePath - The image path
     * @param {function} callback - Callback function with success boolean
     */
    function updateProductImage(productId, imagePath, callback) {
        $.ajax({
            url: '../actions/update_product_image_path_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId,
                image_path: imagePath,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                if (response.status === 'success') {
                    callback(true);
                } else {
                    console.error('Failed to update product with image path:', response.message);
                    callback(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating product image path:', status, error);
                callback(false);
            }
        });
    }

    /**
     * Upload image then add product
     * @param {object} formData - Product form data
     * @param {File} imageFile - Image file to upload
     */
    function uploadImageThenAddProduct(formData, imageFile) {
        const uploadFormData = new FormData();
        uploadFormData.append('product_image', imageFile);
        uploadFormData.append('csrf_token', $('input[name="csrf_token"]').val());

        $.ajax({
            url: '../actions/upload_product_image_action.php',
            type: 'POST',
            data: uploadFormData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Add image path to form data
                    formData.product_image = response.data.image_path;
                    addProduct(formData);
                } else {
                    setAddButtonLoading(false);
                    showError('Image Upload Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                setAddButtonLoading(false);
                console.error('Image upload error:', status, error);
                showError('Upload Error', 'Failed to upload image. Please try again.');
            }
        });
    }

    /**
     * Add a new product via AJAX
     * @param {object} formData - The product data to add
     * @param {File} imageFile - Optional image file to upload after product creation
     */
    function addProduct(formData, imageFile = null) {
        const ajaxData = {
            product_title: formData.product_title.trim(),
            product_price: formData.product_price.trim(),
            product_description: formData.product_description ? formData.product_description.trim() : '',
            product_keywords: formData.product_keywords ? formData.product_keywords.trim() : '',
            category_id: formData.category_id,
            brand_id: formData.brand_id,
            csrf_token: $('input[name="csrf_token"]').val()
        };

        $.ajax({
            url: '../actions/add_product_action.php',
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                if (response.status === 'success') {
                    // Product created successfully
                    const productId = response.data.product_id;
                    
                    // If there's an image file, upload it now
                    if (imageFile) {
                        uploadImageForProduct(productId, imageFile, function(uploadSuccess) {
                            setAddButtonLoading(false);
                            if (uploadSuccess) {
                                showSuccess('Success', 'Product and image added successfully!');
                            } else {
                                showSuccess('Partial Success', 'Product added successfully, but image upload failed. You can edit the product to add an image later.');
                            }
                            $('#add-product-form')[0].reset();
                            $('#brand_id').empty().append('<option value="">Select a brand...</option>');
                            loadProducts();
                        });
                    } else {
                        // No image to upload
                        setAddButtonLoading(false);
                        showSuccess('Success', response.message);
                        $('#add-product-form')[0].reset();
                        $('#brand_id').empty().append('<option value="">Select a brand...</option>');
                        loadProducts();
                    }
                } else {
                    setAddButtonLoading(false);
                    showError('Error', response.message);
                    if (response.field) {
                        highlightField(response.field);
                    }
                }
            },
            error: function(xhr, status, error) {
                setAddButtonLoading(false);
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                
                showError('Connection Error', 'An error occurred while connecting to the server. Please try again later.');
            }
        });
    }

    /**
     * Load and display products organized by categories and brands
     */
    function loadProducts() {
        // Show loading state
        $('#products-loading').show();
        $('#products-empty').hide();
        $('#products-list').hide();

        $.ajax({
            url: '../actions/fetch_product_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#products-loading').hide();
                
                if (response.status === 'success') {
                    if (response.data && response.data.products && response.data.products.length > 0) {
                        displayProducts(response.data.products);
                        $('#products-list').show();
                    } else {
                        $('#products-empty').show();
                    }
                } else {
                    showError('Error', response.message || 'Failed to load products');
                    $('#products-empty').show();
                }
            },
            error: function(xhr, status, error) {
                $('#products-loading').hide();
                $('#products-empty').show();
                console.error('AJAX Error:', status, error);
                
                showError('Connection Error', 'Failed to load products. Please check your connection and try again.');
            }
        });
    }

    /**
     * Display products organized by categories and brands
     * @param {Array} products - Array of product objects
     */
    function displayProducts(products) {
        // Group products by category, then by brand
        const productsByCategory = {};
        
        products.forEach(product => {
            // Use cat_name if available, otherwise fall back to category_name or default
            const categoryName = product.cat_name || product.category_name || 'Unknown Category';
            const brandName = product.brand_name || 'Unknown Brand';
            
            if (!productsByCategory[categoryName]) {
                productsByCategory[categoryName] = {};
            }
            
            if (!productsByCategory[categoryName][brandName]) {
                productsByCategory[categoryName][brandName] = [];
            }
            
            productsByCategory[categoryName][brandName].push(product);
        });

        // Generate HTML for each category and brand group
        let productsHtml = '';
        
        Object.keys(productsByCategory).sort().forEach(categoryName => {
            const categoryBrands = productsByCategory[categoryName];
            
            productsHtml += `
                <div class="category-group">
                    <div class="category-group-header">
                        <h4><i class="fa fa-tag"></i> ${escapeHtml(categoryName)}</h4>
                    </div>
            `;
            
            Object.keys(categoryBrands).sort().forEach(brandName => {
                const brandProducts = categoryBrands[brandName];
                
                productsHtml += `
                    <div class="brand-group">
                        <div class="brand-group-header">
                            <h5><i class="fa fa-copyright"></i> ${escapeHtml(brandName)}</h5>
                            <span class="product-count">${brandProducts.length} product${brandProducts.length !== 1 ? 's' : ''}</span>
                        </div>
                        <div class="products-grid">
                            ${brandProducts.map(product => `
                                <div class="product-card" data-product-id="${product.product_id}">
                                    <div class="product-header">
                                        <h6 class="product-title">${escapeHtml(product.product_title)}</h6>
                                        <span class="product-price">$${parseFloat(product.product_price).toFixed(2)}</span>
                                    </div>
                                    ${product.product_image ? `
                                        <div class="product-image">
                                            <img src="../${product.product_image}" alt="${escapeHtml(product.product_title)}" 
                                                 onerror="this.style.display='none'">
                                        </div>
                                    ` : ''}
                                    ${product.product_description ? `
                                        <div class="product-description">
                                            <p>${escapeHtml(product.product_description.substring(0, 100))}${product.product_description.length > 100 ? '...' : ''}</p>
                                        </div>
                                    ` : ''}
                                    <div class="product-meta">
                                        <small class="product-id">ID: ${product.product_id}</small>
                                        ${product.product_keywords ? `
                                            <small class="product-keywords">
                                                <i class="fa fa-tags"></i> ${escapeHtml(product.product_keywords)}
                                            </small>
                                        ` : ''}
                                        <small class="product-date">
                                            <i class="fa fa-calendar"></i> 
                                            Created: ${formatDate(product.created_at)}
                                        </small>
                                        ${product.updated_at !== product.created_at ? 
                                            `<small class="product-date">
                                                <i class="fa fa-edit"></i> 
                                                Modified: ${formatDate(product.updated_at)}
                                            </small>` : ''
                                        }
                                    </div>
                                    <div class="product-actions">
                                        <button class="btn btn-primary btn-small edit-product" 
                                                data-product-id="${product.product_id}"
                                                data-product-title="${escapeHtml(product.product_title)}"
                                                data-product-price="${product.product_price}"
                                                data-product-description="${escapeHtml(product.product_description || '')}"
                                                data-product-keywords="${escapeHtml(product.product_keywords || '')}"
                                                data-category-id="${product.category_id}"
                                                data-brand-id="${product.brand_id}"
                                                data-product-image="${escapeHtml(product.product_image || '')}">
                                            <i class="fa fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-error btn-small delete-product" 
                                                data-product-id="${product.product_id}" 
                                                data-product-title="${escapeHtml(product.product_title)}">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            });
            
            productsHtml += '</div>';
        });

        $('#products-list').html(productsHtml);

        // Bind action buttons
        $('.edit-product').click(handleEditButtonClick);
        $('.delete-product').click(handleDeleteButtonClick);
    }

    /**
     * Handle edit button click
     */
    function handleEditButtonClick(e) {
        e.preventDefault();
        try {
            // Get product data from individual data attributes
            const productData = {
                product_id: $(this).data('product-id'),
                product_title: $(this).data('product-title'),
                product_price: $(this).data('product-price'),
                product_description: $(this).data('product-description'),
                product_keywords: $(this).data('product-keywords'),
                category_id: $(this).data('category-id'),
                brand_id: $(this).data('brand-id'),
                product_image: $(this).data('product-image')
            };
            
            console.log('Product data for editing:', productData);
            
            // Validate that we have the required data
            if (!productData.product_id || !productData.product_title) {
                throw new Error('Missing required product data');
            }
            
            openEditModal(productData);
        } catch (error) {
            console.error('Error loading product data for editing:', error);
            showError('Error', 'Failed to load product data for editing. Please try refreshing the page.');
        }
    }

    /**
     * Handle delete button click
     */
    function handleDeleteButtonClick(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        const productTitle = $(this).data('product-title');
        
        openDeleteModal(productId, productTitle);
    }

    /**
     * Open edit modal
     * @param {object} product - The product data to edit
     */
    function openEditModal(product) {
        currentEditProductId = product.product_id;
        
        // Populate form fields
        $('#edit_product_id').val(product.product_id);
        $('#edit_product_title').val(product.product_title);
        $('#edit_product_price').val(product.product_price);
        $('#edit_product_description').val(product.product_description || '');
        $('#edit_product_keywords').val(product.product_keywords || '');
        
        // Set category and trigger brand loading
        $('#edit_category_id').val(product.category_id).trigger('change');
        
        // Set brand after a short delay to allow brands to load
        setTimeout(() => {
            $('#edit_brand_id').val(product.brand_id);
        }, 100);
        
        // Show current image info if exists
        if (product.product_image) {
            $('#current-image-info').show();
            $('#current-image-name').text(product.product_image.split('/').pop());
        } else {
            $('#current-image-info').hide();
        }
        
        $('#edit-modal').show();
        $('#edit_product_title').focus();
    }

    /**
     * Close edit modal
     */
    function closeEditModal() {
        $('#edit-modal').hide();
        $('#edit-product-form')[0].reset();
        $('#current-image-info').hide();
        currentEditProductId = null;
        setUpdateButtonLoading(false);
        clearFieldHighlights();
    }

    /**
     * Handle edit product form submission
     */
    function handleEditProduct(e) {
        e.preventDefault();

        // Collect form data
        const formData = {
            product_id: $('#edit_product_id').val(),
            product_title: $('#edit_product_title').val(),
            product_price: $('#edit_product_price').val(),
            product_description: $('#edit_product_description').val(),
            product_keywords: $('#edit_product_keywords').val(),
            category_id: $('#edit_category_id').val(),
            brand_id: $('#edit_brand_id').val()
        };
        
        // Validate input
        const validation = validateProductForm(formData);
        if (!validation.isValid) {
            showError('Validation Error', validation.message);
            highlightField(validation.field, true); // true for edit form
            return;
        }

        // Clear any previous field highlights
        clearFieldHighlights();

        // Show loading state
        setUpdateButtonLoading(true);

        // Handle image upload if file is selected
        const imageFile = $('#edit_product_image')[0].files[0];
        if (imageFile) {
            uploadImageThenUpdateProduct(formData, imageFile);
        } else {
            // Submit without new image
            updateProduct(formData);
        }
    }

    /**
     * Upload image then update product
     * @param {object} formData - Product form data
     * @param {File} imageFile - Image file to upload
     */
    function uploadImageThenUpdateProduct(formData, imageFile) {
        const uploadFormData = new FormData();
        uploadFormData.append('product_image', imageFile);
        uploadFormData.append('product_id', formData.product_id); // Add the product ID
        uploadFormData.append('csrf_token', $('input[name="csrf_token"]').val());

        $.ajax({
            url: '../actions/upload_product_image_action.php',
            type: 'POST',
            data: uploadFormData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Add image update info to form data
                    formData.image_action = 'update';
                    formData.new_product_image = response.data.relative_path; // Use relative_path instead of image_path
                    updateProduct(formData);
                } else {
                    setUpdateButtonLoading(false);
                    showError('Image Upload Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                setUpdateButtonLoading(false);
                console.error('Image upload error:', status, error);
                showError('Upload Error', 'Failed to upload image. Please try again.');
            }
        });
    }

    /**
     * Update product via AJAX
     * @param {object} formData - The product data to update
     */
    function updateProduct(formData) {
        const ajaxData = {
            product_id: formData.product_id,
            product_title: formData.product_title.trim(),
            product_price: formData.product_price.trim(),
            product_description: formData.product_description ? formData.product_description.trim() : '',
            product_keywords: formData.product_keywords ? formData.product_keywords.trim() : '',
            category_id: formData.category_id,
            brand_id: formData.brand_id,
            image_action: formData.image_action || 'keep',
            csrf_token: $('input[name="csrf_token"]').val()
        };

        if (formData.new_product_image) {
            ajaxData.new_product_image = formData.new_product_image;
        }

        $.ajax({
            url: '../actions/update_product_action.php',
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                setUpdateButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    closeEditModal();
                    loadProducts(); // Refresh the list
                } else {
                    showError('Error', response.message);
                    if (response.field) {
                        highlightField(response.field, true); // true for edit form
                    }
                }
            },
            error: function(xhr, status, error) {
                setUpdateButtonLoading(false);
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                
                showError('Connection Error', 'An error occurred while connecting to the server. Please try again later.');
            }
        });
    }

    /**
     * Open delete modal
     * @param {number} productId - The product ID to delete
     * @param {string} productTitle - The product title to display
     */
    function openDeleteModal(productId, productTitle) {
        currentDeleteProductId = productId;
        $('#delete_product_id').val(productId);
        $('#delete-product-name').text(productTitle);
        $('#delete-modal').show();
    }

    /**
     * Close delete modal
     */
    function closeDeleteModal() {
        $('#delete-modal').hide();
        currentDeleteProductId = null;
        setDeleteButtonLoading(false);
    }

    /**
     * Handle delete product confirmation
     */
    function handleDeleteProduct(e) {
        e.preventDefault();

        const productId = $('#delete_product_id').val();
        
        if (!productId) {
            showError('Error', 'Invalid product selected for deletion');
            return;
        }

        // Show loading state
        setDeleteButtonLoading(true);

        // Submit via AJAX
        deleteProduct(productId);
    }

    /**
     * Delete product via AJAX
     * @param {number} productId - The product ID to delete
     */
    function deleteProduct(productId) {
        $.ajax({
            url: '../actions/delete_product_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setDeleteButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    closeDeleteModal();
                    loadProducts(); // Refresh the list
                } else {
                    showError('Error', response.message);
                }
            },
            error: function(xhr, status, error) {
                setDeleteButtonLoading(false);
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                
                showError('Connection Error', 'An error occurred while connecting to the server. Please try again later.');
            }
        });
    }

    /**
     * Highlight form field with error
     * @param {string} fieldName - Name of the field to highlight
     * @param {boolean} isEditForm - Whether this is for the edit form
     */
    function highlightField(fieldName, isEditForm = false) {
        const prefix = isEditForm ? 'edit_' : '';
        const fieldId = fieldName.startsWith(prefix) ? fieldName : prefix + fieldName;
        $(`#${fieldId}`).addClass('error');
        
        // Remove highlight after 3 seconds
        setTimeout(() => {
            $(`#${fieldId}`).removeClass('error');
        }, 3000);
    }

    /**
     * Clear all field highlights
     */
    function clearFieldHighlights() {
        $('.form-input').removeClass('error');
    }

    /**
     * Set loading state for add button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setAddButtonLoading(isLoading) {
        if (isLoading) {
            $('#add-text').hide();
            $('#add-loading').show();
            $('#add-product-btn').prop('disabled', true);
        } else {
            $('#add-text').show();
            $('#add-loading').hide();
            $('#add-product-btn').prop('disabled', false);
        }
    }

    /**
     * Set loading state for update button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setUpdateButtonLoading(isLoading) {
        if (isLoading) {
            $('#update-text').hide();
            $('#update-loading').show();
            $('#update-product-btn').prop('disabled', true);
        } else {
            $('#update-text').show();
            $('#update-loading').hide();
            $('#update-product-btn').prop('disabled', false);
        }
    }

    /**
     * Set loading state for delete button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setDeleteButtonLoading(isLoading) {
        if (isLoading) {
            $('#delete-text').hide();
            $('#delete-loading').show();
            $('#confirm-delete-btn').prop('disabled', true);
        } else {
            $('#delete-text').show();
            $('#delete-loading').hide();
            $('#confirm-delete-btn').prop('disabled', false);
        }
    }

    /**
     * Show success message using SweetAlert2
     * @param {string} title - The title of the alert
     * @param {string} message - The message to display
     */
    function showSuccess(title, message) {
        Swal.fire({
            icon: 'success',
            title: title,
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    /**
     * Show error message using SweetAlert2
     * @param {string} title - The title of the alert
     * @param {string} message - The message to display
     */
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'OK'
        });
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - The text to escape
     * @returns {string} Escaped HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format date for display
     * @param {string} dateString - The date string to format
     * @returns {string} Formatted date
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    // Public API for external access if needed
    window.ProductManager = {
        loadProducts: loadProducts,
        loadCategories: loadCategories,
        loadBrands: loadBrands,
        validateProductForm: validateProductForm
    };
});