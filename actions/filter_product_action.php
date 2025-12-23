<?php

/**
 * Filter Product Action
 * Handles category, brand, and composite filtering for customer-facing product display
 * Requirements: 2.2, 6.1, 8.2
 */

header('Content-Type: application/json');

// No session required for customer-facing product filtering
// This is a public endpoint for filtering products

$response = array();

// Validate request method (GET for filtering)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. GET required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Extract and validate filter parameters
$category_id = isset($_GET['category_id']) && is_numeric($_GET['category_id']) && $_GET['category_id'] > 0 ? (int)$_GET['category_id'] : null;
$brand_id = isset($_GET['brand_id']) && is_numeric($_GET['brand_id']) && $_GET['brand_id'] > 0 ? (int)$_GET['brand_id'] : null;
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) && $_GET['min_price'] >= 0 ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) && $_GET['max_price'] >= 0 ? (float)$_GET['max_price'] : null;
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 && $_GET['limit'] <= 100 ? (int)$_GET['limit'] : 10;

// Validate that at least one filter is provided
if ($category_id === null && $brand_id === null && $min_price === null && $max_price === null && empty($query)) {
    $response['status'] = 'error';
    $response['message'] = 'At least one filter parameter must be provided.';
    $response['error_type'] = 'validation_failed';
    $response['available_filters'] = ['category_id', 'brand_id', 'min_price', 'max_price', 'query'];
    echo json_encode($response);
    exit();
}

// Validate price range if both are provided
if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
    $response['status'] = 'error';
    $response['message'] = 'Minimum price cannot be greater than maximum price.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'price_range';
    $response['min_price'] = $min_price;
    $response['max_price'] = $max_price;
    echo json_encode($response);
    exit();
}

// Validate search query if provided
if (!empty($query)) {
    if (strlen($query) > 255) {
        $response['status'] = 'error';
        $response['message'] = 'Search query cannot exceed 255 characters.';
        $response['error_type'] = 'validation_failed';
        $response['field'] = 'query';
        echo json_encode($response);
        exit();
    }
    
    // Basic sanitization to prevent XSS
    $query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
}

// Validate page parameter
if ($page < 1) {
    $response['status'] = 'error';
    $response['message'] = 'Page number must be a positive integer.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'page';
    echo json_encode($response);
    exit();
}

// Validate limit parameter
if ($limit < 1 || $limit > 100) {
    $response['status'] = 'error';
    $response['message'] = 'Limit must be between 1 and 100.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'limit';
    echo json_encode($response);
    exit();
}

require_once '../controllers/product_display_controller.php';

try {
    // Determine which type of filtering to perform
    $is_composite_search = (!empty($query) || $min_price !== null || $max_price !== null || 
                           ($category_id !== null && $brand_id !== null));
    
    if ($is_composite_search) {
        // Use composite search for complex filtering
        $params = array(
            'query' => $query,
            'category_id' => $category_id,
            'brand_id' => $brand_id,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'page' => $page,
            'limit' => $limit
        );
        
        $result = composite_search_ctr($params);
        
        if ($result['success']) {
            $response['status'] = 'success';
            $response['data'] = $result['data'];
            $response['message'] = 'Composite filter applied successfully';
            $response['filter_type'] = 'composite';
            
        } else {
            $response['status'] = 'error';
            $response['message'] = $result['error'];
            $response['error_type'] = $result['error_type'] ?? 'composite_filter_failed';
        }
        
    } else {
        // Use simple filtering for single criteria
        $filters = array();
        if ($category_id !== null) {
            $filters['category_id'] = $category_id;
        }
        if ($brand_id !== null) {
            $filters['brand_id'] = $brand_id;
        }
        
        $result = filter_products_ctr($filters, $page, $limit);
        
        if ($result['success']) {
            $response['status'] = 'success';
            $response['data'] = $result['data'];
            $response['message'] = 'Filter applied successfully';
            $response['filter_type'] = 'simple';
            
        } else {
            $response['status'] = 'error';
            $response['message'] = $result['error'];
            $response['error_type'] = $result['error_type'] ?? 'filter_failed';
        }
    }
    
    // Add common response data if successful
    if ($response['status'] === 'success') {
        // Add filter metadata
        $response['filter_metadata'] = array(
            'applied_filters' => array(
                'category_id' => $category_id,
                'brand_id' => $brand_id,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'query' => $query
            ),
            'results_count' => count($result['data']['products']),
            'total_results' => $result['data']['pagination']['total_items'],
            'filter_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
        
        // Log successful filter for analytics (optional)
        $filter_summary = [];
        if ($category_id) $filter_summary[] = "category:{$category_id}";
        if ($brand_id) $filter_summary[] = "brand:{$brand_id}";
        if ($min_price !== null) $filter_summary[] = "min_price:{$min_price}";
        if ($max_price !== null) $filter_summary[] = "max_price:{$max_price}";
        if (!empty($query)) $filter_summary[] = "query:'{$query}'";
        
        error_log("Customer filter successful: " . implode(', ', $filter_summary) . 
                 ", {$result['data']['pagination']['total_items']} results, page {$page}");
    }
    
    // Handle error cases
    if ($response['status'] === 'error') {
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
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
                $response['suggestion'] = 'Please check your filter criteria and try again.';
                break;
                
            case 'not_found':
                $response['message'] = 'No products found matching your filter criteria.';
                $response['suggestions'] = array(
                    'Try removing some filters',
                    'Expand your price range',
                    'Try a different category or brand',
                    'Browse all products'
                );
                break;
        }
        
        // Log the error with context for debugging
        $filter_summary = [];
        if ($category_id) $filter_summary[] = "category:{$category_id}";
        if ($brand_id) $filter_summary[] = "brand:{$brand_id}";
        if ($min_price !== null) $filter_summary[] = "min_price:{$min_price}";
        if ($max_price !== null) $filter_summary[] = "max_price:{$max_price}";
        if (!empty($query)) $filter_summary[] = "query:'{$query}'";
        
        error_log("Customer filter failed: " . implode(', ', $filter_summary) . 
                 ", error: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'category_id' => $category_id,
        'brand_id' => $brand_id,
        'min_price' => $min_price,
        'max_price' => $max_price,
        'query' => $query,
        'page' => $page,
        'limit' => $limit,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Customer filter exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while filtering products. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>