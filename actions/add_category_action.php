<?php

/**
 * Add Category Action
 * Handles category creation requests
 * Validates input and calls controller function
 * Returns JSON response with success/error status
 * Requirements: 3.1, 3.2, 3.4, 3.5
 */

header('Content-Type: application/json');

session_start();

// Include core functions for authentication and CSRF protection
require_once '../settings/core.php';

$response = array();

// Enhanced session validation and error handling
$session_validation = validate_session_for_category_operations();
if (!$session_validation['valid']) {
    $response['status'] = 'error';
    $response['message'] = $session_validation['message'];
    $response['error_type'] = $session_validation['error_type'];
    $response['requires_action'] = $session_validation['requires_action'] ?? null;
    
    // Log security event if needed
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
    
    // Log CSRF security event
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

// Validate input parameters
if (!isset($_POST['cat_name']) || empty(trim($_POST['cat_name']))) {
    $response['status'] = 'error';
    $response['message'] = 'Category name is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'cat_name';
    echo json_encode($response);
    exit();
}

$cat_name = trim($_POST['cat_name']);

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

require_once '../controllers/category_controller.php';

/**
 * Validate session for category operations with comprehensive security checks
 * @return array Validation result with detailed information
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
    
    // Check if user is logged in with enhanced session validation
    if (!is_logged_in()) {
        $validation_result['message'] = 'Authentication required. Please log in to manage categories.';
        $validation_result['error_type'] = 'authentication_required';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    // Get user ID for logging
    $user_id = get_current_user_id();
    $validation_result['user_id'] = $user_id;
    
    // Perform comprehensive session security check
    $security_check = perform_session_security_check();
    if (!$security_check['is_secure']) {
        $validation_result['message'] = 'Session security validation failed. Please log in again.';
        $validation_result['error_type'] = 'session_security_failed';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    // Check for session expiration during operations
    if (is_session_expired()) {
        $validation_result['message'] = 'Your session has expired during the operation. Please log in again.';
        $validation_result['error_type'] = 'session_expired_during_operation';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    // Check if user has admin privileges (only admins can manage categories)
    if (!has_admin_privileges()) {
        $validation_result['message'] = 'Access denied. Administrator privileges required to manage categories.';
        $validation_result['error_type'] = 'insufficient_privileges';
        $validation_result['requires_action'] = 'contact_administrator';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    // Validate session data integrity
    if (!validate_and_sanitize_session_data()) {
        $validation_result['message'] = 'Session data integrity check failed. Please log in again.';
        $validation_result['error_type'] = 'session_data_corrupted';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    // All validations passed
    $validation_result['valid'] = true;
    return $validation_result;
}

/**
 * Enhanced CSRF validation with detailed error handling
 * @return array CSRF validation result
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
    
    // Check if CSRF token exists in session
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $csrf_result['message'] = 'Security token not found in session. Please refresh the page.';
        $csrf_result['error_type'] = 'csrf_token_missing_session';
        $csrf_result['requires_action'] = 'refresh_page';
        $csrf_result['log_security_event'] = true;
        return $csrf_result;
    }
    
    // Check if CSRF token exists in POST data
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
        $csrf_result['message'] = 'Security token not provided. Please refresh the page and try again.';
        $csrf_result['error_type'] = 'csrf_token_missing_post';
        $csrf_result['requires_action'] = 'refresh_page';
        $csrf_result['log_security_event'] = true;
        return $csrf_result;
    }
    
    // Validate CSRF token
    if (!validate_csrf_from_post()) {
        $csrf_result['message'] = 'Invalid security token. This may indicate a security issue. Please refresh the page and try again.';
        $csrf_result['error_type'] = 'csrf_validation_failed';
        $csrf_result['requires_action'] = 'refresh_page';
        $csrf_result['log_security_event'] = true;
        return $csrf_result;
    }
    
    // CSRF validation passed
    $csrf_result['valid'] = true;
    return $csrf_result;
}

try {
    // Check session validity before processing (in case it expired during request)
    if (!is_logged_in() || is_session_expired()) {
        $response['status'] = 'error';
        $response['message'] = 'Your session has expired during the operation. Please log in again.';
        $response['error_type'] = 'session_expired_during_operation';
        $response['requires_action'] = 'redirect_to_login';
        
        // Log session expiration during operation
        log_session_security_event('session_expired_during_operation', 
            'Session expired while processing category creation', 
            $user_id);
        
        echo json_encode($response);
        exit();
    }
    
    // Call controller function to add category with enhanced error handling
    $result = add_category_ctr($cat_name, $user_id);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = 'Category created successfully.';
        $response['data'] = $result['data'];
        
        // Update session activity timestamp after successful operation
        $_SESSION['last_activity'] = time();
        
        // Log successful category creation for audit trail
        error_log("Category created successfully: ID {$result['data']['category_id']}, Name '{$result['data']['category_name']}', User {$user_id}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'creation_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
            case 'duplicate_name':
            case 'duplicate_entry':
                $response['field'] = 'cat_name';
                $response['suggestion'] = 'Try a different category name.';
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
                
            case 'validation_error':
                $response['suggestion'] = 'Please check your input and try again.';
                break;
        }
        
        // Log the error with context for debugging
        error_log("Category creation failed for user {$user_id}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'user_id' => $user_id,
        'cat_name' => $cat_name,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Category creation exception: " . json_encode($exception_context));
    
    // Check if this might be a session-related exception
    if (strpos($e->getMessage(), 'session') !== false || 
        strpos($e->getMessage(), 'Session') !== false) {
        $response['status'] = 'error';
        $response['message'] = 'A session-related error occurred. Please log in again and try again.';
        $response['error_type'] = 'session_exception';
        $response['requires_action'] = 'redirect_to_login';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'An unexpected error occurred while creating the category. Please try again.';
        $response['error_type'] = 'server_exception';
        $response['suggestion'] = 'If the problem persists, please contact support.';
        $response['retry_recommended'] = true;
    }
}

echo json_encode($response);

?>