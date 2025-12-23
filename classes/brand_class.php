<?php

require_once __DIR__ . '/../settings/db_class.php';

/**
 * Brand Class
 * Handles brand data access and management operations
 * Requirements: 2.1, 3.1, 4.1
 */
class Brand extends db_connection
{
    /**
     * Add a new brand for a specific user and category
     * Requirements: 2.1, 2.2, 2.3
     * 
     * @param string $brand_name Brand name
     * @param int $category_id Category ID
     * @param int $user_id User ID who owns the brand
     * @return array Result array with success status and data/error details
     */
    public function add_brand($brand_name, $category_id, $user_id)
    {
        // Validate input
        if (empty($brand_name) || empty($category_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand name, category ID, and user ID are required',
                'error_details' => [
                    'brand_name' => empty($brand_name),
                    'category_id' => empty($category_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Validate brand name length
        if (strlen($brand_name) > 255) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand name cannot exceed 255 characters',
                'error_details' => ['brand_name_length' => strlen($brand_name)]
            ];
        }

        // Verify category exists and belongs to user
        $category_check = $this->verify_category_ownership($category_id, $user_id);
        if (!$category_check['success']) {
            return $category_check; // Return the error from category verification
        }

        // Check if brand name already exists for this user and category
        $name_check = $this->check_brand_name_exists($brand_name, $category_id, $user_id);
        if ($name_check['success'] && $name_check['exists']) {
            return [
                'success' => false,
                'error_type' => 'duplicate_name',
                'error_message' => 'Brand name already exists in this category for this user',
                'error_details' => [
                    'brand_name' => $brand_name,
                    'category_id' => $category_id,
                    'user_id' => $user_id
                ]
            ];
        } elseif (!$name_check['success']) {
            return $name_check; // Return the database error from name check
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare statement with error handling
            $stmt = $this->db->prepare("INSERT INTO brands (brand_name, category_id, user_id) VALUES (?, ?, ?)");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare database statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_insert'
                    ]
                ];
            }

            // Bind parameters with error handling
            if (!$stmt->bind_param("sii", $brand_name, $category_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params'
                    ]
                ];
            }
            
