<?php

require_once __DIR__ . '/../classes/product_class.php';
require_once __DIR__ . '/../classes/category_class.php';
require_once __DIR__ . '/../classes/brand_class.php';
require_once __DIR__ . '/../settings/db_class.php';

/**
 * Product Display Controller
 * 
 * Handles customer-facing product display operations with business logic coordination.
 */

/**
 * Get all products with pagination for customer display
 * 
 * @param int $page Current page number (default: 1)
 * @param int $limit Products per page (default: 10)
 * @return array Response array with success status and products data
 */
function get_all_products_ctr($page = 1, $limit = 10)
{
    // Input validation with detailed error responses
    if (!is_numeric($page) || $page < 1) {
        return array(
            'success' => false,
            'error' => 'Page number must be a positive integer',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'page', 'value' => $page, 'issue' => 'invalid_page_number']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 100',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 100]
        );
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Get products with pagination
        $result = $product->view_all_products($limit, $offset);
        
        if ($result['success']) {
            // Get total count for pagination
            $count_result = $product->get_product_count();
            $total_count = $count_result['success'] ? $count_result['data']['total_count'] : 0;
            
            // Calculate pagination data
            $total_pages = ceil($total_count / $limit);
            $has_previous = $page > 1;
            $has_next = $page < $total_pages;
            
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => $total_pages,
                        'total_items' => $total_count,
                        'items_per_page' => $limit,
                        'has_previous' => $has_previous,
                        'has_next' => $has_next,
                        'start_item' => $offset + 1,
                        'end_item' => min($offset + $limit, $total_count)
                    )
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product display exception: " . $e->getMessage());
        
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
 * Search products with pagination for customer display
 * 
 * @param string $query Search query
 * @param int $page Current page number (default: 1)
 * @param int $limit Products per page (default: 10)
 * @return array Response array with success status and search results
 */
function search_products_ctr($query, $page = 1, $limit = 10)
{
    // Input validation with detailed error responses
    if (empty($query) || !is_string($query)) {
        return array(
            'success' => false,
            'error' => 'Search query is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'query', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (!is_numeric($page) || $page < 1) {
        return array(
            'success' => false,
            'error' => 'Page number must be a positive integer',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'page', 'value' => $page, 'issue' => 'invalid_page_number']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 100',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 100]
        );
    }
    
    // Sanitize search query
    $query = trim($query);
    if (strlen($query) < 1 || strlen($query) > 255) {
        return array(
            'success' => false,
            'error' => 'Search query must be between 1 and 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'query', 'length' => strlen($query), 'min' => 1, 'max' => 255]
        );
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Search products with pagination
        $result = $product->search_products($query, $limit, $offset);
        
        if ($result['success']) {
            // Get total search count for pagination
            $count_result = $product->get_search_count($query);
            $total_count = $count_result['success'] ? $count_result['data']['total_count'] : 0;
            
            // Calculate pagination data
            $total_pages = ceil($total_count / $limit);
            $has_previous = $page > 1;
            $has_next = $page < $total_pages;
            
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'search_query' => $query,
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => $total_pages,
                        'total_items' => $total_count,
                        'items_per_page' => $limit,
                        'has_previous' => $has_previous,
                        'has_next' => $has_next,
                        'start_item' => $offset + 1,
                        'end_item' => min($offset + $limit, $total_count)
                    )
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product search exception for query '{$query}': " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while searching products. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Filter products by category and/or brand with pagination
 * 
 * @param array $filters Filter criteria (category_id, brand_id)
 * @param int $page Current page number (default: 1)
 * @param int $limit Products per page (default: 10)
 * @return array Response array with success status and filtered products
 */
function filter_products_ctr($filters, $page = 1, $limit = 10)
{
    // Input validation with detailed error responses
    if (!is_array($filters)) {
        return array(
            'success' => false,
            'error' => 'Filters must be provided as an array',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'filters', 'issue' => 'not_array']
        );
    }
    
    if (!is_numeric($page) || $page < 1) {
        return array(
            'success' => false,
            'error' => 'Page number must be a positive integer',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'page', 'value' => $page, 'issue' => 'invalid_page_number']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 100',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 100]
        );
    }
    
    // Extract and validate filter parameters
    $category_id = isset($filters['category_id']) ? $filters['category_id'] : null;
    $brand_id = isset($filters['brand_id']) ? $filters['brand_id'] : null;
    
    // Validate category_id if provided
    if ($category_id !== null && (!is_numeric($category_id) || $category_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Category ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'category_id', 'value' => $category_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    // Validate brand_id if provided
    if ($brand_id !== null && (!is_numeric($brand_id) || $brand_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Brand ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'value' => $brand_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    // At least one filter must be provided
    if ($category_id === null && $brand_id === null) {
        return array(
            'success' => false,
            'error' => 'At least one filter (category_id or brand_id) must be provided',
            'error_type' => 'validation_error',
            'error_details' => ['filters' => $filters, 'issue' => 'no_filters_provided']
        );
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Apply filters based on what's provided
        if ($category_id !== null && $brand_id === null) {
            // Filter by category only
            $result = $product->filter_products_by_category($category_id, $limit, $offset);
        } elseif ($brand_id !== null && $category_id === null) {
            // Filter by brand only
            $result = $product->filter_products_by_brand($brand_id, $limit, $offset);
        } else {
            // Filter by both category and brand using composite search
            $params = array(
                'category_id' => $category_id,
                'brand_id' => $brand_id,
                'limit' => $limit,
                'offset' => $offset
            );
            $result = $product->composite_search($params);
        }
        
        if ($result['success']) {
            // For pagination, we need to get the total count
            // This is a simplified approach - in a real application, you might want separate count methods for each filter type
            $total_count = count($result['data']['products']) < $limit ? 
                          $offset + count($result['data']['products']) : 
                          ($offset + $limit + 1); // Estimate there might be more
            
            // Calculate pagination data
            $total_pages = ceil($total_count / $limit);
            $has_previous = $page > 1;
            $has_next = count($result['data']['products']) >= $limit;
            
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'filters' => array(
                        'category_id' => $category_id,
                        'brand_id' => $brand_id
                    ),
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => $total_pages,
                        'total_items' => $total_count,
                        'items_per_page' => $limit,
                        'has_previous' => $has_previous,
                        'has_next' => $has_next,
                        'start_item' => $offset + 1,
                        'end_item' => $offset + count($result['data']['products'])
                    )
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Product filter exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while filtering products. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get a single product for customer display
 * 
 * @param int $product_id Product ID
 * @return array Response array with success status and product data
 */
function get_single_product_ctr($product_id)
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
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Get single product (no user_id for customer display)
        $result = $product->view_single_product($product_id);
        
        if ($result['success']) {
            if ($result['data']['product']) {
                return array(
                    'success' => true,
                    'data' => array(
                        'product' => $result['data']['product']
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Product not found',
                    'error_type' => 'not_found',
                    'error_details' => ['product_id' => $product_id]
                );
            }
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Single product retrieval exception for product {$product_id}: " . $e->getMessage());
        
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
 * Perform composite search with multiple criteria
 * 
 * @param array $params Search parameters (query, category_id, brand_id, min_price, max_price, page, limit)
 * @return array Response array with success status and search results
 */
function composite_search_ctr($params)
{
    // Input validation with detailed error responses
    if (!is_array($params)) {
        return array(
            'success' => false,
            'error' => 'Search parameters must be provided as an array',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'params', 'issue' => 'not_array']
        );
    }
    
    // Extract parameters with defaults
    $query = isset($params['query']) ? trim($params['query']) : '';
    $category_id = isset($params['category_id']) ? $params['category_id'] : null;
    $brand_id = isset($params['brand_id']) ? $params['brand_id'] : null;
    $min_price = isset($params['min_price']) ? $params['min_price'] : null;
    $max_price = isset($params['max_price']) ? $params['max_price'] : null;
    $page = isset($params['page']) ? $params['page'] : 1;
    $limit = isset($params['limit']) ? $params['limit'] : 10;
    
    // Validate page and limit
    if (!is_numeric($page) || $page < 1) {
        return array(
            'success' => false,
            'error' => 'Page number must be a positive integer',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'page', 'value' => $page, 'issue' => 'invalid_page_number']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 100',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 100]
        );
    }
    
    // Validate search query if provided
    if (!empty($query) && strlen($query) > 255) {
        return array(
            'success' => false,
            'error' => 'Search query cannot exceed 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'query', 'length' => strlen($query), 'max' => 255]
        );
    }
    
    // Validate category_id if provided
    if ($category_id !== null && (!is_numeric($category_id) || $category_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Category ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'category_id', 'value' => $category_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    // Validate brand_id if provided
    if ($brand_id !== null && (!is_numeric($brand_id) || $brand_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Brand ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'value' => $brand_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    // Validate price range if provided
    if ($min_price !== null && (!is_numeric($min_price) || $min_price < 0)) {
        return array(
            'success' => false,
            'error' => 'Minimum price must be a non-negative number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'min_price', 'value' => $min_price, 'issue' => 'invalid_price']
        );
    }
    
    if ($max_price !== null && (!is_numeric($max_price) || $max_price < 0)) {
        return array(
            'success' => false,
            'error' => 'Maximum price must be a non-negative number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'max_price', 'value' => $max_price, 'issue' => 'invalid_price']
        );
    }
    
    if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
        return array(
            'success' => false,
            'error' => 'Minimum price cannot be greater than maximum price',
            'error_type' => 'validation_error',
            'error_details' => ['min_price' => $min_price, 'max_price' => $max_price, 'issue' => 'invalid_price_range']
        );
    }
    
    // Validate sort parameters if provided
    $sort_by = isset($params['sort_by']) ? $params['sort_by'] : 'date';
    $sort_order = isset($params['sort_order']) ? $params['sort_order'] : 'DESC';
    
    $valid_sort_fields = ['relevance', 'price', 'title', 'date', 'category', 'brand'];
    if (!in_array($sort_by, $valid_sort_fields)) {
        return array(
            'success' => false,
            'error' => 'Invalid sort field. Must be one of: ' . implode(', ', $valid_sort_fields),
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'sort_by', 'value' => $sort_by, 'valid_values' => $valid_sort_fields]
        );
    }
    
    if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
        return array(
            'success' => false,
            'error' => 'Sort order must be ASC or DESC',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'sort_order', 'value' => $sort_order, 'valid_values' => ['ASC', 'DESC']]
        );
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Prepare parameters for composite search
        $search_params = array(
            'query' => $query,
            'category_id' => $category_id,
            'brand_id' => $brand_id,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'limit' => $limit,
            'offset' => $offset
        );
        
        // Perform composite search
        $result = $product->composite_search($search_params);
        
        if ($result['success']) {
            // For pagination, estimate total count based on results
            $total_count = count($result['data']['products']) < $limit ? 
                          $offset + count($result['data']['products']) : 
                          ($offset + $limit + 1); // Estimate there might be more
            
            // Calculate pagination data
            $total_pages = ceil($total_count / $limit);
            $has_previous = $page > 1;
            $has_next = count($result['data']['products']) >= $limit;
            
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'search_criteria' => array(
                        'query' => $query,
                        'category_id' => $category_id,
                        'brand_id' => $brand_id,
                        'min_price' => $min_price,
                        'max_price' => $max_price
                    ),
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => $total_pages,
                        'total_items' => $total_count,
                        'items_per_page' => $limit,
                        'has_previous' => $has_previous,
                        'has_next' => $has_next,
                        'start_item' => $offset + 1,
                        'end_item' => $offset + count($result['data']['products'])
                    )
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Composite search exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while searching products. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get all categories for filter dropdown population
 * 
 * @return array Response array with success status and categories data
 */
function get_categories_for_filter_ctr()
{
    try {
        // Create instance of category class
        $category = new Category();
        
        // Get all categories (we'll need to modify this to get all categories, not user-specific)
        // For customer display, we want all categories that have products
        // This is a simplified approach - in production, you might want a dedicated method
        
        // Connect to database to get categories that have products
        $db = new db_connection();
        if (!$db->db_connect()) {
            return array(
                'success' => false,
                'error' => 'Database connection failed',
                'error_type' => 'connection_error'
            );
        }
        
        $sql = "SELECT DISTINCT c.cat_id, c.cat_name 
                FROM categories c 
                INNER JOIN products p ON c.cat_id = p.category_id 
                ORDER BY c.cat_name ASC";
        
        $result = $db->db->query($sql);
        
        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Failed to retrieve categories',
                'error_type' => 'database_error',
                'error_details' => ['mysql_error' => $db->db->error]
            );
        }
        
        $categories = [];
        if ($result->num_rows > 0) {
            $categories = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        return array(
            'success' => true,
            'data' => array(
                'categories' => $categories,
                'count' => count($categories)
            )
        );
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Categories retrieval exception: " . $e->getMessage());
        
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
 * Advanced keyword search with relevance scoring
 * 
 * @param string $query Search query
 * @param int $page Current page number (default: 1)
 * @param int $limit Products per page (default: 10)
 * @return array Response array with success status and search results
 */
function advanced_search_ctr($query, $page = 1, $limit = 10)
{
    // Input validation with detailed error responses
    if (empty($query) || !is_string($query)) {
        return array(
            'success' => false,
            'error' => 'Search query is required and must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'query', 'issue' => 'empty_or_invalid_type']
        );
    }
    
    if (!is_numeric($page) || $page < 1) {
        return array(
            'success' => false,
            'error' => 'Page number must be a positive integer',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'page', 'value' => $page, 'issue' => 'invalid_page_number']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 100',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 100]
        );
    }
    
    // Sanitize search query
    $query = trim($query);
    if (strlen($query) < 1 || strlen($query) > 255) {
        return array(
            'success' => false,
            'error' => 'Search query must be between 1 and 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'query', 'length' => strlen($query), 'min' => 1, 'max' => 255]
        );
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Perform advanced search with relevance scoring
        $result = $product->advanced_keyword_search($query, $limit, $offset);
        
        if ($result['success']) {
            // Get total search count for pagination
            $count_result = $product->get_search_count($query);
            $total_count = $count_result['success'] ? $count_result['data']['total_count'] : 0;
            
            // Calculate pagination data
            $total_pages = ceil($total_count / $limit);
            $has_previous = $page > 1;
            $has_next = $page < $total_pages;
            
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'search_query' => $query,
                    'search_terms' => $result['data']['search_terms'],
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => $total_pages,
                        'total_items' => $total_count,
                        'items_per_page' => $limit,
                        'has_previous' => $has_previous,
                        'has_next' => $has_next,
                        'start_item' => $offset + 1,
                        'end_item' => min($offset + $limit, $total_count)
                    )
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Advanced search exception for query '{$query}': " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while searching products. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get search suggestions for auto-complete
 * 
 * @param string $partial_query Partial search query
 * @param int $limit Maximum number of suggestions (default: 10)
 * @return array Response array with success status and suggestions
 */
function get_search_suggestions_ctr($partial_query, $limit = 10)
{
    // Input validation
    if (!is_string($partial_query)) {
        return array(
            'success' => false,
            'error' => 'Query must be a valid string',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'partial_query', 'issue' => 'invalid_type']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 50) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 50',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 50]
        );
    }
    
    // Sanitize query
    $partial_query = trim($partial_query);
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Get search suggestions
        $result = $product->get_search_suggestions($partial_query, $limit);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'suggestions' => $result['data']['suggestions'],
                    'count' => $result['data']['count'],
                    'query' => $partial_query
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Search suggestions exception for query '{$partial_query}': " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while getting suggestions. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Enhanced composite search with advanced filtering and sorting
 * 
 * @param array $params Enhanced search parameters
 * @return array Response array with success status and search results
 */
function enhanced_composite_search_ctr($params)
{
    // Input validation with detailed error responses
    if (!is_array($params)) {
        return array(
            'success' => false,
            'error' => 'Search parameters must be provided as an array',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'params', 'issue' => 'not_array']
        );
    }
    
    // Extract parameters with defaults
    $query = isset($params['query']) ? trim($params['query']) : '';
    $category_id = isset($params['category_id']) ? $params['category_id'] : null;
    $brand_id = isset($params['brand_id']) ? $params['brand_id'] : null;
    $min_price = isset($params['min_price']) ? $params['min_price'] : null;
    $max_price = isset($params['max_price']) ? $params['max_price'] : null;
    $sort_by = isset($params['sort_by']) ? $params['sort_by'] : 'relevance';
    $sort_order = isset($params['sort_order']) ? $params['sort_order'] : 'DESC';
    $page = isset($params['page']) ? $params['page'] : 1;
    $limit = isset($params['limit']) ? $params['limit'] : 10;
    
    // Validate page and limit
    if (!is_numeric($page) || $page < 1) {
        return array(
            'success' => false,
            'error' => 'Page number must be a positive integer',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'page', 'value' => $page, 'issue' => 'invalid_page_number']
        );
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        return array(
            'success' => false,
            'error' => 'Limit must be between 1 and 100',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'limit', 'value' => $limit, 'min' => 1, 'max' => 100]
        );
    }
    
    // Validate search query if provided
    if (!empty($query) && strlen($query) > 255) {
        return array(
            'success' => false,
            'error' => 'Search query cannot exceed 255 characters',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'query', 'length' => strlen($query), 'max' => 255]
        );
    }
    
    // Validate category_id if provided
    if ($category_id !== null && (!is_numeric($category_id) || $category_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Category ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'category_id', 'value' => $category_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    // Validate brand_id if provided
    if ($brand_id !== null && (!is_numeric($brand_id) || $brand_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Brand ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'brand_id', 'value' => $brand_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    // Validate price range if provided
    if ($min_price !== null && (!is_numeric($min_price) || $min_price < 0)) {
        return array(
            'success' => false,
            'error' => 'Minimum price must be a non-negative number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'min_price', 'value' => $min_price, 'issue' => 'invalid_price']
        );
    }
    
    if ($max_price !== null && (!is_numeric($max_price) || $max_price < 0)) {
        return array(
            'success' => false,
            'error' => 'Maximum price must be a non-negative number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'max_price', 'value' => $max_price, 'issue' => 'invalid_price']
        );
    }
    
    if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
        return array(
            'success' => false,
            'error' => 'Minimum price cannot be greater than maximum price',
            'error_type' => 'validation_error',
            'error_details' => ['min_price' => $min_price, 'max_price' => $max_price, 'issue' => 'invalid_price_range']
        );
    }
    
    // Validate sort parameters
    $valid_sort_fields = ['relevance', 'price', 'title', 'date', 'category', 'brand'];
    if (!in_array($sort_by, $valid_sort_fields)) {
        return array(
            'success' => false,
            'error' => 'Invalid sort field. Must be one of: ' . implode(', ', $valid_sort_fields),
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'sort_by', 'value' => $sort_by, 'valid_values' => $valid_sort_fields]
        );
    }
    
    if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
        return array(
            'success' => false,
            'error' => 'Sort order must be ASC or DESC',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'sort_order', 'value' => $sort_order, 'valid_values' => ['ASC', 'DESC']]
        );
    }
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $limit;
    
    try {
        // Create instance of product class
        $product = new Product();
        
        // Prepare parameters for enhanced composite search
        $search_params = array(
            'query' => $query,
            'category_id' => $category_id,
            'brand_id' => $brand_id,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'limit' => $limit,
            'offset' => $offset
        );
        
        // Perform enhanced composite search
        $result = $product->enhanced_composite_search($search_params);
        
        if ($result['success']) {
            // For pagination, estimate total count based on results
            $total_count = count($result['data']['products']) < $limit ? 
                          $offset + count($result['data']['products']) : 
                          ($offset + $limit + 1); // Estimate there might be more
            
            // Calculate pagination data
            $total_pages = ceil($total_count / $limit);
            $has_previous = $page > 1;
            $has_next = count($result['data']['products']) >= $limit;
            
            return array(
                'success' => true,
                'data' => array(
                    'products' => $result['data']['products'],
                    'search_criteria' => array(
                        'query' => $query,
                        'category_id' => $category_id,
                        'brand_id' => $brand_id,
                        'min_price' => $min_price,
                        'max_price' => $max_price,
                        'sort_by' => $sort_by,
                        'sort_order' => $sort_order
                    ),
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => $total_pages,
                        'total_items' => $total_count,
                        'items_per_page' => $limit,
                        'has_previous' => $has_previous,
                        'has_next' => $has_next,
                        'start_item' => $offset + 1,
                        'end_item' => $offset + count($result['data']['products'])
                    )
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_product_display_error($result['error_type'], $result['error_message']);
            
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
        error_log("Enhanced composite search exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while searching products. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get all brands for filter dropdown population
 * 
 * @param int $category_id Optional category ID to filter brands
 * @return array Response array with success status and brands data
 */
function get_brands_for_filter_ctr($category_id = null)
{
    // Validate category_id if provided
    if ($category_id !== null && (!is_numeric($category_id) || $category_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Category ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'category_id', 'value' => $category_id, 'issue' => 'invalid_numeric_value']
        );
    }
    
    try {
        // Connect to database to get brands that have products
        $db = new db_connection();
        if (!$db->db_connect()) {
            return array(
                'success' => false,
                'error' => 'Database connection failed',
                'error_type' => 'connection_error'
            );
        }
        
        $sql = "SELECT DISTINCT b.brand_id, b.brand_name, b.category_id, c.cat_name
                FROM brands b 
                INNER JOIN products p ON b.brand_id = p.brand_id 
                INNER JOIN categories c ON b.category_id = c.cat_id";
        
        $params = [];
        $types = "";
        
        if ($category_id !== null) {
            $sql .= " WHERE b.category_id = ?";
            $params[] = $category_id;
            $types = "i";
        }
        
        $sql .= " ORDER BY c.cat_name ASC, b.brand_name ASC";
        
        if (!empty($params)) {
            $stmt = $db->db->prepare($sql);
            if (!$stmt) {
                return array(
                    'success' => false,
                    'error' => 'Failed to prepare brands query',
                    'error_type' => 'database_error',
                    'error_details' => ['mysql_error' => $db->db->error]
                );
            }
            
            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return array(
                    'success' => false,
                    'error' => 'Failed to bind parameters for brands query',
                    'error_type' => 'database_error',
                    'error_details' => ['mysql_error' => $stmt->error]
                );
            }
            
            if (!$stmt->execute()) {
                $stmt->close();
                return array(
                    'success' => false,
                    'error' => 'Failed to execute brands query',
                    'error_type' => 'database_error',
                    'error_details' => ['mysql_error' => $stmt->error]
                );
            }
            
            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return array(
                    'success' => false,
                    'error' => 'Failed to get result from brands query',
                    'error_type' => 'database_error',
                    'error_details' => ['mysql_error' => $db->db->error]
                );
            }
            
            $brands = [];
            if ($result->num_rows > 0) {
                $brands = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
        } else {
            $result = $db->db->query($sql);
            
            if ($result === false) {
                return array(
                    'success' => false,
                    'error' => 'Failed to retrieve brands',
                    'error_type' => 'database_error',
                    'error_details' => ['mysql_error' => $db->db->error]
                );
            }
            
            $brands = [];
            if ($result->num_rows > 0) {
                $brands = $result->fetch_all(MYSQLI_ASSOC);
            }
        }
        
        return array(
            'success' => true,
            'data' => array(
                'brands' => $brands,
                'count' => count($brands),
                'category_filter' => $category_id
            )
        );
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Brands retrieval exception: " . $e->getMessage());
        
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
 * Get user-friendly error messages for product display related errors
 * @param string $error_type The type of error
 * @param string $original_message The original error message
 * @return string User-friendly error message
 */
function get_user_friendly_product_display_error($error_type, $original_message)
{
    switch ($error_type) {
        case 'connection_error':
        case 'connection_exception':
            return 'Database connection error. Please try again later.';
            
        case 'database_error':
            return 'A database error occurred. Please try again or contact support if the problem persists.';
            
        case 'validation_error':
            return 'Invalid input provided. Please check your search criteria and try again.';
            
        case 'not_found':
            return 'No products found matching your criteria.';
            
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
            
        default:
            return 'An error occurred while processing your request. Please try again or contact support if the problem persists.';
    }
}

?>