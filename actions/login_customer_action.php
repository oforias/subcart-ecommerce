<?php

/**
 * Customer Login Action
 * Receives POST data from login form and handles authentication
 * Returns JSON response with session establishment
 * Requirements: 6.5, 6.6, 3.1
 */

header('Content-Type: application/json');

session_start();

// Include core functions for CSRF protection
require_once '../settings/core.php';

$response = array();

// Enforce CSRF protection for this action
enforce_csrf_protection('Security token validation failed. Please refresh the page and try again.');

// Check if the user is already logged in
if (isset($_SESSION['customer_id'])) {
    $response['status'] = 'error';
    $response['message'] = 'You are already logged in';
    echo json_encode($response);
    exit();
}

require_once '../controllers/customer_controller.php';

// Validate that all required POST fields are present
$required_fields = ['email', 'password'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    $response['status'] = 'error';
    $response['message'] = 'Missing required fields: ' . implode(', ', $missing_fields);
    echo json_encode($response);
    exit();
}

// Receive and sanitize POST data
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$password = $_POST['password']; // Don't trim password as it might contain leading/trailing spaces

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid email format';
    echo json_encode($response);
    exit();
}

// Attempt authentication using controller
$customer_data = login_customer_ctr($email, $password);

if ($customer_data) {
    // Authentication successful - establish session variables
    $_SESSION['customer_id'] = $customer_data['customer_id'];
    $_SESSION['user_role'] = $customer_data['user_role'];
    $_SESSION['customer_name'] = $customer_data['customer_name'];
    $_SESSION['customer_email'] = $customer_data['customer_email'];
    
    // Initialize secure session with CSRF token regeneration
    initialize_secure_session();
    regenerate_csrf_token();
    
    // Regenerate session ID to prevent session fixation
    regenerate_session_on_login();
    
    $response['status'] = 'success';
    $response['message'] = 'Login successful! Redirecting...';
    $response['redirect'] = '../index.php';
} else {
    // Authentication failed - return generic error message for security
    $response['status'] = 'error';
    $response['message'] = 'Invalid email or password';
}

echo json_encode($response);