            // Execute with comprehensive error handling
            if ($stmt->execute()) {
                $brand_id = $this->last_insert_id();
                $stmt->close();
                
                if ($brand_id > 0) {
                    return [
                        'success' => true,
                        'data' => [
                            'brand_id' => $brand_id,
                            'brand_name' => $brand_name,
                            'category_id' => $category_id,
                            'user_id' => $user_id
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'database_error',
                        'error_message' => 'Brand was inserted but ID retrieval failed',
                        'error_details' => ['operation' => 'last_insert_id']
                    ];
                }
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_insert'
                ];
                $stmt->close();
                
                // Handle specific MySQL errors
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'add_brand', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Database operation failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'add_brand'
                ]
            ];
        }
    }

    /**
     * Get all brands for a specific user, optionally filtered by category
     * Requirements: 1.1, 1.2
     * 
     * @param int $user_id User ID
     * @param int $category_id Optional category ID to filter by
     * @return array Result array with success status and brands data
     */
    public function get_brands($user_id, $category_id = null)
    {
        // Validate input
        if (empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'User ID is required to retrieve brands',
                'error_details' => ['user_id' => empty($user_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT b.brand_id, b.brand_name, b.category_id, b.user_id, b.created_at, b.updated_at, 
                           c.cat_name
                    FROM brands b
                    INNER JOIN categories c ON b.category_id = c.cat_id
                    WHERE b.user_id = ?";
            $params = [$user_id];
            $types = "i";

            // Add category filter if specified
            if ($category_id !== null) {
                $sql .= " AND b.category_id = ?";
                $params[] = $category_id;
                $types .= "i";
            }

            $sql .= " ORDER BY c.cat_name ASC, b.brand_name ASC";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare brands retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_brands'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for brands retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_brands'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_brands'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_brands', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from brands retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_brands'
                    ]
                ];
            }

            $brands = [];
            if ($result->num_rows > 0) {
                $brands = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'brands' => $brands,
                    'count' => count($brands),
                    'user_id' => $user_id,
                    'category_id' => $category_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Brands retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_brands'
                ]
            ];
        }
    }

    /**
     * Get a specific brand by ID
     * Requirements: 3.5, 4.1
     * 
     * @param int $brand_id Brand ID
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and brand data
     */
    public function get_brand_by_id($brand_id, $user_id = null)
    {
        // Validate input
        if (empty($brand_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand ID is required',
                'error_details' => ['brand_id' => empty($brand_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT b.brand_id, b.brand_name, b.category_id, b.user_id, b.created_at, b.updated_at,
                           c.cat_name
                    FROM brands b
                    INNER JOIN categories c ON b.category_id = c.cat_id
                    WHERE b.brand_id = ?";
            $params = [$brand_id];
            $types = "i";

            // Add user filter if specified (for ownership verification)
            if ($user_id !== null) {
                $sql .= " AND b.user_id = ?";
                $params[] = $user_id;
                $types .= "i";
            }
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare brand retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_by_id'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for brand retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_by_id'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_by_id'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_brand_by_id', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from brand retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_by_id'
                    ]
                ];
            }

            $brand = null;
            if ($result->num_rows > 0) {
                $brand = $result->fetch_assoc();
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'brand' => $brand,
                    'found' => $brand !== null,
                    'brand_id' => $brand_id,
                    'user_id' => $user_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Brand retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_brand_by_id'
                ]
            ];
        }
    }

    /**
     * Update a brand name
     * Requirements: 3.1, 3.2, 3.3
     * 
     * @param int $brand_id Brand ID
     * @param string $brand_name New brand name
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and update details
     */
    public function update_brand($brand_id, $brand_name, $user_id)
    {
        // Validate input
        if (empty($brand_id) || empty($brand_name) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand ID, name, and user ID are required for update',
                'error_details' => [
                    'brand_id' => empty($brand_id),
                    'brand_name' => empty($brand_name),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Validate brand name length
        if (strlen($brand_name) > 255) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand name cannot exceed 255 characters',
                'error_details' => ['brand_name_length' => strlen($brand_name)]
            ];
        }

        // Check if brand exists and belongs to user
        $existing_brand_result = $this->get_brand_by_id($brand_id, $user_id);
        if (!$existing_brand_result['success']) {
            return $existing_brand_result; // Return the database error
        }
        
        if (!$existing_brand_result['data']['brand']) {
            return [
                'success' => false,
                'error_type' => 'not_found',
                'error_message' => 'Brand not found or access denied',
                'error_details' => ['brand_id' => $brand_id, 'user_id' => $user_id]
            ];
        }
        
        $existing_brand = $existing_brand_result['data']['brand'];

        // Check if new name already exists in the same category (excluding current brand)
        $name_check = $this->check_brand_name_exists($brand_name, $existing_brand['category_id'], $user_id, $brand_id);
        if (!$name_check['success']) {
            return $name_check; // Return the database error from name check
        }
        
        if ($name_check['exists']) {
            return [
                'success' => false,
                'error_type' => 'duplicate_name',
                'error_message' => 'Brand name already exists in this category',
                'error_details' => [
                    'brand_name' => $brand_name,
                    'category_id' => $existing_brand['category_id'],
                    'user_id' => $user_id,
                    'exclude_id' => $brand_id
                ]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare and execute update statement
            $stmt = $this->db->prepare("UPDATE brands SET brand_name = ? WHERE brand_id = ? AND user_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare brand update statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_update'
                    ]
                ];
            }

            if (!$stmt->bind_param("sii", $brand_name, $brand_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for brand update',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_update'
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
                            'brand_id' => $brand_id,
                            'brand_name' => $brand_name,
                            'category_id' => $existing_brand['category_id'],
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'no_changes',
                        'error_message' => 'No rows were updated - brand may not exist or no changes were made',
                        'error_details' => [
                            'brand_id' => $brand_id,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                }
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_update'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'update_brand', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Brand update failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'update_brand'
                ]
            ];
        }
    }

    /**
     * Delete a brand
     * Requirements: 4.1, 4.5
     * 
     * @param int $brand_id Brand ID
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and deletion details
     */
    public function delete_brand($brand_id, $user_id)
    {
        // Validate input
        if (empty($brand_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand ID and user ID are required for deletion',
                'error_details' => [
                    'brand_id' => empty($brand_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Check if brand exists and belongs to user
        $existing_brand_result = $this->get_brand_by_id($brand_id, $user_id);
        if (!$existing_brand_result['success']) {
            return $existing_brand_result; // Return the database error
        }
        
        if (!$existing_brand_result['data']['brand']) {
            return [
                'success' => false,
                'error_type' => 'not_found',
                'error_message' => 'Brand not found or access denied',
                'error_details' => ['brand_id' => $brand_id, 'user_id' => $user_id]
            ];
        }
        
        $existing_brand = $existing_brand_result['data']['brand'];

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare and execute delete statement
            $stmt = $this->db->prepare("DELETE FROM brands WHERE brand_id = ? AND user_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare brand deletion statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_delete'
                    ]
                ];
            }

            if (!$stmt->bind_param("ii", $brand_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for brand deletion',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_delete'
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
                            'brand_id' => $brand_id,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows,
                            'deleted_brand' => $existing_brand
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'no_changes',
                        'error_message' => 'No rows were deleted - brand may not exist',
                        'error_details' => [
                            'brand_id' => $brand_id,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                }
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_delete'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'delete_brand', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Brand deletion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'delete_brand'
                ]
            ];
        }
    }

    /**
     * Check if a brand name already exists for a user and category
     * Requirements: 2.3, 3.3
     * 
     * @param string $brand_name Brand name to check
     * @param int $category_id Category ID
     * @param int $user_id User ID
     * @param int $exclude_id Optional brand ID to exclude from check (for updates)
     * @return array Result array with success status and existence data
     */
    public function check_brand_name_exists($brand_name, $category_id, $user_id, $exclude_id = null)
    {
        // Validate input
        if (empty($brand_name) || empty($category_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Brand name, category ID, and user ID are required for existence check',
                'error_details' => [
                    'brand_name' => empty($brand_name),
                    'category_id' => empty($category_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT brand_id FROM brands WHERE brand_name = ? AND category_id = ? AND user_id = ?";
            $params = array($brand_name, $category_id, $user_id);
            $types = "sii";

            // If excluding a specific brand (for updates)
            if ($exclude_id !== null) {
                $sql .= " AND brand_id != ?";
                $params[] = $exclude_id;
                $types .= "i";
            }

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare existence check statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_existence_check'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for existence check',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_existence_check'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_existence_check'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'check_brand_name_exists', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from existence check',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_existence_check'
                    ]
                ];
            }

            $exists = $result->num_rows > 0;
            $stmt->close();
            
            return [
                'success' => true,
                'exists' => $exists,
                'data' => [
                    'brand_name' => $brand_name,
                    'category_id' => $category_id,
                    'user_id' => $user_id,
                    'exclude_id' => $exclude_id,
                    'exists' => $exists
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Database existence check failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'check_brand_name_exists'
                ]
            ];
        }
    }

    /**
     * Verify that a category exists and belongs to the specified user
     * Requirements: 2.1
     * 
     * @param int $category_id Category ID
     * @param int $user_id User ID
     * @return array Result array with success status and verification data
     */
    private function verify_category_ownership($category_id, $user_id)
    {
        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT cat_id FROM categories WHERE cat_id = ? AND user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare category verification statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_category_verification'
                    ]
                ];
            }

            if (!$stmt->bind_param("ii", $category_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for category verification',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_category_verification'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_category_verification'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'verify_category_ownership', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from category verification',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_category_verification'
                    ]
                ];
            }

            $category_exists = $result->num_rows > 0;
            $stmt->close();
            
            if (!$category_exists) {
                return [
                    'success' => false,
                    'error_type' => 'category_not_found',
                    'error_message' => 'Category not found or access denied',
                    'error_details' => [
                        'category_id' => $category_id,
                        'user_id' => $user_id
                    ]
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'category_id' => $category_id,
                    'user_id' => $user_id,
                    'verified' => true
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Category verification failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'verify_category_ownership'
                ]
            ];
        }
    }

    /**
     * Enhanced database connection with comprehensive error handling
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
        error_log("Brand Database Error [{$errno}]: {$error} in operation: {$operation}");
        
        return $error_response;
    }
}

?>