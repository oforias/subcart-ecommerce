<?php

/**
 * Cleanup Orphaned Data Action
 * Scheduled cleanup utility for orphaned cart items and data integrity maintenance
 * Requirements: 8.4, 4.4
 */

header('Content-Type: application/json');

// Include required classes
require_once '../classes/cart_integrity_class.php';

$response = array();

// This script can be run via cron job or manual execution
// For security, check if it's being run from command line or with proper authentication
$is_cli = php_sapi_name() === 'cli';
$is_authenticated = false;

if (!$is_cli) {
    // Check for admin authentication or API key
    session_start();
    
    // Simple authentication check - in production, use proper admin authentication
    $api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? null;
    $admin_key = 'cleanup_admin_key_2024'; // In production, use environment variable
    
    if ($api_key === $admin_key || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')) {
        $is_authenticated = true;
    }
}

if (!$is_cli && !$is_authenticated) {
    $response['status'] = 'error';
    $response['message'] = 'Unauthorized access. Admin authentication required.';
    $response['error_type'] = 'authentication_required';
    echo json_encode($response);
    exit();
}

// Get cleanup options
$cleanup_options = [
    'remove_orphaned' => ($_GET['remove_orphaned'] ?? $_POST['remove_orphaned'] ?? 'true') === 'true',
    'fix_quantities' => ($_GET['fix_quantities'] ?? $_POST['fix_quantities'] ?? 'true') === 'true',
    'merge_duplicates' => ($_GET['merge_duplicates'] ?? $_POST['merge_duplicates'] ?? 'true') === 'true',
    'cleanup_expired_guests' => ($_GET['cleanup_expired_guests'] ?? $_POST['cleanup_expired_guests'] ?? 'true') === 'true',
    'guest_expiry_hours' => (int)($_GET['guest_expiry_hours'] ?? $_POST['guest_expiry_hours'] ?? 24)
];

// Validate guest expiry hours
if ($cleanup_options['guest_expiry_hours'] < 1 || $cleanup_options['guest_expiry_hours'] > 168) {
    $cleanup_options['guest_expiry_hours'] = 24; // Default to 24 hours
}

try {
    // Create cart integrity instance
    $cart_integrity = new CartIntegrity();
    
    $cleanup_results = [];
    $total_items_processed = 0;
    $total_errors = 0;
    
    // 1. Global orphaned data cleanup
    if ($cleanup_options['remove_orphaned'] || $cleanup_options['fix_quantities'] || $cleanup_options['merge_duplicates']) {
        $global_fix_result = $cart_integrity->fix_cart_integrity_issues(null, null, $cleanup_options);
        
        if ($global_fix_result['success']) {
            $cleanup_results['global_integrity_fix'] = $global_fix_result['data'];
            $total_items_processed += array_sum(array_column($global_fix_result['data']['fixes_applied'], 'items_affected'));
            $total_errors += $global_fix_result['data']['total_errors'];
        } else {
            $cleanup_results['global_integrity_fix'] = [
                'status' => 'failed',
                'error' => $global_fix_result['error_message']
            ];
            $total_errors++;
        }
    }
    
    // 2. Cleanup expired guest carts (if enabled)
    if ($cleanup_options['cleanup_expired_guests']) {
        // This would require additional implementation in cart class
        // For now, we'll log that this feature is planned
        $cleanup_results['expired_guest_cleanup'] = [
            'status' => 'planned',
            'message' => 'Expired guest cart cleanup feature is planned for future implementation',
            'expiry_hours' => $cleanup_options['guest_expiry_hours']
        ];
    }
    
    // 3. Generate cleanup statistics
    $stats_start_time = microtime(true);
    
    // Get overall cart statistics
    $integrity_check = $cart_integrity->verify_cart_integrity();
    
    $stats_end_time = microtime(true);
    $stats_duration = round(($stats_end_time - $stats_start_time) * 1000, 2); // milliseconds
    
    if ($integrity_check['success']) {
        $cleanup_results['post_cleanup_integrity'] = $integrity_check['data'];
        $cleanup_results['post_cleanup_integrity']['check_duration_ms'] = $stats_duration;
    }
    
    // Prepare response
    $response['status'] = 'success';
    $response['message'] = "Cleanup completed. Processed {$total_items_processed} items with {$total_errors} errors.";
    $response['data'] = [
        'cleanup_options' => $cleanup_options,
        'results' => $cleanup_results,
        'summary' => [
            'total_items_processed' => $total_items_processed,
            'total_errors' => $total_errors,
            'cleanup_successful' => $total_errors === 0,
            'execution_time' => date('Y-m-d H:i:s'),
            'execution_mode' => $is_cli ? 'cli' : 'web'
        ]
    ];
    
    // Log cleanup operation
    $log_message = "Orphaned data cleanup completed: {$total_items_processed} items processed, {$total_errors} errors";
    error_log($log_message);
    
    // If running from CLI, also output to console
    if ($is_cli) {
        echo "Cleanup Results:\n";
        echo "- Items processed: {$total_items_processed}\n";
        echo "- Errors: {$total_errors}\n";
        echo "- Status: " . ($total_errors === 0 ? 'SUCCESS' : 'COMPLETED WITH ERRORS') . "\n";
        
        if (isset($cleanup_results['post_cleanup_integrity'])) {
            $integrity = $cleanup_results['post_cleanup_integrity'];
            echo "- Post-cleanup integrity: {$integrity['integrity_status']}\n";
            echo "- Issues found: {$integrity['total_issues']}\n";
        }
    }
    
} catch (Exception $e) {
    // Enhanced exception handling
    $exception_context = [
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'cleanup_options' => $cleanup_options,
        'execution_mode' => $is_cli ? 'cli' : 'web'
    ];
    
    // Log the detailed exception
    error_log("Orphaned data cleanup exception: " . json_encode($exception_context));
    
    $response['status'] = 'error';
    $response['message'] = 'An unexpected error occurred during cleanup operation.';
    $response['error_type'] = 'server_exception';
    $response['error_details'] = [
        'exception_message' => $e->getMessage(),
        'exception_code' => $e->getCode(),
        'cleanup_options' => $cleanup_options
    ];
    
    // If running from CLI, also output to console
    if ($is_cli) {
        echo "ERROR: Cleanup failed with exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Output response (for web requests)
if (!$is_cli) {
    echo json_encode($response, JSON_PRETTY_PRINT);
}

?>