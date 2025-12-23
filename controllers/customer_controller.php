<?php

require_once '../classes/customer_class.php';

/**
 * Customer Controller
 * Handles customer registration and management operations
 * Requirements: 6.2, 6.3
 */

/**
 * Register a new customer
 * Creates an instance of the customer class and invokes the createUser method
 * 
 * @param string $name Customer's full name
 * @param string $email Customer's email address
 * @param string $password Customer's password (will be encrypted)
 * @param string $country Customer's country
 * @param string $city Customer's city
 * @param string $contact Customer's contact number
 * @param int $role User role (0 = admin, 1 = customer)
 * @return int|string|false Returns customer_id on success, "email_exists" if email is duplicate, false on failure
 */
function register_customer_ctr($name, $email, $password, $country, $city, $contact, $role)
{
    // Create instance of customer class
    $customer = new Customer();
    
    // Check if email already exists (Requirements: 2.1, 2.2)
    if ($customer->checkEmailExists($email)) {
        return "email_exists";
    }
    
    // Invoke customer class createUser method
    $customer_id = $customer->createUser($name, $email, $password, $country, $city, $contact, $role);
    if ($customer_id) {
        return $customer_id;
    }
    return false;
}

/**
 * Legacy function - use register_customer_ctr instead
 */
function register_user_ctr($name, $email, $password, $country, $city, $phone_number, $role)
{
    return register_customer_ctr($name, $email, $password, $country, $city, $phone_number, $role);
}

/**
 * Check if an email is already registered
 * 
 * @param string $email Email address to check
 * @return bool True if email exists, false otherwise
 */
function check_email_ctr($email)
{
    $customer = new Customer();
    return $customer->checkEmailExists($email);
}

/**
 * Get customer data by customer ID
 * 
 * @param int $customer_id Customer ID
 * @return array|false Customer data array or false if not found
 */
function get_customer_ctr($customer_id)
{
    $customer = new Customer();
    return $customer->getCustomerById($customer_id);
}

/**
 * Get customer data by email address
 * 
 * @param string $email Customer email
 * @return array|false Customer data array or false if not found
 */
function get_user_by_email_ctr($email)
{
    $customer = new Customer();
    return $customer->getUserByEmail($email);
}

/**
 * Get customer data by email address (wrapper for authentication)
 * Requirements: 6.3
 * 
 * @param string $email Customer email address
 * @return array|false Customer data array or false if not found
 */
function get_customer_by_email_ctr($email)
{
    // Input validation
    if (empty($email) || !is_string($email)) {
        return false;
    }
    
    // Create instance of customer class
    $customer = new Customer();
    
    // Call customer class method
    return $customer->get_customer_by_email($email);
}

/**
 * Authenticate customer login credentials
 * Requirements: 6.3, 6.4
 * 
 * @param string $email Customer email address
 * @param string $password Customer password
 * @return array|false Customer data array on success, false on failure
 */
function login_customer_ctr($email, $password)
{
    // Input validation
    if (empty($email) || !is_string($email) || empty($password) || !is_string($password)) {
        return false;
    }
    
    // Create instance of customer class
    $customer = new Customer();
    
    // Invoke customer class authentication method
    $result = $customer->verify_customer_login($email, $password);
    
    if ($result) {
        // Return standardized response format with customer data for session establishment
        return array(
            'customer_id' => $result['customer_id'],
            'customer_name' => $result['customer_name'],
            'customer_email' => $result['customer_email'],
            'user_role' => $result['user_role'],
            'customer_country' => $result['customer_country'],
            'customer_city' => $result['customer_city'],
            'customer_contact' => $result['customer_contact'],
            'customer_image' => $result['customer_image']
        );
    }
    
    return false;
}