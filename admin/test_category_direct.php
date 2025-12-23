<?php
/**
 * Test Category Direct - Run from admin directory
 * This tests the exact same path the JavaScript uses
 */

session_start();
require_once '../settings/core.php';

echo "<h1>Test Category Direct (from admin directory)</h1>\n";

// Check session
$is_logged_in = is_logged_in();
$has_admin = has_admin_privileges();
$user_id = get_current_user_id();

echo "<h2>Session Status:</h2>\n";
echo "Logged in: " . ($is_logged_in ? "YES" : "NO") . "<br>\n";
echo "Admin: " . ($has_admin ? "YES" : "NO") . "<br>\n";
echo "User ID: " . ($user_id ?? 'NULL') . "<br>\n";

if (!$is_logged_in || !$has_admin) {
    echo "<p style='color: red;'>❌ Please log in as admin first!</p>\n";
    echo "<p><a href='../login/login.php'>Login here</a></p>\n";
    exit;
}

// Test the exact path the JavaScript uses
echo "<h2>Testing ../actions/fetch_category_action.php:</h2>\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/register_sample/actions/fetch_category_action.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code<br>\n";
echo "Response:<br>\n";
echo "<textarea rows='10' cols='80'>" . htmlspecialchars($response) . "</textarea><br>\n";

if ($http_code == 200) {
    $json = json_decode($response, true);
    if ($json) {
        echo "<h3>Parsed JSON:</h3>\n";
        echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>\n";
        
        if ($json['status'] === 'success') {
            echo "<p style='color: green;'>✅ SUCCESS! Found " . count($json['data']) . " categories</p>\n";
            
            if (count($json['data']) > 0) {
                echo "<h3>Categories:</h3>\n";
                foreach ($json['data'] as $cat) {
                    echo "- {$cat['cat_name']} (ID: {$cat['cat_id']})<br>\n";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Error: " . $json['message'] . "</p>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON response</p>\n";
    }
} else {
    echo "<p style='color: red;'>❌ HTTP Error: $http_code</p>\n";
}

// Also test if the file exists
$action_file = '../actions/fetch_category_action.php';
if (file_exists($action_file)) {
    echo "<p>✅ Action file exists at: $action_file</p>\n";
} else {
    echo "<p style='color: red;'>❌ Action file NOT found at: $action_file</p>\n";
}

echo "<h2>Next Steps:</h2>\n";
echo "1. If this test shows categories, then the issue is with JavaScript<br>\n";
echo "2. If this test fails, then the issue is with the action file or session<br>\n";
echo "3. Check browser console (F12) for JavaScript errors<br>\n";
echo "4. Check browser Network tab to see if AJAX calls are being made<br>\n";
?>