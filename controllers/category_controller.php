<?php

require_once '../classes/category_class.php';

/**
 * Category Controller
 * Handles category management operations with business logic and validation
 * Requirements: 3.4, 4.4, 5.1
 */

/**
 * Add a new category with comprehensive error handling
 * Creates an instance of the category class and invokes the add_category method
 * 
 * @param string $cat_name Category name
 * @param int $user_id User ID who owns the category
 * @return array Response array with success status and data/error message
 */
function add_category_ctr($cat_name, $user_id)
{
    // Input validation with detailed error responses
    if (empty($cat_name) || !is_string($cat_name)) {
        return array(
            'success' => false,
            'error' => 'Category name is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_name', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (empty($user_id) || !is_numeric($user_id)) {
        return array(
            'success' => false,
            'error' => 'Valid user ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'user_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    // Validate category name length (1-100 characters as per design)
    $cat_name = trim($cat_name);
    if (strlen($cat_name) < 1 || strlen($cat_name) > 100) {
        return array(
            'success' => false,
            'error' => 'Category name must be between 1 and 100 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_name', 'length' => strlen($cat_name), 'min' => 1, 'max' => 100]
        );
    }
    
    try {
        // Create instance of category class
        $category = new Category();
        
        // Invoke category class add_category method with enhanced error handling
        $result = $category->add_category($cat_name, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'category_id' => $result['data']['category_id'],
                    'category_name' => $result['data']['category_name'],
                    'user_id' => $result['data']['user_id'],
                    'message' => 'Category created successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_database_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Category creation exception for user {$user_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while creating the category. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get all categories for a specific user with comprehensive error handling
 * 
 * @param int $user_id User ID
 * @return array Response array with success status and categories data
 */
function get_categories_ctr($user_id)
{
    // Input validation with detailed error responses
    if (empty($user_id) || !is_numeric($user_id)) {
        return array(
            'success' => false,
            'error' => 'Valid user ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'user_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    try {
        // Create instance of category class
        $category = new Category();
        
        // Invoke category class get_categories_by_user method with enhanced error handling
        $result = $category->get_categories_by_user($user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'categories' => $result['data']['categories'],
                    'count' => $result['data']['count'],
                    'user_id' => $result['data']['user_id']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_database_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Category retrieval exception for user {$user_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving categories. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get user-friendly error messages for database errors
 * @param string $error_type The type of database error
 * @param string $original_message The original error message
 * @return string User-friendly error message
 */
function get_user_friendly_database_error($error_type, $original_message)
{
    switch ($error_type) {
        case 'connection_error':
        case 'connection_exception':
            return 'Database connection error. Please try again later.';
            
        case 'duplicate_entry':
        case 'duplicate_name':
            return 'This category name already exists. Please choose a different name.';
            
        case 'foreign_key_constraint':
            return 'Cannot complete operation. This category may be associated with other records.';
            
        case 'table_not_found':
        case 'column_not_found':
            return 'Database schema error. Please contact administrator.';
            
        case 'connection_lost':
            return 'Database connection lost. Please refresh the page and try again.';
            
        case 'lock_timeout':
        case 'deadlock':
            return 'Database is busy. Please try again in a moment.';
            
        case 'too_many_connections':
            return 'Database server is busy. Please try again later.';
            
        case 'access_denied':
            return 'Database access error. Please contact administrator.';
            
        case 'not_found':
            return 'The requested category was not found.';
            
        case 'validation_error':
            return 'Invalid input data provided.';
            
        case 'no_changes':
            return 'No changes were made. The category may not exist or the data is unchanged.';
            
        default:
            return 'A database error occurred. Please try again or contact support if the problem persists.';
    }
}

/**
 * Update an existing category with comprehensive error handling
 * 
 * @param int $cat_id Category ID
 * @param string $cat_name New category name
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and data/error message
 */
function update_category_ctr($cat_id, $cat_name, $user_id)
{
    // Input validation with detailed error responses
    if (empty($cat_id) || !is_numeric($cat_id)) {
        return array(
            'success' => false,
            'error' => 'Valid category ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    if (empty($cat_name) || !is_string($cat_name)) {
        return array(
            'success' => false,
            'error' => 'Category name is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_name', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (empty($user_id) || !is_numeric($user_id)) {
        return array(
            'success' => false,
            'error' => 'Valid user ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'user_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    // Validate category name length (1-100 characters as per design)
    $cat_name = trim($cat_name);
    if (strlen($cat_name) < 1 || strlen($cat_name) > 100) {
        return array(
            'success' => false,
            'error' => 'Category name must be between 1 and 100 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_name', 'length' => strlen($cat_name), 'min' => 1, 'max' => 100]
        );
    }
    
    try {
        // Create instance of category class
        $category = new Category();
        
        // Invoke category class update_category method with enhanced error handling
        $result = $category->update_category($cat_id, $cat_name, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'category_id' => $result['data']['category_id'],
                    'category_name' => $result['data']['category_name'],
                    'user_id' => $result['data']['user_id'],
                    'message' => 'Category updated successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_database_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Category update exception for user {$user_id}, category {$cat_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while updating the category. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Delete a category with comprehensive error handling
 * 
 * @param int $cat_id Category ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and message
 */
function delete_category_ctr($cat_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($cat_id) || !is_numeric($cat_id)) {
        return array(
            'success' => false,
            'error' => 'Valid category ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    if (empty($user_id) || !is_numeric($user_id)) {
        return array(
            'success' => false,
            'error' => 'Valid user ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'user_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    try {
        // Create instance of category class
        $category = new Category();
        
        // Invoke category class delete_category method with enhanced error handling
        $result = $category->delete_category($cat_id, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'category_id' => $result['data']['category_id'],
                    'user_id' => $result['data']['user_id'],
                    'deleted_category' => $result['data']['deleted_category'],
                    'message' => 'Category deleted successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_database_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Category deletion exception for user {$user_id}, category {$cat_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while deleting the category. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get a specific category by ID with ownership verification and comprehensive error handling
 * 
 * @param int $cat_id Category ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and category data
 */
function get_category_ctr($cat_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($cat_id) || !is_numeric($cat_id)) {
        return array(
            'success' => false,
            'error' => 'Valid category ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'cat_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    if (empty($user_id) || !is_numeric($user_id)) {
        return array(
            'success' => false,
            'error' => 'Valid user ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'user_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    try {
        // Create instance of category class
        $category = new Category();
        
        // Get category data with enhanced error handling
        $result = $category->get_category_by_id($cat_id);
        
        if (!$result['success']) {
            // Handle database errors
            $error_message = get_user_friendly_database_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
        if (!$result['data']['category']) {
            return array(
                'success' => false,
                'error' => 'Category not found',
                'error_type' => 'not_found',
                'error_details' => ['cat_id' => $cat_id]
            );
        }
        
        $category_data = $result['data']['category'];
        
        // Verify ownership
        if ($category_data['user_id'] != $user_id) {
            return array(
                'success' => false,
                'error' => 'Access denied. You can only access your own categories',
                'error_type' => 'access_denied',
                'error_details' => [
                    'cat_id' => $cat_id,
                    'requested_user_id' => $user_id,
                    'actual_user_id' => $category_data['user_id']
                ]
            );
        }
        
        return array(
            'success' => true,
            'data' => array(
                'category' => $category_data
            )
        );
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Category retrieval exception for user {$user_id}, category {$cat_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving the category. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

?>