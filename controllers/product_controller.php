<?php

require_once __DIR__ . '/../classes/product_class.php';

/**
 * Product Controller
 * Handles product management operations with business logic coordination
 * Requirements: 3.5, 4.3, 6.1, 6.2
 */

/**
 * Add a new product with comprehensive error handling
 * Creates an instance of the product class and invokes the add_product method
 * 
 * @param string $product_title Product title
 * @param float $product_price Product price
 * @param string $product_description Product description
 * @param string $product_image Product image path
 * @param string $product_keywords Product keywords
 * @param int $category_id Category ID
 * @param int $brand_id Brand ID
 * @param int $user_id User ID who owns the product
 * @return array Response array with success status and data/error message
 */
function add_product_ctr($product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($product_title) || !is_string($product_title)) {
        return array(
            'success' => false,
            'error' => 'Product title is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_title', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (empty($product_price) || !is_numeric($product_price)) {
        return array(
            'success' => false,
            'error' => 'Valid product price is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_price', 'issue' => 'empty_or_non_numeric']
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
    
    // Validate product title length (1-255 characters as per design)
    $product_title = trim($product_title);
    if (strlen($product_title) < 1 || strlen($product_title) > 255) {
        return array(
            'success' => false,
            'error' => 'Product title must be between 1 and 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_title', 'length' => strlen($product_title), 'min' => 1, 'max' => 255]
        );
    }
    
    // Validate price is positive
    if ($product_price < 0) {
        return array(
            'success' => false,
            'error' => 'Product price must be a positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_price', 'value' => $product_price]
        );
    }
    
    // Sanitize optional fields
    $product_description = $product_description ? trim($product_description) : '';
    $product_image = $product_image ? trim($product_image) : '';
    $product_keywords = $product_keywords ? trim($product_keywords) : '';
    
    // Validate keywords length if provided
    if (!empty($product_keywords) && strlen($product_keywords) > 255) {
        return array(
            'success' => false,
            'error' => 'Product keywords cannot exceed 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_keywords', 'length' => strlen($product_keywords), 'max' => 255]
        );
    }
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Invoke product class add_product method with enhanced error handling
        $result = $product->add_product($product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'product_id' => $result['data']['product_id'],
                    'product_title' => $result['data']['product_title'],
                    'product_price' => $result['data']['product_price'],
                    'product_description' => $result['data']['product_description'],
                    'product_image' => $result['data']['product_image'],
                    'product_keywords' => $result['data']['product_keywords'],
                    'category_id' => $result['data']['category_id'],
                    'brand_id' => $result['data']['brand_id'],
                    'user_id' => $result['data']['user_id'],
                    'message' => 'Product created successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product creation exception for user {$user_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while creating the product. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Update an existing product with comprehensive error handling
 * 
 * @param int $product_id Product ID
 * @param string $product_title Product title
 * @param float $product_price Product price
 * @param string $product_description Product description
 * @param string $product_image Product image path
 * @param string $product_keywords Product keywords
 * @param int $category_id Category ID
 * @param int $brand_id Brand ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and data/error message
 */
function update_product_ctr($product_id, $product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($product_id) || !is_numeric($product_id)) {
        return array(
            'success' => false,
            'error' => 'Valid product ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_id', 'issue' => 'empty_or_non_numeric']
        );
    }
    
    if (empty($product_title) || !is_string($product_title)) {
        return array(
            'success' => false,
            'error' => 'Product title is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_title', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (empty($product_price) || !is_numeric($product_price)) {
        return array(
            'success' => false,
            'error' => 'Valid product price is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_price', 'issue' => 'empty_or_non_numeric']
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
    
    // Validate product title length (1-255 characters as per design)
    $product_title = trim($product_title);
    if (strlen($product_title) < 1 || strlen($product_title) > 255) {
        return array(
            'success' => false,
            'error' => 'Product title must be between 1 and 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_title', 'length' => strlen($product_title), 'min' => 1, 'max' => 255]
        );
    }
    
    // Validate price is positive
    if ($product_price < 0) {
        return array(
            'success' => false,
            'error' => 'Product price must be a positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_price', 'value' => $product_price]
        );
    }
    
    // Sanitize optional fields
    $product_description = $product_description ? trim($product_description) : '';
    $product_image = $product_image ? trim($product_image) : '';
    $product_keywords = $product_keywords ? trim($product_keywords) : '';
    
    // Validate keywords length if provided
    if (!empty($product_keywords) && strlen($product_keywords) > 255) {
        return array(
            'success' => false,
            'error' => 'Product keywords cannot exceed 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_keywords', 'length' => strlen($product_keywords), 'max' => 255]
        );
    }
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Invoke product class update_product method with enhanced error handling
        $result = $product->update_product($product_id, $product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'product_id' => $result['data']['product_id'],
                    'product_title' => $result['data']['product_title'],
                    'product_price' => $result['data']['product_price'],
                    'product_description' => $result['data']['product_description'],
                    'product_image' => $result['data']['product_image'],
                    'product_keywords' => $result['data']['product_keywords'],
                    'category_id' => $result['data']['category_id'],
                    'brand_id' => $result['data']['brand_id'],
                    'user_id' => $result['data']['user_id'],
                    'affected_rows' => $result['data']['affected_rows'],
                    'message' => 'Product updated successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product update exception for user {$user_id}, product {$product_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while updating the product. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get all products for a specific user with comprehensive error handling
 * 
 * @param int $user_id User ID
 * @param int $category_id Optional category ID to filter by
 * @param int $brand_id Optional brand ID to filter by
 * @return array Response array with success status and products data
 */
function fetch_products_ctr($user_id, $category_id = null, $brand_id = null)
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
    
    // Validate brand_id if provided
    if ($brand_id !== null && (!is_numeric($brand_id) || $brand_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Brand ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'issue' => 'invalid_numeric_value']
        );
    }
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Invoke product class get_products method with enhanced error handling
        $result = $product->get_products($user_id, $category_id, $brand_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'count' => $result['data']['count'],
                    'user_id' => $result['data']['user_id'],
                    'category_id' => $result['data']['category_id'],
                    'brand_id' => $result['data']['brand_id']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product retrieval exception for user {$user_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving products. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get a specific product by ID with ownership verification and comprehensive error handling
 * 
 * @param int $product_id Product ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and product data
 */
function get_product_ctr($product_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($product_id) || !is_numeric($product_id)) {
        return array(
            'success' => false,
            'error' => 'Valid product ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_id', 'issue' => 'empty_or_non_numeric']
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
        // Create instance of product class
        $product = new Product();
        
        // Get product data with enhanced error handling
        $result = $product->get_product_by_id($product_id, $user_id);
        
        if (!$result['success']) {
            // Handle database errors
            $error_message = get_user_friendly_product_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
        if (!$result['data']['product']) {
            return array(
                'success' => false,
                'error' => 'Product not found or access denied',
                'error_type' => 'not_found',
                'error_details' => ['product_id' => $product_id, 'user_id' => $user_id]
            );
        }
        
        $product_data = $result['data']['product'];
        
        return array(
            'success' => true,
            'data' => array(
                'product' => $product_data
            )
        );
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Product retrieval exception for user {$user_id}, product {$product_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving the product. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Delete a product with comprehensive error handling
 * 
 * @param int $product_id Product ID
 * @param int $user_id User ID (for ownership verification)
 * @return array Response array with success status and message
 */
function delete_product_ctr($product_id, $user_id)
{
    // Input validation with detailed error responses
    if (empty($product_id) || !is_numeric($product_id)) {
        return array(
            'success' => false,
            'error' => 'Valid product ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_id', 'issue' => 'empty_or_non_numeric']
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
        // Create instance of product class
        $product = new Product();
        
        // Invoke product class delete_product method with enhanced error handling
        $result = $product->delete_product($product_id, $user_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'product_id' => $result['data']['product_id'],
                    'user_id' => $result['data']['user_id'],
                    'deleted_product' => $result['data']['deleted_product'],
                    'affected_rows' => $result['data']['affected_rows'],
                    'message' => 'Product deleted successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product deletion exception for user {$user_id}, product {$product_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while deleting the product. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get user-friendly error messages for product-related database errors
 * @param string $error_type The type of database error
 * @param string $original_message The original error message
 * @return string User-friendly error message
 */
function get_user_friendly_product_error($error_type, $original_message)
{
    switch ($error_type) {
        case 'connection_error':
        case 'connection_exception':
            return 'Database connection error. Please try again later.';
            
        case 'duplicate_entry':
            return 'This product information conflicts with existing data. Please check your input.';
            
        case 'foreign_key_constraint':
            return 'Cannot complete operation. The selected category or brand may not exist or may be associated with other records.';
            
        case 'category_not_found':
            return 'The selected category was not found or you do not have access to it.';
            
        case 'brand_not_found':
            return 'The selected brand was not found or you do not have access to it.';
            
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
            return 'The requested product was not found or you do not have access to it.';
            
        case 'validation_error':
            return 'Invalid input data provided.';
            
        case 'no_changes':
            return 'No changes were made. The product may not exist or the data is unchanged.';
            
        default:
            return 'A database error occurred. Please try again or contact support if the problem persists.';
    }
}

?>