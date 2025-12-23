<?php

/**
 * Search Suggestions Action
 * 
 * Provides auto-complete suggestions for search queries.
 */

header('Content-Type: application/json');

// No session required for customer-facing search suggestions
// This is a public endpoint for getting search suggestions

$response = array();

// Validate request method (GET for fetching suggestions)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. GET required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Extract and validate parameters
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 && $_GET['limit'] <= 50 ? (int)$_GET['limit'] : 10;

// Validate query parameter
if (empty($query)) {
    // Return empty suggestions for empty query
    $response['status'] = 'success';
    $response['data'] = array(
        'suggestions' => [],
        'count' => 0,
        'query' => $query
    );
    $response['message'] = 'No query provided - empty suggestions returned';
    echo json_encode($response);
    exit();
}

// Validate query length (minimum 2 characters for suggestions)
if (strlen($query) < 2) {
    $response['status'] = 'success';
    $response['data'] = array(
        'suggestions' => [],
        'count' => 0,
        'query' => $query
    );
    $response['message'] = 'Query too short - minimum 2 characters required for suggestions';
    echo json_encode($response);
    exit();
}

if (strlen($query) > 100) {
    $response['status'] = 'error';
    $response['message'] = 'Query too long - maximum 100 characters allowed.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'query';
    echo json_encode($response);
    exit();
}

// Basic sanitization to prevent XSS
$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

// Validate limit parameter
if ($limit < 1 || $limit > 50) {
    $response['status'] = 'error';
    $response['message'] = 'Limit must be between 1 and 50.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'limit';
    echo json_encode($response);
    exit();
}

require_once '../controllers/product_display_controller.php';

try {
    // Get search suggestions
    $result = get_search_suggestions_ctr($query, $limit);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['data'] = $result['data'];
        $response['message'] = 'Search suggestions retrieved successfully';
        
        // Add suggestion metadata
        $response['suggestion_metadata'] = array(
            'query' => $query,
            'query_length' => strlen($query),
            'suggestions_count' => $result['data']['count'],
            'max_suggestions' => $limit,
            'response_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
        
        // Log successful suggestion retrieval for analytics (optional)
        if ($result['data']['count'] > 0) {
            error_log("Search suggestions successful: query '{$query}', {$result['data']['count']} suggestions");
        }
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'suggestions_failed';
        
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
                $response['suggestion'] = 'Please check your search query and try again.';
                break;
        }
        
        // For suggestions, we can still return empty results on error
        $response['data'] = array(
            'suggestions' => [],
            'count' => 0,
            'query' => $query
        );
        
        // Log the error with context for debugging
        error_log("Search suggestions failed: query '{$query}', error: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'query' => $query,
        'limit' => $limit,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Search suggestions exception: " . json_encode($exception_context));
    
    // For suggestions, return empty results on exception
    $response['status'] = 'success';
    $response['data'] = array(
        'suggestions' => [],
        'count' => 0,
        'query' => $query
    );
    $response['message'] = 'Suggestions temporarily unavailable';
    $response['error_info'] = 'An unexpected error occurred while getting suggestions';
}

echo json_encode($response);

?>