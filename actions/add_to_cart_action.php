<?php

/**
 * Add to Cart Action
 * Handles adding products to cart with comprehensive input validation and error handling
 * Supports both logged-in and guest user scenarios
 * Requirements: 1.1, 1.2, 1.5, 4.1, 8.1
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

// Include validation class
require_once __DIR__ . '/../classes/cart_validation_class.php';

$response = array();

// Validate request method (should be POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. POST required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Prepare input data for validation
$input_data = [
    'product_id' => $_POST['product_id'] ?? null,
    'quantity' => $_POST['quantity'] ?? 1,
    'customer_id' => $_SESSION['customer_id'] ?? null,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
];

// Comprehensive input validation
$validation_result = CartValidation::validate_add_to_cart_input($input_data);

if (!$validation_result['success']) {
    $response['status'] = 'error';
    $response['message'] = $validation_result['error_message'];
    $response['error_type'] = $validation_result['error_type'];
    $response['error_details'] = $validation_result['error_details'];
    
    // Log validation failure for debugging
    error_log("Add to cart validation failed: " . json_encode($validation_result['error_details']));
    
    echo json_encode($response);
    exit();
}

// Extract sanitized values
$sanitized = $validation_result['sanitized_values'];
$product_id = $sanitized['product_id'];
$quantity = $sanitized['quantity'];
$customer_id = $sanitized['customer_id'];
$ip_address = $sanitized['ip_address'];

require_once __DIR__ . '/../controllers/cart_controller.php';

try {
    // Call controller function to add product to cart with enhanced error handling
    $result = add_to_cart_ctr($product_id, $quantity, $customer_id, $ip_address);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = $result['data']['message'];
        $response['data'] = array(
            'product_id' => $result['data']['product_id'],
            'quantity' => $result['data']['quantity'],
            'action' => $result['data']['action'],
            'customer_id' => $result['data']['customer_id'],
            'ip_address' => $result['data']['ip_address']
        );
        
        // Log successful cart addition for audit trail
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Product added to cart successfully: Product ID {$product_id}, Quantity {$quantity}, Action: {$result['data']['action']}, User: {$user_identifier}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'add_to_cart_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
            case 'validation_error':
                $response['suggestion'] = 'Please check your input and try again.';
                break;
                
            case 'foreign_key_constraint':
                $response['suggestion'] = 'The selected product may no longer be available. Please refresh the page.';
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
                
            case 'duplicate_entry':
                $response['suggestion'] = 'This product is already in your cart. The quantity has been updated.';
                break;
                
            default:
                $response['suggestion'] = 'Please try again. If the problem persists, contact support.';
                $response['retry_recommended'] = true;
                break;
        }
        
        // Log the error with context for debugging
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Add to cart failed for user {$user_identifier}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
    $exception_context = [
        'user_identifier' => $user_identifier,
        'product_id' => $product_id,
        'quantity' => $quantity,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Add to cart exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while adding to cart. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>