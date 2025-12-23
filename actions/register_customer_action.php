<?php

/**
 * Customer Registration Action
 * Receives POST data from registration form and invokes controller
 * Returns JSON response
 * Requirements: 6.4, 6.5
 */

header('Content-Type: application/json');

session_start();

// Include core functions for CSRF protection
require_once '../settings/core.php';

$response = array();

// Enforce CSRF protection for this action
enforce_csrf_protection('Security token validation failed. Please refresh the page and try again.');

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    $response['status'] = 'error';
    $response['message'] = 'You are already logged in';
    echo json_encode($response);
    exit();
}

require_once '../controllers/customer_controller.php';

// Validate that all required POST fields are present
$required_fields = ['name', 'email', 'password', 'country', 'city', 'phone_number', 'role'];
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

// Receive POST data
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$country = trim($_POST['country']);
$city = trim($_POST['city']);
$phone_number = trim($_POST['phone_number']);
$role = intval($_POST['role']);

$user_id = register_customer_ctr($name, $email, $password, $country, $city, $phone_number, $role);

if ($user_id === "email_exists") {
    $response['status'] = 'error';
    $response['message'] = 'This email address is already registered. Please use a different email or login.';
} elseif ($user_id) {
    $response['status'] = 'success';
    $response['message'] = 'Registration successful! Redirecting to login page...';
    $response['user_id'] = $user_id;
} else {
    $response['status'] = 'error';
    $response['message'] = 'Registration failed. Please check your information and try again.';
}

echo json_encode($response);
