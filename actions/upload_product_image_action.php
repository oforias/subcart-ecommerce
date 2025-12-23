<?php

/**
 * Upload Product Image Action
 * Handles secure product image upload requests
 * Validates files and creates user/product directory structure
 * Returns JSON response with success/error status
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
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

// Validate required parameters
if (!isset($_POST['product_id']) || empty(trim($_POST['product_id']))) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID is required.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

$product_id = trim($_POST['product_id']);

// Validate product ID is numeric
if (!is_numeric($product_id)) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid product ID format.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

$product_id = (int)$product_id;

// Validate product ID is positive
if ($product_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Product ID must be a positive number.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_id';
    echo json_encode($response);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] === UPLOAD_ERR_NO_FILE) {
    $response['status'] = 'error';
    $response['message'] = 'No image file was uploaded.';
    $response['error_type'] = 'validation_failed';
    $response['field'] = 'product_image';
    echo json_encode($response);
    exit();
}

$uploaded_file = $_FILES['product_image'];

// Validate file upload errors
if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit.',
        UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
    ];
    
    $error_message = $upload_errors[$uploaded_file['error']] ?? 'Unknown upload error.';
    
    $response['status'] = 'error';
    $response['message'] = $error_message;
    $response['error_type'] = 'upload_error';
    $response['upload_error_code'] = $uploaded_file['error'];
    echo json_encode($response);
    exit();
}

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
 * Validate uploaded file for security and format requirements
 * @param array $file Uploaded file array from $_FILES
 * @return array Validation result with success status and details
 */
function validate_uploaded_file($file)
{
    $validation_result = [
        'valid' => false,
        'message' => '',
        'error_type' => '',
        'file_info' => []
    ];
    
    // Check file size (max 5MB)
    $max_file_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_file_size) {
        $validation_result['message'] = 'File size exceeds maximum limit of 5MB.';
        $validation_result['error_type'] = 'file_too_large';
        $validation_result['file_info']['size'] = $file['size'];
        $validation_result['file_info']['max_size'] = $max_file_size;
        return $validation_result;
    }
    
    // Check if file is empty
    if ($file['size'] <= 0) {
        $validation_result['message'] = 'Uploaded file is empty.';
        $validation_result['error_type'] = 'empty_file';
        $validation_result['file_info']['size'] = $file['size'];
        return $validation_result;
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $validation_result['message'] = 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.';
        $validation_result['error_type'] = 'invalid_file_type';
        $validation_result['file_info']['extension'] = $file_extension;
        $validation_result['file_info']['allowed_extensions'] = $allowed_extensions;
        return $validation_result;
    }
    
    // Validate MIME type for additional security
    $allowed_mime_types = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    $file_mime_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_mime_type, $allowed_mime_types)) {
        $validation_result['message'] = 'Invalid file format detected. File may be corrupted or not a valid image.';
        $validation_result['error_type'] = 'invalid_mime_type';
        $validation_result['file_info']['mime_type'] = $file_mime_type;
        $validation_result['file_info']['allowed_mime_types'] = $allowed_mime_types;
        return $validation_result;
    }
    
    // Validate image dimensions (optional - can be configured)
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $validation_result['message'] = 'File is not a valid image or is corrupted.';
        $validation_result['error_type'] = 'invalid_image';
        return $validation_result;
    }
    
    $max_width = 2048;
    $max_height = 2048;
    
    if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
        $validation_result['message'] = "Image dimensions exceed maximum allowed size of {$max_width}x{$max_height} pixels.";
        $validation_result['error_type'] = 'image_too_large';
        $validation_result['file_info']['width'] = $image_info[0];
        $validation_result['file_info']['height'] = $image_info[1];
        $validation_result['file_info']['max_width'] = $max_width;
        $validation_result['file_info']['max_height'] = $max_height;
        return $validation_result;
    }
    
    // All validations passed
    $validation_result['valid'] = true;
    $validation_result['file_info'] = [
        'extension' => $file_extension,
        'mime_type' => $file_mime_type,
        'size' => $file['size'],
        'width' => $image_info[0],
        'height' => $image_info[1]
    ];
    
    return $validation_result;
}

/**
 * Create secure directory structure for user and product
 * @param int $user_id User ID
 * @param int $product_id Product ID
 * @return array Result with success status and directory path
 */
function create_secure_directory_structure($user_id, $product_id)
{
    $result = [
        'success' => false,
        'message' => '',
        'error_type' => '',
        'directory_path' => '',
        'full_path' => ''
    ];
    
    // Define base uploads directory
    $base_uploads_dir = '../uploads';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($base_uploads_dir)) {
        if (!mkdir($base_uploads_dir, 0755, true)) {
            $result['message'] = 'Failed to create uploads directory.';
            $result['error_type'] = 'directory_creation_failed';
            return $result;
        }
    }
    
    // Validate that we're creating directories only within uploads
    $real_base_path = realpath($base_uploads_dir);
    if ($real_base_path === false) {
        $result['message'] = 'Invalid uploads directory path.';
        $result['error_type'] = 'invalid_base_path';
        return $result;
    }
    
    // Create user directory structure: uploads/u{user_id}/
    $user_dir = $base_uploads_dir . '/u' . $user_id;
    if (!file_exists($user_dir)) {
        if (!mkdir($user_dir, 0755, true)) {
            $result['message'] = 'Failed to create user directory.';
            $result['error_type'] = 'user_directory_creation_failed';
            return $result;
        }
    }
    
    // Create product directory structure: uploads/u{user_id}/p{product_id}/
    $product_dir = $user_dir . '/p' . $product_id;
    if (!file_exists($product_dir)) {
        if (!mkdir($product_dir, 0755, true)) {
            $result['message'] = 'Failed to create product directory.';
            $result['error_type'] = 'product_directory_creation_failed';
            return $result;
        }
    }
    
    // Validate that the created directory is within the uploads directory (security check)
    $real_product_path = realpath($product_dir);
    if ($real_product_path === false || strpos($real_product_path, $real_base_path) !== 0) {
        $result['message'] = 'Security violation: Directory path outside uploads directory.';
        $result['error_type'] = 'path_traversal_attempt';
        
        // Log security event
        log_session_security_event('path_traversal_attempt', 
            "Attempted to create directory outside uploads: {$product_dir}", 
            $user_id);
        
        return $result;
    }
    
    $result['success'] = true;
    $result['directory_path'] = 'uploads/u' . $user_id . '/p' . $product_id;
    $result['full_path'] = $product_dir;
    
    return $result;
}

