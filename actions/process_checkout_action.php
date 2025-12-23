<?php
/**
 * Process Checkout Action
 * Handles complete checkout workflow with comprehensive input validation and error handling
 * Requirements: 3.2, 3.3, 3.4, 3.5, 8.1
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start session for user identification
session_start();

// Include required files
require_once __DIR__ . '/../controllers/order_controller.php';
require_once __DIR__ . '/../controllers/cart_controller.php';
require_once __DIR__ . '/../classes/cart_validation_class.php';
require_once __DIR__ . '/../settings/core.php';

// Initialize response array
$response = array(
    'success' => false,
    'error' => '',
    'data' => null
);

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['error'] = 'Invalid request method. POST required.';
        $response['error_type'] = 'method_not_allowed';
        echo json_encode($response);
        exit();
    }

    // Prepare input data for validation
    $input_data = [
        'customer_id' => $_SESSION['customer_id'] ?? null,
        'total_amount' => $_POST['total_amount'] ?? null,
        'currency' => $_POST['currency'] ?? 'USD',
        'payment_method' => $_POST['payment_method'] ?? null
    ];

    // Comprehensive input validation for checkout
    $validation_result = CartValidation::validate_checkout_input($input_data);

    if (!$validation_result['success']) {
        $response['error'] = $validation_result['error_message'];
        $response['error_type'] = $validation_result['error_type'];
        $response['error_details'] = $validation_result['error_details'];
        
        // Log validation failure for debugging
        error_log("Checkout validation failed: " . json_encode($validation_result['error_details']));
        
        echo json_encode($response);
        exit();
    }

    // Extract sanitized values
    $sanitized = $validation_result['sanitized_values'];
    $customer_id = $sanitized['customer_id'];
    $total_amount = $sanitized['total_amount'];
    $currency = $sanitized['currency'];
    $payment_method = $sanitized['payment_method'];

    // Log checkout attempt for debugging
    error_log("Checkout attempt - Customer: {$customer_id}, Amount: {$total_amount}, Payment: {$payment_method}");

    // Simulate payment processing based on payment method
    $payment_success = false;
    $payment_error = '';
    $payment_processing_time = 0;

    switch ($payment_method) {
        case 'simulated_success':
            // Simulate successful payment processing
            $payment_success = true;
            $payment_processing_time = rand(1, 3); // Simulate 1-3 second processing time
            error_log("Simulating successful payment processing for customer {$customer_id}");
            break;
            
        case 'simulated_failure':
            // Simulate payment failure scenarios
            $failure_reasons = [
                'Payment declined by bank. Please check your card details.',
                'Insufficient funds. Please try a different payment method.',
                'Card expired. Please use a valid payment method.',
                'Payment processor temporarily unavailable. Please try again later.',
                'Transaction limit exceeded. Please contact your bank.'
            ];
            $payment_success = false;
            $payment_error = $failure_reasons[array_rand($failure_reasons)];
            $payment_processing_time = rand(2, 4); // Simulate processing time even for failures
            error_log("Simulating payment failure for customer {$customer_id}: {$payment_error}");
            break;
            
        case 'simulated_timeout':
            // Simulate payment timeout scenario
            $payment_success = false;
            $payment_error = 'Payment processing timed out. Please try again.';
            $payment_processing_time = 10; // Simulate longer processing time
            error_log("Simulating payment timeout for customer {$customer_id}");
            break;
            
        default:
            // Default to success for demo purposes, but log unknown payment method
            $payment_success = true;
            error_log("Unknown payment method '{$payment_method}' for customer {$customer_id}, defaulting to success");
            break;
    }

    // Add realistic processing delay for demonstration
    if ($payment_processing_time > 0) {
        // In a real application, this would be actual payment processing time
        // For demo purposes, we'll just log the simulated processing time
        error_log("Simulated payment processing time: {$payment_processing_time} seconds");
    }

    // Handle payment failure
    if (!$payment_success) {
        $response['error'] = $payment_error ?: 'Payment processing failed. Please try again.';
        $response['error_type'] = 'payment_failed';
        $response['error_details'] = [
            'payment_method' => $payment_method,
            'customer_id' => $customer_id
        ];
        echo json_encode($response);
        exit();
    }

    // Payment successful, proceed with order creation
    $order_status = 'confirmed'; // Set to confirmed since payment succeeded
    
    // Create order from cart
    $order_result = create_order_from_cart_ctr(
        $customer_id, 
        $total_amount, 
        $currency, 
        $order_status, 
        $payment_method
    );

    if ($order_result['success']) {
        // Order created successfully
        $order_data = $order_result['data'];
        
        // Log successful order creation
        error_log("Order created successfully - Order ID: {$order_data['order_id']}, Invoice: {$order_data['invoice_no']}, Customer: {$customer_id}");
        
        // Prepare success response
        $response['success'] = true;
        $response['data'] = array(
            'order_id' => $order_data['order_id'],
            'customer_id' => $order_data['customer_id'],
            'invoice_no' => $order_data['invoice_no'],
            'order_status' => $order_data['order_status'],
            'total_amount' => $order_data['total_amount'],
            'currency' => $order_data['currency'],
            'order_date' => $order_data['order_date'],
            'payment_id' => $order_data['payment_id'],
            'items_count' => $order_data['items_count'],
            'payment_method' => $order_data['payment_method'],
            'cart_emptied' => $order_data['cart_emptied'],
            'message' => 'Order placed successfully! Thank you for your purchase.',
            'confirmation_message' => "Your order #{$order_data['order_id']} has been confirmed with invoice number {$order_data['invoice_no']}."
        );
        
        // Include order details if available
        if (isset($order_data['order_details']) && !empty($order_data['order_details'])) {
            $response['data']['order_details'] = $order_data['order_details'];
        }
        
    } else {
        // Order creation failed
        error_log("Order creation failed for customer {$customer_id}: " . $order_result['error']);
        
        $response['error'] = $order_result['error'] ?: 'Failed to create order. Please try again.';
        $response['error_type'] = $order_result['error_type'] ?: 'order_creation_failed';
        $response['error_details'] = $order_result['error_details'] ?? [
            'customer_id' => $customer_id,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method
        ];
        $response['original_error'] = $order_result['original_error'] ?? null;
    }

} catch (Exception $e) {
    // Handle unexpected exceptions
    error_log("Checkout exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    $response['error'] = 'An unexpected error occurred during checkout. Please try again.';
    $response['error_type'] = 'checkout_exception';
    $response['error_details'] = [
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

// Log the response for debugging (excluding sensitive data)
$log_response = $response;
if (isset($log_response['data']['payment_id'])) {
    $log_response['data']['payment_id'] = '[REDACTED]';
}
error_log("Checkout response: " . json_encode($log_response));

?>