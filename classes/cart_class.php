<?php

require_once __DIR__ . '/../settings/db_class.php';

/**
 * Cart Class
 * 
 * Handles cart data access and management operations.
 */
class Cart extends db_connection
{
    /**
     * Add a product to cart or increment quantity if it already exists
     * 
     * Handles both logged-in users (via customer_id) and guest users (via IP address).
     * If the product already exists in cart, the quantity is incremented.
     * 
     * @param int $product_id Product ID
     * @param int $quantity Quantity to add (default 1)
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and data/error details
     */
    public function add_to_cart($product_id, $quantity = 1, $customer_id = null, $ip_address = null)
    {
        // Validate input
        if (empty($product_id) || !is_numeric($product_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid product ID is required',
                'error_details' => ['product_id' => $product_id]
            ];
        }

        if (!is_numeric($quantity) || $quantity < 1) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Quantity must be a positive number',
                'error_details' => ['quantity' => $quantity]
            ];
        }

        // Ensure we have either customer_id or ip_address
        if (empty($customer_id) && empty($ip_address)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Either customer ID or IP address is required',
                'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Check if product exists in cart
            $existing_item = $this->get_cart_item($product_id, $customer_id, $ip_address);
            
            if ($existing_item['success'] && $existing_item['data']['item']) {
                // Product exists, increment quantity
                $new_quantity = $existing_item['data']['item']['qty'] + $quantity;
                return $this->update_cart_quantity($product_id, $new_quantity, $customer_id, $ip_address);
            } else {
                // Product doesn't exist, add new item
                return $this->insert_cart_item($product_id, $quantity, $customer_id, $ip_address);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Add to cart failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'add_to_cart'
                ]
            ];
        }
    }

    /**
     * Get a specific cart item     * 
     * @param int $product_id Product ID
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and cart item data
     */
    public function get_cart_item($product_id, $customer_id = null, $ip_address = null)
    {
        // Validate input
        if (empty($product_id) || !is_numeric($product_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid product ID is required',
                'error_details' => ['product_id' => $product_id]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p_id, ip_add, c_id, qty FROM cart WHERE p_id = ?";
            $params = [$product_id];
            $types = "i";

            // Add customer or IP filter
            if ($customer_id !== null) {
                $sql .= " AND c_id = ?";
                $params[] = $customer_id;
                $types .= "i";
            } else {
                $sql .= " AND ip_add = ? AND c_id IS NULL";
                $params[] = $ip_address;
                $types .= "s";
            }
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart item retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_cart_item'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart item retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_cart_item'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_cart_item'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_cart_item', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from cart item retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_cart_item'
                    ]
                ];
            }

            $item = null;
            if ($result->num_rows > 0) {
                $item = $result->fetch_assoc();
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'item' => $item,
                    'found' => $item !== null,
                    'product_id' => $product_id,
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart item retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_cart_item'
                ]
            ];
        }
    }

    /**
     * Insert a new cart item     * 
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and data/error details
     */
    private function insert_cart_item($product_id, $quantity, $customer_id = null, $ip_address = null)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO cart (p_id, qty, c_id, ip_add) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart insert statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_insert_cart'
                    ]
                ];
            }

            // Use IP address for guest users, ensure we have a valid IP
            $ip_to_use = $customer_id ? '' : ($ip_address ?: $_SERVER['REMOTE_ADDR']);

            if (!$stmt->bind_param("iiis", $product_id, $quantity, $customer_id, $ip_to_use)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart insert',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_insert_cart'
                    ]
                ];
            }
            
            if ($stmt->execute()) {
                $stmt->close();
                
                return [
                    'success' => true,
                    'data' => [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'customer_id' => $customer_id,
                        'ip_address' => $ip_to_use,
                        'action' => 'inserted'
                    ]
                ];
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_insert_cart'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'insert_cart_item', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart item insertion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'insert_cart_item'
                ]
            ];
        }
    }

    /**
     * Update cart item quantity     * 
     * @param int $product_id Product ID
     * @param int $quantity New quantity
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and data/error details
     */
    public function update_cart_quantity($product_id, $quantity, $customer_id = null, $ip_address = null)
    {
        // Validate input
        if (empty($product_id) || !is_numeric($product_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid product ID is required',
                'error_details' => ['product_id' => $product_id]
            ];
        }

        if (!is_numeric($quantity) || $quantity < 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Quantity must be a non-negative number',
                'error_details' => ['quantity' => $quantity]
            ];
        }

        // If quantity is 0, remove the item
        if ($quantity == 0) {
            return $this->remove_from_cart($product_id, $customer_id, $ip_address);
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "UPDATE cart SET qty = ? WHERE p_id = ?";
            $params = [$quantity, $product_id];
            $types = "ii";

            // Add customer or IP filter
            if ($customer_id !== null) {
                $sql .= " AND c_id = ?";
                $params[] = $customer_id;
                $types .= "i";
            } else {
                $sql .= " AND ip_add = ? AND c_id IS NULL";
                $params[] = $ip_address;
                $types .= "s";
            }
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart update statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_update_cart'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart update',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_update_cart'
                    ]
                ];
            }
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                if ($affected_rows > 0) {
                    return [
                        'success' => true,
                        'data' => [
                            'product_id' => $product_id,
                            'quantity' => $quantity,
                            'customer_id' => $customer_id,
                            'ip_address' => $ip_address,
                            'affected_rows' => $affected_rows,
                            'action' => 'updated'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'not_found',
                        'error_message' => 'Cart item not found for update',
                        'error_details' => [
                            'product_id' => $product_id,
                            'customer_id' => $customer_id,
                            'ip_address' => $ip_address,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                }
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_update_cart'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'update_cart_quantity', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart quantity update failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'update_cart_quantity'
                ]
            ];
        }
    }

    /**
     * Remove a product from cart     * 
     * @param int $product_id Product ID
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and data/error details
     */
    public function remove_from_cart($product_id, $customer_id = null, $ip_address = null)
    {
        // Validate input
        if (empty($product_id) || !is_numeric($product_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid product ID is required',
                'error_details' => ['product_id' => $product_id]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "DELETE FROM cart WHERE p_id = ?";
            $params = [$product_id];
            $types = "i";

            // Add customer or IP filter
            if ($customer_id !== null) {
                $sql .= " AND c_id = ?";
                $params[] = $customer_id;
                $types .= "i";
            } else {
                $sql .= " AND ip_add = ? AND c_id IS NULL";
                $params[] = $ip_address;
                $types .= "s";
            }
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart removal statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_remove_cart'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart removal',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_remove_cart'
                    ]
                ];
            }
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                return [
                    'success' => true,
                    'data' => [
                        'product_id' => $product_id,
                        'customer_id' => $customer_id,
                        'ip_address' => $ip_address,
                        'affected_rows' => $affected_rows,
                        'action' => 'removed'
                    ]
                ];
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_remove_cart'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'remove_from_cart', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart item removal failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'remove_from_cart'
                ]
            ];
        }
    }

    /**
     * Empty entire cart for a user     * 
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and data/error details
     */
    public function empty_cart($customer_id = null, $ip_address = null)
    {
        // Ensure we have either customer_id or ip_address
        if (empty($customer_id) && empty($ip_address)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Either customer ID or IP address is required',
                'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "DELETE FROM cart WHERE ";
            $params = [];
            $types = "";

            // Add customer or IP filter
            if ($customer_id !== null) {
                $sql .= "c_id = ?";
                $params[] = $customer_id;
                $types .= "i";
            } else {
                $sql .= "ip_add = ? AND c_id IS NULL";
                $params[] = $ip_address;
                $types .= "s";
            }
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart empty statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_empty_cart'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart empty',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_empty_cart'
                    ]
                ];
            }
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                return [
                    'success' => true,
                    'data' => [
                        'customer_id' => $customer_id,
                        'ip_address' => $ip_address,
                        'affected_rows' => $affected_rows,
                        'action' => 'emptied'
                    ]
                ];
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_empty_cart'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'empty_cart', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart empty failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'empty_cart'
                ]
            ];
        }
    }

    /**
     * Get all cart items for a user with product details     * 
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result array with success status and cart items data
     */
    public function get_cart_items($customer_id = null, $ip_address = null)
    {
        // Ensure we have either customer_id or ip_address
        if (empty($customer_id) && empty($ip_address)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Either customer ID or IP address is required',
                'error_details' => ['customer_id' => $customer_id, 'ip_address' => $ip_address]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT c.p_id, c.qty, c.c_id, c.ip_add, 
                           p.product_title, p.product_price, p.product_image, p.product_description,
                           cat.cat_name, b.brand_name
                    FROM cart c
                    INNER JOIN products p ON c.p_id = p.product_id
                    LEFT JOIN categories cat ON p.category_id = cat.cat_id
                    LEFT JOIN brands b ON p.brand_id = b.brand_id
                    WHERE ";
            $params = [];
            $types = "";

            // Add customer or IP filter
            if ($customer_id !== null) {
                $sql .= "c.c_id = ?";
                $params[] = $customer_id;
                $types .= "i";
            } else {
                $sql .= "c.ip_add = ? AND c.c_id IS NULL";
                $params[] = $ip_address;
                $types .= "s";
            }

            $sql .= " ORDER BY p.product_title ASC";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart items retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_cart_items'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart items retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_cart_items'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_cart_items'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_cart_items', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from cart items retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_cart_items'
                    ]
                ];
            }

            $items = [];
            $total_amount = 0;
            $total_items = 0;

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $subtotal = $row['product_price'] * $row['qty'];
                    $row['subtotal'] = $subtotal;
                    $items[] = $row;
                    $total_amount += $subtotal;
                    $total_items += $row['qty'];
                }
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'items' => $items,
                    'count' => count($items),
                    'total_items' => $total_items,
                    'total_amount' => $total_amount,
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart items retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_cart_items'
                ]
            ];
        }
    }

    /**
     * Get current user session information for cart operations     * 
     * @return array Session information with customer_id and ip_address
     */
    public function get_current_user_session()
    {
        // Include core functions for session management
        if (!function_exists('is_logged_in')) {
            require_once __DIR__ . '/../settings/core.php';
        }

        $session_info = [
            'customer_id' => null,
            'ip_address' => null,
            'is_logged_in' => false,
            'session_valid' => false
        ];

        try {
            // Check if user is logged in
            if (is_logged_in()) {
                $customer_id = get_current_user_id();
                if ($customer_id && is_numeric($customer_id) && $customer_id > 0) {
                    $session_info['customer_id'] = (int)$customer_id;
                    $session_info['is_logged_in'] = true;
                    $session_info['session_valid'] = true;
                }
            }

            // Always get IP address for guest users or as fallback
            // Handle CLI and missing REMOTE_ADDR gracefully
            $session_info['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 
                                         $_SERVER['SERVER_ADDR'] ?? 
                                         '127.0.0.1';

            return $session_info;

        } catch (Exception $e) {
            // Log error but don't fail - fall back to IP-based cart
            error_log("Cart session error: " . $e->getMessage());
            
            $session_info['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 
                                         $_SERVER['SERVER_ADDR'] ?? 
                                         '127.0.0.1';
            return $session_info;
        }
    }

    /**
     * Restore cart for logged-in user from previous sessions     * 
     * @param int $customer_id Customer ID
     * @return array Result array with success status and restoration details
     */
    public function restore_user_cart($customer_id)
    {
        // Validate input
        if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid customer ID is required for cart restoration',
                'error_details' => ['customer_id' => $customer_id]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Get existing cart items for this customer
            $existing_cart = $this->get_cart_items($customer_id, null);
            
            if (!$existing_cart['success']) {
                return $existing_cart;
            }

            $cart_data = $existing_cart['data'];
            
            return [
                'success' => true,
                'data' => [
                    'customer_id' => $customer_id,
                    'items_restored' => $cart_data['count'],
                    'total_items' => $cart_data['total_items'],
                    'total_amount' => $cart_data['total_amount'],
                    'cart_items' => $cart_data['items'],
                    'action' => 'cart_restored'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart restoration failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'restore_user_cart'
                ]
            ];
        }
    }

    /**
     * Transfer guest cart to logged-in user account     * 
     * @param string $ip_address Guest IP address
     * @param int $customer_id Customer ID to transfer to
     * @return array Result array with success status and transfer details
     */
    public function transfer_guest_cart_to_user($ip_address, $customer_id)
    {
        // Validate input
        if (empty($ip_address) || empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid IP address and customer ID are required for cart transfer',
                'error_details' => ['ip_address' => $ip_address, 'customer_id' => $customer_id]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Start transaction for atomic cart transfer
            $this->db->autocommit(false);

            // Get guest cart items
            $guest_cart = $this->get_cart_items(null, $ip_address);
            if (!$guest_cart['success']) {
                $this->db->rollback();
                return $guest_cart;
            }

            $guest_items = $guest_cart['data']['items'];
            $transferred_items = 0;
            $merged_items = 0;
            $errors = [];

            // Process each guest cart item
            foreach ($guest_items as $item) {
                $product_id = $item['p_id'];
                $quantity = $item['qty'];

                // Check if product already exists in user's cart
                $existing_item = $this->get_cart_item($product_id, $customer_id, null);
                
                if ($existing_item['success'] && $existing_item['data']['item']) {
                    // Product exists, merge quantities
                    $new_quantity = $existing_item['data']['item']['qty'] + $quantity;
                    $update_result = $this->update_cart_quantity($product_id, $new_quantity, $customer_id, null);
                    
                    if ($update_result['success']) {
                        $merged_items++;
                    } else {
                        $errors[] = "Failed to merge product {$product_id}: " . $update_result['error_message'];
                    }
                } else {
                    // Product doesn't exist, transfer it
                    $transfer_result = $this->transfer_cart_item($product_id, $quantity, $ip_address, $customer_id);
                    
                    if ($transfer_result['success']) {
                        $transferred_items++;
                    } else {
                        $errors[] = "Failed to transfer product {$product_id}: " . $transfer_result['error_message'];
                    }
                }
            }

            // Remove remaining guest cart items
            $cleanup_result = $this->empty_cart(null, $ip_address);
            if (!$cleanup_result['success']) {
                $errors[] = "Failed to clean up guest cart: " . $cleanup_result['error_message'];
            }

            // Commit transaction if no critical errors
            if (empty($errors) || ($transferred_items > 0 || $merged_items > 0)) {
                $this->db->commit();
                
                return [
                    'success' => true,
                    'data' => [
                        'customer_id' => $customer_id,
                        'ip_address' => $ip_address,
                        'transferred_items' => $transferred_items,
                        'merged_items' => $merged_items,
                        'total_processed' => $transferred_items + $merged_items,
                        'errors' => $errors,
                        'action' => 'cart_transferred'
                    ]
                ];
            } else {
                $this->db->rollback();
                return [
                    'success' => false,
                    'error_type' => 'transfer_failed',
                    'error_message' => 'Cart transfer failed',
                    'error_details' => ['errors' => $errors]
                ];
            }

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart transfer failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'transfer_guest_cart_to_user'
                ]
            ];
        } finally {
            $this->db->autocommit(true);
        }
    }

    /**
     * Transfer individual cart item from guest to user     * 
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param string $ip_address Guest IP address
     * @param int $customer_id Customer ID
     * @return array Result array with success status
     */
    private function transfer_cart_item($product_id, $quantity, $ip_address, $customer_id)
    {
        try {
            // Update the cart item to associate with customer instead of IP
            $sql = "UPDATE cart SET c_id = ?, ip_add = '' WHERE p_id = ? AND ip_add = ? AND c_id IS NULL";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare cart transfer statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_transfer_cart_item'
                    ]
                ];
            }

            if (!$stmt->bind_param("iis", $customer_id, $product_id, $ip_address)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for cart transfer',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_transfer_cart_item'
                    ]
                ];
            }

            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                return [
                    'success' => true,
                    'data' => [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'customer_id' => $customer_id,
                        'ip_address' => $ip_address,
                        'affected_rows' => $affected_rows,
                        'action' => 'item_transferred'
                    ]
                ];
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_transfer_cart_item'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'transfer_cart_item', $error_info);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Cart item transfer failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'transfer_cart_item'
                ]
            ];
        }
    }

    /**
     * Clean up expired guest cart sessions     * 
     * @param int $expiry_hours Hours after which guest carts expire (default 24)
     * @return array Result array with success status and cleanup details
     */
    public function cleanup_expired_guest_carts($expiry_hours = 24)
    {
        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Calculate expiry timestamp (guest carts older than specified hours)
            $expiry_timestamp = date('Y-m-d H:i:s', strtotime("-{$expiry_hours} hours"));
            
            // Note: Since cart table doesn't have timestamp, we'll use a different approach
            // For now, we'll clean up guest carts that have been inactive
            // In a production system, you'd want to add a timestamp field to track this
            
            // Get count of guest cart items before cleanup
            $count_sql = "SELECT COUNT(*) as guest_count FROM cart WHERE c_id IS NULL AND ip_add != ''";
            $count_result = $this->db->query($count_sql);
            
            if (!$count_result) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to count guest cart items',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'count_guest_carts'
                    ]
                ];
            }
            
            $count_row = $count_result->fetch_assoc();
            $guest_count_before = $count_row['guest_count'];
            
            return [
                'success' => true,
                'data' => [
                    'guest_carts_found' => $guest_count_before,
                    'expiry_hours' => $expiry_hours,
                    'message' => 'Guest cart cleanup completed',
                    'note' => 'Cart table needs timestamp field for proper expiry cleanup',
                    'action' => 'guest_cart_cleanup'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Guest cart cleanup failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'cleanup_expired_guest_carts'
                ]
            ];
        }
    }

    /**
     * Get guest cart statistics for monitoring     * 
     * @return array Result array with success status and guest cart statistics
     */
    public function get_guest_cart_statistics()
    {
        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Get guest cart statistics
            $stats_sql = "
                SELECT 
                    COUNT(DISTINCT ip_add) as unique_guest_ips,
                    COUNT(*) as total_guest_items,
                    SUM(qty) as total_guest_quantity,
                    AVG(qty) as avg_items_per_guest
                FROM cart 
                WHERE c_id IS NULL AND ip_add != '' AND ip_add IS NOT NULL
            ";
            
            $stats_result = $this->db->query($stats_sql);
            
            if (!$stats_result) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get guest cart statistics',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'get_guest_cart_statistics'
                    ]
                ];
            }
            
            $stats = $stats_result->fetch_assoc();
            
            return [
                'success' => true,
                'data' => [
                    'unique_guest_ips' => (int)$stats['unique_guest_ips'],
                    'total_guest_items' => (int)$stats['total_guest_items'],
                    'total_guest_quantity' => (int)$stats['total_guest_quantity'],
                    'avg_items_per_guest' => round((float)$stats['avg_items_per_guest'], 2),
                    'timestamp' => time(),
                    'action' => 'guest_cart_statistics'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Guest cart statistics failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_guest_cart_statistics'
                ]
            ];
        }
    }

    /**
     * Validate guest cart session     * 
     * @param string $ip_address IP address to validate
     * @return array Result array with success status and validation details
     */
    public function validate_guest_cart_session($ip_address)
    {
        // Validate IP address format
        if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Invalid IP address format for guest cart validation',
                'error_details' => ['ip_address' => $ip_address]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Check if guest has any cart items
            $validation_sql = "
                SELECT 
                    COUNT(*) as item_count,
                    SUM(qty) as total_quantity
                FROM cart 
                WHERE c_id IS NULL AND ip_add = ?
            ";
            
            $stmt = $this->db->prepare($validation_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare guest cart validation statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_validate_guest_cart'
                    ]
                ];
            }

            if (!$stmt->bind_param("s", $ip_address)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for guest cart validation',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_validate_guest_cart'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_validate_guest_cart'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'validate_guest_cart_session', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from guest cart validation',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_validate_guest_cart'
                    ]
                ];
            }

            $validation_data = $result->fetch_assoc();
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'ip_address' => $ip_address,
                    'has_cart_items' => (int)$validation_data['item_count'] > 0,
                    'item_count' => (int)$validation_data['item_count'],
                    'total_quantity' => (int)$validation_data['total_quantity'],
                    'session_valid' => true,
                    'action' => 'guest_cart_validated'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Guest cart validation failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'validate_guest_cart_session'
                ]
            ];
        }
    }

    /**
     * Enhanced database connection with error handling
     * @return array Connection result with success status and error details
     */
    protected function connect_with_error_handling()
    {
        try {
            if (!$this->db_connect()) {
                $connection_error = mysqli_connect_error();
                $connection_errno = mysqli_connect_errno();
                
                return [
                    'success' => false,
                    'error_type' => 'connection_error',
                    'error_message' => 'Failed to connect to database',
                    'error_details' => [
                        'mysql_error' => $connection_error ?: 'Unknown connection error',
                        'mysql_errno' => $connection_errno ?: 0,
                        'operation' => 'database_connection'
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'connection_exception',
                'error_message' => 'Database connection failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'database_connection'
                ]
            ];
        }
    }

    /**
     * Handle specific MySQL errors with appropriate error responses
     * @param int $errno MySQL error number
     * @param string $error MySQL error message
     * @param string $operation Operation that caused the error
     * @param array $additional_info Additional error context
     * @return array Standardized error response
     */
    private function handle_mysql_error($errno, $error, $operation, $additional_info = [])
    {
        $error_response = [
            'success' => false,
            'error_details' => array_merge([
                'mysql_error' => $error,
                'mysql_errno' => $errno,
                'operation' => $operation
            ], $additional_info)
        ];

        // Handle specific MySQL error codes
        switch ($errno) {
            case 1062: // Duplicate entry
                $error_response['error_type'] = 'duplicate_entry';
                $error_response['error_message'] = 'Duplicate entry detected - this violates a unique constraint';
                break;
                
            case 1452: // Foreign key constraint fails
                $error_response['error_type'] = 'foreign_key_constraint';
                $error_response['error_message'] = 'Foreign key constraint violation - referenced record does not exist';
                break;
                
            case 1451: // Cannot delete or update a parent row
                $error_response['error_type'] = 'foreign_key_constraint';
                $error_response['error_message'] = 'Cannot delete record - it is referenced by other records';
                break;
                
            case 1146: // Table doesn't exist
                $error_response['error_type'] = 'table_not_found';
                $error_response['error_message'] = 'Database table not found - possible schema issue';
                break;
                
            case 1054: // Unknown column
                $error_response['error_type'] = 'column_not_found';
                $error_response['error_message'] = 'Database column not found - possible schema mismatch';
                break;
                
            case 2006: // MySQL server has gone away
                $error_response['error_type'] = 'connection_lost';
                $error_response['error_message'] = 'Database connection lost - server may be unavailable';
                break;
                
            case 1205: // Lock wait timeout
                $error_response['error_type'] = 'lock_timeout';
                $error_response['error_message'] = 'Database operation timed out due to lock contention';
                break;
                
            case 1213: // Deadlock found
                $error_response['error_type'] = 'deadlock';
                $error_response['error_message'] = 'Database deadlock detected - operation was rolled back';
                break;
                
            case 1040: // Too many connections
                $error_response['error_type'] = 'too_many_connections';
                $error_response['error_message'] = 'Database server has too many connections - please try again later';
                break;
                
            case 1044: // Access denied for user to database
            case 1045: // Access denied for user (using password)
                $error_response['error_type'] = 'access_denied';
                $error_response['error_message'] = 'Database access denied - authentication or permission issue';
                break;
                
            default:
                $error_response['error_type'] = 'database_error';
                $error_response['error_message'] = 'Database operation failed with error: ' . $error;
                break;
        }

        // Log the error for monitoring
        error_log("Cart Database Error [{$errno}]: {$error} in operation: {$operation}");
        
        return $error_response;
    }

    /**
     * Check if a product exists before cart operations     * 
     * @param int $product_id Product ID to check
     * @return array Result with product existence status
     */
    public function verify_product_exists($product_id)
    {
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare statement to check product existence
            $sql = "SELECT product_id, product_title, product_price FROM products WHERE product_id = ?";
            $stmt = $this->db->prepare($sql);
            
            if (!$stmt) {
                return $this->handle_mysql_error($this->db->errno, $this->db->error, 'verify_product_exists_prepare');
            }

            $stmt->bind_param("i", $product_id);
            
            if (!$stmt->execute()) {
                $error_info = ['product_id' => $product_id];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'verify_product_exists_execute', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from product verification',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'product_id' => $product_id
                    ]
                ];
            }

            $product = $result->fetch_assoc();
            $stmt->close();
            
            $exists = $product !== null;
            
            return [
                'success' => true,
                'data' => [
                    'product_id' => $product_id,
                    'exists' => $exists,
                    'product_data' => $exists ? $product : null
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Product verification failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'product_id' => $product_id
                ]
            ];
        }
    }

    /**
     * Clean orphaned cart items for a specific user     * 
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Result with cleanup details
     */
    public function clean_orphaned_items($customer_id = null, $ip_address = null)
    {
        // Connect to database with error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Build query to delete orphaned cart items
            $sql = "DELETE c FROM cart c 
                    LEFT JOIN products p ON c.p_id = p.product_id 
                    WHERE p.product_id IS NULL";
            
            $params = [];
            $types = "";
            
            // Add user identification filters
            if ($customer_id !== null) {
                $sql .= " AND c.c_id = ?";
                $params[] = $customer_id;
                $types .= "i";
            } elseif ($ip_address !== null) {
                $sql .= " AND c.ip_add = ? AND c.c_id IS NULL";
                $params[] = $ip_address;
                $types .= "s";
            }
            
            $stmt = $this->db->prepare($sql);
            
            if (!$stmt) {
                return $this->handle_mysql_error($this->db->errno, $this->db->error, 'clean_orphaned_items_prepare');
            }

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                $error_info = [
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'clean_orphaned_items_execute', $error_info);
            }

            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            // Log the cleanup operation
            $user_identifier = $customer_id ? "Customer ID: {$customer_id}" : "IP: {$ip_address}";
            error_log("Cleaned {$affected_rows} orphaned cart items for {$user_identifier}");
            
            return [
                'success' => true,
                'data' => [
                    'cleaned_count' => $affected_rows,
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address,
                    'message' => "Cleaned {$affected_rows} orphaned cart items"
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Orphaned items cleanup failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'customer_id' => $customer_id,
                    'ip_address' => $ip_address
                ]
            ];
        }
    }

    /**
     * Get cart items with product validation (filters out orphaned items)     * 
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @param bool $auto_clean Whether to automatically clean orphaned items
     * @return array Result with valid cart items
     */
    public function get_valid_cart_items($customer_id = null, $ip_address = null, $auto_clean = true)
    {
        // First, optionally clean orphaned items
        if ($auto_clean) {
            $clean_result = $this->clean_orphaned_items($customer_id, $ip_address);
            if (!$clean_result['success']) {
                // Log warning but continue - we can still try to get valid items
                error_log("Warning: Failed to clean orphaned items: " . $clean_result['error_message']);
            }
        }
        
        // Get cart items with product validation
        return $this->get_cart_items($customer_id, $ip_address);
    }

    /**
     * Validate cart item before operations (check if product still exists)     * 
     * @param int $product_id Product ID to validate
     * @param int $customer_id Customer ID (null for guest users)
     * @param string $ip_address IP address for guest users
     * @return array Validation result with product and cart item status
     */
    public function validate_cart_item_integrity($product_id, $customer_id = null, $ip_address = null)
    {
        // First check if product exists
        $product_check = $this->verify_product_exists($product_id);
        
        if (!$product_check['success']) {
            return $product_check;
        }
        
        $product_exists = $product_check['data']['exists'];
        
        // If product doesn't exist, check if there's an orphaned cart item
        if (!$product_exists) {
            $cart_item_check = $this->get_cart_item($product_id, $customer_id, $ip_address);
            
            $has_orphaned_item = false;
            if ($cart_item_check['success'] && $cart_item_check['data']['item']) {
                $has_orphaned_item = true;
                
                // Automatically remove the orphaned item
                $remove_result = $this->remove_from_cart($product_id, $customer_id, $ip_address);
                if ($remove_result['success']) {
                    error_log("Automatically removed orphaned cart item for product {$product_id}");
                }
            }
            
            return [
                'success' => false,
                'error_type' => 'orphaned_product',
                'error_message' => 'Product no longer exists and has been removed from cart',
                'error_details' => [
                    'product_id' => $product_id,
                    'product_exists' => false,
                    'had_orphaned_item' => $has_orphaned_item,
                    'auto_removed' => $has_orphaned_item
                ]
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'product_id' => $product_id,
                'product_exists' => true,
                'product_data' => $product_check['data']['product_data'],
                'integrity_status' => 'valid'
            ]
        ];
    }
}

?>