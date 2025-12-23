<?php

require_once __DIR__ . '/../classes/brand_class.php';

/**
 * Brand Controller
 * Handles brand management operations with business logic coordination
 * Requirements: 2.1, 3.1, 4.1
 */

/**
 * Add a new brand with comprehensive error handling
 * Creates an instance of the brand class and invokes the add_brand method
 * 
 * @param string $brand_name Brand name
 * @param int $category_id Category ID
 * @param int $user_id User ID who owns the brand
 * @return array Response array with success status and data/error message
 */
function add_brand_ctr($brand_name, $category_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($brand_name) || !is_string($brand_name)) {
        return array(
            'success' => false,
            'error' => 'Brand name is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_name', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (empty($category_id) || !is_numeric($category_id)) {
        return array(
            'success' => false,
            'error' => 'Valid category ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'category_id', 'issue' => 'empty_or_non_numeric']
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
    
    // Validate brand name length (1-255 characters as per design)
    $brand_name = trim($brand_name);
    if (strlen($brand_name) < 1 || strlen($brand_name) > 255) {
        return array(
            'success' => false,
            'error' => 'Brand name must be between 1 and 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_name', 'length' => strlen($brand_name), 'min' => 1, 'max' => 255]
        );
    }
    
    try {
        // Create instance of brand class
        $brand = new Brand();
        
        // Invoke brand class add_brand method with enhanced error handling
        $result = $brand->add_brand($brand_name, $category_id, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'brand_id' => $result['data']['brand_id'],
                    'brand_name' => $result['data']['brand_name'],
                    'category_id' => $result['data']['category_id'],
                    'user_id' => $result['data']['user_id'],
                    'message' => 'Brand created successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_brand_error($result['error_type'], $result['error_message']);
            
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
        error_log("Brand creation exception for user {$user_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while creating the brand. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get all brands for a specific user with comprehensive error handling
 * 
 * @param int $user_id User ID
 * @param int $category_id Optional category ID to filter by
 * @return array Response array with success status and brands data
 */
function fetch_brands_ctr($user_id, $category_id = null)
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
    
    // Validate category_id if provided
    if ($category_id !== null && (!is_numeric($category_id) || $category_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Category ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'category_id', 'issue' => 'invalid_numeric_value']
        );
    }
    
    try {
        // Create instance of brand class
        $brand = new Brand();
        
        // Invoke brand class get_brands method with enhanced error handling
        $result = $brand->get_brands($user_id, $category_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'brands' => $result['data']['brands'],
                    'count' => $result['data']['count'],
                    'user_id' => $result['data']['user_id'],
                    'category_id' => $result['data']['category_id']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_brand_error($result['error_type'], $result['error_message']);
            
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
        error_log("Brand retrieval exception for user {$user_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving brands. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Update an existing brand with comprehensive error handling
 * 
 * @param int $brand_id Brand ID
 * @param string $brand_name New brand name
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and data/error message
 */
function update_brand_ctr($brand_id, $brand_name, $user_id)
{
    // Input validation with detailed error responses
    if (empty($brand_id) || !is_numeric($brand_id)) {
        return array(
            'success' => false,
            'error' => 'Valid brand ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    if (empty($brand_name) || !is_string($brand_name)) {
        return array(
            'success' => false,
            'error' => 'Brand name is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_name', 'issue' => 'empty_or_invalid_type']
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
    
    // Validate brand name length (1-255 characters as per design)
    $brand_name = trim($brand_name);
    if (strlen($brand_name) < 1 || strlen($brand_name) > 255) {
        return array(
            'success' => false,
            'error' => 'Brand name must be between 1 and 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_name', 'length' => strlen($brand_name), 'min' => 1, 'max' => 255]
        );
    }
    
    try {
        // Create instance of brand class
        $brand = new Brand();
        
        // Invoke brand class update_brand method with enhanced error handling
        $result = $brand->update_brand($brand_id, $brand_name, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'brand_id' => $result['data']['brand_id'],
                    'brand_name' => $result['data']['brand_name'],
                    'category_id' => $result['data']['category_id'],
                    'user_id' => $result['data']['user_id'],
                    'message' => 'Brand updated successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_brand_error($result['error_type'], $result['error_message']);
            
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
        error_log("Brand update exception for user {$user_id}, brand {$brand_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while updating the brand. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Delete a brand with comprehensive error handling
 * 
 * @param int $brand_id Brand ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and message
 */
function delete_brand_ctr($brand_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($brand_id) || !is_numeric($brand_id)) {
        return array(
            'success' => false,
            'error' => 'Valid brand ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'issue' => 'empty_or_non_numeric']
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
        // Create instance of brand class
        $brand = new Brand();
        
        // Invoke brand class delete_brand method with enhanced error handling
        $result = $brand->delete_brand($brand_id, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'brand_id' => $result['data']['brand_id'],
                    'user_id' => $result['data']['user_id'],
                    'deleted_brand' => $result['data']['deleted_brand'],
                    'message' => 'Brand deleted successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_brand_error($result['error_type'], $result['error_message']);
            
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
        error_log("Brand deletion exception for user {$user_id}, brand {$brand_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while deleting the brand. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get a specific brand by ID with ownership verification and comprehensive error handling
 * 
 * @param int $brand_id Brand ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and brand data
 */
function get_brand_ctr($brand_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($brand_id) || !is_numeric($brand_id)) {
        return array(
            'success' => false,
            'error' => 'Valid brand ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'issue' => 'empty_or_non_numeric']
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
        // Create instance of brand class
        $brand = new Brand();
        
        // Get brand data with enhanced error handling
        $result = $brand->get_brand_by_id($brand_id, $user_id);
        
        if (!$result['success']) {
            // Handle database errors
            $error_message = get_user_friendly_brand_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
        if (!$result['data']['brand']) {
            return array(
                'success' => false,
                'error' => 'Brand not found or access denied',
                'error_type' => 'not_found',
                'error_details' => ['brand_id' => $brand_id, 'user_id' => $user_id]
            );
        }
        
        $brand_data = $result['data']['brand'];
        
        return array(
            'success' => true,
            'data' => array(
                'brand' => $brand_data
            )
        );
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Brand retrieval exception for user {$user_id}, brand {$brand_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving the brand. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get user-friendly error messages for brand-related database errors
 * @param string $error_type The type of database error
 * @param string $original_message The original error message
 * @return string User-friendly error message
 */
function get_user_friendly_brand_error($error_type, $original_message)
{
    switch ($error_type) {
        case 'connection_error':
        case 'connection_exception':
            return 'Database connection error. Please try again later.';
            
        case 'duplicate_entry':
        case 'duplicate_name':
            return 'This brand name already exists in this category. Please choose a different name.';
            
        case 'foreign_key_constraint':
            return 'Cannot complete operation. The selected category may not exist or may be associated with other records.';
            
        case 'category_not_found':
            return 'The selected category was not found or you do not have access to it.';
            
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
            return 'The requested brand was not found or you do not have access to it.';
            
        case 'validation_error':
            return 'Invalid input data provided.';
            
        case 'no_changes':
            return 'No changes were made. The brand may not exist or the data is unchanged.';
            
        default:
            return 'A database error occurred. Please try again or contact support if the problem persists.';
    }
}

?>