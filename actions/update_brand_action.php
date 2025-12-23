<?php

/**
 * Update Brand Action
 * Handles brand update requests
 * Validates input and enforces field restrictions
 * Returns JSON response with operation status
 * Requirements: 3.1, 3.2, 3.3, 3.5
 */

header('Content-Type: application/json');

session_start();

// Include core functions for authentication and CSRF protection
require_once '../settings/core.php';

$response = array();

/**
 * Validate session for brand operations with comprehensive security checks
 */
function validate_session_for_brand_operations()
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
        $validation_result['message'] = 'Authentication required. Please log in to update brands.';
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
        $validation_result['message'] = 'Access denied. Administrator privileges required to update brands.';
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
$session_validation = validate_session_for_brand_operations();
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
if (!isset($_POST['brand_id']) || empty(trim($_POST['brand_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Brand ID is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_id';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['brand_name']) || empty(trim($_POST['brand_name']))) {
    $response['status'] = 'error';
    $response['message'] = 'Brand name is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_name';
    echo json_encode($response);
    exit();
}

$brand_id = trim($_POST['brand_id']);
$brand_name = trim($_POST['brand_name']);

// Validate brand ID is numeric
if (!is_numeric($brand_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid brand ID format.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_id';
    echo json_encode($response);
    exit();
}

// Convert to integer
$brand_id = (int)$brand_id;

// Validate brand ID is positive
if ($brand_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Brand ID must be a positive number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_id';
    echo json_encode($response);
    exit();
}

// Validate brand name length (1-255 characters as per design)
if (strlen($brand_name) < 1) {
    $response['status'] = 'error';
    $response['message'] = 'Brand name cannot be empty.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_name';
    echo json_encode($response);
    exit();
}

if (strlen($brand_name) > 255) {
    $response['status'] = 'error';
    $response['message'] = 'Brand name must be 255 characters or less.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_name';
    echo json_encode($response);
    exit();
}

// Validate brand name format (basic sanitization)
if (preg_match('/[<>"\']/', $brand_name)) {
    $response['status'] = 'error';
    $response['message'] = 'Brand name contains invalid characters.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_name';
    echo json_encode($response);
    exit();
}

// Enforce field restrictions - only brand_name should be modifiable
// Brand ID should not be modifiable (Requirements 3.2)
// This is enforced by only accepting brand_name for updates and using brand_id for identification only

require_once '../controllers/brand_controller.php';

try {
    // Check session validity before processing (in case it expired during request)
    if (!is_logged_in() || is_session_expired()) {
        $response['status'] = 'error';
        $response['message'] = 'Your session has expired during the operation. Please log in again.';
        $response['error_type'] = 'session_expired_during_operation';
        $response['requires_action'] = 'redirect_to_login';
        
        // Log session expiration during operation
        log_session_security_event('session_expired_during_operation', 
            'Session expired while processing brand update', 
            $user_id);
        
        echo json_encode($response);
        exit();
    }
    
    // Call controller function to update brand
    $result = update_brand_ctr($brand_id, $brand_name, $user_id);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = 'Brand updated successfully.';
        $response['data'] = $result['data'];
        
        // Update session activity timestamp after successful operation
        $_SESSION['last_activity'] = time();
        
        // Log successful brand update for audit trail
        error_log("Brand updated successfully: ID {$result['data']['brand_id']}, Name '{$result['data']['brand_name']}', User {$user_id}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'update_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Provide specific error types for different failure scenarios
        switch ($result['error_type']) {
            case 'duplicate_name':
            case 'duplicate_entry':
                $response['field'] = 'brand_name';
                $response['suggestion'] = 'Try a different brand name for this category.';
                break;
                
            case 'not_found':
                $response['field'] = 'brand_id';
                $response['suggestion'] = 'The brand may have been deleted or you may not have access to it.';
                break;
                
            case 'access_denied':
                $response['suggestion'] = 'You do not have permission to update this brand.';
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
        error_log("Brand update failed for user {$user_id}, brand {$brand_id}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'user_id' => $user_id,
        'brand_id' => $brand_id,
        'brand_name' => $brand_name,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Brand update exception: " . json_encode($exception_context));
    
    // Check if this might be a session-related exception
    if (strpos($e->getMessage(), 'session') !== false || 
        strpos($e->getMessage(), 'Session') !== false) {
        $response['status'] = 'error';
        $response['message'] = 'A session-related error occurred. Please log in again and try again.';
        $response['error_type'] = 'session_exception';
        $response['requires_action'] = 'redirect_to_login';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'An unexpected error occurred while updating the brand. Please try again.';
        $response['error_type'] = 'server_exception';
        $response['suggestion'] = 'If the problem persists, please contact support.';
        $response['retry_recommended'] = true;
    }
}

echo json_encode($response);

?>