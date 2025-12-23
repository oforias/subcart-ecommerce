<?php
// Session configuration for security
// Only configure and start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings only if headers haven't been sent
    if (!headers_sent()) {
        @ini_set('session.cookie_httponly', 1);
        @ini_set('session.cookie_secure', 1);
        @ini_set('session.use_strict_mode', 1);
        @ini_set('session.cookie_samesite', 'Strict');
    }
    
    // Start session only if headers haven't been sent
    if (!headers_sent()) {
        session_start();
    }
}

// User role enumeration constants
define('USER_ROLE_ADMIN', 0);
define('USER_ROLE_CUSTOMER', 1);

// Role validation constants
define('VALID_USER_ROLES', [USER_ROLE_ADMIN, USER_ROLE_CUSTOMER]);

//for header redirection
ob_start();

// Session timeout configuration (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Initialize secure session if user is logged in
if (session_status() === PHP_SESSION_ACTIVE && (isset($_SESSION['customer_id']) || isset($_SESSION['user_id']) || isset($_SESSION['id']))) {
    initialize_secure_session();
}

/**
 * Check if a user is logged in by checking if a session has been created
 * Simplified to prevent recursive loops and memory exhaustion
 * @return bool Returns true if a valid session exists, false otherwise
 */
function is_logged_in()
{
    // Basic session checks without recursive calls
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Check if customer_id exists (supporting both new and old naming conventions)
    if (!isset($_SESSION['customer_id']) && !isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
        return false;
    }
    
    // Basic timeout check
    if (isset($_SESSION['last_activity'])) {
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800; // 30 minutes default
        if ((time() - $_SESSION['last_activity']) > $timeout) {
            return false;
        }
    }
    
    // Validate customer ID format (supporting both new and old naming conventions)
    $customer_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if (!is_numeric($customer_id) || $customer_id <= 0) {
        return false;
    }
    
    return true;
}

/**
 * Check if a user has administrative privileges by checking the user's role in the session array
 * Simplified to prevent recursive loops and memory exhaustion
 * @return bool Returns true if user has admin privileges, false otherwise
 */
function has_admin_privileges()
{
    // Check if user is logged in first
    if (!is_logged_in()) {
        return false;
    }
    
    // Get user role directly from session (supporting both new and old naming conventions)
    $user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
    
    // Check if role matches admin role (0 = admin)
    if ($user_role === 0 || $user_role === '0' || $user_role === USER_ROLE_ADMIN) {
        return true;
    }
    
    return false;
}

/**
 * Initialize session with security settings and timeout tracking
 * @return void
 */
