<?php

/**
 * Fetch Product Display Action
 * Handles AJAX product retrieval for customer-facing display with pagination support
 * Requirements: 1.1, 8.2
 */

header('Content-Type: application/json');

// No session required for customer-facing product display
// This is a public endpoint for browsing products

$response = array();

// Validate request method (GET for fetching)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. GET required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Extract and validate pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 && $_GET['limit'] <= 100 ? (int)$_GET['limit'] : 10;

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
    // Fetch products for customer display with pagination
    $result = get_all_products_ctr($page, $limit);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['data'] = $result['data'];
        $response['message'] = 'Products retrieved successfully';
        
        // Log successful product fetch for monitoring (optional)
        error_log("Customer product fetch successful: {$result['data']['pagination']['total_items']} total products, page {$page}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'fetch_failed';
        
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
                $response['suggestion'] = 'Please refresh the page and try again.';
                break;
        }
        
        // Log the error with context for debugging
        error_log("Customer product fetch failed: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
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
    error_log("Customer product fetch exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while retrieving products. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>