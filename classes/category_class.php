<?php

require_once __DIR__ . '/../settings/db_class.php';

/**
 * Category Class
 * Handles category data access and management operations
 * Requirements: 3.1, 3.2, 4.1, 5.1
 */
class Category extends db_connection
{
    /**
     * Add a new category for a specific user
     * Requirements: 3.1, 3.2
     * 
     * @param string $cat_name Category name
     * @param int $user_id User ID who owns the category
     * @return array Result array with success status and data/error details
     */
    public function add_category($cat_name, $user_id)
    {
        // Validate input
        if (empty($cat_name) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Category name and user ID are required',
                'error_details' => ['cat_name' => empty($cat_name), 'user_id' => empty($user_id)]
            ];
        }

        // Check if category name already exists for this user
        $name_check = $this->check_category_name_exists($cat_name, $user_id);
        if ($name_check['success'] && $name_check['exists']) {
            return [
                'success' => false,
                'error_type' => 'duplicate_name',
                'error_message' => 'Category name already exists for this user',
                'error_details' => ['cat_name' => $cat_name, 'user_id' => $user_id]
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
            $stmt = $this->db->prepare("INSERT INTO categories (cat_name, user_id) VALUES (?, ?)");
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
            if (!$stmt->bind_param("si", $cat_name, $user_id)) {
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
                $category_id = $this->last_insert_id();
                $stmt->close();
                
                if ($category_id > 0) {
                    return [
                        'success' => true,
                        'data' => [
                            'category_id' => $category_id,
                            'category_name' => $cat_name,
                            'user_id' => $user_id
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'database_error',
                        'error_message' => 'Category was inserted but ID retrieval failed',
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'add_category', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Database operation failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'add_category'
                ]
            ];
        }
    }

    /**
     * Get all categories for a specific user
     * Requirements: 2.1, 2.2
     * 
     * @param int $user_id User ID
     * @return array Result array with success status and categories data
     */
    public function get_categories_by_user($user_id)
    {
        // Validate input
        if (empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'User ID is required to retrieve categories',
                'error_details' => ['user_id' => empty($user_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT cat_id, cat_name, user_id, date_created, date_modified 
                    FROM categories 
                    WHERE user_id = ? 
                    ORDER BY cat_name ASC";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare categories retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_categories'
                    ]
                ];
            }

            if (!$stmt->bind_param("i", $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for categories retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_categories'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_categories'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_categories_by_user', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from categories retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_categories'
                    ]
                ];
            }

            $categories = [];
            if ($result->num_rows > 0) {
                $categories = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'count' => count($categories),
                    'user_id' => $user_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Categories retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_categories_by_user'
                ]
            ];
        }
    }

    /**
     * Get a specific category by ID
     * Requirements: 4.1, 5.1
     * 
     * @param int $cat_id Category ID
     * @return array Result array with success status and category data
     */
    public function get_category_by_id($cat_id)
    {
        // Validate input
        if (empty($cat_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Category ID is required',
                'error_details' => ['cat_id' => empty($cat_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT cat_id, cat_name, user_id, date_created, date_modified 
                    FROM categories 
                    WHERE cat_id = ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare category retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_by_id'
                    ]
                ];
            }

            if (!$stmt->bind_param("i", $cat_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for category retrieval',
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_category_by_id', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from category retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_by_id'
                    ]
                ];
            }

            $category = null;
            if ($result->num_rows > 0) {
                $category = $result->fetch_assoc();
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'category' => $category,
                    'found' => $category !== null,
                    'cat_id' => $cat_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Category retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_category_by_id'
                ]
            ];
        }
    }

    /**
     * Update a category name
     * Requirements: 4.1, 4.2, 4.3
     * 
     * @param int $cat_id Category ID
     * @param string $cat_name New category name
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and update details
     */
    public function update_category($cat_id, $cat_name, $user_id)
    {
        // Validate input
        if (empty($cat_id) || empty($cat_name) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Category ID, name, and user ID are required for update',
                'error_details' => [
                    'cat_id' => empty($cat_id),
                    'cat_name' => empty($cat_name),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Check if category exists and belongs to user
        $existing_category_result = $this->get_category_by_id($cat_id);
        if (!$existing_category_result['success']) {
            return $existing_category_result; // Return the database error
        }
        
        if (!$existing_category_result['data']) {
            return [
                'success' => false,
                'error_type' => 'not_found',
                'error_message' => 'Category not found',
                'error_details' => ['cat_id' => $cat_id]
            ];
        }
        
        $existing_category = $existing_category_result['data']['category'];
        if ($existing_category['user_id'] != $user_id) {
            return [
                'success' => false,
                'error_type' => 'access_denied',
                'error_message' => 'Access denied - category belongs to different user',
                'error_details' => [
                    'cat_id' => $cat_id,
                    'requested_user_id' => $user_id,
                    'actual_user_id' => $existing_category['user_id']
                ]
            ];
        }

        // Check if new name already exists for this user (excluding current category)
        $name_check = $this->check_category_name_exists($cat_name, $user_id, $cat_id);
        if (!$name_check['success']) {
            return $name_check; // Return the database error from name check
        }
        
        if ($name_check['exists']) {
            return [
                'success' => false,
                'error_type' => 'duplicate_name',
                'error_message' => 'Category name already exists for this user',
                'error_details' => [
                    'cat_name' => $cat_name,
                    'user_id' => $user_id,
                    'exclude_id' => $cat_id
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
            $stmt = $this->db->prepare("UPDATE categories SET cat_name = ? WHERE cat_id = ? AND user_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare category update statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_update'
                    ]
                ];
            }

            if (!$stmt->bind_param("sii", $cat_name, $cat_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for category update',
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
                            'category_id' => $cat_id,
                            'category_name' => $cat_name,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'no_changes',
                        'error_message' => 'No rows were updated - category may not exist or no changes were made',
                        'error_details' => [
                            'cat_id' => $cat_id,
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'update_category', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Category update failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'update_category'
                ]
            ];
        }
    }

    /**
     * Delete a category
     * Requirements: 5.1
     * 
     * @param int $cat_id Category ID
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and deletion details
     */
    public function delete_category($cat_id, $user_id)
    {
        // Validate input
        if (empty($cat_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Category ID and user ID are required for deletion',
                'error_details' => [
                    'cat_id' => empty($cat_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Check if category exists and belongs to user
        $existing_category_result = $this->get_category_by_id($cat_id);
        if (!$existing_category_result['success']) {
            return $existing_category_result; // Return the database error
        }
        
        if (!$existing_category_result['data']['category']) {
            return [
                'success' => false,
                'error_type' => 'not_found',
                'error_message' => 'Category not found',
                'error_details' => ['cat_id' => $cat_id]
            ];
        }
        
        $existing_category = $existing_category_result['data']['category'];
        if ($existing_category['user_id'] != $user_id) {
            return [
                'success' => false,
                'error_type' => 'access_denied',
                'error_message' => 'Access denied - category belongs to different user',
                'error_details' => [
                    'cat_id' => $cat_id,
                    'requested_user_id' => $user_id,
                    'actual_user_id' => $existing_category['user_id']
                ]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare and execute delete statement
            $stmt = $this->db->prepare("DELETE FROM categories WHERE cat_id = ? AND user_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare category deletion statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_delete'
                    ]
                ];
            }

            if (!$stmt->bind_param("ii", $cat_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for category deletion',
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
                            'category_id' => $cat_id,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows,
                            'deleted_category' => $existing_category
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'no_changes',
                        'error_message' => 'No rows were deleted - category may not exist',
                        'error_details' => [
                            'cat_id' => $cat_id,
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'delete_category', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Category deletion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'delete_category'
                ]
            ];
        }
    }

    /**
     * Check if a category name already exists for a user
     * Requirements: 3.2, 4.3
     * 
     * @param string $cat_name Category name to check
     * @param int $user_id User ID
     * @param int $exclude_id Optional category ID to exclude from check (for updates)
     * @return array Result array with success status and existence data
     */
    public function check_category_name_exists($cat_name, $user_id, $exclude_id = null)
    {
        // Validate input
        if (empty($cat_name) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Category name and user ID are required for existence check',
                'error_details' => ['cat_name' => empty($cat_name), 'user_id' => empty($user_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT cat_id FROM categories WHERE cat_name = ? AND user_id = ?";
            $params = array($cat_name, $user_id);
            $types = "si";

            // If excluding a specific category (for updates)
            if ($exclude_id !== null) {
                $sql .= " AND cat_id != ?";
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'check_category_name_exists', $error_info);
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
                    'cat_name' => $cat_name,
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
                    'operation' => 'check_category_name_exists'
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
        error_log("Category Database Error [{$errno}]: {$error} in operation: {$operation}");
        
        return $error_response;
    }
}

?>