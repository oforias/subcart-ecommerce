/**
 * Brand Management JavaScript
 * Handles form validation, AJAX operations, and user feedback for brand management
 * Requirements: 5.1, 5.2, 5.5
 */

$(document).ready(function() {
    // Regular expressions for validation
    const brandNameRegex = /^[a-zA-Z0-9\s\-_&().,]{1,255}$/;
    
    // Global variables for modal management
    let currentEditBrandId = null;
    let currentDeleteBrandId = null;
    let categoriesData = [];

    // Initialize the page
    initializePage();

    /**
     * Initialize page functionality
     */
    function initializePage() {
        loadCategories();
        loadBrands();
        bindEventHandlers();
    }

    /**
     * Bind all event handlers
     */
    function bindEventHandlers() {
        // Add brand form submission
        $('#add-brand-form').submit(handleAddBrand);
        
        // Edit brand form submission
        $('#edit-brand-form').submit(handleEditBrand);
        
        // Refresh brands button
        $('#refresh-brands').click(function(e) {
            e.preventDefault();
            loadBrands();
        });
        
        // Modal close handlers
        $('#close-edit-modal, #cancel-edit').click(closeEditModal);
        $('#close-delete-modal, #cancel-delete').click(closeDeleteModal);
        
        // Delete confirmation
        $('#confirm-delete-btn').click(handleDeleteBrand);
        
        // Close modals when clicking outside
        $(window).click(function(e) {
            if ($(e.target).hasClass('modal')) {
                closeEditModal();
                closeDeleteModal();
            }
        });
    }

    /**
     * Validate brand form data
     * @param {string} brandName - The brand name to validate
     * @param {number} categoryId - The category ID to validate
     * @returns {object} Validation result with isValid and message properties
     */
    function validateBrandForm(brandName, categoryId) {
        // Check for empty name
        if (!brandName || brandName.trim() === '') {
            return {
                isValid: false,
                message: 'Brand name is required!'
            };
        }

        // Trim the name
        brandName = brandName.trim();

        // Check length
        if (brandName.length > 255) {
            return {
                isValid: false,
                message: 'Brand name must be 255 characters or less!'
            };
        }

        // Check format
        if (!brandNameRegex.test(brandName)) {
            return {
                isValid: false,
                message: 'Brand name contains invalid characters! Only letters, numbers, spaces, and common punctuation are allowed.'
            };
        }

        // Check category selection
        if (!categoryId || categoryId === '') {
            return {
                isValid: false,
                message: 'Please select a category for the brand!'
            };
        }

        return {
            isValid: true,
            message: 'Valid'
        };
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
     * Populate category dropdown with options
     * @param {Array} categories - Array of category objects
     */
    function populateCategoryDropdown(categories) {
        const categorySelect = $('#category_id');
        categorySelect.empty();
        categorySelect.append('<option value="">Select a category...</option>');
        
        categories.forEach(category => {
            categorySelect.append(`<option value="${category.cat_id}">${escapeHtml(category.cat_name)}</option>`);
        });
    }

    /**
     * Handle add brand form submission
     */
    function handleAddBrand(e) {
        e.preventDefault();

        const brandName = $('#brand_name').val().trim();
        const categoryId = $('#category_id').val();
        
        // Validate input
        const validation = validateBrandForm(brandName, categoryId);
        if (!validation.isValid) {
            showError('Validation Error', validation.message);
            return;
        }

        // Show loading state
        setAddButtonLoading(true);

        // Submit via AJAX
        addBrand(brandName, categoryId);
    }

    /**
     * Add a new brand via AJAX
     * @param {string} brandName - The brand name to add
     * @param {number} categoryId - The category ID for the brand
     */
    function addBrand(brandName, categoryId) {
        $.ajax({
            url: '../actions/add_brand_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                brand_name: brandName,
                category_id: categoryId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setAddButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    $('#add-brand-form')[0].reset();
                    loadBrands(); // Refresh the list
                } else {
                    showError('Error', response.message);
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
     * Load and display brands organized by categories
     */
    function loadBrands() {
        // Show loading state
        $('#brands-loading').show();
        $('#brands-empty').hide();
        $('#brands-list').hide();

        $.ajax({
            url: '../actions/fetch_brand_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#brands-loading').hide();
                
                if (response.status === 'success') {
                    if (response.data && response.data.brands && response.data.brands.length > 0) {
                        displayBrands(response.data.brands);
                        $('#brands-list').show();
                    } else {
                        $('#brands-empty').show();
                    }
                } else {
                    showError('Error', response.message || 'Failed to load brands');
                    $('#brands-empty').show();
                }
            },
            error: function(xhr, status, error) {
                $('#brands-loading').hide();
                $('#brands-empty').show();
                console.error('AJAX Error:', status, error);
                
                showError('Connection Error', 'Failed to load brands. Please check your connection and try again.');
            }
        });
    }

    /**
     * Display brands organized by categories
     * @param {Array} brands - Array of brand objects
     */
    function displayBrands(brands) {
        // Group brands by category
        const brandsByCategory = {};
        
        brands.forEach(brand => {
            const categoryName = brand.cat_name || brand.category_name || 'Unknown Category';
            if (!brandsByCategory[categoryName]) {
                brandsByCategory[categoryName] = [];
            }
            brandsByCategory[categoryName].push(brand);
        });

        // Generate HTML for each category group
        let brandsHtml = '';
        
        Object.keys(brandsByCategory).sort().forEach(categoryName => {
            const categoryBrands = brandsByCategory[categoryName];
            
            brandsHtml += `
                <div class="category-group">
                    <div class="category-group-header">
                        <h5><i class="fa fa-tag"></i> ${escapeHtml(categoryName)}</h5>
                        <span class="brand-count">${categoryBrands.length} brand${categoryBrands.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="brands-grid">
                        ${categoryBrands.map(brand => `
                            <div class="brand-card" data-brand-id="${brand.brand_id}">
                                <div class="brand-header">
                                    <h6 class="brand-name">${escapeHtml(brand.brand_name)}</h6>
                                    <span class="brand-id">ID: ${brand.brand_id}</span>
                                </div>
                                <div class="brand-meta">
                                    <small class="brand-date">
                                        <i class="fa fa-calendar"></i> 
                                        Created: ${formatDate(brand.created_at)}
                                    </small>
                                    ${brand.updated_at !== brand.created_at ? 
                                        `<small class="brand-date">
                                            <i class="fa fa-edit"></i> 
                                            Modified: ${formatDate(brand.updated_at)}
                                        </small>` : ''
                                    }
                                </div>
                                <div class="brand-actions">
                                    <button class="btn btn-primary btn-small edit-brand" 
                                            data-brand-id="${brand.brand_id}" 
                                            data-brand-name="${escapeHtml(brand.brand_name)}"
                                            data-category-id="${brand.category_id}"
                                            data-category-name="${escapeHtml(brand.category_name)}">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-error btn-small delete-brand" 
                                            data-brand-id="${brand.brand_id}" 
                                            data-brand-name="${escapeHtml(brand.brand_name)}">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        });

        $('#brands-list').html(brandsHtml);

        // Bind action buttons
        $('.edit-brand').click(handleEditButtonClick);
        $('.delete-brand').click(handleDeleteButtonClick);
    }

    /**
     * Handle edit button click
     */
    function handleEditButtonClick(e) {
        e.preventDefault();
        const brandId = $(this).data('brand-id');
        const brandName = $(this).data('brand-name');
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        
        openEditModal(brandId, brandName, categoryId, categoryName);
    }

    /**
     * Handle delete button click
     */
    function handleDeleteButtonClick(e) {
        e.preventDefault();
        const brandId = $(this).data('brand-id');
        const brandName = $(this).data('brand-name');
        
        openDeleteModal(brandId, brandName);
    }

    /**
     * Open edit modal
     * @param {number} brandId - The brand ID to edit
     * @param {string} brandName - The current brand name
     * @param {number} categoryId - The category ID
     * @param {string} categoryName - The category name
     */
    function openEditModal(brandId, brandName, categoryId, categoryName) {
        currentEditBrandId = brandId;
        $('#edit_brand_id').val(brandId);
        $('#edit_brand_name').val(brandName);
        $('#edit_category_id').val(categoryId);
        $('#edit_category_name').val(categoryName);
        $('#edit-modal').show();
        $('#edit_brand_name').focus();
    }

    /**
     * Close edit modal
     */
    function closeEditModal() {
        $('#edit-modal').hide();
        $('#edit-brand-form')[0].reset();
        currentEditBrandId = null;
        setUpdateButtonLoading(false);
    }

    /**
     * Handle edit brand form submission
     */
    function handleEditBrand(e) {
        e.preventDefault();

        const brandId = $('#edit_brand_id').val();
        const brandName = $('#edit_brand_name').val().trim();
        const categoryId = $('#edit_category_id').val();
        
        // Validate input (category ID is not changeable in edit, but we still validate the name)
        const validation = validateBrandForm(brandName, categoryId);
        if (!validation.isValid) {
            showError('Validation Error', validation.message);
            return;
        }

        // Show loading state
        setUpdateButtonLoading(true);

        // Submit via AJAX
        updateBrand(brandId, brandName);
    }

    /**
     * Update brand via AJAX
     * @param {number} brandId - The brand ID to update
     * @param {string} brandName - The new brand name
     */
    function updateBrand(brandId, brandName) {
        $.ajax({
            url: '../actions/update_brand_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                brand_id: brandId,
                brand_name: brandName,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setUpdateButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    closeEditModal();
                    loadBrands(); // Refresh the list
                } else {
                    showError('Error', response.message);
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
     * @param {number} brandId - The brand ID to delete
     * @param {string} brandName - The brand name to display
     */
    function openDeleteModal(brandId, brandName) {
        currentDeleteBrandId = brandId;
        $('#delete_brand_id').val(brandId);
        $('#delete-brand-name').text(brandName);
        $('#delete-modal').show();
    }

    /**
     * Close delete modal
     */
    function closeDeleteModal() {
        $('#delete-modal').hide();
        currentDeleteBrandId = null;
        setDeleteButtonLoading(false);
    }

    /**
     * Handle delete brand confirmation
     */
    function handleDeleteBrand(e) {
        e.preventDefault();

        const brandId = $('#delete_brand_id').val();
        
        if (!brandId) {
            showError('Error', 'Invalid brand selected for deletion');
            return;
        }

        // Show loading state
        setDeleteButtonLoading(true);

        // Submit via AJAX
        deleteBrand(brandId);
    }

    /**
     * Delete brand via AJAX
     * @param {number} brandId - The brand ID to delete
     */
    function deleteBrand(brandId) {
        $.ajax({
            url: '../actions/delete_brand_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                brand_id: brandId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setDeleteButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    closeDeleteModal();
                    loadBrands(); // Refresh the list
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
     * Set loading state for add button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setAddButtonLoading(isLoading) {
        if (isLoading) {
            $('#add-text').hide();
            $('#add-loading').show();
            $('#add-brand-btn').prop('disabled', true);
        } else {
            $('#add-text').show();
            $('#add-loading').hide();
            $('#add-brand-btn').prop('disabled', false);
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
            $('#update-brand-btn').prop('disabled', true);
        } else {
            $('#update-text').show();
            $('#update-loading').hide();
            $('#update-brand-btn').prop('disabled', false);
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
    window.BrandManager = {
        loadBrands: loadBrands,
        loadCategories: loadCategories,
        validateBrandForm: validateBrandForm
    };
});