/**
 * Generate secure filename to prevent conflicts and security issues
 * @param string $original_filename Original uploaded filename
 * @param string $file_extension File extension
 * @return string Secure filename
 */
function generate_secure_filename($original_filename, $file_extension)
{
    // Remove any path information from original filename
    $safe_basename = basename($original_filename, '.' . $file_extension);
    
    // Remove special characters and limit length
    $safe_basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $safe_basename);
    $safe_basename = substr($safe_basename, 0, 50); // Limit to 50 characters
    
    // Add timestamp and random component for uniqueness
    $timestamp = time();
    $random_component = bin2hex(random_bytes(4)); // 8 character random string
    
    // Construct secure filename
    $secure_filename = $safe_basename . '_' . $timestamp . '_' . $random_component . '.' . $file_extension;
    
    return $secure_filename;
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
            'Session expired while processing image upload', 
            $user_id);
        
        echo json_encode($response);
        exit();
    }
    
    // Validate uploaded file
    $file_validation = validate_uploaded_file($uploaded_file);
    if (!$file_validation['valid']) {
        $response['status'] = 'error';
        $response['message'] = $file_validation['message'];
        $response['error_type'] = $file_validation['error_type'];
        $response['file_info'] = $file_validation['file_info'];
        echo json_encode($response);
        exit();
    }
    
    // Verify product exists and belongs to user
    require_once '../classes/product_class.php';
    $product_obj = new Product();
    $product_check = $product_obj->get_product_by_id($product_id, $user_id);
    
    if (!$product_check['success']) {
        $response['status'] = 'error';
        $response['message'] = 'Failed to verify product ownership: ' . $product_check['error_message'];
        $response['error_type'] = 'product_verification_failed';
        echo json_encode($response);
        exit();
    }
    
    if (!$product_check['data']['product']) {
        $response['status'] = 'error';
        $response['message'] = 'Product not found or access denied.';
        $response['error_type'] = 'product_not_found';
        echo json_encode($response);
        exit();
    }
    
    // Create secure directory structure
    $directory_result = create_secure_directory_structure($user_id, $product_id);
    if (!$directory_result['success']) {
        $response['status'] = 'error';
        $response['message'] = $directory_result['message'];
        $response['error_type'] = $directory_result['error_type'];
        echo json_encode($response);
        exit();
    }
    
    // Generate secure filename
    $file_extension = $file_validation['file_info']['extension'];
    $secure_filename = generate_secure_filename($uploaded_file['name'], $file_extension);
    $target_file_path = $directory_result['full_path'] . '/' . $secure_filename;
    
    // Move uploaded file to secure location
    if (!move_uploaded_file($uploaded_file['tmp_name'], $target_file_path)) {
        $response['status'] = 'error';
        $response['message'] = 'Failed to save uploaded file.';
        $response['error_type'] = 'file_move_failed';
        echo json_encode($response);
        exit();
    }
    
    // Generate relative path for database storage
    $relative_path = $directory_result['directory_path'] . '/' . $secure_filename;
    
    // Success response
    $response['status'] = 'success';
    $response['message'] = 'Image uploaded successfully.';
    $response['data'] = [
        'product_id' => $product_id,
        'user_id' => $user_id,
        'filename' => $secure_filename,
        'relative_path' => $relative_path,
        'file_size' => $uploaded_file['size'],
        'file_type' => $file_validation['file_info']['mime_type'],
        'dimensions' => [
            'width' => $file_validation['file_info']['width'],
            'height' => $file_validation['file_info']['height']
        ]
    ];
    
    // Update session activity timestamp after successful operation
    $_SESSION['last_activity'] = time();
    
    // Log successful image upload for audit trail
    error_log("Product image uploaded successfully: Product {$product_id}, User {$user_id}, File: {$secure_filename}");
    
} catch (Exception $e) {
    // Enhanced exception handling with detailed logging
    $exception_context = [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'filename' => $uploaded_file['name'] ?? 'unknown',
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Log the detailed exception for debugging
    error_log("Product image upload exception: " . json_encode($exception_context));
    
    // Check if this might be a session-related exception
    if (strpos($e->getMessage(), 'session') !== false || 
        strpos($e->getMessage(), 'Session') !== false) {
        $response['status'] = 'error';
        $response['message'] = 'A session-related error occurred. Please log in again and try again.';
        $response['error_type'] = 'session_exception';
        $response['requires_action'] = 'redirect_to_login';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'An unexpected error occurred while uploading the image. Please try again.';
        $response['error_type'] = 'server_exception';
        $response['suggestion'] = 'If the problem persists, please contact support.';
        $response['retry_recommended'] = true;
    }
}

echo json_encode($response);

?>