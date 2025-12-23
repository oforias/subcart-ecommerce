<?php

require_once __DIR__ . '/../classes/cart_class.php';

/**
 * Cart Controller
 * Handles cart management operations with business logic coordination
 * Requirements: 1.1, 1.2, 1.3, 2.1
 */

/**
 * Add a product to cart with comprehensive error handling and business logic
 * Enhanced with automatic session management
 * 
 * @param int $product_id Product ID
 * @param int $quantity Quantity to add (default 1)
 * @param int $customer_id Customer ID (optional, will use session if not provided)
 * @param string $ip_address IP address (optional, will use session if not provided)
 * @return array Response array with success status and data/error message
 */
function add_to_cart_ctr($product_id, $quantity = 1, $customer_id = null, $ip_address = null)
{
    // Input validation with detailed error responses
    if (empty($product_id) || !is_numeric($product_id) || $product_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid product ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_id', 'value' => $product_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    if (!is_numeric($quantity) || $quantity < 1 || $quantity > 999) {
        return array(
            'success' => false,
            'error' => 'Quantity must be between 1 and 999',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'quantity', 'value' => $quantity, 'min' => 1, 'max' => 999]
        );
    }
    
    // Get session information if customer_id and ip_address not provided
    if ($customer_id === null && $ip_address === null) {
        $session_info = get_cart_user_session_ctr();
        if ($session_info['success']) {
            $customer_id = $session_info['data']['customer_id'];
            $ip_address = $session_info['data']['ip_address'];
        } else {
            // Fallback to IP address from server
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
    
    // Ensure we have either customer_id or ip_address for cart association
    if (empty($customer_id) && empty($ip_address)) {
        return array(
            'success' => false,
            'error' => 'Unable to identify user for cart association',
            'error_type' => 'validation_error',
            'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
        );
    }
    
    // Validate customer_id if provided
    if ($customer_id !== null && (!is_numeric($customer_id) || $customer_id <= 0)) {
        return array(
            'success' => false,
            'error' => 'Customer ID must be a valid positive number',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'customer_id', 'value' => $customer_id]
        );
    }
    
    // Sanitize IP address if provided
    if ($ip_address !== null) {
        $ip_address = filter_var($ip_address, FILTER_VALIDATE_IP);
        if ($ip_address === false) {
            return array(
                'success' => false,
                'error' => 'Invalid IP address format',
                'error_type' => 'validation_error',
                'error_details' => ['field' => 'ip_address', 'value' => $ip_address]
            );
        }
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Validate cart item integrity before adding
        $integrity_check = $cart->validate_cart_item_integrity($product_id, $customer_id, $ip_address);
        
        if (!$integrity_check['success']) {
            // If product doesn't exist (orphaned), return appropriate error
            if ($integrity_check['error_type'] === 'orphaned_product') {
                return array(
                    'success' => false,
                    'error' => 'The selected product is no longer available.',
                    'error_type' => 'product_not_available',
                    'error_details' => $integrity_check['error_details']
                );
            } else {
                // Other integrity check failures
                return array(
                    'success' => false,
                    'error' => $integrity_check['error_message'],
                    'error_type' => $integrity_check['error_type'],
                    'error_details' => $integrity_check['error_details']
                );
            }
        }
        
        // Invoke cart class add_to_cart method with enhanced error handling
        $result = $cart->add_to_cart($product_id, $quantity, $customer_id, $ip_address);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'product_id' => $result['data']['product_id'],
                    'quantity' => $result['data']['quantity'],
                    'customer_id' => $result['data']['customer_id'],
                    'ip_address' => $result['data']['ip_address'],
                    'action' => $result['data']['action'],
                    'message' => 'Product added to cart successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart add exception for product {$product_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while adding to cart. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Update cart item quantity with comprehensive error handling
 * 
 * @param int $product_id Product ID
 * @param int $quantity New quantity
 * @param int $customer_id Customer ID (null for guest users)
 * @param string $ip_address IP address for guest users
 * @return array Response array with success status and data/error message
 */
function update_cart_quantity_ctr($product_id, $quantity, $customer_id = null, $ip_address = null)
{
    // Input validation with detailed error responses
    if (empty($product_id) || !is_numeric($product_id) || $product_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid product ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_id', 'value' => $product_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    if (!is_numeric($quantity) || $quantity < 0 || $quantity > 999) {
        return array(
            'success' => false,
            'error' => 'Quantity must be between 0 and 999',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'quantity', 'value' => $quantity, 'min' => 0, 'max' => 999]
        );
    }
    
    // Ensure we have either customer_id or ip_address for cart association
    if (empty($customer_id) && empty($ip_address)) {
        // Try to get IP address from server if not provided
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (empty($ip_address)) {
            return array(
                'success' => false,
                'error' => 'Unable to identify user for cart association',
                'error_type' => 'validation_error',
                'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
            );
        }
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Validate cart item integrity before updating
        $integrity_check = $cart->validate_cart_item_integrity($product_id, $customer_id, $ip_address);
        
        if (!$integrity_check['success']) {
            // If product doesn't exist (orphaned), return appropriate error
            if ($integrity_check['error_type'] === 'orphaned_product') {
                return array(
                    'success' => false,
                    'error' => 'The selected product is no longer available and has been removed from your cart.',
                    'error_type' => 'product_not_available',
                    'error_details' => $integrity_check['error_details']
                );
            } else {
                // Other integrity check failures
                return array(
                    'success' => false,
                    'error' => $integrity_check['error_message'],
                    'error_type' => $integrity_check['error_type'],
                    'error_details' => $integrity_check['error_details']
                );
            }
        }
        
        // Invoke cart class update_cart_quantity method with enhanced error handling
        $result = $cart->update_cart_quantity($product_id, $quantity, $customer_id, $ip_address);
        
        if ($result['success']) {
            $message = $quantity == 0 ? 'Product removed from cart successfully' : 'Cart quantity updated successfully';
            
            return array(
                'success' => true,
                'data' => array(
                    'product_id' => $result['data']['product_id'],
                    'quantity' => $result['data']['quantity'],
                    'customer_id' => $result['data']['customer_id'],
                    'ip_address' => $result['data']['ip_address'],
                    'action' => $result['data']['action'],
                    'affected_rows' => $result['data']['affected_rows'] ?? null,
                    'message' => $message
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart update exception for product {$product_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while updating cart. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Remove a product from cart with comprehensive error handling
 * 
 * @param int $product_id Product ID
 * @param int $customer_id Customer ID (null for guest users)
 * @param string $ip_address IP address for guest users
 * @return array Response array with success status and data/error message
 */
function remove_from_cart_ctr($product_id, $customer_id = null, $ip_address = null)
{
    // Input validation with detailed error responses
    if (empty($product_id) || !is_numeric($product_id) || $product_id <= 0) {
        return array(
            'success' => false,
            'error' => 'Valid product ID is required',
            'error_type' => 'validation_error',
            'error_details' => ['field' => 'product_id', 'value' => $product_id, 'issue' => 'empty_or_invalid']
        );
    }
    
    // Ensure we have either customer_id or ip_address for cart association
    if (empty($customer_id) && empty($ip_address)) {
        // Try to get IP address from server if not provided
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (empty($ip_address)) {
            return array(
                'success' => false,
                'error' => 'Unable to identify user for cart association',
                'error_type' => 'validation_error',
                'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
            );
        }
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Invoke cart class remove_from_cart method with enhanced error handling
        $result = $cart->remove_from_cart($product_id, $customer_id, $ip_address);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'product_id' => $result['data']['product_id'],
                    'customer_id' => $result['data']['customer_id'],
                    'ip_address' => $result['data']['ip_address'],
                    'action' => $result['data']['action'],
                    'affected_rows' => $result['data']['affected_rows'],
                    'message' => 'Product removed from cart successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart remove exception for product {$product_id}: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while removing from cart. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Empty entire cart with comprehensive error handling
 * 
 * @param int $customer_id Customer ID (null for guest users)
 * @param string $ip_address IP address for guest users
 * @return array Response array with success status and data/error message
 */
function empty_cart_ctr($customer_id = null, $ip_address = null)
{
    // Ensure we have either customer_id or ip_address for cart association
    if (empty($customer_id) && empty($ip_address)) {
        // Try to get IP address from server if not provided
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (empty($ip_address)) {
            return array(
                'success' => false,
                'error' => 'Unable to identify user for cart association',
                'error_type' => 'validation_error',
                'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
            );
        }
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Invoke cart class empty_cart method with enhanced error handling
        $result = $cart->empty_cart($customer_id, $ip_address);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'customer_id' => $result['data']['customer_id'],
                    'ip_address' => $result['data']['ip_address'],
                    'action' => $result['data']['action'],
                    'affected_rows' => $result['data']['affected_rows'],
                    'message' => 'Cart emptied successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart empty exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while emptying cart. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get all cart items with comprehensive error handling
 * Enhanced with automatic session management
 * 
 * @param int $customer_id Customer ID (optional, will use session if not provided)
 * @param string $ip_address IP address (optional, will use session if not provided)
 * @return array Response array with success status and cart items data
 */
function get_cart_items_ctr($customer_id = null, $ip_address = null)
{
    // Get session information if customer_id and ip_address not provided
    if ($customer_id === null && $ip_address === null) {
        $session_info = get_cart_user_session_ctr();
        if ($session_info['success']) {
            $customer_id = $session_info['data']['customer_id'];
            $ip_address = $session_info['data']['ip_address'];
        } else {
            // Fallback to IP address from server
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
    
    // Ensure we have either customer_id or ip_address for cart association
    if (empty($customer_id) && empty($ip_address)) {
        return array(
            'success' => false,
            'error' => 'Unable to identify user for cart association',
            'error_type' => 'validation_error',
            'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
        );
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Get valid cart items (automatically cleans orphaned items)
        $result = $cart->get_valid_cart_items($customer_id, $ip_address, true);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'items' => $result['data']['items'],
                    'count' => $result['data']['count'],
                    'total_items' => $result['data']['total_items'],
                    'total_amount' => $result['data']['total_amount'],
                    'customer_id' => $result['data']['customer_id'],
                    'ip_address' => $result['data']['ip_address']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart retrieval exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while retrieving cart items. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get cart item count for display purposes
 * 
 * @param int $customer_id Customer ID (null for guest users)
 * @param string $ip_address IP address for guest users
 * @return array Response array with success status and cart count data
 */
function get_cart_count_ctr($customer_id = null, $ip_address = null)
{
    try {
        // Get cart items first
        $cart_result = get_cart_items_ctr($customer_id, $ip_address);
        
        if ($cart_result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'count' => $cart_result['data']['count'],
                    'total_items' => $cart_result['data']['total_items'],
                    'total_amount' => $cart_result['data']['total_amount']
                )
            );
        } else {
            return $cart_result;
        }
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Cart count exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while getting cart count. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get current user session information for cart operations
 * Requirements: 4.1, 4.2
 * 
 * @return array Session information with customer_id and ip_address
 */
function get_cart_user_session_ctr()
{
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Get current session information
        $session_info = $cart->get_current_user_session();
        
        return array(
            'success' => true,
            'data' => $session_info
        );
        
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("Cart session retrieval exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while getting session information. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Restore cart for logged-in user from previous sessions
 * Requirements: 4.1, 4.2
 * 
 * @param int $customer_id Customer ID (optional, will use session if not provided)
 * @return array Response array with success status and restoration details
 */
function restore_user_cart_ctr($customer_id = null)
{
    try {
        // Get customer ID from session if not provided
        if ($customer_id === null) {
            $session_info = get_cart_user_session_ctr();
            if (!$session_info['success'] || !$session_info['data']['is_logged_in']) {
                return array(
                    'success' => false,
                    'error' => 'User must be logged in to restore cart',
                    'error_type' => 'authentication_required'
                );
            }
            $customer_id = $session_info['data']['customer_id'];
        }
        
        // Validate customer_id
        if (!is_numeric($customer_id) || $customer_id <= 0) {
            return array(
                'success' => false,
                'error' => 'Valid customer ID is required for cart restoration',
                'error_type' => 'validation_error',
                'error_details' => ['customer_id' => $customer_id]
            );
        }
        
        // Create instance of cart class
        $cart = new Cart();
        
        // Restore user cart
        $result = $cart->restore_user_cart($customer_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'customer_id' => $result['data']['customer_id'],
                    'items_restored' => $result['data']['items_restored'],
                    'total_items' => $result['data']['total_items'],
                    'total_amount' => $result['data']['total_amount'],
                    'cart_items' => $result['data']['cart_items'],
                    'message' => 'Cart restored successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart restoration exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while restoring cart. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Transfer guest cart to logged-in user account
 * Requirements: 4.1, 4.2
 * 
 * @param string $ip_address Guest IP address (optional, will use current IP if not provided)
 * @param int $customer_id Customer ID (optional, will use session if not provided)
 * @return array Response array with success status and transfer details
 */
function transfer_guest_cart_to_user_ctr($ip_address = null, $customer_id = null)
{
    try {
        // Get session information if customer_id not provided
        if ($customer_id === null) {
            $session_info = get_cart_user_session_ctr();
            if (!$session_info['success'] || !$session_info['data']['is_logged_in']) {
                return array(
                    'success' => false,
                    'error' => 'User must be logged in to transfer cart',
                    'error_type' => 'authentication_required'
                );
            }
            $customer_id = $session_info['data']['customer_id'];
            
            // Use IP from session if not provided
            if ($ip_address === null) {
                $ip_address = $session_info['data']['ip_address'];
            }
        }
        
        // Get IP address from server if still not provided
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        
        // Validate inputs
        if (empty($ip_address)) {
            return array(
                'success' => false,
                'error' => 'IP address is required for cart transfer',
                'error_type' => 'validation_error',
                'error_details' => ['ip_address' => $ip_address]
            );
        }
        
        if (!is_numeric($customer_id) || $customer_id <= 0) {
            return array(
                'success' => false,
                'error' => 'Valid customer ID is required for cart transfer',
                'error_type' => 'validation_error',
                'error_details' => ['customer_id' => $customer_id]
            );
        }
        
        // Create instance of cart class
        $cart = new Cart();
        
        // Transfer guest cart to user
        $result = $cart->transfer_guest_cart_to_user($ip_address, $customer_id);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'customer_id' => $result['data']['customer_id'],
                    'ip_address' => $result['data']['ip_address'],
                    'transferred_items' => $result['data']['transferred_items'],
                    'merged_items' => $result['data']['merged_items'],
                    'total_processed' => $result['data']['total_processed'],
                    'errors' => $result['data']['errors'],
                    'message' => 'Guest cart transferred successfully'
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Cart transfer exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while transferring cart. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Clean up expired guest cart sessions
 * Requirements: 1.5, 4.3
 * 
 * @param int $expiry_hours Hours after which guest carts expire (default 24)
 * @return array Response array with success status and cleanup details
 */
function cleanup_expired_guest_carts_ctr($expiry_hours = 24)
{
    // Validate expiry hours
    if (!is_numeric($expiry_hours) || $expiry_hours < 1 || $expiry_hours > 168) { // Max 1 week
        return array(
            'success' => false,
            'error' => 'Expiry hours must be between 1 and 168 (1 week)',
            'error_type' => 'validation_error',
            'error_details' => ['expiry_hours' => $expiry_hours]
        );
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Clean up expired guest carts
        $result = $cart->cleanup_expired_guest_carts($expiry_hours);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'guest_carts_found' => $result['data']['guest_carts_found'],
                    'expiry_hours' => $result['data']['expiry_hours'],
                    'message' => $result['data']['message'],
                    'note' => $result['data']['note']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Guest cart cleanup exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while cleaning up guest carts. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get guest cart statistics for monitoring
 * Requirements: 1.5, 4.3
 * 
 * @return array Response array with success status and guest cart statistics
 */
function get_guest_cart_statistics_ctr()
{
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Get guest cart statistics
        $result = $cart->get_guest_cart_statistics();
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'unique_guest_ips' => $result['data']['unique_guest_ips'],
                    'total_guest_items' => $result['data']['total_guest_items'],
                    'total_guest_quantity' => $result['data']['total_guest_quantity'],
                    'avg_items_per_guest' => $result['data']['avg_items_per_guest'],
                    'timestamp' => $result['data']['timestamp']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Guest cart statistics exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while getting guest cart statistics. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Validate guest cart session
 * Requirements: 1.5, 4.3
 * 
 * @param string $ip_address IP address to validate (optional, will use current IP if not provided)
 * @return array Response array with success status and validation details
 */
function validate_guest_cart_session_ctr($ip_address = null)
{
    // Get IP address from server if not provided
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    // Validate IP address
    if (empty($ip_address)) {
        return array(
            'success' => false,
            'error' => 'IP address is required for guest cart validation',
            'error_type' => 'validation_error',
            'error_details' => ['ip_address' => $ip_address]
        );
    }
    
    try {
        // Create instance of cart class
        $cart = new Cart();
        
        // Validate guest cart session
        $result = $cart->validate_guest_cart_session($ip_address);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'data' => array(
                    'ip_address' => $result['data']['ip_address'],
                    'has_cart_items' => $result['data']['has_cart_items'],
                    'item_count' => $result['data']['item_count'],
                    'total_quantity' => $result['data']['total_quantity'],
                    'session_valid' => $result['data']['session_valid']
                )
            );
        } else {
            // Handle different types of database errors
            $error_message = get_user_friendly_cart_error($result['error_type'], $result['error_message']);
            
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
        error_log("Guest cart validation exception: " . $e->getMessage());
        
        return array(
            'success' => false,
            'error' => 'An unexpected error occurred while validating guest cart session. Please try again.',
            'error_type' => 'controller_exception',
            'error_details' => [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode()
            ]
        );
    }
}

/**
 * Get user-friendly error messages for cart-related database errors
 * @param string $error_type The type of database error
 * @param string $original_message The original error message
 * @return string User-friendly error message
 */
function get_user_friendly_cart_error($error_type, $original_message)
{
    switch ($error_type) {
        case 'connection_error':
        case 'connection_exception':
            return 'Database connection error. Please try again later.';
            
        case 'duplicate_entry':
            return 'This item is already in your cart.';
            
        case 'foreign_key_constraint':
            return 'The selected product may no longer be available. Please refresh the page.';
            
        case 'table_not_found':
        case 'column_not_found':
            return 'Database schema error. Please contact administrator.';
            
        case 'connection_lost':
            return 'Database connection lost. Please refresh the page and try again.';
            
        case 'lock_timeout':
        case 'deadlock':
            return 'Cart is busy. Please try again in a moment.';
            
        case 'too_many_connections':
            return 'Server is busy. Please try again later.';
            
        case 'access_denied':
            return 'Database access error. Please contact administrator.';
            
        case 'not_found':
            return 'Cart item not found. It may have been removed already.';
            
        case 'validation_error':
            return 'Invalid cart data provided.';
            
        case 'no_changes':
            return 'No changes were made to the cart.';
            
        default:
            return 'A cart error occurred. Please try again or contact support if the problem persists.';
    }
}

?>
