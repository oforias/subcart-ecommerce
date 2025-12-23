<?php

/**
 * Remove from Cart Action
 * Handles removing individual cart items completely
 * Recalculates cart totals after removal
 * Requirements: 2.3
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

// Validate required input parameters
if (!isset($_POST['product_id']) || empty(trim($_POST['product_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

// Extract and sanitize input data
$product_id = trim($_POST['product_id']);

// Validate product ID is numeric
if (!is_numeric($product_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID must be a valid number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

// Convert to integer and validate it's positive
$product_id = (int)$product_id;
if ($product_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID must be a positive number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
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
    // Call controller function to remove product from cart with enhanced error handling
    $result = remove_from_cart_ctr($product_id, $customer_id, $ip_address);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = $result['data']['message'];
        $response['data'] = array(
            'product_id' => $result['data']['product_id'],
            'action' => $result['data']['action'],
            'affected_rows' => $result['data']['affected_rows'],
            'customer_id' => $result['data']['customer_id'],
            'ip_address' => $result['data']['ip_address']
        );
        
        // Get updated cart totals after removal
        $cart_result = get_cart_items_ctr($customer_id, $ip_address);
        if ($cart_result['success']) {
            $response['cart_totals'] = array(
                'total_items' => $cart_result['data']['total_items'],
                'total_amount' => $cart_result['data']['total_amount'],
                'item_count' => $cart_result['data']['count']
            );
        }
        
        // Log successful cart removal for audit trail
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Product removed from cart successfully: Product ID {$product_id}, Affected Rows: {$result['data']['affected_rows']}, User: {$user_identifier}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'remove_from_cart_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
            case 'validation_error':
                $response['suggestion'] = 'Please check your input and try again.';
                break;
                
            case 'not_found':
                $response['suggestion'] = 'This item may have been removed from your cart already. Please refresh the page.';
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
        error_log("Remove from cart failed for user {$user_identifier}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
    $exception_context = [
        'user_identifier' => $user_identifier,
        'product_id' => $product_id,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Remove from cart exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while removing from cart. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>