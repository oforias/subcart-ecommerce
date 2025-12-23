<?php

/**
 * Cart Input Validation Class
 * Provides comprehensive input validation for all cart operations
 * Requirements: 8.1
 */
class CartValidation
{
    /**
     * Validate product ID input
     * Requirements: 8.1
     * 
     * @param mixed $product_id Product ID to validate
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_product_id($product_id)
    {
        // Check if product_id is provided
        if (is_null($product_id) || $product_id === '') {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID is required',
                'error_details' => [
                    'field' => 'product_id',
                    'value' => $product_id,
                    'issue' => 'missing_or_empty'
                ]
            ];
        }
        
        // Check if product_id is numeric
        if (!is_numeric($product_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID must be a valid number',
                'error_details' => [
                    'field' => 'product_id',
                    'value' => $product_id,
                    'issue' => 'not_numeric'
                ]
            ];
        }
        
        // Convert to integer and validate range
        $int_product_id = (int)$product_id;
        
        if ($int_product_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID must be a positive number',
                'error_details' => [
                    'field' => 'product_id',
                    'value' => $int_product_id,
                    'issue' => 'not_positive'
                ]
            ];
        }
        
        // Check reasonable upper limit (prevent potential overflow or abuse)
        if ($int_product_id > 2147483647) { // Max 32-bit signed integer
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID is too large',
                'error_details' => [
                    'field' => 'product_id',
                    'value' => $int_product_id,
                    'issue' => 'too_large',
                    'max_allowed' => 2147483647
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_value' => $int_product_id
        ];
    }
    
    /**
     * Validate quantity input for cart operations
     * Requirements: 8.1
     * 
     * @param mixed $quantity Quantity to validate
     * @param array $options Validation options (allow_zero, max_quantity)
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_quantity($quantity, $options = [])
    {
        $allow_zero = $options['allow_zero'] ?? false;
        $max_quantity = $options['max_quantity'] ?? 999;
        
        // Check if quantity is provided
        if (is_null($quantity) || $quantity === '') {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Quantity is required',
                'error_details' => [
                    'field' => 'quantity',
                    'value' => $quantity,
                    'issue' => 'missing_or_empty'
                ]
            ];
        }
        
        // Check if quantity is numeric
        if (!is_numeric($quantity)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Quantity must be a valid number',
                'error_details' => [
                    'field' => 'quantity',
                    'value' => $quantity,
                    'issue' => 'not_numeric'
                ]
            ];
        }
        
        // Convert to integer and validate range
        $int_quantity = (int)$quantity;
        
        // Check minimum value (0 or 1 depending on options)
        $min_value = $allow_zero ? 0 : 1;
        if ($int_quantity < $min_value) {
            $message = $allow_zero ? 
                'Quantity must be zero or greater' : 
                'Quantity must be at least 1';
                
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => $message,
                'error_details' => [
                    'field' => 'quantity',
                    'value' => $int_quantity,
                    'issue' => 'below_minimum',
                    'min_allowed' => $min_value
                ]
            ];
        }
        
        // Check maximum value
        if ($int_quantity > $max_quantity) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => "Quantity must be at most {$max_quantity}",
                'error_details' => [
                    'field' => 'quantity',
                    'value' => $int_quantity,
                    'issue' => 'above_maximum',
                    'max_allowed' => $max_quantity
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_value' => $int_quantity
        ];
    }
    
    /**
     * Validate customer ID input
     * Requirements: 8.1
     * 
     * @param mixed $customer_id Customer ID to validate (can be null for guest users)
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_customer_id($customer_id)
    {
        // Customer ID can be null for guest users
        if (is_null($customer_id) || $customer_id === '') {
            return [
                'success' => true,
                'sanitized_value' => null
            ];
        }
        
        // Check if customer_id is numeric
        if (!is_numeric($customer_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Customer ID must be a valid number',
                'error_details' => [
                    'field' => 'customer_id',
                    'value' => $customer_id,
                    'issue' => 'not_numeric'
                ]
            ];
        }
        
        // Convert to integer and validate range
        $int_customer_id = (int)$customer_id;
        
        if ($int_customer_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Customer ID must be a positive number',
                'error_details' => [
                    'field' => 'customer_id',
                    'value' => $int_customer_id,
                    'issue' => 'not_positive'
                ]
            ];
        }
        
        // Check reasonable upper limit
        if ($int_customer_id > 2147483647) { // Max 32-bit signed integer
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Customer ID is too large',
                'error_details' => [
                    'field' => 'customer_id',
                    'value' => $int_customer_id,
                    'issue' => 'too_large',
                    'max_allowed' => 2147483647
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_value' => $int_customer_id
        ];
    }
    
    /**
     * Validate IP address input
     * Requirements: 8.1
     * 
     * @param mixed $ip_address IP address to validate (can be null)
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_ip_address($ip_address)
    {
        // IP address can be null
        if (is_null($ip_address) || $ip_address === '') {
            // Fallback to localhost for CLI or missing IP
            $fallback_ip = '127.0.0.1';
            return [
                'success' => true,
                'sanitized_value' => $fallback_ip
            ];
        }
        
        // Trim whitespace
        $trimmed_ip = trim((string)$ip_address);
        
        // Handle empty string after trimming
        if ($trimmed_ip === '') {
            $fallback_ip = '127.0.0.1';
            return [
                'success' => true,
                'sanitized_value' => $fallback_ip
            ];
        }
        
        // Validate IP address format
        if (!filter_var($trimmed_ip, FILTER_VALIDATE_IP)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Invalid IP address format',
                'error_details' => [
                    'field' => 'ip_address',
                    'value' => $trimmed_ip,
                    'issue' => 'invalid_format'
                ]
            ];
        }
        
        // Check for private/reserved IP ranges if needed
        $options = [
            'allow_private' => true,
            'allow_reserved' => true,
            'allow_localhost' => true
        ];
        
        return [
            'success' => true,
            'sanitized_value' => $trimmed_ip
        ];
    }
    
    /**
     * Validate user identification (customer_id or ip_address must be provided)
     * Requirements: 8.1
     * 
     * @param mixed $customer_id Customer ID
     * @param mixed $ip_address IP address
     * @return array Validation result with success status and sanitized values/error details
     */
    public static function validate_user_identification($customer_id, $ip_address)
    {
        // Validate customer_id
        $customer_validation = self::validate_customer_id($customer_id);
        if (!$customer_validation['success']) {
            return $customer_validation;
        }
        
        // Validate ip_address
        $ip_validation = self::validate_ip_address($ip_address);
        if (!$ip_validation['success']) {
            return $ip_validation;
        }
        
        $sanitized_customer_id = $customer_validation['sanitized_value'];
        $sanitized_ip_address = $ip_validation['sanitized_value'];
        
        // At least one must be provided for user identification
        if (is_null($sanitized_customer_id) && is_null($sanitized_ip_address)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Either customer ID or IP address is required for user identification',
                'error_details' => [
                    'customer_id' => $sanitized_customer_id,
                    'ip_address' => $sanitized_ip_address,
                    'issue' => 'no_user_identification'
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_values' => [
                'customer_id' => $sanitized_customer_id,
                'ip_address' => $sanitized_ip_address
            ]
        ];
    }
    
