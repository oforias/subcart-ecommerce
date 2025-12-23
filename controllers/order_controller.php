<?php

require_once __DIR__ . '/../classes/order_class.php';
require_once __DIR__ . '/../classes/cart_class.php';

/**
 * Order Controller
 * Handles order management operations with business logic coordination
 * Requirements: 3.3, 3.4, 6.1, 6.2, 6.3, 6.4, 6.5
 */

/**
 * Create a new order from cart items with comprehensive error handling
 * Requirements: 3.3, 6.1, 6.2, 6.3, 6.4, 6.5
 * 
 * @param int $customer_id Customer ID
 * @param float $total_amount Total order amount
 * @param string $currency Currency code (default 'USD')
 * @param string $order_status Order status (default 'pending')
 * @param string $payment_method Payment method used
 * @return array Response array with success status and order data/error message
 */
function create_order_from_cart_ctr($customer_id, $total_amount, $currency = 'USD', $order_status = 'pending', $payment_method = 'simulated')
{
    // Input validation with detailed error responses
    if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid customer ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'customer_id', 'value' => $customer_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    if (!is_numeric($total_amount) || $total_amount <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid total amount is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'total_amount', 'value' => $total_amount, 'issue' => 'empty_or_invalid']
        );
    }
    
    if (empty($currency) || !is_string($currency)) {
        $currency = 'USD'; // Default currency
    }
    
    if (empty($order_status) || !is_string($order_status)) {
        $order_status = 'pending'; // Default status
    }
    
    try {
        // Get cart items for the customer
        $cart = new Cart();
        $cart_result = $cart->get_cart_items($customer_id, null);
        
        if (!$cart_result['success']) {
            return array(
                'success' => false,
                'error' => 'Failed to retrieve cart items: ' . $cart_result['error_message'],
                'error_type' => 'cart_retrieval_error',
                'error_details' => $cart_result['error_details'] ?? null
            );
        }
        
        $cart_items = $cart_result['data']['items'];
        
        if (empty($cart_items)) {
            return array(
                'success' => false,
                'error' => 'Cart is empty. Cannot create order.',
                'error_type' => 'empty_cart_error',
                'error_details' => ['customer_id' => $customer_id]
            );
        }
        
        // Calculate final total with tax and shipping (matching frontend logic)
        $cart_total = $cart_result['data']['total_amount'];
        $tax_rate = 0.08; // 8% tax rate (matching checkout.php)
        $tax_amount = $cart_total * $tax_rate;
        $shipping_cost = $cart_total >= 50 ? 0 : 5.99; // Free shipping over $50 (matching checkout.php)
        $calculated_final_total = $cart_total + $tax_amount + $shipping_cost;
        
        // Validate final total matches provided total (with small tolerance for rounding)
        $tolerance = 0.01; // 1 cent tolerance
        
        if (abs($calculated_final_total - $total_amount) > $tolerance) {
            return array(
                'success' => false,
                'error' => 'Cart total mismatch. Please refresh and try again.',
                'error_type' => 'total_mismatch_error',
                'error_details' => [
                    'cart_subtotal' => $cart_total,
                    'tax_amount' => $tax_amount,
                    'shipping_cost' => $shipping_cost,
                    'calculated_final_total' => $calculated_final_total,
                    'provided_total' => $total_amount,
                    'difference' => abs($calculated_final_total - $total_amount)
                ]
            );
        }
        
        // Create instance of order class
        $order = new Order();
        
        // Create order with cart items
        $order_result = $order->create_order($customer_id, $cart_items, $total_amount, $currency, $order_status);
        
        if ($order_result['success']) {
            // Order created successfully, now empty the cart
            $empty_cart_result = $cart->empty_cart($customer_id, null);
            
            if (!$empty_cart_result['success']) {
                // Log warning but don't fail the order - cart can be manually cleared
                error_log("Warning: Failed to empty cart after order creation for customer {$customer_id}: " . $empty_cart_result['error_message']);
            }
            
            return array(
                'success' => true,
                'data' => array(
                    'order_id' => $order_result['data']['order_id'],
                    'customer_id' => $order_result['data']['customer_id'],
                    'invoice_no' => $order_result['data']['invoice_no'],
                    'order_status' => $order_result['data']['order_status'],
                    'total_amount' => $order_result['data']['total_amount'],
                    'currency' => $order_result['data']['currency'],
                    'order_date' => $order_result['data']['order_date'],
                    'payment_id' => $order_result['data']['payment_id'],
                    'items_count' => $order_result['data']['items_count'],
                    'order_details' => $order_result['data']['order_details'],
                    'payment_method' => $payment_method,
                    'cart_emptied' => $empty_cart_result['success'],
                    'message' => 'Order created successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_order_error($order_result['error_type'], $order_result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $order_result['error_type'],
                'error_details' => $order_result['error_details'] ?? null,
                'original_error' => $order_result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Order creation exception for customer {$customer_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while creating order. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get order details by order ID with comprehensive error handling
 * Requirements: 6.1, 6.2, 6.3
 * 
 * @param int $order_id Order ID
 * @return array Response array with success status and order data/error message
 */
function get_order_by_id_ctr($order_id)
{
    // Input validation with detailed error responses
    if (empty($order_id) || !is_numeric($order_id) || $order_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid order ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'order_id', 'value' => $order_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    try {
        // Create instance of order class
        $order = new Order();
        
        // Get order details
        $result = $order->get_order_by_id($order_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'order' => $result['data']['order'],
                    'order_details' => $result['data']['order_details'],
                    'items_count' => $result['data']['items_count'],
                    'total_items' => $result['data']['total_items']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_order_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Order retrieval exception for order {$order_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving order. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get customer orders with comprehensive error handling
 * Requirements: 6.1, 6.3
 * 
 * @param int $customer_id Customer ID
 * @param int $limit Number of orders to retrieve (default 10)
 * @param int $offset Offset for pagination (default 0)
 * @return array Response array with success status and orders data/error message
 */
function get_customer_orders_ctr($customer_id, $limit = 10, $offset = 0)
{
    // Input validation with detailed error responses
    if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid customer ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'customer_id', 'value' => $customer_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    if (!is_numeric($limit) || $limit <= 0 || $limit > 100) {
        $limit = 10; // Default limit with maximum cap
    }
    
    if (!is_numeric($offset) || $offset < 0) {
        $offset = 0; // Default offset
    }
    
    try {
        // Create instance of order class
        $order = new Order();
        
        // Get customer orders
        $result = $order->get_customer_orders($customer_id, $limit, $offset);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'customer_id' => $result['data']['customer_id'],
                    'orders' => $result['data']['orders'],
                    'count' => $result['data']['count'],
                    'limit' => $result['data']['limit'],
                    'offset' => $result['data']['offset']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_order_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Customer orders retrieval exception for customer {$customer_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving orders. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Update order status with comprehensive error handling
 * Requirements: 6.1
 * 
 * @param int $order_id Order ID
 * @param string $new_status New order status
 * @return array Response array with success status and update data/error message
 */
function update_order_status_ctr($order_id, $new_status)
{
    // Input validation with detailed error responses
    if (empty($order_id) || !is_numeric($order_id) || $order_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid order ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'order_id', 'value' => $order_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    if (empty($new_status) || !is_string($new_status)) {
        return array(
            'success' => false,
            'error' => 'Valid order status is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'new_status', 'value' => $new_status, 'issue' => 'empty_or_invalid']
        );
    }
    
    try {
        // Create instance of order class
        $order = new Order();
        
        // Update order status
        $result = $order->update_order_status($order_id, $new_status);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'order_id' => $result['data']['order_id'],
                    'new_status' => $result['data']['new_status'],
                    'affected_rows' => $result['data']['affected_rows'],
                    'message' => 'Order status updated successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_order_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Order status update exception for order {$order_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while updating order status. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get order statistics with comprehensive error handling
 * Requirements: 6.1, 6.3
 * 
 * @param string $start_date Start date for statistics (Y-m-d format)
 * @param string $end_date End date for statistics (Y-m-d format)
 * @return array Response array with success status and statistics data/error message
 */
function get_order_statistics_ctr($start_date = null, $end_date = null)
{
    try {
        // Create instance of order class
        $order = new Order();
        
        // Get order statistics
        $result = $order->get_order_statistics($start_date, $end_date);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'start_date' => $result['data']['start_date'],
                    'end_date' => $result['data']['end_date'],
                    'total_orders' => $result['data']['total_orders'],
                    'unique_customers' => $result['data']['unique_customers'],
                    'total_revenue' => $result['data']['total_revenue'],
                    'avg_order_value' => $result['data']['avg_order_value'],
                    'pending_orders' => $result['data']['pending_orders'],
                    'confirmed_orders' => $result['data']['confirmed_orders'],
                    'cancelled_orders' => $result['data']['cancelled_orders'],
                    'timestamp' => $result['data']['timestamp']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_order_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Order statistics exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving order statistics. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Generate unique invoice number with comprehensive error handling
 * Requirements: 3.2
 * 
 * @return array Response array with success status and invoice number/error message
 */
function generate_unique_invoice_number_ctr()
{
    try {
        // Create instance of order class
        $order = new Order();
        
        // Generate unique invoice number
        $result = $order->generate_unique_invoice_number();
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'invoice_no' => $result['data']['invoice_no'],
                    'attempts' => $result['data']['attempts']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_order_error($result['error_type'], $result['error_message']);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'error_type' => $result['error_type'],
                'error_details' => $result['error_details'] ?? null,
                'original_error' => $result['error_message']
            );
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Invoice number generation exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while generating invoice number. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get user-friendly error messages for order-related database errors
 * @param string $error_type The type of database error
 * @param string $original_message The original error message
 * @return string User-friendly error message
 */
function get_user_friendly_order_error($error_type, $original_message)
{
    switch ($error_type) {
        case 'connection_error':
        case 'connection_exception':
            return 'Database connection error. Please try again later.';
            
        case 'duplicate_entry':
            return 'Duplicate order detected. Please refresh the page.';
            
        case 'foreign_key_constraint':
            return 'Order data integrity error. Please contact administrator.';
            
        case 'table_not_found':
        case 'column_not_found':
            return 'Database schema error. Please contact administrator.';
            
        case 'connection_lost':
            return 'Database connection lost. Please refresh the page and try again.';
            
        case 'lock_timeout':
        case 'deadlock':
            return 'Order system is busy. Please try again in a moment.';
            
        case 'too_many_connections':
            return 'Server is busy. Please try again later.';
            
        case 'access_denied':
            return 'Database access error. Please contact administrator.';
            
        case 'not_found':
            return 'Order not found. It may have been removed or does not exist.';
            
        case 'validation_error':
            return 'Invalid order data provided.';
            
        case 'generation_failed':
            return 'Failed to generate unique order reference. Please try again.';
            
        case 'cart_retrieval_error':
            return 'Failed to retrieve cart items for order creation.';
            
        case 'empty_cart_error':
            return 'Cannot create order from empty cart.';
            
        case 'total_mismatch_error':
            return 'Order total mismatch detected. The cart total has changed. Please refresh your cart and try again.';
            
        default:
            return 'An order processing error occurred. Please try again or contact support if the problem persists.';
    }
}

?>