function initialize_secure_session()
{
    // Set session timeout tracking
    if (!isset($_SESSION['login_time'])) {
        $_SESSION['login_time'] = time();
    }
    $_SESSION['last_activity'] = time();
    
    // Generate CSRF token if not exists or regenerate on login
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_csrf_token();
    }
    
    // Initialize session security tracking
    if (!isset($_SESSION['session_initialized'])) {
        $_SESSION['session_initialized'] = time();
        $_SESSION['session_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['session_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Check if session has expired based on timeout settings
 * @return bool Returns true if session has expired, false otherwise
 */
function is_session_expired()
{
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    return (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT;
}

/**
 * Clean up expired session data
 * @return void
 */
function cleanup_expired_session()
{
    if (is_session_expired()) {
        destroy_user_session();
    } else {
        // Update last activity time
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Destroy user session and clean up all session data
 * @return void
 */
function destroy_user_session()
{
    // Log session destruction for security monitoring
    $user_id = get_current_user_id();
    if ($user_id) {
        error_log("Session destroyed for user: " . $user_id);
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Generate CSRF token for form protection
 * @return string Returns a secure random token
 */
function generate_csrf_token()
{
    return bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token against session token
 * @param string $token The token to validate
 * @return bool Returns true if token is valid, false otherwise
 */
function validate_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token from session, generating one if it doesn't exist
 * @return string Returns the current CSRF token
 */
function get_csrf_token()
{
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_csrf_token();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Generate HTML hidden input field for CSRF token
 * @return string Returns HTML input field with CSRF token
 */
function csrf_token_field()
{
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST data
 * @return bool Returns true if CSRF token is valid, false otherwise
 */
function validate_csrf_from_post()
{
    $token = $_POST['csrf_token'] ?? '';
    return validate_csrf_token($token);
}

/**
 * Regenerate CSRF token for enhanced security
 * @return string Returns the new CSRF token
 */
function regenerate_csrf_token()
{
    $_SESSION['csrf_token'] = generate_csrf_token();
    return $_SESSION['csrf_token'];
}

/**
 * Check if request requires CSRF validation
 * @return bool Returns true if CSRF validation is required, false otherwise
 */
function requires_csrf_validation()
{
    // CSRF validation required for POST, PUT, DELETE requests
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    return in_array($method, ['POST', 'PUT', 'DELETE'], true);
}

/**
 * Enforce CSRF protection for forms
 * Validates CSRF token and terminates execution if invalid
 * @param string $error_message Custom error message for CSRF failure
 * @return void
 */
function enforce_csrf_protection($error_message = 'Invalid security token. Please refresh the page and try again.')
{
    if (requires_csrf_validation() && !validate_csrf_from_post()) {
        // Log CSRF attack attempt
        error_log("CSRF attack attempt detected from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Return JSON error for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => $error_message,
                'error_type' => 'csrf_validation_failed'
            ]);
            exit();
        }
        
        // Redirect with error for regular form submissions
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php') . '?error=' . urlencode($error_message));
        exit();
    }
}

/**
 * Validate session integrity and security
 * @return bool Returns true if session is valid and secure, false otherwise
 */
function validate_session_integrity()
{
    // Check if session exists
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Check for required session data structure (supporting customer_id first)
    if (!isset($_SESSION['customer_id']) && !isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
        return false;
    }
    
    // Check session timeout
    if (is_session_expired()) {
        return false;
    }
    
    // Validate session data types (check customer_id first)
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if (!is_numeric($user_id) || $user_id <= 0) {
        return false;
    }
    
    return true;
}

/**
 * Regenerate session ID for security (prevents session fixation)
 * @param bool $delete_old_session Whether to delete the old session file
 * @return bool Returns true on success, false on failure
 */
function regenerate_session_id($delete_old_session = true)
{
    // Only regenerate if session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Store current session data
    $session_data = $_SESSION;
    
    // Regenerate session ID
    $result = session_regenerate_id($delete_old_session);
    
    if ($result) {
        // Update regeneration timestamp
        $_SESSION['last_regeneration'] = time();
        
        // Log successful regeneration for security monitoring
        error_log("Session ID regenerated for user: " . (get_current_user_id() ?? 'anonymous'));
    }
    
    return $result;
}

/**
 * Check if session needs regeneration based on time or security criteria
 * @return bool Returns true if session should be regenerated, false otherwise
 */
function should_regenerate_session()
{
    // Regenerate if no regeneration timestamp exists
    if (!isset($_SESSION['last_regeneration'])) {
        return true;
    }
    
    // Regenerate every 30 minutes for security
    $regeneration_interval = 1800; // 30 minutes
    $time_since_regeneration = time() - $_SESSION['last_regeneration'];
    
    if ($time_since_regeneration > $regeneration_interval) {
        return true;
    }
    
    // Regenerate if session is approaching timeout
    if (isset($_SESSION['last_activity'])) {
        $time_until_timeout = SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
        // Regenerate if less than 5 minutes until timeout
        if ($time_until_timeout < 300 && $time_until_timeout > 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Perform periodic session regeneration for enhanced security
 * @return bool Returns true if regeneration occurred, false otherwise
 */
function perform_periodic_regeneration()
{
    if (!is_logged_in()) {
        return false;
    }
    
    if (should_regenerate_session()) {
        return regenerate_session_id();
    }
    
    return false;
}

/**
 * Regenerate session on login to prevent session fixation attacks
 * @return bool Returns true on success, false on failure
 */
function regenerate_session_on_login()
{
    // Always regenerate session ID on login
    $result = regenerate_session_id(true);
    
    if ($result) {
        // Mark as login regeneration
        $_SESSION['login_regeneration'] = time();
        
        // Log security event
        error_log("Session regenerated on login for user: " . get_current_user_id());
    }
    
    return $result;
}

/**
 * Implement session fixation protection
 * @return bool Returns true if protection is active, false otherwise
 */
function protect_against_session_fixation()
{
    // Check if this is a new session without proper initialization
    if (!isset($_SESSION['session_initialized'])) {
        // This might be a session fixation attempt
        if (isset($_SESSION['user_id']) || isset($_SESSION['id'])) {
            // User data exists but session not properly initialized - potential attack
            error_log("Potential session fixation attack detected - destroying session");
            destroy_user_session();
            return false;
        }
        
        // Mark session as properly initialized
        $_SESSION['session_initialized'] = time();
        $_SESSION['session_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['session_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    // Validate session consistency
    return validate_session_consistency();
}

/**
 * Validate session consistency to detect hijacking attempts
 * @return bool Returns true if session is consistent, false otherwise
 */
function validate_session_consistency()
{
    // Check IP address consistency (optional - can be disabled for mobile users)
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $session_ip = $_SESSION['session_ip'] ?? null;
    
    // For now, we'll log IP changes but not block them (mobile users change IPs)
    if ($session_ip && $session_ip !== $current_ip) {
        error_log("Session IP changed from {$session_ip} to {$current_ip} for user: " . get_current_user_id());
        // Update IP but don't block - just log for monitoring
        $_SESSION['session_ip'] = $current_ip;
    }
    
    // Check User-Agent consistency (more strict)
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_user_agent = $_SESSION['session_user_agent'] ?? null;
    
    if ($session_user_agent && $session_user_agent !== $current_user_agent) {
        error_log("Session User-Agent changed - potential session hijacking for user: " . get_current_user_id());
        // This is more suspicious - destroy session
        destroy_user_session();
        return false;
    }
    
    return true;
}

/**
 * Enhanced session security with automatic regeneration
 * @return bool Returns true if session is secure, false otherwise
 */
function ensure_session_security()
{
    if (!is_logged_in()) {
        return false;
    }
    
    // Protect against session fixation
    if (!protect_against_session_fixation()) {
        return false;
    }
    
    // Perform periodic regeneration
    perform_periodic_regeneration();
    
    // Validate session integrity
    return validate_session_integrity();
}

/**
 * Get current user ID from session with validation
 * Enhanced authentication state management function
 * @return int|null Returns user ID if valid session exists, null otherwise
 */
function get_current_user_id()
{
    if (!is_logged_in()) {
        return null;
    }
    
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    
    // Additional validation for user ID
    if (!is_numeric($user_id) || $user_id <= 0) {
        return null;
    }
    
    return (int)$user_id;
}

/**
 * Get current user role from session with validation
 * Enhanced authentication state management function
 * @return int|null Returns user role if valid session exists, null otherwise
 */
function get_current_user_role()
{
    if (!is_logged_in()) {
        return null;
    }
    
    $user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    
    // Validate role data
    if ($user_role === null || (!is_numeric($user_role) && !is_string($user_role))) {
        return null;
    }
    
    return is_numeric($user_role) ? (int)$user_role : $user_role;
}

/**
 * Get current user email from session with validation
 * Enhanced authentication state management function
 * @return string|null Returns user email if valid session exists, null otherwise
 */
function get_current_user_email()
{
    if (!is_logged_in()) {
        return null;
    }
    
    $email = $_SESSION['email'] ?? null;
    
    // Validate email format if present
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    
    return $email;
}

/**
 * Check if current session has valid authentication data structure
 * Enhanced login state detection with comprehensive validation
 * @return bool Returns true if session has proper authentication structure, false otherwise
 */
function has_valid_authentication_structure()
{
    if (!is_logged_in()) {
        return false;
    }
    
    // Check for required authentication fields
    $user_id = get_current_user_id();
    if ($user_id === null) {
        return false;
    }
    
    // Check for login timestamp
    if (!isset($_SESSION['login_time']) || !is_numeric($_SESSION['login_time'])) {
        return false;
    }
    
    // Check for last activity timestamp
    if (!isset($_SESSION['last_activity']) || !is_numeric($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check for CSRF token
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return true;
}

/**
 * Validate and sanitize session data for security
 * Enhanced session data validation utility
 * @return bool Returns true if session data is valid and sanitized, false otherwise
 */
function validate_and_sanitize_session_data()
{
    if (!is_logged_in()) {
        return false;
    }
    
    $is_valid = true;
    
    // Validate and sanitize user ID (check customer_id first)
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if ($user_id !== null) {
        if (!is_numeric($user_id) || $user_id <= 0) {
            $is_valid = false;
        } else {
            // Ensure user_id is stored as integer
            $_SESSION['user_id'] = (int)$user_id;
            // Remove duplicate id field if exists
            if (isset($_SESSION['id']) && isset($_SESSION['user_id'])) {
                unset($_SESSION['id']);
            }
        }
    }
    
    // Validate and sanitize role
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    if ($role !== null) {
        if (is_numeric($role)) {
            $_SESSION['role'] = (int)$role;
            // Remove duplicate user_role field if exists
            if (isset($_SESSION['user_role']) && isset($_SESSION['role'])) {
                unset($_SESSION['user_role']);
            }
        } elseif (!is_string($role)) {
            $is_valid = false;
        }
    }
    
    // Validate and sanitize email
    $email = $_SESSION['email'] ?? null;
    if ($email !== null) {
        $sanitized_email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($sanitized_email, FILTER_VALIDATE_EMAIL)) {
            unset($_SESSION['email']); // Remove invalid email
        } else {
            $_SESSION['email'] = $sanitized_email;
        }
    }
    
    // Validate timestamps
    $login_time = $_SESSION['login_time'] ?? null;
    if ($login_time !== null && !is_numeric($login_time)) {
        $_SESSION['login_time'] = time(); // Reset to current time if invalid
    }
    
    $last_activity = $_SESSION['last_activity'] ?? null;
    if ($last_activity !== null && !is_numeric($last_activity)) {
        $_SESSION['last_activity'] = time(); // Reset to current time if invalid
    }
    
    return $is_valid;
}

/**
 * Enhanced error handling for invalid sessions
 * Handles corrupted session data gracefully with comprehensive logging
 * @return array Returns array with error status and message
 */
function handle_session_error()
{
    $error_info = [
        'has_error' => false,
        'error_type' => null,
        'message' => null,
        'action_taken' => null,
        'user_id' => null,
        'timestamp' => time()
    ];
    
    // Get user ID for logging before potential session destruction (check customer_id first)
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    $error_info['user_id'] = $user_id;
    
    // Check if session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $error_info['has_error'] = true;
        $error_info['error_type'] = 'session_inactive';
        $error_info['message'] = 'Session is not active';
        log_session_security_event('session_inactive', 'Session not active during validation', $user_id);
        return $error_info;
    }
    
    // Check for session timeout with graceful handling
    if (is_session_expired()) {
        $error_info['has_error'] = true;
        $error_info['error_type'] = 'session_expired';
        $error_info['message'] = 'Session has expired';
        $error_info['action_taken'] = 'session_destroyed';
        
        // Log timeout event before destroying session
        $timeout_duration = isset($_SESSION['last_activity']) ? (time() - $_SESSION['last_activity']) : 'unknown';
        log_session_security_event('session_timeout', "Session expired after {$timeout_duration} seconds of inactivity", $user_id);
        
        // Graceful session timeout handling
        handle_session_timeout_gracefully();
        return $error_info;
    }
    
    // Check for corrupted session data with detailed analysis
    $corruption_details = analyze_session_corruption();
    if ($corruption_details['is_corrupted']) {
        $error_info['has_error'] = true;
        $error_info['error_type'] = 'session_corrupted';
        $error_info['message'] = 'Session data is corrupted: ' . implode(', ', $corruption_details['issues']);
        $error_info['action_taken'] = 'session_destroyed';
        
        // Log corruption details
        log_session_security_event('session_corrupted', 
            'Session data corruption detected: ' . json_encode($corruption_details['issues']), 
            $user_id);
        
        // Handle corrupted session gracefully
        handle_corrupted_session_gracefully($corruption_details);
        return $error_info;
    }
    
    // Check for security threats
    $security_threats = detect_session_security_threats();
    if (!empty($security_threats)) {
        $error_info['has_error'] = true;
        $error_info['error_type'] = 'security_threat';
        $error_info['message'] = 'Security threats detected: ' . implode(', ', $security_threats);
        $error_info['action_taken'] = 'session_destroyed';
        
        // Log security threats
        log_session_security_event('security_threat', 
            'Session security threats detected: ' . json_encode($security_threats), 
            $user_id);
        
        // Handle security threats
        handle_session_security_threats($security_threats);
        return $error_info;
    }
    
    return $error_info;
}

/**
 * Validate specific session field with type checking
 * Session data validation utility
 * @param string $field_name The session field to validate
 * @param string $expected_type The expected data type (int, string, email, timestamp)
 * @return bool Returns true if field is valid, false otherwise
 */
function validate_session_field($field_name, $expected_type = 'string')
{
    if (!isset($_SESSION[$field_name])) {
        return false;
    }
    
    $value = $_SESSION[$field_name];
    
    switch ($expected_type) {
        case 'int':
            return is_numeric($value) && $value > 0;
        case 'string':
            return is_string($value) && !empty(trim($value));
        case 'email':
            return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        case 'timestamp':
            return is_numeric($value) && $value > 0 && $value <= time();
        default:
            return !empty($value);
    }
}

/**
 * Sanitize session data to prevent XSS and injection attacks
 * Session security utility
 * @return void
 */
function sanitize_session_data()
{
    if (!is_logged_in()) {
        return;
    }
    
    // Sanitize string fields that might be displayed
    $string_fields = ['email', 'username', 'first_name', 'last_name'];
    
    foreach ($string_fields as $field) {
        if (isset($_SESSION[$field]) && is_string($_SESSION[$field])) {
            $_SESSION[$field] = htmlspecialchars(trim($_SESSION[$field]), ENT_QUOTES, 'UTF-8');
        }
    }
}

/**
 * Check if session data contains required authentication fields
 * Session structure validation utility
 * @param array $required_fields Array of required field names
 * @return bool Returns true if all required fields are present and valid, false otherwise
 */
function has_required_session_fields($required_fields = ['user_id', 'role'])
{
    if (!is_logged_in()) {
        return false;
    }
    
    foreach ($required_fields as $field) {
        // Handle alternative field names
        $field_exists = false;
        
        if ($field === 'user_id') {
            $field_exists = isset($_SESSION['customer_id']) || isset($_SESSION['user_id']) || isset($_SESSION['id']);
        } elseif ($field === 'role') {
            $field_exists = isset($_SESSION['role']) || isset($_SESSION['user_role']);
        } else {
            $field_exists = isset($_SESSION[$field]);
        }
        
        if (!$field_exists) {
            return false;
        }
    }
    
    return true;
}

/**
 * Perform comprehensive session security check
 * Complete session validation and security utility
 * @return array Returns detailed security check results
 */
function perform_session_security_check()
{
    $security_check = [
        'is_secure' => true,
        'issues' => [],
        'warnings' => []
    ];
    
    // Check if session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $security_check['is_secure'] = false;
        $security_check['issues'][] = 'Session is not active';
        return $security_check;
    }
    
    // Check session timeout
    if (is_session_expired()) {
        $security_check['is_secure'] = false;
        $security_check['issues'][] = 'Session has expired';
    }
    
    // Check required fields
    if (!has_required_session_fields()) {
        $security_check['is_secure'] = false;
        $security_check['issues'][] = 'Missing required session fields';
    }
    
    // Check CSRF token
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $security_check['is_secure'] = false;
        $security_check['issues'][] = 'Missing CSRF token';
    }
    
    // Check for suspicious session data (check customer_id first)
    $user_id_check = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? null;
    if (isset($user_id_check) && (!is_numeric($user_id_check) || $user_id_check <= 0)) {
        $security_check['is_secure'] = false;
        $security_check['issues'][] = 'Invalid user ID format';
    }
    
    // Check session age (warn if session is very old)
    if (isset($_SESSION['login_time'])) {
        $session_age = time() - $_SESSION['login_time'];
        if ($session_age > 86400) { // 24 hours
            $security_check['warnings'][] = 'Session is older than 24 hours';
        }
    }
    
    return $security_check;
}

/**
 * Validate session data structure against expected schema
 * Enhanced session structure validation utility
 * @param array $expected_schema Array defining expected session structure
 * @return array Returns validation results with details
 */
function validate_session_structure($expected_schema = null)
{
    if ($expected_schema === null) {
        // Default expected session structure
        $expected_schema = [
            'user_id' => 'int',
            'role' => 'int',
            'login_time' => 'timestamp',
            'last_activity' => 'timestamp',
            'csrf_token' => 'string'
        ];
    }
    
    $validation_result = [
        'is_valid' => true,
        'missing_fields' => [],
        'invalid_fields' => [],
        'extra_fields' => []
    ];
    
    if (!is_logged_in()) {
        $validation_result['is_valid'] = false;
        return $validation_result;
    }
    
    // Check for required fields
    foreach ($expected_schema as $field => $type) {
        $field_exists = false;
        
        // Handle alternative field names
        if ($field === 'user_id') {
            $field_exists = isset($_SESSION['customer_id']) || isset($_SESSION['user_id']) || isset($_SESSION['id']);
        } elseif ($field === 'role') {
            $field_exists = isset($_SESSION['role']) || isset($_SESSION['user_role']);
        } else {
            $field_exists = isset($_SESSION[$field]);
        }
        
        if (!$field_exists) {
            $validation_result['is_valid'] = false;
            $validation_result['missing_fields'][] = $field;
        } else {
            // Validate field type
            if (!validate_session_field($field, $type)) {
                $validation_result['is_valid'] = false;
                $validation_result['invalid_fields'][] = $field;
            }
        }
    }
    
    return $validation_result;
}

/**
 * Clean and normalize session data
 * Enhanced session data sanitization utility
 * @return bool Returns true if cleaning was successful, false otherwise
 */
function clean_session_data()
{
    if (!is_logged_in()) {
        return false;
    }
    
    // Remove any potentially dangerous or unnecessary session data
    $dangerous_keys = ['__', 'eval', 'exec', 'system', 'shell_exec'];
    foreach ($dangerous_keys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    // Normalize user ID field (prefer 'user_id' over 'id')
    if (isset($_SESSION['id']) && !isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $_SESSION['id'];
        unset($_SESSION['id']);
    }
    
    // Normalize role field (prefer 'role' over 'user_role')
    if (isset($_SESSION['user_role']) && !isset($_SESSION['role'])) {
        $_SESSION['role'] = $_SESSION['user_role'];
        unset($_SESSION['user_role']);
    }
    
    // Ensure timestamps are integers
    $timestamp_fields = ['login_time', 'last_activity'];
    foreach ($timestamp_fields as $field) {
        if (isset($_SESSION[$field]) && is_numeric($_SESSION[$field])) {
            $_SESSION[$field] = (int)$_SESSION[$field];
        }
    }
    
    // Ensure user_id and role are integers if numeric
    if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
        $_SESSION['user_id'] = (int)$_SESSION['user_id'];
    }
    
    if (isset($_SESSION['role']) && is_numeric($_SESSION['role'])) {
        $_SESSION['role'] = (int)$_SESSION['role'];
    }
    
    return true;
}

/**
 * Validate session data against potential security threats
 * Enhanced session security validation utility
 * @return array Returns security validation results
 */
function validate_session_security()
{
    $security_validation = [
        'is_secure' => true,
        'threats_detected' => [],
        'recommendations' => []
    ];
    
    if (!is_logged_in()) {
        $security_validation['is_secure'] = false;
        $security_validation['threats_detected'][] = 'No valid session';
        return $security_validation;
    }
    
    // Check for session hijacking indicators
    if (!isset($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $security_validation['is_secure'] = false;
        $security_validation['threats_detected'][] = 'Weak or missing CSRF token';
        $security_validation['recommendations'][] = 'Regenerate CSRF token';
    }
    
    // Check for suspicious session age
    if (isset($_SESSION['login_time'])) {
        $session_age = time() - $_SESSION['login_time'];
        if ($session_age > 86400) { // 24 hours
            $security_validation['threats_detected'][] = 'Session age exceeds recommended limit';
            $security_validation['recommendations'][] = 'Consider session regeneration';
        }
    }
    
    // Check for suspicious activity patterns
    if (isset($_SESSION['last_activity'])) {
        $inactivity_time = time() - $_SESSION['last_activity'];
        if ($inactivity_time > SESSION_TIMEOUT) {
            $security_validation['is_secure'] = false;
            $security_validation['threats_detected'][] = 'Session timeout exceeded';
            $security_validation['recommendations'][] = 'Destroy session immediately';
        }
    }
    
    // Check for data integrity
    if (!validate_and_sanitize_session_data()) {
        $security_validation['is_secure'] = false;
        $security_validation['threats_detected'][] = 'Session data integrity compromised';
        $security_validation['recommendations'][] = 'Destroy and recreate session';
    }
    
    return $security_validation;
}

/**
 * Get session data summary for debugging and monitoring
 * Session data inspection utility (safe for logging)
 * @return array Returns sanitized session data summary
 */
function get_session_data_summary()
{
    $summary = [
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'user_logged_in' => is_logged_in(),
        'session_fields' => [],
        'security_status' => 'unknown'
    ];
    
    if (!$summary['session_active']) {
        return $summary;
    }
    
    // Safe fields to include in summary (no sensitive data)
    $safe_fields = ['user_id', 'role', 'login_time', 'last_activity'];
    
    foreach ($safe_fields as $field) {
        if (isset($_SESSION[$field])) {
            $summary['session_fields'][$field] = gettype($_SESSION[$field]);
        }
    }
    
    // Add alternative field names
    if (isset($_SESSION['id'])) {
        $summary['session_fields']['id'] = gettype($_SESSION['id']);
    }
    if (isset($_SESSION['user_role'])) {
        $summary['session_fields']['user_role'] = gettype($_SESSION['user_role']);
    }
    
    // Check security status
    $security_check = perform_session_security_check();
    $summary['security_status'] = $security_check['is_secure'] ? 'secure' : 'insecure';
    $summary['security_issues_count'] = count($security_check['issues']);
    
    return $summary;
}

// ============================================================================
// ENHANCED SESSION ERROR HANDLING FUNCTIONS
// ============================================================================

/**
 * Log session security events for monitoring and analysis
 * @param string $event_type Type of security event
 * @param string $message Detailed message about the event
 * @param int|null $user_id User ID associated with the event
 * @param array $additional_data Additional context data
 * @return void
 */
function log_session_security_event($event_type, $message, $user_id = null, $additional_data = [])
{
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'message' => $message,
        'user_id' => $user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_id' => session_id(),
        'additional_data' => $additional_data
    ];
    
    // Format log message
    $log_message = sprintf(
        "[SESSION_SECURITY] %s | Type: %s | User: %s | IP: %s | Message: %s",
        $log_entry['timestamp'],
        $event_type,
        $user_id ?? 'anonymous',
        $log_entry['ip_address'],
        $message
    );
    
    // Add additional data if present
    if (!empty($additional_data)) {
        $log_message .= " | Data: " . json_encode($additional_data);
    }
    
    // Log to error log
    error_log($log_message);
    
    // Optional: Log to separate security log file if configured
    if (defined('SESSION_SECURITY_LOG_FILE') && is_writable(dirname(SESSION_SECURITY_LOG_FILE))) {
        file_put_contents(SESSION_SECURITY_LOG_FILE, $log_message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Analyze session data for corruption and return detailed results
 * @return array Returns analysis results with corruption details
 */
function analyze_session_corruption()
{
    $analysis = [
        'is_corrupted' => false,
        'issues' => [],
        'severity' => 'none',
        'recoverable' => true
    ];
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $analysis['is_corrupted'] = true;
        $analysis['issues'][] = 'Session not active';
        $analysis['severity'] = 'critical';
        $analysis['recoverable'] = false;
        return $analysis;
    }
    
    // Check for required fields
    $required_fields = ['user_id', 'role'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        $field_exists = false;
        if ($field === 'user_id') {
            $field_exists = isset($_SESSION['user_id']) || isset($_SESSION['id']);
        } elseif ($field === 'role') {
            $field_exists = isset($_SESSION['role']) || isset($_SESSION['user_role']);
        } else {
            $field_exists = isset($_SESSION[$field]);
        }
        
        if (!$field_exists) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $analysis['is_corrupted'] = true;
        $analysis['issues'][] = 'Missing required fields: ' . implode(', ', $missing_fields);
        $analysis['severity'] = 'high';
        $analysis['recoverable'] = false;
    }
    
    // Check data type integrity
    $type_issues = [];
    
    // Check user_id type (check customer_id first)
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if ($user_id !== null && (!is_numeric($user_id) || $user_id <= 0)) {
        $type_issues[] = 'Invalid user_id format';
    }
    
    // Check role type
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    if ($role !== null && !is_numeric($role) && !is_string($role)) {
        $type_issues[] = 'Invalid role format';
    }
    
    // Check timestamp fields
    $timestamp_fields = ['login_time', 'last_activity'];
    foreach ($timestamp_fields as $field) {
        if (isset($_SESSION[$field]) && (!is_numeric($_SESSION[$field]) || $_SESSION[$field] <= 0)) {
            $type_issues[] = "Invalid {$field} format";
        }
    }
    
    if (!empty($type_issues)) {
        $analysis['is_corrupted'] = true;
        $analysis['issues'] = array_merge($analysis['issues'], $type_issues);
        $analysis['severity'] = $analysis['severity'] === 'high' ? 'high' : 'medium';
        $analysis['recoverable'] = true; // Type issues might be recoverable
    }
    
    // Check for suspicious data patterns
    $suspicious_patterns = [];
    
    // Check for injection attempts
    foreach ($_SESSION as $key => $value) {
        if (is_string($value) && (
            strpos($value, '<script') !== false ||
            strpos($value, 'javascript:') !== false ||
            strpos($value, 'eval(') !== false ||
            strpos($value, 'exec(') !== false
        )) {
            $suspicious_patterns[] = "Suspicious content in {$key}";
        }
    }
    
    if (!empty($suspicious_patterns)) {
        $analysis['is_corrupted'] = true;
        $analysis['issues'] = array_merge($analysis['issues'], $suspicious_patterns);
        $analysis['severity'] = 'critical';
        $analysis['recoverable'] = false;
    }
    
    return $analysis;
}

/**
 * Handle session timeout gracefully with user-friendly cleanup
 * @return void
 */
function handle_session_timeout_gracefully()
{
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    
    // Store timeout information for potential user notification
    $timeout_info = [
        'user_id' => $user_id,
        'timeout_time' => time(),
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : null
    ];
    
    // Log graceful timeout handling
    log_session_security_event('graceful_timeout', 
        'Handling session timeout gracefully', 
        $user_id, 
        $timeout_info);
    
    // Clear session data gradually to prevent errors
    try {
        // First, mark session as expired
        $_SESSION['session_expired'] = true;
        $_SESSION['timeout_timestamp'] = time();
        
        // Clear sensitive data first
        unset($_SESSION['csrf_token']);
        unset($_SESSION['user_id']);
        unset($_SESSION['id']);
        unset($_SESSION['role']);
        unset($_SESSION['user_role']);
        
        // Then destroy the session
        destroy_user_session();
        
    } catch (Exception $e) {
        // If graceful cleanup fails, force destroy
        log_session_security_event('timeout_cleanup_error', 
            'Error during graceful timeout cleanup: ' . $e->getMessage(), 
            $user_id);
        
        // Force session destruction
        session_destroy();
    }
}

/**
 * Handle corrupted session data gracefully with recovery attempts
 * @param array $corruption_details Details about the corruption
 * @return void
 */
function handle_corrupted_session_gracefully($corruption_details)
{
    $user_id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    
    // Log corruption handling attempt
    log_session_security_event('corruption_handling', 
        'Attempting to handle corrupted session data', 
        $user_id, 
        $corruption_details);
    
    // Attempt recovery if corruption is recoverable
    if ($corruption_details['recoverable'] && $corruption_details['severity'] !== 'critical') {
        try {
            // Attempt to sanitize and recover session data
            $recovery_success = attempt_session_recovery();
            
            if ($recovery_success) {
                log_session_security_event('corruption_recovered', 
                    'Successfully recovered corrupted session data', 
                    $user_id);
                return;
            }
        } catch (Exception $e) {
            log_session_security_event('recovery_failed', 
                'Session recovery failed: ' . $e->getMessage(), 
                $user_id);
        }
    }
    
    // If recovery fails or corruption is critical, destroy session
    try {
        // Mark session as corrupted before destruction
        $_SESSION['session_corrupted'] = true;
        $_SESSION['corruption_timestamp'] = time();
        
        // Destroy the session
        destroy_user_session();
        
    } catch (Exception $e) {
        // Force destruction if normal cleanup fails
        log_session_security_event('corruption_cleanup_error', 
            'Error during corruption cleanup: ' . $e->getMessage(), 
            $user_id);
        
        session_destroy();
    }
}

/**
 * Attempt to recover corrupted session data
 * @return bool Returns true if recovery was successful, false otherwise
 */
function attempt_session_recovery()
{
    $recovery_success = true;
    
    // Try to fix user_id field
    if (!isset($_SESSION['user_id']) && isset($_SESSION['id'])) {
        if (is_numeric($_SESSION['id']) && $_SESSION['id'] > 0) {
            $_SESSION['user_id'] = (int)$_SESSION['id'];
            unset($_SESSION['id']);
        } else {
            $recovery_success = false;
        }
    }
    
    // Try to fix role field
    if (!isset($_SESSION['role']) && isset($_SESSION['user_role'])) {
        if (is_numeric($_SESSION['user_role']) || is_string($_SESSION['user_role'])) {
            $_SESSION['role'] = is_numeric($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : $_SESSION['user_role'];
            unset($_SESSION['user_role']);
        } else {
            $recovery_success = false;
        }
    }
    
    // Validate recovered data
    if ($recovery_success) {
        $user_id = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['role'] ?? null;
        
        if (!is_numeric($user_id) || $user_id <= 0) {
            $recovery_success = false;
        }
        
        if ($role !== null && !is_valid_user_role($role)) {
            $recovery_success = false;
        }
    }
    
    // Reset timestamps if they're invalid
    if (!isset($_SESSION['login_time']) || !is_numeric($_SESSION['login_time'])) {
        $_SESSION['login_time'] = time();
    }
    
    if (!isset($_SESSION['last_activity']) || !is_numeric($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
    
    // Regenerate CSRF token if missing
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_csrf_token();
    }
    
    return $recovery_success;
}

/**
 * Detect potential security threats in session data
 * @return array Returns array of detected threats
 */
function detect_session_security_threats()
{
    $threats = [];
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return $threats;
    }
    
    // Check for session hijacking indicators
    if (isset($_SESSION['session_ip']) && isset($_SERVER['REMOTE_ADDR'])) {
        $session_ip = $_SESSION['session_ip'];
        $current_ip = $_SERVER['REMOTE_ADDR'];
        
        // For now, just log IP changes (don't treat as threat due to mobile users)
        if ($session_ip !== $current_ip) {
            log_session_security_event('ip_change', 
                "Session IP changed from {$session_ip} to {$current_ip}", 
                get_current_user_id());
        }
    }
    
    // Check for User-Agent changes (more suspicious)
    if (isset($_SESSION['session_user_agent']) && isset($_SERVER['HTTP_USER_AGENT'])) {
        $session_ua = $_SESSION['session_user_agent'];
        $current_ua = $_SERVER['HTTP_USER_AGENT'];
        
        if ($session_ua !== $current_ua) {
            $threats[] = 'User-Agent mismatch detected';
        }
    }
    
    // Check for suspicious session age
    if (isset($_SESSION['login_time'])) {
        $session_age = time() - $_SESSION['login_time'];
        if ($session_age > 86400) { // 24 hours
            $threats[] = 'Session age exceeds security threshold';
        }
    }
    
    // Check for missing security tokens
    if (!isset($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $threats[] = 'Missing or weak CSRF token';
    }
    
    // Check for privilege escalation attempts
    $current_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    if ($current_role !== null && !is_valid_user_role($current_role)) {
        $threats[] = 'Invalid role detected - possible privilege escalation';
    }
    
    return $threats;
}

/**
 * Handle detected security threats
 * @param array $threats Array of detected threats
 * @return void
 */
function handle_session_security_threats($threats)
{
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    
    // Log all threats
    foreach ($threats as $threat) {
        log_session_security_event('security_threat_handled', $threat, $user_id);
    }
    
    // Determine threat severity
    $critical_threats = ['User-Agent mismatch detected', 'Invalid role detected - possible privilege escalation'];
    $has_critical_threat = !empty(array_intersect($threats, $critical_threats));
    
    if ($has_critical_threat) {
        // Critical threats - destroy session immediately
        log_session_security_event('critical_threat_response', 
            'Destroying session due to critical security threats', 
            $user_id, 
            ['threats' => $threats]);
        
        destroy_user_session();
    } else {
        // Non-critical threats - regenerate session and log
        log_session_security_event('threat_mitigation', 
            'Regenerating session due to security threats', 
            $user_id, 
            ['threats' => $threats]);
        
        regenerate_session_id(true);
    }
}

// ============================================================================
// AUTHENTICATION AND AUTHORIZATION ERROR HANDLING
// ============================================================================

/**
 * Handle authentication failure with proper error response and logging
 * @param string $failure_reason Reason for authentication failure
 * @param string $attempted_identifier User identifier that was attempted (email, username, etc.)
 * @param array $additional_context Additional context about the failure
 * @return array Returns standardized error response
 */
function handle_authentication_failure($failure_reason, $attempted_identifier = null, $additional_context = [])
{
    $error_response = [
        'success' => false,
        'error_type' => 'authentication_failure',
        'message' => get_user_friendly_auth_error_message($failure_reason),
        'timestamp' => time(),
        'requires_action' => determine_required_action($failure_reason)
    ];
    
    // Log the authentication failure for security monitoring
    log_authentication_security_event('auth_failure', $failure_reason, $attempted_identifier, $additional_context);
    
    // Handle specific failure types
    switch ($failure_reason) {
        case 'invalid_credentials':
            $error_response['retry_allowed'] = true;
            $error_response['lockout_info'] = check_account_lockout_status($attempted_identifier);
            break;
            
        case 'account_locked':
            $error_response['retry_allowed'] = false;
            $error_response['lockout_info'] = get_lockout_details($attempted_identifier);
            break;
            
        case 'session_expired':
            $error_response['requires_action'] = 'redirect_to_login';
            $error_response['message'] = 'Your session has expired. Please log in again.';
            break;
            
        case 'csrf_validation_failed':
            $error_response['requires_action'] = 'refresh_page';
            $error_response['message'] = 'Security token validation failed. Please refresh the page and try again.';
            break;
            
        case 'suspicious_activity':
            $error_response['retry_allowed'] = false;
            $error_response['requires_action'] = 'contact_support';
            break;
    }
    
    return $error_response;
}

/**
 * Handle authorization failure with appropriate error response and logging
 * @param string $failure_reason Reason for authorization failure
 * @param string $attempted_resource Resource that was attempted to be accessed
 * @param string $required_permission Permission that was required
 * @param array $additional_context Additional context about the failure
 * @return array Returns standardized error response
 */
function handle_authorization_failure($failure_reason, $attempted_resource = null, $required_permission = null, $additional_context = [])
{
    $user_id = get_current_user_id();
    $user_role = get_current_user_role();
    
    $error_response = [
        'success' => false,
        'error_type' => 'authorization_failure',
        'message' => get_user_friendly_authz_error_message($failure_reason, $required_permission),
        'timestamp' => time(),
        'suggested_action' => determine_suggested_action($failure_reason, $user_role)
    ];
    
    // Enhanced context for logging
    $log_context = array_merge($additional_context, [
        'user_id' => $user_id,
        'user_role' => $user_role,
        'attempted_resource' => $attempted_resource,
        'required_permission' => $required_permission,
        'current_permissions' => get_user_permissions()
    ]);
    
    // Log the authorization failure
    log_authentication_security_event('authz_failure', $failure_reason, $user_id, $log_context);
    
    // Handle specific failure types
    switch ($failure_reason) {
        case 'insufficient_privileges':
            $error_response['can_request_access'] = can_request_elevated_access($user_role, $required_permission);
            break;
            
        case 'invalid_role':
            $error_response['requires_action'] = 'contact_administrator';
            $error_response['message'] = 'Your account role is invalid. Please contact an administrator.';
            break;
            
        case 'session_invalid':
            $error_response['requires_action'] = 'redirect_to_login';
            $error_response['message'] = 'Your session is invalid. Please log in again.';
            break;
            
        case 'resource_not_found':
            $error_response['message'] = 'The requested resource was not found or you do not have permission to access it.';
            break;
    }
    
    return $error_response;
}

/**
 * Log authentication and authorization security events
 * @param string $event_type Type of security event (auth_failure, authz_failure, etc.)
 * @param string $failure_reason Specific reason for the failure
 * @param string|int|null $user_identifier User identifier (ID, email, etc.)
 * @param array $context Additional context information
 * @return void
 */
function log_authentication_security_event($event_type, $failure_reason, $user_identifier = null, $context = [])
{
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'failure_reason' => $failure_reason,
        'user_identifier' => $user_identifier,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'session_id' => session_id(),
        'context' => $context
    ];
    
    // Format log message
    $log_message = sprintf(
        "[AUTH_SECURITY] %s | Type: %s | Reason: %s | User: %s | IP: %s | URI: %s",
        $log_entry['timestamp'],
        $event_type,
        $failure_reason,
        $user_identifier ?? 'anonymous',
        $log_entry['ip_address'],
        $log_entry['request_uri']
    );
    
    // Add context if present
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context);
    }
    
    // Log to error log
    error_log($log_message);
    
    // Optional: Log to separate authentication security log file
    if (defined('AUTH_SECURITY_LOG_FILE') && is_writable(dirname(AUTH_SECURITY_LOG_FILE))) {
        file_put_contents(AUTH_SECURITY_LOG_FILE, $log_message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    // Track failed attempts for potential lockout
    track_failed_attempt($user_identifier, $event_type, $failure_reason);
}

/**
 * Get user-friendly error message for authentication failures
 * @param string $failure_reason Internal failure reason
 * @return string User-friendly error message
 */
function get_user_friendly_auth_error_message($failure_reason)
{
    $messages = [
        'invalid_credentials' => 'Invalid email or password. Please check your credentials and try again.',
        'account_locked' => 'Your account has been temporarily locked due to multiple failed login attempts. Please try again later or contact support.',
        'account_disabled' => 'Your account has been disabled. Please contact an administrator for assistance.',
        'session_expired' => 'Your session has expired. Please log in again to continue.',
        'csrf_validation_failed' => 'Security validation failed. Please refresh the page and try again.',
        'suspicious_activity' => 'Suspicious activity detected. For security reasons, access has been restricted. Please contact support.',
        'rate_limit_exceeded' => 'Too many login attempts. Please wait a few minutes before trying again.',
        'maintenance_mode' => 'The system is currently under maintenance. Please try again later.',
        'invalid_session_data' => 'Session data is invalid. Please log in again.',
        'authentication_required' => 'Authentication is required to access this resource. Please log in.'
    ];
    
    return $messages[$failure_reason] ?? 'Authentication failed. Please try again or contact support if the problem persists.';
}

/**
 * Get user-friendly error message for authorization failures
 * @param string $failure_reason Internal failure reason
 * @param string|null $required_permission Required permission that was missing
 * @return string User-friendly error message
 */
function get_user_friendly_authz_error_message($failure_reason, $required_permission = null)
{
    $messages = [
        'insufficient_privileges' => 'You do not have sufficient privileges to perform this action.',
        'invalid_role' => 'Your account role is invalid or has been changed. Please contact an administrator.',
        'session_invalid' => 'Your session is invalid. Please log in again.',
        'resource_not_found' => 'The requested resource was not found or you do not have permission to access it.',
        'permission_denied' => 'Access denied. You do not have the required permissions.',
        'role_mismatch' => 'Your current role does not allow access to this resource.',
        'admin_required' => 'Administrator privileges are required to access this resource.',
        'owner_required' => 'Resource owner privileges are required to perform this action.'
    ];
    
    $base_message = $messages[$failure_reason] ?? 'Access denied. You do not have permission to perform this action.';
    
    // Add specific permission information if available
    if ($required_permission && $failure_reason === 'insufficient_privileges') {
        $permission_names = [
            'admin_access' => 'administrator access',
            'user_management' => 'user management',
            'system_settings' => 'system settings',
            'manage_menu' => 'menu management',
            'view_orders' => 'order viewing',
            'place_orders' => 'order placement'
        ];
        
        $permission_name = $permission_names[$required_permission] ?? $required_permission;
        $base_message .= " This action requires {$permission_name} permissions.";
    }
    
    return $base_message;
}

/**
 * Determine required action based on failure reason
 * @param string $failure_reason The reason for failure
 * @return string|null Required action or null if no specific action needed
 */
function determine_required_action($failure_reason)
{
    $actions = [
        'session_expired' => 'redirect_to_login',
        'csrf_validation_failed' => 'refresh_page',
        'account_locked' => 'wait_and_retry',
        'suspicious_activity' => 'contact_support',
        'maintenance_mode' => 'retry_later',
        'rate_limit_exceeded' => 'wait_and_retry'
    ];
    
    return $actions[$failure_reason] ?? null;
}

/**
 * Determine suggested action for authorization failures
 * @param string $failure_reason The reason for authorization failure
 * @param int|null $user_role Current user role
 * @return string Suggested action for the user
 */
function determine_suggested_action($failure_reason, $user_role = null)
{
    switch ($failure_reason) {
        case 'insufficient_privileges':
            if ($user_role === USER_ROLE_CUSTOMER) {
                return 'Contact an administrator to request elevated privileges.';
            }
            return 'You may need to log in with a different account that has the required permissions.';
            
        case 'invalid_role':
            return 'Contact an administrator to verify your account status.';
            
        case 'session_invalid':
            return 'Please log out and log back in to refresh your session.';
            
        case 'resource_not_found':
            return 'Verify the resource exists and you have been granted access to it.';
            
        default:
            return 'Contact support if you believe you should have access to this resource.';
    }
}

/**
 * Check if user can request elevated access for a specific permission
 * @param int|null $user_role Current user role
 * @param string|null $required_permission Required permission
 * @return bool True if user can request access, false otherwise
 */
function can_request_elevated_access($user_role, $required_permission)
{
    // Admin access cannot be requested through normal channels
    if ($required_permission === 'admin_access') {
        return false;
    }
    
    return false;
}

/**
 * Track failed authentication/authorization attempts for security monitoring
 * @param string|int|null $user_identifier User identifier
 * @param string $event_type Type of event (auth_failure, authz_failure)
 * @param string $failure_reason Specific failure reason
 * @return void
 */
function track_failed_attempt($user_identifier, $event_type, $failure_reason)
{
    if (!$user_identifier) {
        return;
    }
    
    // Initialize session tracking if not exists
    if (!isset($_SESSION['failed_attempts'])) {
        $_SESSION['failed_attempts'] = [];
    }
    
    $attempt_key = $user_identifier . '_' . $event_type;
    
    if (!isset($_SESSION['failed_attempts'][$attempt_key])) {
        $_SESSION['failed_attempts'][$attempt_key] = [
            'count' => 0,
            'first_attempt' => time(),
            'last_attempt' => time(),
            'reasons' => []
        ];
    }
    
    $_SESSION['failed_attempts'][$attempt_key]['count']++;
    $_SESSION['failed_attempts'][$attempt_key]['last_attempt'] = time();
    $_SESSION['failed_attempts'][$attempt_key]['reasons'][] = $failure_reason;
    
    // Log if attempts exceed threshold
    if ($_SESSION['failed_attempts'][$attempt_key]['count'] >= 5) {
        log_authentication_security_event('excessive_failures', 
            "Excessive failed attempts detected for {$user_identifier}", 
            $user_identifier, 
            $_SESSION['failed_attempts'][$attempt_key]);
    }
}

/**
 * Check account lockout status (placeholder for future implementation)
 * @param string|null $user_identifier User identifier to check
 * @return array Lockout status information
 */
function check_account_lockout_status($user_identifier)
{
    // Placeholder implementation - would integrate with database in full system
    return [
        'is_locked' => false,
        'lockout_expires' => null,
        'attempts_remaining' => 3,
        'lockout_duration' => 900 // 15 minutes
    ];
}

/**
 * Get lockout details for a user (placeholder for future implementation)
 * @param string|null $user_identifier User identifier
 * @return array Lockout details
 */
function get_lockout_details($user_identifier)
{
    // Placeholder implementation
    return [
        'locked_until' => time() + 900, // 15 minutes from now
        'reason' => 'Multiple failed login attempts',
        'attempts_made' => 5,
        'can_reset' => true
    ];
}

/**
 * Handle authentication error response based on request type (AJAX vs regular)
 * @param array $error_response Error response array from handle_authentication_failure
 * @param bool $force_json Force JSON response even for non-AJAX requests
 * @return void This function handles the response and may exit
 */
function send_authentication_error_response($error_response, $force_json = false)
{
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($is_ajax || $force_json) {
        // Send JSON response for AJAX requests
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode($error_response);
        exit();
    } else {
        // Handle regular form submissions with redirect
        $error_message = urlencode($error_response['message']);
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? '../login/login.php';
        
        // Add error parameters to URL
        $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
        $redirect_url .= $separator . "error={$error_message}&error_type={$error_response['error_type']}";
        
        header("Location: {$redirect_url}");
        exit();
    }
}

/**
 * Handle authorization error response based on request type
 * @param array $error_response Error response array from handle_authorization_failure
 * @param bool $force_json Force JSON response even for non-AJAX requests
 * @return void This function handles the response and may exit
 */
function send_authorization_error_response($error_response, $force_json = false)
{
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($is_ajax || $force_json) {
        // Send JSON response for AJAX requests
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode($error_response);
        exit();
    } else {
        // Handle regular requests with redirect to appropriate page
        $error_message = urlencode($error_response['message']);
        
        // Determine redirect destination based on error type
        if ($error_response['error_type'] === 'authorization_failure' && 
            isset($error_response['requires_action']) && 
            $error_response['requires_action'] === 'redirect_to_login') {
            $redirect_url = '../login/login.php';
        } else {
            $redirect_url = $_SERVER['HTTP_REFERER'] ?? '../index.php';
        }
        
        // Add error parameters to URL
        $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
        $redirect_url .= $separator . "error={$error_message}&error_type={$error_response['error_type']}";
        
        header("Location: {$redirect_url}");
        exit();
    }
}

// ============================================================================
// ROLE MANAGEMENT UTILITIES
// ============================================================================

/**
 * Validate if a role value is a valid user role
 * @param mixed $role The role value to validate
 * @return bool Returns true if role is valid, false otherwise
 */
function is_valid_user_role($role)
{
    // Convert string numbers to integers for comparison
    if (is_string($role) && is_numeric($role)) {
        $role = (int)$role;
    }
    
    return in_array($role, VALID_USER_ROLES, true);
}

/**
 * Get the role name for a given role value
 * @param mixed $role The role value
 * @return string|null Returns role name or null if invalid
 */
function get_role_name($role)
{
    // Convert string numbers to integers for comparison
    if (is_string($role) && is_numeric($role)) {
        $role = (int)$role;
    }
    
    switch ($role) {
        case USER_ROLE_ADMIN:
            return 'Admin';
        case USER_ROLE_CUSTOMER:
            return 'Customer';
        default:
            return null;
    }
}

/**
 * Check if a role has administrative privileges
 * @param mixed $role The role value to check
 * @return bool Returns true if role has admin privileges, false otherwise
 */
function role_has_admin_privileges($role)
{
    // Convert string numbers to integers for comparison
    if (is_string($role) && is_numeric($role)) {
        $role = (int)$role;
    }
    
    return $role === USER_ROLE_ADMIN;
}

/**
 * Check if a role has customer privileges
 * @param mixed $role The role value to check
 * @return bool Returns true if role has customer privileges, false otherwise
 */
function role_has_customer_privileges($role)
{
    // Convert string numbers to integers for comparison
    if (is_string($role) && is_numeric($role)) {
        $role = (int)$role;
    }
    
    return $role === USER_ROLE_CUSTOMER;
}

/**
 * Validate and normalize role data from session or input
 * @param mixed $role The role value to validate and normalize
 * @return int|null Returns normalized role as integer or null if invalid
 */
function validate_and_normalize_role($role)
{
    // Handle null or empty values
    if ($role === null || $role === '') {
        return null;
    }
    
    // Convert string numbers to integers
    if (is_string($role) && is_numeric($role)) {
        $role = (int)$role;
    }
    
    // Validate role
    if (!is_valid_user_role($role)) {
        return null;
    }
    
    return (int)$role;
}

/**
 * Get all valid user roles with their names
 * @return array Returns associative array of role values and names
 */
function get_all_user_roles()
{
    return [
        USER_ROLE_ADMIN => 'Admin',
        USER_ROLE_CUSTOMER => 'Customer'
    ];
}

/**
 * Check if current user has specific role
 * @param int $required_role The role to check for
 * @return bool Returns true if user has the specified role, false otherwise
 */
function user_has_role($required_role)
{
    if (!is_logged_in()) {
        return false;
    }
    
    $current_role = get_current_user_role();
    if ($current_role === null) {
        return false;
    }
    
    return $current_role === $required_role;
}

/**
 * Check if current user has any of the specified roles
 * @param array $allowed_roles Array of role values that are allowed
 * @return bool Returns true if user has any of the specified roles, false otherwise
 */
function user_has_any_role($allowed_roles)
{
    if (!is_logged_in()) {
        return false;
    }
    
    $current_role = get_current_user_role();
    if ($current_role === null) {
        return false;
    }
    
    return in_array($current_role, $allowed_roles, true);
}

/**
 * Check if current user can access admin functions
 * Role-based access control helper for admin functions
 * @return bool Returns true if user can access admin functions, false otherwise
 */
function can_access_admin_functions()
{
    return user_has_role(USER_ROLE_ADMIN);
}

/**
 * Check if current user can access customer functions
 * Role-based access control helper for customer functions
 * @return bool Returns true if user can access customer functions, false otherwise
 */
function can_access_customer_functions()
{
    return user_has_role(USER_ROLE_CUSTOMER);
}

/**
 * Get role-based permissions for current user
 * @return array Returns array of permissions based on user role
 */
function get_user_permissions()
{
    if (!is_logged_in()) {
        return [];
    }
    
    $current_role = get_current_user_role();
    if ($current_role === null) {
        return [];
    }
    
    switch ($current_role) {
        case USER_ROLE_ADMIN:
            return [
                'admin_access' => true,
                'customer_access' => true,
                'restaurant_access' => true,
                'user_management' => true,
                'system_settings' => true
            ];
        case USER_ROLE_CUSTOMER:
            return [
                'admin_access' => false,
                'customer_access' => true,
                'place_orders' => true,
                'view_menu' => true
            ];
        default:
            return [];
    }
}

?>