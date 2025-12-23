<?php

/**
 * Update Product Image Path Action
 * Updates a product's image path after image upload
 * Returns JSON response with success/error status
 */

header('Content-Type: application/json');

session_start();

// Include core functions for authentication and CSRF protection
require_once '../settings/core.php';

$response = array();

// Check if user is logged in
if (!is_logged_in()) {
    $response['status'] = 'error';
    $response['message'] = 'Authentication required. Please log in to update products.';
    $response['error_type'] = 'authentication_required';
    echo json_encode($response);
    exit();
}

// Check if user has admin privileges
if (!has_admin_privileges()) {
    $response['status'] = 'error';
    $response['message'] = 'Access denied. Administrator privileges required.';
    $response['error_type'] = 'insufficient_privileges';
    echo json_encode($response);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. POST required.';
    $response['error_type'] = 'invalid_method';
    echo json_encode($response);
    exit();
}

// Validate CSRF token
if (!validate_csrf_from_post()) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid security token. Please refresh the page and try again.';
    $response['error_type'] = 'csrf_validation_failed';
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
    echo json_encode($response);
    exit();
}

if (!isset($_POST['image_path']) || empty(trim($_POST['image_path']))) {
    $response['status'] = 'error';
    $response['message'] = 'Image path is required.';
    $response['error_type'] = 'validation_failed';
    echo json_encode($response);
    exit();
}

$product_id = (int)trim($_POST['product_id']);
$image_path = trim($_POST['image_path']);

// Validate product ID
if ($product_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Invalid product ID.';
    $response['error_type'] = 'validation_failed';
    echo json_encode($response);
    exit();
}

require_once '../controllers/product_controller.php';

try {
    // Get current product data to preserve other fields
    $current_product_result = get_product_ctr($product_id, $user_id);
    
    if (!$current_product_result['success']) {
        $response['status'] = 'error';
        $response['message'] = 'Product not found or access denied.';
        $response['error_type'] = 'product_not_found';
        echo json_encode($response);
        exit();
    }
    
    $current_product = $current_product_result['data']['product'];
    
    // Update product with new image path
    $result = update_product_ctr(
        $product_id,
        $current_product['product_title'],
        $current_product['product_price'],
        $current_product['product_description'],
        $image_path, // New image path
        $current_product['product_keywords'],
        $current_product['category_id'],
        $current_product['brand_id'],
        $user_id
    );
    
    if ($result['success']) {
        $response['status'] = 'success';
        $response['message'] = 'Product image updated successfully';
        $response['data'] = [
            'product_id' => $product_id,
            'image_path' => $image_path
        ];
        
        // Update session activity timestamp
        $_SESSION['last_activity'] = time();
        
    } else {
        $response['status'] = 'error';
        $response['message'] = $result['error'];
        $response['error_type'] = $result['error_type'] ?? 'update_failed';
    }
    
} catch (Exception $e) {
    error_log("Product image path update exception: " . $e->getMessage());
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred while updating the product image.';
    $response['error_type'] = 'server_exception';
}

echo json_encode($response);

?>