    /**
     * Validate order amount for checkout operations
     * Requirements: 8.1
     * 
     * @param mixed $amount Amount to validate
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_order_amount($amount)
    {
        // Check if amount is provided
        if (is_null($amount) || $amount === '') {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Order amount is required',
                'error_details' => [
                    'field' => 'amount',
                    'value' => $amount,
                    'issue' => 'missing_or_empty'
                ]
            ];
        }
        
        // Check if amount is numeric
        if (!is_numeric($amount)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Order amount must be a valid number',
                'error_details' => [
                    'field' => 'amount',
                    'value' => $amount,
                    'issue' => 'not_numeric'
                ]
            ];
        }
        
        // Convert to float and validate range
        $float_amount = (float)$amount;
        
        // Check minimum value (must be positive)
        if ($float_amount <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Order amount must be greater than zero',
                'error_details' => [
                    'field' => 'amount',
                    'value' => $float_amount,
                    'issue' => 'not_positive'
                ]
            ];
        }
        
        // Check reasonable maximum value (prevent potential abuse)
        $max_amount = 999999.99; // $999,999.99 maximum
        if ($float_amount > $max_amount) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => "Order amount exceeds maximum allowed value of {$max_amount}",
                'error_details' => [
                    'field' => 'amount',
                    'value' => $float_amount,
                    'issue' => 'above_maximum',
                    'max_allowed' => $max_amount
                ]
            ];
        }
        
        // Round to 2 decimal places for currency
        $rounded_amount = round($float_amount, 2);
        
        return [
            'success' => true,
            'sanitized_value' => $rounded_amount
        ];
    }
    
    /**
     * Validate currency code
     * Requirements: 8.1
     * 
     * @param mixed $currency Currency code to validate
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_currency($currency)
    {
        // Currency can be null (will default to USD)
        if (is_null($currency) || $currency === '') {
            return [
                'success' => true,
                'sanitized_value' => 'USD' // Default currency
            ];
        }
        
        // Trim and convert to uppercase
        $trimmed_currency = strtoupper(trim((string)$currency));
        
        // Check length (currency codes are 3 characters)
        if (strlen($trimmed_currency) !== 3) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Currency code must be exactly 3 characters',
                'error_details' => [
                    'field' => 'currency',
                    'value' => $trimmed_currency,
                    'issue' => 'invalid_length',
                    'expected_length' => 3
                ]
            ];
        }
        
        // Check if all characters are alphabetic
        if (!ctype_alpha($trimmed_currency)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Currency code must contain only alphabetic characters',
                'error_details' => [
                    'field' => 'currency',
                    'value' => $trimmed_currency,
                    'issue' => 'non_alphabetic'
                ]
            ];
        }
        
        // List of common valid currency codes (can be extended)
        $valid_currencies = [
            'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'SEK', 'NZD',
            'MXN', 'SGD', 'HKD', 'NOK', 'TRY', 'RUB', 'INR', 'BRL', 'ZAR', 'KRW'
        ];
        
        if (!in_array($trimmed_currency, $valid_currencies)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Unsupported currency code',
                'error_details' => [
                    'field' => 'currency',
                    'value' => $trimmed_currency,
                    'issue' => 'unsupported_currency',
                    'supported_currencies' => $valid_currencies
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_value' => $trimmed_currency
        ];
    }
    
    /**
     * Validate payment method
     * Requirements: 8.1
     * 
     * @param mixed $payment_method Payment method to validate
     * @return array Validation result with success status and sanitized value/error details
     */
    public static function validate_payment_method($payment_method)
    {
        // Check if payment_method is provided
        if (is_null($payment_method) || $payment_method === '') {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Payment method is required',
                'error_details' => [
                    'field' => 'payment_method',
                    'value' => $payment_method,
                    'issue' => 'missing_or_empty'
                ]
            ];
        }
        
