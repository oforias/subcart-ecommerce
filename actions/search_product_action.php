<?php

/**
 * Search Product Action
 * Handles search queries and returns JSON results for customer-facing product search
 * Requirements: 3.1, 5.1, 8.2
 */

header('Content-Type: application/json');

// No session required for customer-facing product search
// This is a public endpoint for searching products

$response = array();

// Validate request method (GET for searching)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. GET required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Extract and validate search parameters
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 && $_GET['limit'] <= 100 ? (int)$_GET['limit'] : 10;

// Validate search query
if (empty($query)) {
    $response['status'] = 'error';
    $response['message'] = 'Search query is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'query';
    echo json_encode($response);
    exit();
}

// Validate query length
if (strlen($query) < 1 || strlen($query) > 255) {
    $response['status'] = 'error';
    $response['message'] = 'Search query must be between 1 and 255 characters.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'query';
    echo json_encode($response);
    exit();
}

// Basic sanitization to prevent XSS
$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

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
    // Search products for customer display with pagination
    $result = search_products_ctr($query, $page, $limit);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['data'] = $result['data'];
        $response['message'] = 'Search completed successfully';
        
        // Add search metadata
        $response['search_metadata'] = array(
            'query' => $query,
            'results_count' => count($result['data']['products']),
            'total_results' => $result['data']['pagination']['total_items'],
            'search_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
        
        // Log successful search for analytics (optional)
        error_log("Customer search successful: query '{$query}', {$result['data']['pagination']['total_items']} results, page {$page}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'search_failed';
        
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
                $response['suggestion'] = 'Please check your search terms and try again.';
                break;
                
            case 'not_found':
                $response['message'] = 'No products found matching your search.';
                $response['suggestions'] = array(
                    'Try different keywords',
                    'Check spelling',
                    'Use more general terms',
                    'Browse all products'
                );
                break;
        }
        
        // Log the error with context for debugging
        error_log("Customer search failed: query '{$query}', error: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
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
    error_log("Customer search exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while searching products. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>