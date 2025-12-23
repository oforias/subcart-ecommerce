<?php

/**
 * Update Cart Quantity Action
 * Handles cart item quantity modifications with comprehensive validation and error handling
 * Updates cart totals and returns updated cart state
 * Requirements: 2.2, 8.1
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
    'quantity' => $_POST['quantity'] ?? null,
    'customer_id' => $_SESSION['customer_id'] ?? null,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
];

// Validate product_id
$product_validation = CartValidation::validate_product_id($input_data['product_id']);
if (!$product_validation['success']) {
    $response['status'] = 'error';
    $response['message'] = $product_validation['error_message'];
    $response['error_type'] = $product_validation['error_type'];
    $response['error_details'] = $product_validation['error_details'];
    
    error_log("Update quantity product validation failed: " . json_encode($product_validation['error_details']));
    echo json_encode($response);
    exit();
}

// Validate quantity (allow zero for removal)
$quantity_validation = CartValidation::validate_quantity($input_data['quantity'], ['allow_zero' => true]);
if (!$quantity_validation['success']) {
    $response['status'] = 'error';
    $response['message'] = $quantity_validation['error_message'];
    $response['error_type'] = $quantity_validation['error_type'];
    $response['error_details'] = $quantity_validation['error_details'];
    
    error_log("Update quantity validation failed: " . json_encode($quantity_validation['error_details']));
    echo json_encode($response);
    exit();
}

// Validate user identification
$user_validation = CartValidation::validate_user_identification(
    $input_data['customer_id'],
    $input_data['ip_address']
);
if (!$user_validation['success']) {
    $response['status'] = 'error';
    $response['message'] = $user_validation['error_message'];
    $response['error_type'] = $user_validation['error_type'];
    $response['error_details'] = $user_validation['error_details'];
    
    error_log("Update quantity user validation failed: " . json_encode($user_validation['error_details']));
    echo json_encode($response);
    exit();
}

// Extract sanitized values
$product_id = $product_validation['sanitized_value'];
$quantity = $quantity_validation['sanitized_value'];
$customer_id = $user_validation['sanitized_values']['customer_id'];
$ip_address = $user_validation['sanitized_values']['ip_address'];

require_once __DIR__ . '/../controllers/cart_controller.php';

try {
    // Call controller function to update cart quantity with enhanced error handling
    $result = update_cart_quantity_ctr($product_id, $quantity, $customer_id, $ip_address);
    
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
        
        // Get updated cart totals after quantity change
        $cart_result = get_cart_items_ctr($customer_id, $ip_address);
        if ($cart_result['success']) {
            $response['cart_totals'] = array(
                'total_items' => $cart_result['data']['total_items'],
                'total_amount' => $cart_result['data']['total_amount'],
                'item_count' => $cart_result['data']['count']
            );
        }
        
        // Log successful cart update for audit trail
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Cart quantity updated successfully: Product ID {$product_id}, New Quantity {$quantity}, Action: {$result['data']['action']}, User: {$user_identifier}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'update_quantity_failed';
        
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
                
            default:
                $response['suggestion'] = 'Please try again. If the problem persists, contact support.';
                $response['retry_recommended'] = true;
                break;
        }
        
        // Log the error with context for debugging
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Cart quantity update failed for user {$user_identifier}: {$result['error']} (Type: {$result['error_type']})");
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
    error_log("Update cart quantity exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while updating cart quantity. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['suggestion'] = 'If the problem persists, please contact support.';
    $response['retry_recommended'] = true;
}

echo json_encode($response);

?>