        // Trim and convert to lowercase
        $trimmed_method = strtolower(trim((string)$payment_method));
        
        // List of valid payment methods for simulation
        $valid_methods = [
            'simulated_success',
            'simulated_failure',
            'simulated_timeout',
            'credit_card',
            'debit_card',
            'paypal',
            'bank_transfer'
        ];
        
        if (!in_array($trimmed_method, $valid_methods)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Invalid payment method',
                'error_details' => [
                    'field' => 'payment_method',
                    'value' => $trimmed_method,
                    'issue' => 'invalid_method',
                    'valid_methods' => $valid_methods
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_value' => $trimmed_method
        ];
    }
    
    /**
     * Comprehensive validation for add to cart operation
     * Requirements: 8.1
     * 
     * @param array $input Input data to validate
     * @return array Validation result with success status and sanitized values/error details
     */
    public static function validate_add_to_cart_input($input)
    {
        $errors = [];
        $sanitized = [];
        
        // Validate product_id
        $product_validation = self::validate_product_id($input['product_id'] ?? null);
        if (!$product_validation['success']) {
            $errors[] = $product_validation;
        } else {
            $sanitized['product_id'] = $product_validation['sanitized_value'];
        }
        
        // Validate quantity (default to 1 if not provided)
        $quantity = $input['quantity'] ?? 1;
        $quantity_validation = self::validate_quantity($quantity);
        if (!$quantity_validation['success']) {
            $errors[] = $quantity_validation;
        } else {
            $sanitized['quantity'] = $quantity_validation['sanitized_value'];
        }
        
        // Validate user identification
        $user_validation = self::validate_user_identification(
            $input['customer_id'] ?? null,
            $input['ip_address'] ?? null
        );
        if (!$user_validation['success']) {
            $errors[] = $user_validation;
        } else {
            $sanitized = array_merge($sanitized, $user_validation['sanitized_values']);
        }
        
        // Return results
        if (!empty($errors)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Input validation failed',
                'error_details' => [
                    'validation_errors' => $errors,
                    'failed_fields' => count($errors)
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_values' => $sanitized
        ];
    }
    
    /**
     * Comprehensive validation for checkout operation
     * Requirements: 8.1
     * 
     * @param array $input Input data to validate
     * @return array Validation result with success status and sanitized values/error details
     */
    public static function validate_checkout_input($input)
    {
        $errors = [];
        $sanitized = [];
        
        // Validate customer_id (required for checkout)
        $customer_validation = self::validate_customer_id($input['customer_id'] ?? null);
        if (!$customer_validation['success'] || is_null($customer_validation['sanitized_value'])) {
            $errors[] = [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Customer ID is required for checkout',
                'error_details' => [
                    'field' => 'customer_id',
                    'issue' => 'required_for_checkout'
                ]
            ];
        } else {
            $sanitized['customer_id'] = $customer_validation['sanitized_value'];
        }
        
        // Validate total_amount
        $amount_validation = self::validate_order_amount($input['total_amount'] ?? null);
        if (!$amount_validation['success']) {
            $errors[] = $amount_validation;
        } else {
            $sanitized['total_amount'] = $amount_validation['sanitized_value'];
        }
        
        // Validate currency
        $currency_validation = self::validate_currency($input['currency'] ?? null);
        if (!$currency_validation['success']) {
            $errors[] = $currency_validation;
        } else {
            $sanitized['currency'] = $currency_validation['sanitized_value'];
        }
        
        // Validate payment_method
        $payment_validation = self::validate_payment_method($input['payment_method'] ?? null);
        if (!$payment_validation['success']) {
            $errors[] = $payment_validation;
        } else {
            $sanitized['payment_method'] = $payment_validation['sanitized_value'];
        }
        
        // Return results
        if (!empty($errors)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Checkout input validation failed',
                'error_details' => [
                    'validation_errors' => $errors,
                    'failed_fields' => count($errors)
                ]
            ];
        }
        
        return [
            'success' => true,
            'sanitized_values' => $sanitized
        ];
    }
}

?>