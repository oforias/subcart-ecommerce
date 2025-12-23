<?php

require_once __DIR__ . '/../settings/db_class.php';

/**
 * Cart Data Integrity Class
 * Handles orphaned data scenarios and data integrity checks
 * Requirements: 8.3, 8.4, 4.4
 */
class CartIntegrity extends db_connection
{
    /**
     * Check for orphaned cart items (items referencing deleted products)
     * Requirements: 8.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with orphaned items data
     */
    public function find_orphaned_cart_items($customer_id = null, $ip_address = null)
    {
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Build query to find cart items with no matching product
            $sql = "SELECT c.p_id, c.c_id, c.ip_add, c.qty 
                    FROM cart c 
                    LEFT JOIN products p ON c.p_id = p.product_id 
                    WHERE p.product_id IS NULL";
            
            // Add filters if provided
            $conditions = [];
            if ($customer_id !== null) {
                $conditions[] = "c.c_id = " . (int)$customer_id;
            }
            if ($ip_address !== null) {
                $ip_escaped = mysqli_real_escape_string($this->db, $ip_address);
                $conditions[] = "c.ip_add = '{$ip_escaped}'";
            }
            
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
            
            // Execute query
            $result = $this->execute_query_with_error_handling($sql, 'SELECT');
            
            if (!$result['success']) {
                return $result;
            }
            
            // Fetch orphaned items
            $orphaned_items = [];
            if ($result['num_rows'] > 0) {
                $orphaned_items = mysqli_fetch_all($result['result'], MYSQLI_ASSOC);
            }
            
            return [
                'success' => true,
                'data' => [
                    'orphaned_items' => $orphaned_items,
                    'count' => count($orphaned_items),
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Find orphaned cart items exception: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Failed to find orphaned cart items',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode()
                ]
            ];
        }
    }
    
    /**
     * Remove orphaned cart items
     * Requirements: 8.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with removal details
     */
    public function remove_orphaned_cart_items($customer_id = null, $ip_address = null)
    {
        // First, find orphaned items
        $find_result = $this->find_orphaned_cart_items($customer_id, $ip_address);
        
        if (!$find_result['success']) {
            return $find_result;
        }
        
        $orphaned_count = $find_result['data']['count'];
        
        if ($orphaned_count === 0) {
            return [
                'success' => true,
                'data' => [
                    'message' => 'No orphaned cart items found',
                    'removed_count' => 0,
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ]
            ];
        }
        
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Build delete query for orphaned items
            $sql = "DELETE c FROM cart c 
                    LEFT JOIN products p ON c.p_id = p.product_id 
                    WHERE p.product_id IS NULL";
            
            // Add filters if provided
            $conditions = [];
            if ($customer_id !== null) {
                $conditions[] = "c.c_id = " . (int)$customer_id;
            }
            if ($ip_address !== null) {
                $ip_escaped = mysqli_real_escape_string($this->db, $ip_address);
                $conditions[] = "c.ip_add = '{$ip_escaped}'";
            }
            
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
            
            // Execute delete query
            $result = $this->execute_query_with_error_handling($sql, 'DELETE');
            
            if (!$result['success']) {
                return $result;
            }
            
            $removed_count = $result['affected_rows'];
            
            // Log the cleanup operation
            error_log("Removed {$removed_count} orphaned cart items for customer_id: {$customer_id}, ip: {$ip_address}");
            
            return [
                'success' => true,
                'data' => [
                    'message' => "Successfully removed {$removed_count} orphaned cart items",
                    'removed_count' => $removed_count,
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Remove orphaned cart items exception: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Failed to remove orphaned cart items',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode()
                ]
            ];
        }
    }
    
    /**
     * Verify cart data integrity
     * Requirements: 4.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with integrity check details
     */
    public function verify_cart_integrity($customer_id = null, $ip_address = null)
    {
        $integrity_issues = [];
        
        // Check for orphaned cart items
        $orphaned_result = $this->find_orphaned_cart_items($customer_id, $ip_address);
        if ($orphaned_result['success'] && $orphaned_result['data']['count'] > 0) {
            $integrity_issues[] = [
                'issue_type' => 'orphaned_products',
                'severity' => 'medium',
                'description' => 'Cart contains items referencing deleted products',
                'affected_count' => $orphaned_result['data']['count'],
                'affected_items' => $orphaned_result['data']['orphaned_items']
            ];
        }
        
        // Check for invalid quantities
        $invalid_qty_result = $this->find_invalid_quantities($customer_id, $ip_address);
        if ($invalid_qty_result['success'] && $invalid_qty_result['data']['count'] > 0) {
            $integrity_issues[] = [
                'issue_type' => 'invalid_quantities',
                'severity' => 'high',
                'description' => 'Cart contains items with invalid quantities',
                'affected_count' => $invalid_qty_result['data']['count'],
                'affected_items' => $invalid_qty_result['data']['invalid_items']
            ];
        }
        
        // Check for duplicate entries
        $duplicate_result = $this->find_duplicate_cart_entries($customer_id, $ip_address);
        if ($duplicate_result['success'] && $duplicate_result['data']['count'] > 0) {
            $integrity_issues[] = [
                'issue_type' => 'duplicate_entries',
                'severity' => 'medium',
                'description' => 'Cart contains duplicate product entries',
                'affected_count' => $duplicate_result['data']['count'],
                'affected_items' => $duplicate_result['data']['duplicate_items']
            ];
        }
        
        // Determine overall integrity status
        $has_issues = !empty($integrity_issues);
        $high_severity_issues = array_filter($integrity_issues, function($issue) {
            return $issue['severity'] === 'high';
        });
        
        return [
            'success' => true,
            'data' => [
                'integrity_status' => $has_issues ? 'issues_found' : 'healthy',
                'has_issues' => $has_issues,
                'has_critical_issues' => !empty($high_severity_issues),
                'total_issues' => count($integrity_issues),
                'issues' => $integrity_issues,
                'customer_id' => $customer_id,
                'ip_address' => $ip_address,
                'checked_at' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Find cart items with invalid quantities
     * Requirements: 4.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with invalid quantity items
     */
    private function find_invalid_quantities($customer_id = null, $ip_address = null)
    {
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Build query to find cart items with invalid quantities
            $sql = "SELECT p_id, c_id, ip_add, qty 
                    FROM cart 
                    WHERE qty <= 0 OR qty > 999";
            
            // Add filters if provided
            $conditions = [];
            if ($customer_id !== null) {
                $conditions[] = "c_id = " . (int)$customer_id;
            }
            if ($ip_address !== null) {
                $ip_escaped = mysqli_real_escape_string($this->db, $ip_address);
                $conditions[] = "ip_add = '{$ip_escaped}'";
            }
            
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
            
            // Execute query
            $result = $this->execute_query_with_error_handling($sql, 'SELECT');
            
            if (!$result['success']) {
                return $result;
            }
            
            // Fetch invalid items
            $invalid_items = [];
            if ($result['num_rows'] > 0) {
                $invalid_items = mysqli_fetch_all($result['result'], MYSQLI_ASSOC);
            }
            
            return [
                'success' => true,
                'data' => [
                    'invalid_items' => $invalid_items,
                    'count' => count($invalid_items)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Find invalid quantities exception: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Failed to find invalid quantities',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode()
                ]
            ];
        }
    }
    
    /**
     * Find duplicate cart entries (same product for same user)
     * Requirements: 4.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with duplicate entries
     */
    private function find_duplicate_cart_entries($customer_id = null, $ip_address = null)
    {
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Build query to find duplicate entries
            $sql = "SELECT p_id, c_id, ip_add, COUNT(*) as entry_count, SUM(qty) as total_qty
                    FROM cart 
                    WHERE 1=1";
            
            // Add filters if provided
            if ($customer_id !== null) {
                $sql .= " AND c_id = " . (int)$customer_id;
            }
            if ($ip_address !== null) {
                $ip_escaped = mysqli_real_escape_string($this->db, $ip_address);
                $sql .= " AND ip_add = '{$ip_escaped}'";
            }
            
            $sql .= " GROUP BY p_id, c_id, ip_add 
                      HAVING COUNT(*) > 1";
            
            // Execute query
            $result = $this->execute_query_with_error_handling($sql, 'SELECT');
            
            if (!$result['success']) {
                return $result;
            }
            
            // Fetch duplicate items
            $duplicate_items = [];
            if ($result['num_rows'] > 0) {
                $duplicate_items = mysqli_fetch_all($result['result'], MYSQLI_ASSOC);
            }
            
            return [
                'success' => true,
                'data' => [
                    'duplicate_items' => $duplicate_items,
                    'count' => count($duplicate_items)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Find duplicate cart entries exception: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Failed to find duplicate cart entries',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode()
                ]
            ];
        }
    }
    
    /**
     * Fix cart data integrity issues
     * Requirements: 4.4, 8.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @param array $options Fix options (remove_orphaned, fix_quantities, merge_duplicates)
     * @return array Result with fix details
     */
    public function fix_cart_integrity_issues($customer_id = null, $ip_address = null, $options = [])
    {
        $remove_orphaned = $options['remove_orphaned'] ?? true;
        $fix_quantities = $options['fix_quantities'] ?? true;
        $merge_duplicates = $options['merge_duplicates'] ?? true;
        
        $fixes_applied = [];
        $errors = [];
        
        // Remove orphaned cart items
        if ($remove_orphaned) {
            $orphaned_result = $this->remove_orphaned_cart_items($customer_id, $ip_address);
            if ($orphaned_result['success']) {
                $fixes_applied[] = [
                    'fix_type' => 'remove_orphaned',
                    'status' => 'success',
                    'items_affected' => $orphaned_result['data']['removed_count'],
                    'message' => $orphaned_result['data']['message']
                ];
            } else {
                $errors[] = [
                    'fix_type' => 'remove_orphaned',
                    'status' => 'failed',
                    'error' => $orphaned_result['error_message']
                ];
            }
        }
        
        // Fix invalid quantities
        if ($fix_quantities) {
            $qty_result = $this->fix_invalid_quantities($customer_id, $ip_address);
            if ($qty_result['success']) {
                $fixes_applied[] = [
                    'fix_type' => 'fix_quantities',
                    'status' => 'success',
                    'items_affected' => $qty_result['data']['fixed_count'],
                    'message' => $qty_result['data']['message']
                ];
            } else {
                $errors[] = [
                    'fix_type' => 'fix_quantities',
                    'status' => 'failed',
                    'error' => $qty_result['error_message']
                ];
            }
        }
        
        // Merge duplicate entries
        if ($merge_duplicates) {
            $duplicate_result = $this->merge_duplicate_cart_entries($customer_id, $ip_address);
            if ($duplicate_result['success']) {
                $fixes_applied[] = [
                    'fix_type' => 'merge_duplicates',
                    'status' => 'success',
                    'items_affected' => $duplicate_result['data']['merged_count'],
                    'message' => $duplicate_result['data']['message']
                ];
            } else {
                $errors[] = [
                    'fix_type' => 'merge_duplicates',
                    'status' => 'failed',
                    'error' => $duplicate_result['error_message']
                ];
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'fixes_applied' => $fixes_applied,
                'total_fixes' => count($fixes_applied),
                'errors' => $errors,
                'total_errors' => count($errors),
                'customer_id' => $customer_id,
                'ip_address' => $ip_address,
                'fixed_at' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Fix invalid quantities in cart
     * Requirements: 4.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with fix details
     */
    private function fix_invalid_quantities($customer_id = null, $ip_address = null)
    {
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Delete items with quantity <= 0 or > 999
            $sql = "DELETE FROM cart WHERE qty <= 0 OR qty > 999";
            
            // Add filters if provided
            $conditions = [];
            if ($customer_id !== null) {
                $conditions[] = "c_id = " . (int)$customer_id;
            }
            if ($ip_address !== null) {
                $ip_escaped = mysqli_real_escape_string($this->db, $ip_address);
                $conditions[] = "ip_add = '{$ip_escaped}'";
            }
            
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
            
            // Execute delete query
            $result = $this->execute_query_with_error_handling($sql, 'DELETE');
            
            if (!$result['success']) {
                return $result;
            }
            
            $fixed_count = $result['affected_rows'];
            
            // Log the fix operation
            error_log("Fixed {$fixed_count} invalid quantity cart items for customer_id: {$customer_id}, ip: {$ip_address}");
            
            return [
                'success' => true,
                'data' => [
                    'message' => "Successfully fixed {$fixed_count} invalid quantity items",
                    'fixed_count' => $fixed_count
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Fix invalid quantities exception: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Failed to fix invalid quantities',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode()
                ]
            ];
        }
    }
    
    /**
     * Merge duplicate cart entries
     * Requirements: 4.4
     * 
     * @param int $customer_id Customer ID (null for all customers)
     * @param string $ip_address IP address (null for all guests)
     * @return array Result with merge details
     */
    private function merge_duplicate_cart_entries($customer_id = null, $ip_address = null)
    {
        // Find duplicates first
        $duplicate_result = $this->find_duplicate_cart_entries($customer_id, $ip_address);
        
        if (!$duplicate_result['success']) {
            return $duplicate_result;
        }
        
        $duplicates = $duplicate_result['data']['duplicate_items'];
        
        if (empty($duplicates)) {
            return [
                'success' => true,
                'data' => [
                    'message' => 'No duplicate entries found',
                    'merged_count' => 0
                ]
            ];
        }
        
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $merged_count = 0;
            
            // For each duplicate, merge quantities and remove extras
            foreach ($duplicates as $duplicate) {
                $p_id = (int)$duplicate['p_id'];
                $c_id = $duplicate['c_id'] ? (int)$duplicate['c_id'] : 'NULL';
                $ip_add = $duplicate['ip_add'];
                $total_qty = (int)$duplicate['total_qty'];
                
                // Delete all entries for this product/user combination
                $delete_sql = "DELETE FROM cart WHERE p_id = {$p_id}";
                if ($c_id !== 'NULL') {
                    $delete_sql .= " AND c_id = {$c_id}";
                } else {
                    $delete_sql .= " AND c_id IS NULL";
                }
                $ip_escaped = mysqli_real_escape_string($this->db, $ip_add);
                $delete_sql .= " AND ip_add = '{$ip_escaped}'";
                
                $delete_result = $this->execute_query_with_error_handling($delete_sql, 'DELETE');
                
                if (!$delete_result['success']) {
                    error_log("Failed to delete duplicate entries: " . $delete_result['error_message']);
                    continue;
                }
                
                // Insert single merged entry
                $insert_sql = "INSERT INTO cart (p_id, c_id, ip_add, qty) VALUES ({$p_id}, ";
                $insert_sql .= ($c_id !== 'NULL' ? $c_id : 'NULL') . ", '{$ip_escaped}', {$total_qty})";
                
                $insert_result = $this->execute_query_with_error_handling($insert_sql, 'INSERT');
                
                if ($insert_result['success']) {
                    $merged_count++;
                } else {
                    error_log("Failed to insert merged entry: " . $insert_result['error_message']);
                }
            }
            
            // Log the merge operation
            error_log("Merged {$merged_count} duplicate cart entries for customer_id: {$customer_id}, ip: {$ip_address}");
            
            return [
                'success' => true,
                'data' => [
                    'message' => "Successfully merged {$merged_count} duplicate entries",
                    'merged_count' => $merged_count
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Merge duplicate cart entries exception: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Failed to merge duplicate cart entries',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode()
                ]
            ];
        }
    }
}

?>