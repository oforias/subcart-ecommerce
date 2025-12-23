<?php

/**
 * Update Product Action
 * Handles product update requests with session/CSRF validation
 * Loads existing product data and manages image updates/replacements
 * Requirements: 4.1, 4.4, 6.4
 */

header('Content-Type: application/json');

session_start();

// Include core functions for authentication and CSRF protection
require_once '../settings/core.php';

$response = array();

// Enhanced session validation and error handling
$session_validation = validate_session_for_product_operations();
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

// Validate required input parameters
if (!isset($_POST['product_id']) || empty(trim($_POST['product_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['product_title']) || empty(trim($_POST['product_title']))) {
    $response['status'] = 'error';
    $response['message'] = 'Product title is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_title';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['product_price']) || empty(trim($_POST['product_price']))) {
    $response['status'] = 'error';
    $response['message'] = 'Product price is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_price';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['category_id']) || empty(trim($_POST['category_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Category selection is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'category_id';
    echo json_encode($response);
    exit();
}

if (!isset($_POST['brand_id']) || empty(trim($_POST['brand_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Brand selection is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_id';
    echo json_encode($response);
    exit();
}

// Extract and sanitize input data
$product_id = trim($_POST['product_id']);
$product_title = trim($_POST['product_title']);
$product_price = trim($_POST['product_price']);
$product_description = isset($_POST['product_description']) ? trim($_POST['product_description']) : '';
$product_keywords = isset($_POST['product_keywords']) ? trim($_POST['product_keywords']) : '';
$category_id = trim($_POST['category_id']);
$brand_id = trim($_POST['brand_id']);

// Handle image updates and replacements
$product_image = '';
$image_action = isset($_POST['image_action']) ? trim($_POST['image_action']) : 'keep'; // keep, update, remove

// Validate product ID is numeric
if (!is_numeric($product_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid product ID format.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

// Convert to integer and validate it's positive
$product_id = (int)$product_id;
if ($product_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID must be a positive number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

// Validate product title length (1-255 characters as per design)
if (strlen($product_title) < 1) {
    $response['status'] = 'error';
    $response['message'] = 'Product title cannot be empty.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_title';
    echo json_encode($response);
    exit();
}

if (strlen($product_title) > 255) {
    $response['status'] = 'error';
    $response['message'] = 'Product title must be 255 characters or less.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_title';
    echo json_encode($response);
    exit();
}

// Validate product title format (basic sanitization)
if (preg_match('/[<>"\']/', $product_title)) {
    $response['status'] = 'error';
    $response['message'] = 'Product title contains invalid characters.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_title';
    echo json_encode($response);
    exit();
}

// Validate product price is numeric
if (!is_numeric($product_price)) {
    $response['status'] = 'error';
    $response['message'] = 'Product price must be a valid number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_price';
    echo json_encode($response);
    exit();
}

// Convert to float and validate it's positive
$product_price = (float)$product_price;
if ($product_price < 0) {
    $response['status'] = 'error';
    $response['message'] = 'Product price must be a positive number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_price';
    echo json_encode($response);
    exit();
}

// Validate category ID is numeric
if (!is_numeric($category_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid category selection.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'category_id';
    echo json_encode($response);
    exit();
}

// Convert to integer and validate it's positive
$category_id = (int)$category_id;
if ($category_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Category selection is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'category_id';
    echo json_encode($response);
    exit();
}

// Validate brand ID is numeric
if (!is_numeric($brand_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid brand selection.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_id';
    echo json_encode($response);
    exit();
}

// Convert to integer and validate it's positive
$brand_id = (int)$brand_id;
if ($brand_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Brand selection is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'brand_id';
    echo json_encode($response);
    exit();
}

// Validate optional fields
if (!empty($product_keywords) && strlen($product_keywords) > 255) {
    $response['status'] = 'error';
    $response['message'] = 'Product keywords must be 255 characters or less.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_keywords';
    echo json_encode($response);
    exit();
}

// Validate image action
$valid_image_actions = ['keep', 'update', 'remove'];
if (!in_array($image_action, $valid_image_actions)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid image action specified.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'image_action';
    echo json_encode($response);
    exit();
}

require_once '../controllers/product_controller.php';

/**
 * Validate session for product operations with comprehensive security checks
 * @return array Validation result with detailed information
 */
function validate_session_for_product_operations()
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
        $validation_result['message'] = 'Authentication required. Please log in to manage products.';
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
    
    // Check if user has admin privileges (only admins can manage products)
    if (!has_admin_privileges()) {
        $validation_result['message'] = 'Access denied. Administrator privileges required to manage products.';
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

/**
 * Handle image updates and replacements based on image action
 * @param int $product_id Product ID
 * @param int $user_id User ID
 * @param string $image_action Action to perform (keep, update, remove)
 * @param string $current_image Current image path from existing product data
 * @return array Result with success status and new image path
 */
function handle_image_update($product_id, $user_id, $image_action, $current_image)
{
    $result = [
        'success' => false,
        'message' => '',
        'error_type' => '',
        'image_path' => ''
    ];
    
    switch ($image_action) {
        case 'keep':
            // Keep existing image
            $result['success'] = true;
            $result['image_path'] = $current_image;
            $result['message'] = 'Existing image retained.';
            break;
            
        case 'remove':
            // Remove existing image
            $result['success'] = true;
            $result['image_path'] = '';
            $result['message'] = 'Image removed.';
            
            // Optionally delete the physical file (if it exists and is within uploads directory)
            if (!empty($current_image) && strpos($current_image, 'uploads/') === 0) {
                $file_path = '../' . $current_image;
                if (file_exists($file_path)) {
                    // Additional security check: ensure file is within uploads directory
                    $real_file_path = realpath($file_path);
                    $real_uploads_path = realpath('../uploads');
                    
                    if ($real_file_path && $real_uploads_path && strpos($real_file_path, $real_uploads_path) === 0) {
                        unlink($file_path);
                        error_log("Deleted product image file: {$file_path} for product {$product_id}, user {$user_id}");
                    }
                }
            }
            break;
            
        case 'update':
            // Handle new image upload (coordinated with image upload processing)
            if (isset($_POST['new_product_image']) && !empty(trim($_POST['new_product_image']))) {
                $new_image_path = trim($_POST['new_product_image']);
                
                // Validate new image path (security check)
                if (strpos($new_image_path, 'uploads/') !== 0) {
                    $result['message'] = 'Invalid new image path provided.';
                    $result['error_type'] = 'validation_failed';
                    return $result;
                }
                
                // Additional security: check for path traversal attempts
                if (strpos($new_image_path, '..') !== false || strpos($new_image_path, '//') !== false) {
                    $result['message'] = 'Invalid new image path format.';
                    $result['error_type'] = 'validation_failed';
                    return $result;
                }
                
                $result['success'] = true;
                $result['image_path'] = $new_image_path;
                $result['message'] = 'Image updated successfully.';
                
                // Optionally remove old image file
                if (!empty($current_image) && $current_image !== $new_image_path && strpos($current_image, 'uploads/') === 0) {
                    $old_file_path = '../' . $current_image;
                    if (file_exists($old_file_path)) {
                        // Additional security check: ensure file is within uploads directory
                        $real_file_path = realpath($old_file_path);
                        $real_uploads_path = realpath('../uploads');
                        
                        if ($real_file_path && $real_uploads_path && strpos($real_file_path, $real_uploads_path) === 0) {
                            unlink($old_file_path);
                            error_log("Deleted old product image file: {$old_file_path} for product {$product_id}, user {$user_id}");
                        }
                    }
                }
            } else {
                $result['message'] = 'New image path is required when updating image.';
                $result['error_type'] = 'validation_failed';
                return $result;
            }
            break;
            
        default:
            $result['message'] = 'Invalid image action specified.';
            $result['error_type'] = 'validation_failed';
            return $result;
    }
    
    return $result;
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
            'Session expired while processing product update', 
            $user_id);
        
        echo json_encode($response);
        exit();
    }
    
    // First, load existing product data to verify ownership and get current values
    $existing_product_result = get_product_ctr($product_id, $user_id);
    
    if (!$existing_product_result['success']) {
        $response['status'] = 'error';
        $response['message'] = 'Failed to load existing product data: ' . $existing_product_result['error'];
        $response['error_type'] = $existing_product_result['error_type'];
        echo json_encode($response);
        exit();
    }
    
    $existing_product = $existing_product_result['data']['product'];
    if (!$existing_product) {
        $response['status'] = 'error';
        $response['message'] = 'Product not found or access denied.';
        $response['error_type'] = 'product_not_found';
        echo json_encode($response);
        exit();
    }
    
    // Handle image updates and replacements
    $image_result = handle_image_update($product_id, $user_id, $image_action, $existing_product['product_image']);
    
    if (!$image_result['success']) {
        $response['status'] = 'error';
        $response['message'] = $image_result['message'];
        $response['error_type'] = $image_result['error_type'];
        echo json_encode($response);
        exit();
    }
    
    $product_image = $image_result['image_path'];
    
    // Call controller function to update product with enhanced error handling
    $result = update_product_ctr($product_id, $product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id);
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = 'Product updated successfully.';
        $response['data'] = $result['data'];
        $response['image_action_result'] = $image_result['message'];
        
        // Update session activity timestamp after successful operation
        $_SESSION['last_activity'] = time();
        
        // Log successful product update for audit trail
        error_log("Product updated successfully: ID {$result['data']['product_id']}, Title '{$result['data']['product_title']}', Price {$result['data']['product_price']}, User {$user_id}, Image Action: {$image_action}");
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'update_failed';
        
        // Include additional error details if available
        if (isset($result['error_details'])) {
            $response['error_details'] = $result['error_details'];
        }
        
        // Handle specific error types with appropriate user guidance
        switch ($result['error_type']) {
            case 'duplicate_entry':
                $response['field'] = 'product_title';
                $response['suggestion'] = 'A product with this title may already exist. Try a different title.';
                break;
                
            case 'category_not_found':
                $response['field'] = 'category_id';
                $response['suggestion'] = 'Please select a valid category.';
                break;
                
            case 'brand_not_found':
                $response['field'] = 'brand_id';
                $response['suggestion'] = 'Please select a valid brand.';
                break;
                
            case 'foreign_key_constraint':
                $response['suggestion'] = 'The selected category or brand is not valid. Please refresh the page and try again.';
                break;
                
            case 'not_found':
                $response['suggestion'] = 'The product may have been deleted or you may not have access to it.';
                break;
                
            case 'no_changes':
                $response['suggestion'] = 'No changes were detected. The product data may be unchanged.';
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
        error_log("Product update failed for user {$user_id}, product {$product_id}: {$result['error']} (Type: {$result['error_type']})");
    }
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'product_title' => $product_title,
        'product_price' => $product_price,
        'category_id' => $category_id,
        'brand_id' => $brand_id,
        'image_action' => $image_action,
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Product update exception: " . json_encode($exception_context));
    
    // Check if this might be a session-related exception
    if (strpos($e->getMessage(), 'session') !== false || 
        strpos($e->getMessage(), 'Session') !== false) {
        $response['status'] = 'error';
        $response['message'] = 'A session-related error occurred. Please log in again and try again.';
        $response['error_type'] = 'session_exception';
        $response['requires_action'] = 'redirect_to_login';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'An unexpected error occurred while updating the product. Please try again.';
        $response['error_type'] = 'server_exception';
        $response['suggestion'] = 'If the problem persists, please contact support.';
        $response['retry_recommended'] = true;
    }
}

echo json_encode($response);

?>