<?php

/**
 * Empty Cart Action
 * Handles clearing entire cart for current user/session
 * Supports both customer ID and IP address scenarios
 * Requirements: 2.4
 */

// Start output buffering to catch any unexpected output
ob_start();

// Suppress error display to prevent JSON corruption
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();

// Clean any output that might have occurred
ob_clean();

header('Content-Type: application/json');

$response = array();

// Validate request method (should be POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. POST required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Determine user identification (logged-in customer or guest)
$customer_id = null;
$ip_address = null;

// Check if user is logged in
if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
    $customer_id = (int)$_SESSION['customer_id'];
} else {
    // Guest user - use IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (empty($ip_address)) {
        $response['status'] = 'error';
        $response['message'] = 'Unable to identify user for cart association.';
        $response['error_type'] = 'user_identification_failed';
        echo json_encode($response);
        exit();
    }
}

require_once __DIR__ . '/../controllers/cart_controller.php';

try {
    // Get current cart count before emptying for logging purposes
    $cart_before = get_cart_count_ctr($customer_id, $ip_address);
    $items_before = $cart_before['success'] ? $cart_before['data']['count'] : 0;
    
    // Call controller function to empty cart with enhanced error handling
    $result = empty_cart_ctr($customer_id, $ip_address);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = $result['data']['message'];
        $response['data'] = array(
            'action' => $result['data']['action'],
            'affected_rows' => $result['data']['affected_rows'],
            'customer_id' => $result['data']['customer_id'],
            'ip_address' => $result['data']['ip_address'],
            'items_removed' => $items_before
        );
        
        // Set cart totals to zero after emptying
        $response['cart_totals'] = array(
            'total_items' => 0,
            'total_amount' => 0,
            'item_count' => 0
        );
        
        // Log successful cart emptying for audit trail
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Cart emptied successfully: Affected Rows: {$result['data']['affected_rows']}, Items Removed: {$items_before}, User: {$user_identifier}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'empty_cart_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
            case 'validation_error':
                $response['suggestion'] = 'Please check your session and try again.';
                break;
                
            case 'connection_error':
            case 'connection_exception':
                $response['suggestion'] = 'Please check your internet connection and try again.';
                $response['retry_recommended'] = true;
                break;
                
            case 'database_error':
                $response['suggestion'] = 'Please try again. If the problem persists, contact support.';
                $response['retry_recommended'] = true;
                break;
                
            default:
                $response['suggestion'] = 'Please try again. If the problem persists, contact support.';
                $response['retry_recommended'] = true;
                break;
        }
        
        // Log the error with context for debugging
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Empty cart failed for user {$user_identifier}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
    $exception_context = [
        'user_identifier' => $user_identifier,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Empty cart exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while emptying cart. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>