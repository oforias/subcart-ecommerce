<?php

/**
 * Cart Integrity Action
 * Handles cart data integrity checks and fixes for orphaned data scenarios
 * Requirements: 8.3, 8.4, 4.4
 */

header('Content-Type: application/json');

session_start();

// Include required classes
require_once '../classes/cart_integrity_class.php';
require_once '../classes/cart_validation_class.php';

$response = array();

// Validate request method (should be POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. POST required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Get and validate operation type
$operation = $_POST['operation'] ?? null;
$valid_operations = ['check', 'fix', 'find_orphaned', 'remove_orphaned'];

if (!in_array($operation, $valid_operations)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid operation. Valid operations: ' . implode(', ', $valid_operations);
    $response['error_type'] = 'validation_error';
    $response['error_details'] = ['operation' => $operation, 'valid_operations' => $valid_operations];
    echo json_encode($response);
    exit();
}

// Validate user identification (optional for integrity operations)
$customer_id = null;
$ip_address = null;

if (isset($_POST['customer_id']) && !empty($_POST['customer_id'])) {
    $customer_validation = CartValidation::validate_customer_id($_POST['customer_id']);
    if (!$customer_validation['success']) {
        $response['status'] = 'error';
        $response['message'] = $customer_validation['error_message'];
        $response['error_type'] = $customer_validation['error_type'];
        $response['error_details'] = $customer_validation['error_details'];
        echo json_encode($response);
        exit();
    }
    $customer_id = $customer_validation['sanitized_value'];
}

if (isset($_POST['ip_address']) && !empty($_POST['ip_address'])) {
    $ip_validation = CartValidation::validate_ip_address($_POST['ip_address']);
    if (!$ip_validation['success']) {
        $response['status'] = 'error';
        $response['message'] = $ip_validation['error_message'];
        $response['error_type'] = $ip_validation['error_type'];
        $response['error_details'] = $ip_validation['error_details'];
        echo json_encode($response);
        exit();
    }
    $ip_address = $ip_validation['sanitized_value'];
}

// If no specific user provided, use current session/IP
if ($customer_id === null && $ip_address === null) {
    if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
        $customer_id = (int)$_SESSION['customer_id'];
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

try {
    // Create cart integrity instance
    $cart_integrity = new CartIntegrity();
    
    // Execute requested operation
    switch ($operation) {
        case 'check':
            $result = $cart_integrity->verify_cart_integrity($customer_id, $ip_address);
            break;
            
        case 'fix':
            // Get fix options
            $fix_options = [
                'remove_orphaned' => ($_POST['remove_orphaned'] ?? 'true') === 'true',
                'fix_quantities' => ($_POST['fix_quantities'] ?? 'true') === 'true',
                'merge_duplicates' => ($_POST['merge_duplicates'] ?? 'true') === 'true'
            ];
            
            $result = $cart_integrity->fix_cart_integrity_issues($customer_id, $ip_address, $fix_options);
            break;
            
        case 'find_orphaned':
            $result = $cart_integrity->find_orphaned_cart_items($customer_id, $ip_address);
            break;
            
        case 'remove_orphaned':
            $result = $cart_integrity->remove_orphaned_cart_items($customer_id, $ip_address);
            break;
            
        default:
            throw new Exception("Unsupported operation: {$operation}");
    }
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['operation'] = $operation;
        $response['data'] = $result['data'];
        
        // Add operation-specific messages
        switch ($operation) {
            case 'check':
                $integrity_status = $result['data']['integrity_status'];
                $response['message'] = $integrity_status === 'healthy' ? 
                    'Cart integrity check passed - no issues found' : 
                    'Cart integrity issues detected';
                break;
                
            case 'fix':
                $total_fixes = $result['data']['total_fixes'];
                $total_errors = $result['data']['total_errors'];
                $response['message'] = "Applied {$total_fixes} fixes with {$total_errors} errors";
                break;
                
            case 'find_orphaned':
                $count = $result['data']['count'];
                $response['message'] = "Found {$count} orphaned cart items";
                break;
                
            case 'remove_orphaned':
                $removed_count = $result['data']['removed_count'];
                $response['message'] = "Removed {$removed_count} orphaned cart items";
                break;
        }
        
        // Log successful operation
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Cart integrity operation '{$operation}' completed successfully for {$user_identifier}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error_message'];
        $response['error_type'] = $result['error_type'];
        $response['error_details'] = $result['error_details'] ?? null;
        
        // Log the error
        $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
        error_log("Cart integrity operation '{$operation}' failed for {$user_identifier}: {$result['error_message']}");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling
    $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
    $exception_context = [
        'user_identifier' => $user_identifier,
        'operation' => $operation,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception
    error_log("Cart integrity operation exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred during cart integrity operation. Please try again.';
    $response['error_type'] = 'server_exception';
    $response['error_details'] = [
        'operation' => $operation,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode()
    ];
}

echo json_encode($response);

?>