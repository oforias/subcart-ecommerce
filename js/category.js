/**
 * Category Management JavaScript
 * 
 * Handles category CRUD operations with form validation,
 * AJAX requests, and user interface interactions.
 * 
 * @author Your Name
 * @version 1.0
 */

$(document).ready(function() {
    const categoryNameRegex = /^[a-zA-Z0-9\s\-_&().,]{1,100}$/;
    
    let currentEditCategoryId = null;
    let currentDeleteCategoryId = null;

    initializePage();

    /**
     * Initialize page functionality
     */
    function initializePage() {
        loadCategories();
        bindEventHandlers();
    }

    /**
     * Bind event handlers for form interactions
     */
    function bindEventHandlers() {
        $('#add-category-form').submit(handleAddCategory);
        
        // Edit category form submission
        $('#edit-category-form').submit(handleEditCategory);
        
        // Refresh categories button
        $('#refresh-categories').click(function(e) {
            e.preventDefault();
            loadCategories();
        });
        
        // Modal close handlers
        $('#close-edit-modal, #cancel-edit').click(closeEditModal);
        $('#close-delete-modal, #cancel-delete').click(closeDeleteModal);
        
        // Delete confirmation
        $('#confirm-delete-btn').click(handleDeleteCategory);
        
        // Close modals when clicking outside
        $(window).click(function(e) {
            if ($(e.target).hasClass('modal')) {
                closeEditModal();
                closeDeleteModal();
            }
        });
    }

    /**
     * Validate category form data
     * @param {string} categoryName - The category name to validate
     * @param {number|null} excludeId - Category ID to exclude from uniqueness check (for updates)
     * @returns {object} Validation result with isValid and message properties
     */
    function validateCategoryForm(categoryName, excludeId = null) {
        // Check for empty name
        if (!categoryName || categoryName.trim() === '') {
            return {
                isValid: false,
                message: 'Category name is required!'
            };
        }

        // Trim the name
        categoryName = categoryName.trim();

        // Check length
        if (categoryName.length > 100) {
            return {
                isValid: false,
                message: 'Category name must be 100 characters or less!'
            };
        }

        // Check format
        if (!categoryNameRegex.test(categoryName)) {
            return {
                isValid: false,
                message: 'Category name contains invalid characters! Only letters, numbers, spaces, and common punctuation are allowed.'
            };
        }

        return {
            isValid: true,
            message: 'Valid'
        };
    }

    /**
     * Handle add category form submission
     */
    function handleAddCategory(e) {
        e.preventDefault();

        const categoryName = $('#cat_name').val().trim();
        
        // Validate input
        const validation = validateCategoryForm(categoryName);
        if (!validation.isValid) {
            showError('Validation Error', validation.message);
            return;
        }

        // Show loading state
        setAddButtonLoading(true);

        // Submit via AJAX
        addCategory(categoryName);
    }

    /**
     * Add a new category via AJAX
     * @param {string} categoryName - The category name to add
     */
    function addCategory(categoryName) {
        $.ajax({
            url: '../actions/add_category_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                cat_name: categoryName,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setAddButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    $('#add-category-form')[0].reset();
                    loadCategories(); // Refresh the list
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
     * Load and display categories
     */
    function loadCategories() {
        // Show loading state
        $('#categories-loading').show();
        $('#categories-empty').hide();
        $('#categories-list').hide();

        $.ajax({
            url: '../actions/fetch_category_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#categories-loading').hide();
                
                if (response.status === 'success') {
                    if (response.data && response.data.categories && response.data.categories.length > 0) {
                        displayCategories(response.data.categories);
                        $('#categories-list').show();
                    } else {
                        $('#categories-empty').show();
                    }
                } else {
                    showError('Error', response.message || 'Failed to load categories');
                    $('#categories-empty').show();
                }
            },
            error: function(xhr, status, error) {
                $('#categories-loading').hide();
                $('#categories-empty').show();
                console.error('AJAX Error:', status, error);
                
                showError('Connection Error', 'Failed to load categories. Please check your connection and try again.');
            }
        });
    }

    /**
     * Display categories in the grid
     * @param {Array} categories - Array of category objects
     */
    function displayCategories(categories) {
        const categoriesHtml = categories.map(category => `
            <div class="category-card" data-category-id="${category.cat_id}">
                <div class="category-header">
                    <h5 class="category-name">${escapeHtml(category.cat_name)}</h5>
                    <span class="category-id">ID: ${category.cat_id}</span>
                </div>
                <div class="category-meta">
                    <small class="category-date">
                        <i class="fa fa-calendar"></i> 
                        Created: ${formatDate(category.date_created)}
                    </small>
                    ${category.date_modified !== category.date_created ? 
                        `<small class="category-date">
                            <i class="fa fa-edit"></i> 
                            Modified: ${formatDate(category.date_modified)}
                        </small>` : ''
                    }
                </div>
                <div class="category-actions">
                    <button class="btn btn-primary btn-small edit-category" 
                            data-category-id="${category.cat_id}" 
                            data-category-name="${escapeHtml(category.cat_name)}">
                        <i class="fa fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-error btn-small delete-category" 
                            data-category-id="${category.cat_id}" 
                            data-category-name="${escapeHtml(category.cat_name)}">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `).join('');

        $('#categories-list').html(categoriesHtml);

        // Bind action buttons
        $('.edit-category').click(handleEditButtonClick);
        $('.delete-category').click(handleDeleteButtonClick);
    }

    /**
     * Handle edit button click
     */
    function handleEditButtonClick(e) {
        e.preventDefault();
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        
        openEditModal(categoryId, categoryName);
    }

    /**
     * Handle delete button click
     */
    function handleDeleteButtonClick(e) {
        e.preventDefault();
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).data('category-name');
        
        openDeleteModal(categoryId, categoryName);
    }

    /**
     * Open edit modal
     * @param {number} categoryId - The category ID to edit
     * @param {string} categoryName - The current category name
     */
    function openEditModal(categoryId, categoryName) {
        currentEditCategoryId = categoryId;
        $('#edit_cat_id').val(categoryId);
        $('#edit_cat_name').val(categoryName);
        $('#edit-modal').show();
        $('#edit_cat_name').focus();
    }

    /**
     * Close edit modal
     */
    function closeEditModal() {
        $('#edit-modal').hide();
        $('#edit-category-form')[0].reset();
        currentEditCategoryId = null;
        setUpdateButtonLoading(false);
    }

    /**
     * Handle edit category form submission
     */
    function handleEditCategory(e) {
        e.preventDefault();

        const categoryId = $('#edit_cat_id').val();
        const categoryName = $('#edit_cat_name').val().trim();
        
        // Validate input
        const validation = validateCategoryForm(categoryName, categoryId);
        if (!validation.isValid) {
            showError('Validation Error', validation.message);
            return;
        }

        // Show loading state
        setUpdateButtonLoading(true);

        // Submit via AJAX
        updateCategory(categoryId, categoryName);
    }

    /**
     * Update category via AJAX
     * @param {number} categoryId - The category ID to update
     * @param {string} categoryName - The new category name
     */
    function updateCategory(categoryId, categoryName) {
        $.ajax({
            url: '../actions/update_category_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                cat_id: categoryId,
                cat_name: categoryName,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setUpdateButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    closeEditModal();
                    loadCategories(); // Refresh the list
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
     * @param {number} categoryId - The category ID to delete
     * @param {string} categoryName - The category name to display
     */
    function openDeleteModal(categoryId, categoryName) {
        currentDeleteCategoryId = categoryId;
        $('#delete_cat_id').val(categoryId);
        $('#delete-category-name').text(categoryName);
        $('#delete-modal').show();
    }

    /**
     * Close delete modal
     */
    function closeDeleteModal() {
        $('#delete-modal').hide();
        currentDeleteCategoryId = null;
        setDeleteButtonLoading(false);
    }

    /**
     * Handle delete category confirmation
     */
    function handleDeleteCategory(e) {
        e.preventDefault();

        const categoryId = $('#delete_cat_id').val();
        
        if (!categoryId) {
            showError('Error', 'Invalid category selected for deletion');
            return;
        }

        // Show loading state
        setDeleteButtonLoading(true);

        // Submit via AJAX
        deleteCategory(categoryId);
    }

    /**
     * Delete category via AJAX
     * @param {number} categoryId - The category ID to delete
     */
    function deleteCategory(categoryId) {
        $.ajax({
            url: '../actions/delete_category_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                cat_id: categoryId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                setDeleteButtonLoading(false);
                
                if (response.status === 'success') {
                    showSuccess('Success', response.message);
                    closeDeleteModal();
                    loadCategories(); // Refresh the list
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
            $('#add-category-btn').prop('disabled', true);
        } else {
            $('#add-text').show();
            $('#add-loading').hide();
            $('#add-category-btn').prop('disabled', false);
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
            $('#update-category-btn').prop('disabled', true);
        } else {
            $('#update-text').show();
            $('#update-loading').hide();
            $('#update-category-btn').prop('disabled', false);
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
    window.CategoryManager = {
        loadCategories: loadCategories,
        validateCategoryForm: validateCategoryForm
    };
});