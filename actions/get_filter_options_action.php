<?php

/**
 * Get Filter Options Action
 * Retrieves categories and brands for filter dropdown population
 * Requirements: 2.1, 7.3
 */

header('Content-Type: application/json');

// No session required for customer-facing filter options
// This is a public endpoint for getting filter dropdown data

$response = array();

// Validate request method (GET for fetching)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. GET required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Extract optional category filter for brands
$category_id = isset($_GET['category_id']) && is_numeric($_GET['category_id']) && $_GET['category_id'] > 0 ? (int)$_GET['category_id'] : null;

// Validate category_id if provided
if ($category_id !== null && $category_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Category ID must be a positive integer.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'category_id';
    echo json_encode($response);
    exit();
}

require_once '../controllers/product_display_controller.php';

try {
    // Get categories for filter dropdown
    $categories_result = get_categories_for_filter_ctr();
    
    // Get brands for filter dropdown (optionally filtered by category)
    $brands_result = get_brands_for_filter_ctr($category_id);
    
    if ($categories_result['success'] && $brands_result['success']) {
        $response['status'] = 'success';
        $response['data'] = array(
            'categories' => $categories_result['data']['categories'],
            'brands' => $brands_result['data']['brands'],
            'category_filter' => $category_id
        );
        $response['message'] = 'Filter options retrieved successfully';
        
        // Add metadata
        $response['metadata'] = array(
            'categories_count' => $categories_result['data']['count'],
            'brands_count' => $brands_result['data']['count'],
            'filtered_by_category' => $category_id !== null
        );
        
        // Log successful retrieval for monitoring (optional)
        error_log("Filter options retrieved: {$categories_result['data']['count']} categories, {$brands_result['data']['count']} brands" . 
                 ($category_id ? " (filtered by category {$category_id})" : ""));
        
    } else {
        // Handle partial failures
        $response['status'] = 'error';
        
        if (!$categories_result['success'] && !$brands_result['success']) {
            $response['message'] = 'Failed to retrieve both categories and brands.';
            $response['error_type'] = 'both_failed';
            $response['categories_error'] = $categories_result['error'];
            $response['brands_error'] = $brands_result['error'];
        } elseif (!$categories_result['success']) {
            $response['message'] = 'Failed to retrieve categories.';
            $response['error_type'] = 'categories_failed';
            $response['error'] = $categories_result['error'];
            $response['brands_data'] = $brands_result['data']; // Include successful brands data
        } else {
            $response['message'] = 'Failed to retrieve brands.';
            $response['error_type'] = 'brands_failed';
            $response['error'] = $brands_result['error'];
            $response['categories_data'] = $categories_result['data']; // Include successful categories data
        }
        
        // Handle specific error types with appropriate user guidance
        $error_type = $categories_result['success'] ? $brands_result['error_type'] : $categories_result['error_type'];
        
        switch ($error_type) {
            case 'connection_error':
            case 'connection_exception':
                $response['suggestion'] = 'Please check your internet connection and try again.';
                $response['retry_recommended'] = true;
                break;
                
            case 'database_error':
                $response['suggestion'] = 'Please try again. If the problem persists, contact support.';
                $response['retry_recommended'] = true;
                break;
                
            case 'validation_error':
                $response['suggestion'] = 'Please refresh the page and try again.';
                break;
        }
        
        // Log the error with context for debugging
        error_log("Filter options retrieval failed: categories success={$categories_result['success']}, brands success={$brands_result['success']}");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'category_id' => $category_id,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Filter options exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while retrieving filter options. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>