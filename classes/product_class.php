<?php

require_once __DIR__ . '/../settings/db_class.php';

/**
 * Product Class
 * 
 * Manages product data operations including CRUD functionality,
 * search capabilities, and category/brand associations.
 */
class Product extends db_connection
{
    /**
     * Add a new product to the database
    /**
     * Add a new product to the database
     * 
     * Creates a new product with all associated metadata including category,
     * brand, pricing, and optional image/description information.
     * 
     * @param string $product_title Product name/title
     * @param float $product_price Product price
     * @param string $product_description Product description
     * @param string $product_image Image file path
     * @param string $product_keywords Search keywords
     * @param int $category_id Category ID
     * @param int $brand_id Brand ID
     * @param int $user_id Owner user ID
     * @return array Success status and data/error details
     */
    public function add_product($product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id)
    {
        if (empty($product_title) || empty($product_price) || empty($category_id) || empty($brand_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product title, price, category ID, brand ID, and user ID are required',
                'error_details' => [
                    'product_title' => empty($product_title),
                    'product_price' => empty($product_price),
                    'category_id' => empty($category_id),
                    'brand_id' => empty($brand_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Validate product title length
        if (strlen($product_title) > 255) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product title cannot exceed 255 characters',
                'error_details' => ['product_title_length' => strlen($product_title)]
            ];
        }

        // Validate price is numeric and positive
        if (!is_numeric($product_price) || $product_price < 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product price must be a positive number',
                'error_details' => ['product_price' => $product_price]
            ];
        }

        // Verify category exists and belongs to user
        $category_check = $this->verify_category_ownership($category_id, $user_id);
        if (!$category_check['success']) {
            return $category_check;
        }

        // Verify brand exists and belongs to user
        $brand_check = $this->verify_brand_ownership($brand_id, $user_id);
        if (!$brand_check['success']) {
            return $brand_check;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare statement with error handling
            $stmt = $this->db->prepare("INSERT INTO products (product_title, product_price, product_description, product_image, product_keywords, category_id, brand_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
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
            if (!$stmt->bind_param("sdssssii", $product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id)) {
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
                $product_id = $this->last_insert_id();
                $stmt->close();
                
                if ($product_id > 0) {
                    return [
                        'success' => true,
                        'data' => [
                            'product_id' => $product_id,
                            'product_title' => $product_title,
                            'product_price' => $product_price,
                            'product_description' => $product_description,
                            'product_image' => $product_image,
                            'product_keywords' => $product_keywords,
                            'category_id' => $category_id,
                            'brand_id' => $brand_id,
                            'user_id' => $user_id
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'database_error',
                        'error_message' => 'Product was inserted but ID retrieval failed',
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'add_product', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Database operation failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'add_product'
                ]
            ];
        }
    }

    /**
     * Get all products for a specific user, optionally filtered by category or brand     * 
     * @param int $user_id User ID
     * @param int $category_id Optional category ID to filter by
     * @param int $brand_id Optional brand ID to filter by
     * @return array Result array with success status and products data
     */
    public function get_products($user_id, $category_id = null, $brand_id = null)
    {
        // Validate input
        if (empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'User ID is required to retrieve products',
                'error_details' => ['user_id' => empty($user_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, p.user_id, 
                           p.created_at, p.updated_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.user_id = ?";
            $params = [$user_id];
            $types = "i";

            // Add category filter if specified
            if ($category_id !== null) {
                $sql .= " AND p.category_id = ?";
                $params[] = $category_id;
                $types .= "i";
            }

            // Add brand filter if specified
            if ($brand_id !== null) {
                $sql .= " AND p.brand_id = ?";
                $params[] = $brand_id;
                $types .= "i";
            }

            $sql .= " ORDER BY c.cat_name ASC, b.brand_name ASC, p.product_title ASC";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare products retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_products'
                    ]
                ];
            }

            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for products retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_products'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_products'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_products', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from products retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_products'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'user_id' => $user_id,
                    'category_id' => $category_id,
                    'brand_id' => $brand_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Products retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_products'
                ]
            ];
        }
    }

    /**
     * Get a specific product by ID     * 
     * @param int $product_id Product ID
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and product data
     */
    public function get_product_by_id($product_id, $user_id = null)
    {
        // Validate input
        if (empty($product_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID is required',
                'error_details' => ['product_id' => empty($product_id)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description,
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, p.user_id,
                           p.created_at, p.updated_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.product_id = ?";
            $params = [$product_id];
            $types = "i";

            // Add user filter if specified (for ownership verification)
            if ($user_id !== null) {
                $sql .= " AND p.user_id = ?";
                $params[] = $user_id;
                $types .= "i";
            }
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare product retrieval statement',
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
                    'error_message' => 'Failed to bind parameters for product retrieval',
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_product_by_id', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from product retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_by_id'
                    ]
                ];
            }

            $product = null;
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'product' => $product,
                    'found' => $product !== null,
                    'product_id' => $product_id,
                    'user_id' => $user_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Product retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_product_by_id'
                ]
            ];
        }
    }

    /**
     * Update a product     * 
     * @param int $product_id Product ID
     * @param string $product_title Product title
     * @param float $product_price Product price
     * @param string $product_description Product description
     * @param string $product_image Product image path
     * @param string $product_keywords Product keywords
     * @param int $category_id Category ID
     * @param int $brand_id Brand ID
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and update details
     */
    public function update_product($product_id, $product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $user_id)
    {
        // Validate input
        if (empty($product_id) || empty($product_title) || empty($product_price) || empty($category_id) || empty($brand_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID, title, price, category ID, brand ID, and user ID are required for update',
                'error_details' => [
                    'product_id' => empty($product_id),
                    'product_title' => empty($product_title),
                    'product_price' => empty($product_price),
                    'category_id' => empty($category_id),
                    'brand_id' => empty($brand_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Validate product title length
        if (strlen($product_title) > 255) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product title cannot exceed 255 characters',
                'error_details' => ['product_title_length' => strlen($product_title)]
            ];
        }

        // Validate price is numeric and positive
        if (!is_numeric($product_price) || $product_price < 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product price must be a positive number',
                'error_details' => ['product_price' => $product_price]
            ];
        }

        // Check if product exists and belongs to user
        $existing_product_result = $this->get_product_by_id($product_id, $user_id);
        if (!$existing_product_result['success']) {
            return $existing_product_result;
        }
        
        if (!$existing_product_result['data']['product']) {
            return [
                'success' => false,
                'error_type' => 'not_found',
                'error_message' => 'Product not found or access denied',
                'error_details' => ['product_id' => $product_id, 'user_id' => $user_id]
            ];
        }

        // Verify category exists and belongs to user
        $category_check = $this->verify_category_ownership($category_id, $user_id);
        if (!$category_check['success']) {
            return $category_check;
        }

        // Verify brand exists and belongs to user
        $brand_check = $this->verify_brand_ownership($brand_id, $user_id);
        if (!$brand_check['success']) {
            return $brand_check;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare and execute update statement
            $stmt = $this->db->prepare("UPDATE products SET product_title = ?, product_price = ?, product_description = ?, product_image = ?, product_keywords = ?, category_id = ?, brand_id = ? WHERE product_id = ? AND user_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare product update statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_update'
                    ]
                ];
            }

            if (!$stmt->bind_param("sdsssiiii", $product_title, $product_price, $product_description, $product_image, $product_keywords, $category_id, $brand_id, $product_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for product update',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_update'
                    ]
                ];
            }
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                if ($affected_rows >= 0) {
                    return [
                        'success' => true,
                        'data' => [
                            'product_id' => $product_id,
                            'product_title' => $product_title,
                            'product_price' => $product_price,
                            'product_description' => $product_description,
                            'product_image' => $product_image,
                            'product_keywords' => $product_keywords,
                            'category_id' => $category_id,
                            'brand_id' => $brand_id,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'no_changes',
                        'error_message' => 'No rows were updated - product may not exist',
                        'error_details' => [
                            'product_id' => $product_id,
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'update_product', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Product update failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'update_product'
                ]
            ];
        }
    }

    /**
     * Delete a product     * 
     * @param int $product_id Product ID
     * @param int $user_id User ID (for ownership verification)
     * @return array Result array with success status and deletion details
     */
    public function delete_product($product_id, $user_id)
    {
        // Validate input
        if (empty($product_id) || empty($user_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Product ID and user ID are required for deletion',
                'error_details' => [
                    'product_id' => empty($product_id),
                    'user_id' => empty($user_id)
                ]
            ];
        }

        // Check if product exists and belongs to user
        $existing_product_result = $this->get_product_by_id($product_id, $user_id);
        if (!$existing_product_result['success']) {
            return $existing_product_result;
        }
        
        if (!$existing_product_result['data']['product']) {
            return [
                'success' => false,
                'error_type' => 'not_found',
                'error_message' => 'Product not found or access denied',
                'error_details' => ['product_id' => $product_id, 'user_id' => $user_id]
            ];
        }
        
        $existing_product = $existing_product_result['data']['product'];

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare and execute delete statement
            $stmt = $this->db->prepare("DELETE FROM products WHERE product_id = ? AND user_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare product deletion statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_delete'
                    ]
                ];
            }

            if (!$stmt->bind_param("ii", $product_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for product deletion',
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
                            'product_id' => $product_id,
                            'user_id' => $user_id,
                            'affected_rows' => $affected_rows,
                            'deleted_product' => $existing_product
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'no_changes',
                        'error_message' => 'No rows were deleted - product may not exist',
                        'error_details' => [
                            'product_id' => $product_id,
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
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'delete_product', $error_info);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Product deletion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'delete_product'
                ]
            ];
        }
    }
    /**
     * Verify that a category exists and belongs to the specified user     * 
     * @param int $category_id Category ID
     * @param int $user_id User ID
     * @return array Result array with success status and verification data
     */
    public function verify_category_ownership($category_id, $user_id)
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
     * Verify that a brand exists and belongs to the specified user     * 
     * @param int $brand_id Brand ID
     * @param int $user_id User ID
     * @return array Result array with success status and verification data
     */
    public function verify_brand_ownership($brand_id, $user_id)
    {
        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT brand_id FROM brands WHERE brand_id = ? AND user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare brand verification statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_brand_verification'
                    ]
                ];
            }

            if (!$stmt->bind_param("ii", $brand_id, $user_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for brand verification',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_brand_verification'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_brand_verification'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'verify_brand_ownership', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from brand verification',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_brand_verification'
                    ]
                ];
            }

            $brand_exists = $result->num_rows > 0;
            $stmt->close();
            
            if (!$brand_exists) {
                return [
                    'success' => false,
                    'error_type' => 'brand_not_found',
                    'error_message' => 'Brand not found or access denied',
                    'error_details' => [
                        'brand_id' => $brand_id,
                        'user_id' => $user_id
                    ]
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'brand_id' => $brand_id,
                    'user_id' => $user_id,
                    'verified' => true
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Brand verification failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'verify_brand_ownership'
                ]
            ];
        }
    }

    /**
     * View all products for customer display (public-facing)     * 
     * @param int $limit Number of products per page (default 10)
     * @param int $offset Starting position for pagination
     * @return array Result array with success status and products data
     */
    public function view_all_products($limit = 10, $offset = 0)
    {
        // Validate input
        if (!is_numeric($limit) || $limit < 1) {
            $limit = 10;
        }
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                           p.created_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare view all products statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_view_all_products'
                    ]
                ];
            }

            if (!$stmt->bind_param("ii", $limit, $offset)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for view all products',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_view_all_products'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_view_all_products'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'view_all_products', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from view all products',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_view_all_products'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'View all products failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'view_all_products'
                ]
            ];
        }
    }

    /**
     * Search products by title and keywords (public-facing)     * 
     * @param string $query Search query
     * @param int $limit Number of products per page (default 10)
     * @param int $offset Starting position for pagination
     * @return array Result array with success status and search results
     */
    public function search_products($query, $limit = 10, $offset = 0)
    {
        // Validate input
        if (empty($query)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Search query is required',
                'error_details' => ['query' => empty($query)]
            ];
        }

        if (!is_numeric($limit) || $limit < 1) {
            $limit = 10;
        }
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Prepare search terms for partial matching
            $search_term = '%' . $query . '%';
            
            // Split query into multiple terms for OR matching
            $terms = explode(' ', trim($query));
            $search_conditions = [];
            $search_params = [];
            
            foreach ($terms as $term) {
                if (!empty(trim($term))) {
                    $term_pattern = '%' . trim($term) . '%';
                    $search_conditions[] = "(p.product_title LIKE ? OR p.product_keywords LIKE ?)";
                    $search_params[] = $term_pattern;
                    $search_params[] = $term_pattern;
                }
            }
            
            if (empty($search_conditions)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'No valid search terms found',
                    'error_details' => ['query' => $query]
                ];
            }
            
            $search_where = implode(' OR ', $search_conditions);
            
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                           p.created_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE ({$search_where})
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare search products statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_search_products'
                    ]
                ];
            }

            // Add limit and offset to parameters
            $search_params[] = $limit;
            $search_params[] = $offset;
            
            // Create type string for bind_param
            $types = str_repeat('s', count($search_params) - 2) . 'ii';
            
            if (!$stmt->bind_param($types, ...$search_params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for search products',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_search_products'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_search_products'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'search_products', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from search products',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_search_products'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'query' => $query,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Search products failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'search_products'
                ]
            ];
        }
    }

    /**
     * Filter products by category (public-facing)     * 
     * @param int $category_id Category ID to filter by
     * @param int $limit Number of products per page (default 10)
     * @param int $offset Starting position for pagination
     * @return array Result array with success status and filtered products
     */
    public function filter_products_by_category($category_id, $limit = 10, $offset = 0)
    {
        // Validate input
        if (empty($category_id) || !is_numeric($category_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid category ID is required',
                'error_details' => ['category_id' => $category_id]
            ];
        }

        if (!is_numeric($limit) || $limit < 1) {
            $limit = 10;
        }
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                           p.created_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.category_id = ?
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare filter by category statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_filter_by_category'
                    ]
                ];
            }

            if (!$stmt->bind_param("iii", $category_id, $limit, $offset)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for filter by category',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_filter_by_category'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_filter_by_category'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'filter_products_by_category', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from filter by category',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_filter_by_category'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'category_id' => $category_id,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Filter by category failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'filter_products_by_category'
                ]
            ];
        }
    }

    /**
     * Filter products by brand (public-facing)     * 
     * @param int $brand_id Brand ID to filter by
     * @param int $limit Number of products per page (default 10)
     * @param int $offset Starting position for pagination
     * @return array Result array with success status and filtered products
     */
    public function filter_products_by_brand($brand_id, $limit = 10, $offset = 0)
    {
        // Validate input
        if (empty($brand_id) || !is_numeric($brand_id)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid brand ID is required',
                'error_details' => ['brand_id' => $brand_id]
            ];
        }

        if (!is_numeric($limit) || $limit < 1) {
            $limit = 10;
        }
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                           p.created_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.brand_id = ?
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare filter by brand statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_filter_by_brand'
                    ]
                ];
            }

            if (!$stmt->bind_param("iii", $brand_id, $limit, $offset)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for filter by brand',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_filter_by_brand'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_filter_by_brand'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'filter_products_by_brand', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from filter by brand',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_filter_by_brand'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'brand_id' => $brand_id,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Filter by brand failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'filter_products_by_brand'
                ]
            ];
        }
    }

    /**
     * View single product with full details (public-facing)     * 
     * @param int $product_id Product ID
     * @return array Result array with success status and product data
     */
    public function view_single_product($product_id)
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
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description,
                           p.product_image, p.product_keywords, p.category_id, p.brand_id,
                           p.created_at, p.updated_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE p.product_id = ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare view single product statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_view_single_product'
                    ]
                ];
            }

            if (!$stmt->bind_param("i", $product_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for view single product',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_view_single_product'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_view_single_product'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'view_single_product', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from view single product',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_view_single_product'
                    ]
                ];
            }

            $product = null;
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'product' => $product,
                    'found' => $product !== null,
                    'product_id' => $product_id
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'View single product failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'view_single_product'
                ]
            ];
        }
    }

    /**
     * Composite search with multiple criteria (public-facing)     * 
     * @param array $params Search parameters including query, category_id, brand_id, min_price, max_price, limit, offset
     * @return array Result array with success status and search results
     */
    public function composite_search($params)
    {
        // Set default parameters
        $query = isset($params['query']) ? trim($params['query']) : '';
        $category_id = isset($params['category_id']) && is_numeric($params['category_id']) ? (int)$params['category_id'] : 0;
        $brand_id = isset($params['brand_id']) && is_numeric($params['brand_id']) ? (int)$params['brand_id'] : 0;
        $min_price = isset($params['min_price']) && is_numeric($params['min_price']) ? (float)$params['min_price'] : 0;
        $max_price = isset($params['max_price']) && is_numeric($params['max_price']) ? (float)$params['max_price'] : 0;
        $limit = isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0 ? (int)$params['limit'] : 10;
        $offset = isset($params['offset']) && is_numeric($params['offset']) && $params['offset'] >= 0 ? (int)$params['offset'] : 0;

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                           p.created_at, c.cat_name, b.brand_name
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE 1=1";
            
            $bind_params = [];
            $types = '';
            
            // Add text search condition
            if (!empty($query)) {
                $terms = explode(' ', $query);
                $search_conditions = [];
                
                foreach ($terms as $term) {
                    if (!empty(trim($term))) {
                        $term_pattern = '%' . trim($term) . '%';
                        $search_conditions[] = "(p.product_title LIKE ? OR p.product_keywords LIKE ?)";
                        $bind_params[] = $term_pattern;
                        $bind_params[] = $term_pattern;
                        $types .= 'ss';
                    }
                }
                
                if (!empty($search_conditions)) {
                    $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
                }
            }
            
            // Add category filter
            if ($category_id > 0) {
                $sql .= " AND p.category_id = ?";
                $bind_params[] = $category_id;
                $types .= 'i';
            }
            
            // Add brand filter
            if ($brand_id > 0) {
                $sql .= " AND p.brand_id = ?";
                $bind_params[] = $brand_id;
                $types .= 'i';
            }
            
            // Add price range filters
            if ($min_price > 0) {
                $sql .= " AND p.product_price >= ?";
                $bind_params[] = $min_price;
                $types .= 'd';
            }
            
            if ($max_price > 0) {
                $sql .= " AND p.product_price <= ?";
                $bind_params[] = $max_price;
                $types .= 'd';
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $bind_params[] = $limit;
            $bind_params[] = $offset;
            $types .= 'ii';
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare composite search statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_composite_search'
                    ]
                ];
            }

            if (!empty($bind_params) && !$stmt->bind_param($types, ...$bind_params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for composite search',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_composite_search'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_composite_search'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'composite_search', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from composite search',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_composite_search'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'search_params' => [
                        'query' => $query,
                        'category_id' => $category_id,
                        'brand_id' => $brand_id,
                        'min_price' => $min_price,
                        'max_price' => $max_price,
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Composite search failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'composite_search'
                ]
            ];
        }
    }

    /**
     * Get total product count for pagination (public-facing)     * 
     * @return array Result array with success status and total count
     */
    public function get_product_count()
    {
        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $sql = "SELECT COUNT(*) as total_count FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare product count statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_product_count'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_product_count'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_product_count', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from product count',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_product_count'
                    ]
                ];
            }

            $count_data = $result->fetch_assoc();
            $total_count = $count_data ? (int)$count_data['total_count'] : 0;
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'total_count' => $total_count
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Get product count failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_product_count'
                ]
            ];
        }
    }

    /**
     * Get search result count for pagination (public-facing)     * 
     * @param string $query Search query
     * @return array Result array with success status and search count
     */
    public function get_search_count($query)
    {
        // Validate input
        if (empty($query)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Search query is required',
                'error_details' => ['query' => empty($query)]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Split query into multiple terms for OR matching
            $terms = explode(' ', trim($query));
            $search_conditions = [];
            $search_params = [];
            
            foreach ($terms as $term) {
                if (!empty(trim($term))) {
                    $term_pattern = '%' . trim($term) . '%';
                    $search_conditions[] = "(p.product_title LIKE ? OR p.product_keywords LIKE ?)";
                    $search_params[] = $term_pattern;
                    $search_params[] = $term_pattern;
                }
            }
            
            if (empty($search_conditions)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'No valid search terms found',
                    'error_details' => ['query' => $query]
                ];
            }
            
            $search_where = implode(' OR ', $search_conditions);
            
            $sql = "SELECT COUNT(*) as total_count FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE ({$search_where})";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare search count statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_search_count'
                    ]
                ];
            }

            // Create type string for bind_param
            $types = str_repeat('s', count($search_params));
            
            if (!$stmt->bind_param($types, ...$search_params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for search count',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_search_count'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_search_count'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_search_count', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from search count',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_search_count'
                    ]
                ];
            }

            $count_data = $result->fetch_assoc();
            $total_count = $count_data ? (int)$count_data['total_count'] : 0;
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'total_count' => $total_count,
                    'query' => $query
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Get search count failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_search_count'
                ]
            ];
        }
    }

    /**
     * Advanced keyword search with relevance scoring and indexing support     * 
     * @param string $query Search query
     * @param int $limit Number of products per page (default 10)
     * @param int $offset Starting position for pagination
     * @return array Result array with success status and search results with relevance scores
     */
    public function advanced_keyword_search($query, $limit = 10, $offset = 0)
    {
        // Validate input
        if (empty($query)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Search query is required',
                'error_details' => ['query' => empty($query)]
            ];
        }

        if (!is_numeric($limit) || $limit < 1) {
            $limit = 10;
        }
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Clean and prepare search terms
            $search_terms = $this->prepare_search_terms($query);
            
            if (empty($search_terms)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'No valid search terms found',
                    'error_details' => ['query' => $query]
                ];
            }

            // Build advanced search query with relevance scoring
            $sql = "SELECT p.product_id, p.product_title, p.product_price, p.product_description, 
                           p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                           p.created_at, c.cat_name, b.brand_name,
                           " . $this->build_relevance_score($search_terms) . " as relevance_score
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE " . $this->build_search_conditions($search_terms) . "
                    ORDER BY relevance_score DESC, p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare advanced search statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_advanced_search'
                    ]
                ];
            }

            // Prepare parameters for binding
            $bind_params = [];
            $types = '';
            
            // Add search term parameters (each term appears multiple times in the query)
            foreach ($search_terms as $term) {
                $term_pattern = '%' . $term . '%';
                // Each term is used 6 times in the relevance score and conditions
                for ($i = 0; $i < 6; $i++) {
                    $bind_params[] = $term_pattern;
                    $types .= 's';
                }
            }
            
            // Add limit and offset
            $bind_params[] = $limit;
            $bind_params[] = $offset;
            $types .= 'ii';
            
            if (!$stmt->bind_param($types, ...$bind_params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for advanced search',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_advanced_search'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_advanced_search'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'advanced_keyword_search', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from advanced search',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_advanced_search'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'query' => $query,
                    'search_terms' => $search_terms,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Advanced search failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'advanced_keyword_search'
                ]
            ];
        }
    }

    /**
     * Get search suggestions based on partial query     * 
     * @param string $partial_query Partial search query
     * @param int $limit Maximum number of suggestions (default 10)
     * @return array Result array with success status and suggestions
     */
    public function get_search_suggestions($partial_query, $limit = 10)
    {
        // Validate input
        if (empty($partial_query) || strlen($partial_query) < 2) {
            return [
                'success' => true,
                'data' => [
                    'suggestions' => [],
                    'count' => 0,
                    'query' => $partial_query
                ]
            ];
        }

        if (!is_numeric($limit) || $limit < 1) {
            $limit = 10;
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $search_pattern = $partial_query . '%';
            
            // Get suggestions from product titles and keywords
            $sql = "SELECT DISTINCT 
                        CASE 
                            WHEN p.product_title LIKE ? THEN p.product_title
                            ELSE NULL
                        END as title_suggestion,
                        CASE 
                            WHEN p.product_keywords LIKE ? THEN p.product_keywords
                            ELSE NULL
                        END as keyword_suggestion
                    FROM products p
                    WHERE p.product_title LIKE ? OR p.product_keywords LIKE ?
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare suggestions statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_suggestions'
                    ]
                ];
            }

            if (!$stmt->bind_param("ssssi", $search_pattern, $search_pattern, $search_pattern, $search_pattern, $limit)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for suggestions',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_suggestions'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_suggestions'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_search_suggestions', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from suggestions',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_suggestions'
                    ]
                ];
            }

            $suggestions = [];
            if ($result->num_rows > 0) {
                $raw_suggestions = $result->fetch_all(MYSQLI_ASSOC);
                
                // Process and clean suggestions
                foreach ($raw_suggestions as $row) {
                    if (!empty($row['title_suggestion'])) {
                        $suggestions[] = $row['title_suggestion'];
                    }
                    if (!empty($row['keyword_suggestion'])) {
                        // Extract individual keywords
                        $keywords = explode(',', $row['keyword_suggestion']);
                        foreach ($keywords as $keyword) {
                            $keyword = trim($keyword);
                            if (!empty($keyword) && stripos($keyword, $partial_query) === 0) {
                                $suggestions[] = $keyword;
                            }
                        }
                    }
                }
                
                // Remove duplicates and limit results
                $suggestions = array_unique($suggestions);
                $suggestions = array_slice($suggestions, 0, $limit);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'suggestions' => array_values($suggestions),
                    'count' => count($suggestions),
                    'query' => $partial_query
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Get suggestions failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_search_suggestions'
                ]
            ];
        }
    }

    /**
     * Enhanced composite search with advanced filtering and sorting     * 
     * @param array $params Enhanced search parameters
     * @return array Result array with success status and search results
     */
    public function enhanced_composite_search($params)
    {
        // Set default parameters
        $query = isset($params['query']) ? trim($params['query']) : '';
        $category_id = isset($params['category_id']) && is_numeric($params['category_id']) ? (int)$params['category_id'] : 0;
        $brand_id = isset($params['brand_id']) && is_numeric($params['brand_id']) ? (int)$params['brand_id'] : 0;
        $min_price = isset($params['min_price']) && is_numeric($params['min_price']) ? (float)$params['min_price'] : 0;
        $max_price = isset($params['max_price']) && is_numeric($params['max_price']) ? (float)$params['max_price'] : 0;
        $sort_by = isset($params['sort_by']) ? $params['sort_by'] : 'relevance';
        $sort_order = isset($params['sort_order']) ? strtoupper($params['sort_order']) : 'DESC';
        $limit = isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0 ? (int)$params['limit'] : 10;
        $offset = isset($params['offset']) && is_numeric($params['offset']) && $params['offset'] >= 0 ? (int)$params['offset'] : 0;

        // Validate sort parameters
        $valid_sort_fields = ['relevance', 'price', 'title', 'date', 'category', 'brand'];
        if (!in_array($sort_by, $valid_sort_fields)) {
            $sort_by = 'relevance';
        }
        
        if (!in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = 'DESC';
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Build the enhanced query
            $select_fields = "p.product_id, p.product_title, p.product_price, p.product_description, 
                             p.product_image, p.product_keywords, p.category_id, p.brand_id, 
                             p.created_at, c.cat_name, b.brand_name";
            
            // Add relevance score if there's a text query
            if (!empty($query)) {
                $search_terms = $this->prepare_search_terms($query);
                if (!empty($search_terms)) {
                    $select_fields .= ", " . $this->build_relevance_score($search_terms) . " as relevance_score";
                }
            } else {
                $select_fields .= ", 0 as relevance_score";
            }
            
            $sql = "SELECT {$select_fields}
                    FROM products p
                    INNER JOIN categories c ON p.category_id = c.cat_id
                    INNER JOIN brands b ON p.brand_id = b.brand_id
                    WHERE 1=1";
            
            $bind_params = [];
            $types = '';
            
            // Add text search condition with advanced matching
            if (!empty($query)) {
                $search_terms = $this->prepare_search_terms($query);
                if (!empty($search_terms)) {
                    $sql .= " AND (" . $this->build_search_conditions($search_terms) . ")";
                    
                    // Add parameters for search conditions
                    foreach ($search_terms as $term) {
                        $term_pattern = '%' . $term . '%';
                        // Each term appears 3 times in search conditions
                        for ($i = 0; $i < 3; $i++) {
                            $bind_params[] = $term_pattern;
                            $types .= 's';
                        }
                    }
                }
            }
            
            // Add category filter
            if ($category_id > 0) {
                $sql .= " AND p.category_id = ?";
                $bind_params[] = $category_id;
                $types .= 'i';
            }
            
            // Add brand filter
            if ($brand_id > 0) {
                $sql .= " AND p.brand_id = ?";
                $bind_params[] = $brand_id;
                $types .= 'i';
            }
            
            // Add price range filters
            if ($min_price > 0) {
                $sql .= " AND p.product_price >= ?";
                $bind_params[] = $min_price;
                $types .= 'd';
            }
            
            if ($max_price > 0) {
                $sql .= " AND p.product_price <= ?";
                $bind_params[] = $max_price;
                $types .= 'd';
            }
            
            // Add sorting
            $sql .= " ORDER BY " . $this->build_sort_clause($sort_by, $sort_order);
            $sql .= " LIMIT ? OFFSET ?";
            $bind_params[] = $limit;
            $bind_params[] = $offset;
            $types .= 'ii';
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare enhanced composite search statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_enhanced_composite_search'
                    ]
                ];
            }

            if (!empty($bind_params) && !$stmt->bind_param($types, ...$bind_params)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for enhanced composite search',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_enhanced_composite_search'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_enhanced_composite_search'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'enhanced_composite_search', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from enhanced composite search',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_enhanced_composite_search'
                    ]
                ];
            }

            $products = [];
            if ($result->num_rows > 0) {
                $products = $result->fetch_all(MYSQLI_ASSOC);
            }
            
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products),
                    'search_params' => [
                        'query' => $query,
                        'category_id' => $category_id,
                        'brand_id' => $brand_id,
                        'min_price' => $min_price,
                        'max_price' => $max_price,
                        'sort_by' => $sort_by,
                        'sort_order' => $sort_order,
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Enhanced composite search failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'enhanced_composite_search'
                ]
            ];
        }
    }

    /**
     * Prepare search terms for advanced searching
     * 
     * @param string $query Raw search query
     * @return array Array of cleaned search terms
     */
    private function prepare_search_terms($query)
    {
        // Remove special characters and normalize
        $query = preg_replace('/[^\w\s]/', ' ', $query);
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        // Split into terms and filter
        $terms = explode(' ', $query);
        $clean_terms = [];
        
        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) >= 2) { // Minimum term length
                $clean_terms[] = $term;
            }
        }
        
        return array_unique($clean_terms);
    }

    /**
     * Build relevance score calculation for search results
     * 
     * @param array $search_terms Array of search terms
     * @return string SQL expression for relevance score
     */
    private function build_relevance_score($search_terms)
    {
        $score_parts = [];
        
        foreach ($search_terms as $term) {
            // Higher weight for exact title matches
            $score_parts[] = "(CASE WHEN p.product_title LIKE ? THEN 10 ELSE 0 END)";
            // Medium weight for keyword matches
            $score_parts[] = "(CASE WHEN p.product_keywords LIKE ? THEN 5 ELSE 0 END)";
            // Lower weight for description matches
            $score_parts[] = "(CASE WHEN p.product_description LIKE ? THEN 2 ELSE 0 END)";
        }
        
        return "(" . implode(' + ', $score_parts) . ")";
    }

    /**
     * Build search conditions for WHERE clause
     * 
     * @param array $search_terms Array of search terms
     * @return string SQL WHERE conditions
     */
    private function build_search_conditions($search_terms)
    {
        $conditions = [];
        
        foreach ($search_terms as $term) {
            $conditions[] = "(p.product_title LIKE ? OR p.product_keywords LIKE ? OR p.product_description LIKE ?)";
        }
        
        return implode(' OR ', $conditions);
    }

    /**
     * Build ORDER BY clause for sorting
     * 
     * @param string $sort_by Sort field
     * @param string $sort_order Sort direction
     * @return string SQL ORDER BY clause
     */
    private function build_sort_clause($sort_by, $sort_order)
    {
        switch ($sort_by) {
            case 'price':
                return "p.product_price {$sort_order}";
            case 'title':
                return "p.product_title {$sort_order}";
            case 'date':
                return "p.created_at {$sort_order}";
            case 'category':
                return "c.cat_name {$sort_order}, p.product_title ASC";
            case 'brand':
                return "b.brand_name {$sort_order}, p.product_title ASC";
            case 'relevance':
            default:
                return "relevance_score {$sort_order}, p.created_at DESC";
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
        error_log("Product Database Error [{$errno}]: {$error} in operation: {$operation}");
        
        return $error_response;
    }
}

?>