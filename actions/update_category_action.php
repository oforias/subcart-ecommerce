<?php

/**
 * Update Category Action
 * Handles category update requests
 * Validates input and enforces field restrictions
 * Returns JSON response with operation status
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 */

header('Content-Type: application/json');

session_start();

// Include core functions for authentication and CSRF protection
require_once '../settings/core.php';

$response = array();

/**
 * Validate session for category operations with comprehensive security checks
 */
function validate_session_for_category_operations()
{
    $validation_result = [
        'valid' => false,
        'message' => '',
        'error_type' => '',
        'requires_action' => null,
        'log_security_event' => false,
        'user_id' => null
    ];
    
    if (!is_logged_in()) {
        $validation_result['message'] = 'Authentication required. Please log in to update categories.';
        $validation_result['error_type'] = 'authentication_required';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    $user_id = get_current_user_id();
    $validation_result['user_id'] = $user_id;
    
    $security_check = perform_session_security_check();
    if (!$security_check['is_secure']) {
        $validation_result['message'] = 'Session security validation failed. Please log in again.';
        $validation_result['error_type'] = 'session_security_failed';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    if (is_session_expired()) {
        $validation_result['message'] = 'Your session has expired. Please log in again.';
        $validation_result['error_type'] = 'session_expired_during_operation';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    if (!has_admin_privileges()) {
        $validation_result['message'] = 'Access denied. Administrator privileges required to update categories.';
        $validation_result['error_type'] = 'insufficient_privileges';
        $validation_result['requires_action'] = 'contact_administrator';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    if (!validate_and_sanitize_session_data()) {
        $validation_result['message'] = 'Session data integrity check failed. Please log in again.';
        $validation_result['error_type'] = 'session_data_corrupted';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    $validation_result['valid'] = true;
    return $validation_result;
}

/**
 * Enhanced CSRF validation with detailed error handling
 */
function validate_csrf_with_enhanced_error_handling()
{
    $csrf_result = [
        'valid' => false,
        'message' => '',
        'error_type' => '',
        'requires_action' => null,
        'log_security_event' => false
    ];
    
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $csrf_result['message'] = 'Security token not found in session. Please refresh the page.';
        $csrf_result['error_type'] = 'csrf_token_missing_session';
        $csrf_result['requires_action'] = 'refresh_page';
        $csrf_result['log_security_event'] = true;
        return $csrf_result;
    }
    
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
        $csrf_result['message'] = 'Security token not provided. Please refresh the page and try again.';
        $csrf_result['error_type'] = 'csrf_token_missing_post';
        $csrf_result['requires_action'] = 'refresh_page';
        $csrf_result['log_security_event'] = true;
        return $csrf_result;
    }
    
    if (!validate_csrf_from_post()) {
        $csrf_result['message'] = 'Invalid security token. This may indicate a security issue. Please refresh the page and try again.';
        $csrf_result['error_type'] = 'csrf_validation_failed';
        $csrf_result['requires_action'] = 'refresh_page';
        $csrf_result['log_security_event'] = true;
        return $csrf_result;
    }
    
    $csrf_result['valid'] = true;
    return $csrf_result;
}

// Enhanced session validation and error handling
$session_validation = validate_session_for_category_operations();
if (!$session_validation['valid']) {
    $response['status'] = 'error';
    $response['message'] = $session_validation['message'];
    $response['error_type'] = $session_validation['error_type'];
    $response['requires_action'] = $session_validation['requires_action'] ?? null;
    
    if ($session_validation['log_security_event']) {
        log_session_security_event($session_validation['error_type'], 
            $session_validation['message'], 
            $session_validation['user_id'] ?? null);
    }
    
    echo json_encode($response);
    exit();
}

// Validate request method (should be POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. POST required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Enhanced CSRF validation with detailed error handling
$csrf_validation = validate_csrf_with_enhanced_error_handling();
if (!$csrf_validation['valid']) {
    $response['status'] = 'error';
    $response['message'] = $csrf_validation['message'];
    $response['error_type'] = $csrf_validation['error_type'];
    $response['requires_action'] = $csrf_validation['requires_action'];
    
    if ($csrf_validation['log_security_event']) {
        log_session_security_event($csrf_validation['error_type'], 
            $csrf_validation['message'], 
            get_current_user_id());
    }
    
    echo json_encode($response);
    exit();
}

// Get current user ID
$user_id = get_current_user_id();
if (!$user_id) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid session. Please log in again.';
    $response['error_type'] = 'invalid_session';
    echo json_encode($response);
    exit();
}

// Validate required input parameters
if (!isset($_POST['cat_id']) || empty(trim($_POST['cat_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Category ID is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_id';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['cat_name']) || empty(trim($_POST['cat_name']))) {
    $response['status'] = 'error';
    $response['message'] = 'Category name is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_name';
    echo json_encode($response);
    exit();
}

$cat_id = trim($_POST['cat_id']);
$cat_name = trim($_POST['cat_name']);

// Validate category ID is numeric
if (!is_numeric($cat_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid category ID format.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_id';
    echo json_encode($response);
    exit();
}

// Convert to integer
$cat_id = (int)$cat_id;

// Validate category name length (1-100 characters as per design)
if (strlen($cat_name) < 1) {
    $response['status'] = 'error';
    $response['message'] = 'Category name cannot be empty.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_name';
    echo json_encode($response);
    exit();
}

if (strlen($cat_name) > 100) {
    $response['status'] = 'error';
    $response['message'] = 'Category name must be 100 characters or less.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_name';
    echo json_encode($response);
    exit();
}

// Validate category name format (basic sanitization)
if (preg_match('/[<>"\']/', $cat_name)) {
    $response['status'] = 'error';
    $response['message'] = 'Category name contains invalid characters.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_name';
    echo json_encode($response);
    exit();
}

// Enforce field restrictions - only cat_name should be modifiable
// Category ID should not be modifiable (Requirements 4.1, 4.2)
// This is enforced by only accepting cat_name for updates and using cat_id for identification only

require_once '../controllers/category_controller.php';

try {
    // Call controller function to update category
    $result = update_category_ctr($cat_id, $cat_name, $user_id);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = 'Category updated successfully.';
        $response['data'] = $result['data'];
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = 'update_failed';
        
        // Provide specific error types for different failure scenarios
        if (strpos($result['error'], 'already exists') !== false) {
            $response['error_type'] = 'duplicate_name';
            $response['field'] = 'cat_name';
        } elseif (strpos($result['error'], 'not found') !== false) {
            $response['error_type'] = 'category_not_found';
            $response['field'] = 'cat_id';
        } elseif (strpos($result['error'], 'Access denied') !== false) {
            $response['error_type'] = 'access_denied';
        }
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Category update error for user {$user_id}, category {$cat_id}: " . $e->getMessage());
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while updating the category. Please try again.';
    $response['error_type'] = 'server_error';
}

echo json_encode($response);

?>