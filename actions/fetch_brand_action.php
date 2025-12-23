<?php

/**
 * Fetch Brands Action
 * Retrieves brands for the logged-in user
 * Returns JSON response with brand data organized by categories
 * Requirements: 1.1, 2.1
 */

header('Content-Type: application/json');

session_start();

// Include core functions for authentication and CSRF protection
require_once '../settings/core.php';

$response = array();

/**
 * Validate session for brand operations with comprehensive security checks
 * @return array Validation result with detailed information
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
    
    // Check if user is logged in with enhanced session validation
    if (!is_logged_in()) {
        $validation_result['message'] = 'Authentication required. Please log in to access brands.';
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
        $validation_result['message'] = 'Your session has expired. Please log in again.';
        $validation_result['error_type'] = 'session_expired_during_operation';
        $validation_result['requires_action'] = 'redirect_to_login';
        $validation_result['log_security_event'] = true;
        return $validation_result;
    }
    
    // Check if user has admin privileges (only admins can manage brands)
    if (!has_admin_privileges()) {
        $validation_result['message'] = 'Access denied. Administrator privileges required to access brands.';
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

// Enhanced session validation and error handling
$session_validation = validate_session_for_brand_operations();
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

// Get current user ID
$user_id = get_current_user_id();
if (!$user_id) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid session. Please log in again.';
    $response['error_type'] = 'invalid_session';
    echo json_encode($response);
    exit();
}

// Optional category filter
$category_id = null;
if (isset($_GET['category_id']) && !empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $category_id = (int)$_GET['category_id'];
}

require_once '../controllers/brand_controller.php';

try {
    // Check session validity before processing (in case it expired during request)
    if (!is_logged_in() || is_session_expired()) {
        $response['status'] = 'error';
        $response['message'] = 'Your session has expired. Please log in again to access brands.';
        $response['error_type'] = 'session_expired_during_operation';
        $response['requires_action'] = 'redirect_to_login';
        
        // Log session expiration during operation
        log_session_security_event('session_expired_during_operation', 
            'Session expired while fetching brands', 
            $user_id);
        
        echo json_encode($response);
        exit();
    }
    
    // Fetch brands for the current user with enhanced error handling
    $result = fetch_brands_ctr($user_id, $category_id);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['data'] = $result['data'];
        $response['message'] = 'Brands retrieved successfully';
        
        // Update session activity timestamp after successful operation
        $_SESSION['last_activity'] = time();
        
        // Log successful brand fetch for audit trail (only if brands exist)
        if ($result['data']['count'] > 0) {
            error_log("Brands fetched successfully: {$result['data']['count']} brands for user {$user_id}");
        }
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'fetch_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
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
                $response['suggestion'] = 'Please refresh the page and try again.';
                break;
        }
        
        // Log the error with context for debugging
        error_log("Brand fetch failed for user {$user_id}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'user_id' => $user_id,
        'category_id' => $category_id,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Brand fetch exception: " . json_encode($exception_context));
    
    // Check if this might be a session-related exception
    if (strpos($e->getMessage(), 'session') !== false || 
        strpos($e->getMessage(), 'Session') !== false) {
        $response['status'] = 'error';
        $response['message'] = 'A session-related error occurred. Please log in again and try again.';
        $response['error_type'] = 'session_exception';
        $response['requires_action'] = 'redirect_to_login';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'An unexpected error occurred while retrieving brands. Please try again.';
        $response['error_type'] = 'server_exception';
        $response['suggestion'] = 'If the problem persists, please contact support.';
        $response['retry_recommended'] = true;
    }
}

echo json_encode($response);

?>