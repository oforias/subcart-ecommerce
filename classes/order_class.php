<?php

require_once __DIR__ . '/../settings/db_class.php';

/**
 * Order Class
 * 
 * Handles order data access and management operations.
 */
class Order extends db_connection
{
    /**
     * Create a new order with order details and payment information     * 
     * @param int $customer_id Customer ID
     * @param array $cart_items Array of cart items with product details
     * @param float $total_amount Total order amount
     * @param string $currency Currency code (default 'USD')
     * @param string $order_status Order status (default 'pending')
     * @return array Result array with success status and order data/error details
     */
    public function create_order($customer_id, $cart_items, $total_amount, $currency = 'USD', $order_status = 'pending')
    {
        // Validate input
        if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid customer ID is required',
                'error_details' => ['customer_id' => $customer_id]
            ];
        }

        if (empty($cart_items) || !is_array($cart_items)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Cart items array is required',
                'error_details' => ['cart_items' => $cart_items]
            ];
        }

        if (!is_numeric($total_amount) || $total_amount <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid total amount is required',
                'error_details' => ['total_amount' => $total_amount]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Start transaction for atomic order creation
            $this->db->autocommit(false);

            // Generate unique invoice number
            $invoice_no = $this->generate_unique_invoice_number();
            if (!$invoice_no['success']) {
                $this->db->rollback();
                return $invoice_no;
            }

            // Create order record
            $order_result = $this->insert_order($customer_id, $invoice_no['data']['invoice_no'], $order_status);
            if (!$order_result['success']) {
                $this->db->rollback();
                return $order_result;
            }

            $order_id = $order_result['data']['order_id'];

            // Create order details
            $order_details_result = $this->insert_order_details($order_id, $cart_items);
            if (!$order_details_result['success']) {
                $this->db->rollback();
                return $order_details_result;
            }

            // Create payment record
            $payment_result = $this->insert_payment($customer_id, $order_id, $total_amount, $currency);
            if (!$payment_result['success']) {
                $this->db->rollback();
                return $payment_result;
            }

            // Commit transaction
            $this->db->commit();

            return [
                'success' => true,
                'data' => [
                    'order_id' => $order_id,
                    'customer_id' => $customer_id,
                    'invoice_no' => $invoice_no['data']['invoice_no'],
                    'order_status' => $order_status,
                    'total_amount' => $total_amount,
                    'currency' => $currency,
                    'order_date' => date('Y-m-d'),
                    'payment_id' => $payment_result['data']['payment_id'],
                    'items_count' => count($cart_items),
                    'order_details' => $order_details_result['data']['order_details'],
                    'action' => 'order_created'
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order creation failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'create_order'
                ]
            ];
        } finally {
            $this->db->autocommit(true);
        }
    }

    /**
     * Generate unique invoice number     * 
     * @return array Result array with success status and invoice number
     */
    public function generate_unique_invoice_number()
    {
        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $max_attempts = 10;
            $attempt = 0;

            while ($attempt < $max_attempts) {
                // Generate invoice number based on timestamp and random component
                $timestamp = time();
                $random = mt_rand(1000, 9999);
                $invoice_no = (int)($timestamp . $random);

                // Check if invoice number already exists
                $check_result = $this->check_invoice_exists($invoice_no);
                if (!$check_result['success']) {
                    return $check_result;
                }

                if (!$check_result['data']['exists']) {
                    return [
                        'success' => true,
                        'data' => [
                            'invoice_no' => $invoice_no,
                            'attempts' => $attempt + 1,
                            'action' => 'invoice_generated'
                        ]
                    ];
                }

                $attempt++;
                // Add small delay to ensure different timestamp
                usleep(1000);
            }

            return [
                'success' => false,
                'error_type' => 'generation_failed',
                'error_message' => 'Failed to generate unique invoice number after maximum attempts',
                'error_details' => [
                    'max_attempts' => $max_attempts,
                    'operation' => 'generate_unique_invoice_number'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Invoice number generation failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'generate_unique_invoice_number'
                ]
            ];
        }
    }

    /**
     * Check if invoice number already exists     * 
     * @param int $invoice_no Invoice number to check
     * @return array Result array with success status and existence check
     */
    private function check_invoice_exists($invoice_no)
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM orders WHERE invoice_no = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare invoice check statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_check_invoice'
                    ]
                ];
            }

            if (!$stmt->bind_param("i", $invoice_no)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for invoice check',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_check_invoice'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_check_invoice'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'check_invoice_exists', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from invoice check',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_check_invoice'
                    ]
                ];
            }

            $row = $result->fetch_assoc();
            $stmt->close();

            return [
                'success' => true,
                'data' => [
                    'invoice_no' => $invoice_no,
                    'exists' => (int)$row['count'] > 0,
                    'count' => (int)$row['count']
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Invoice check failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'check_invoice_exists'
                ]
            ];
        }
    }

    /**
     * Insert order record into orders table     * 
     * @param int $customer_id Customer ID
     * @param int $invoice_no Invoice number
     * @param string $order_status Order status
     * @return array Result array with success status and order data
     */
    private function insert_order($customer_id, $invoice_no, $order_status)
    {
        try {
            $order_date = date('Y-m-d');
            
            $stmt = $this->db->prepare("INSERT INTO orders (customer_id, invoice_no, order_date, order_status) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare order insert statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_insert_order'
                    ]
                ];
            }

            if (!$stmt->bind_param("iiss", $customer_id, $invoice_no, $order_date, $order_status)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for order insert',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_insert_order'
                    ]
                ];
            }

            if ($stmt->execute()) {
                $order_id = $this->db->insert_id;
                $stmt->close();

                return [
                    'success' => true,
                    'data' => [
                        'order_id' => $order_id,
                        'customer_id' => $customer_id,
                        'invoice_no' => $invoice_no,
                        'order_date' => $order_date,
                        'order_status' => $order_status,
                        'action' => 'order_inserted'
                    ]
                ];
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_insert_order'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'insert_order', $error_info);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order insertion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'insert_order'
                ]
            ];
        }
    }

    /**
     * Insert order details into orderdetails table     * 
     * @param int $order_id Order ID
     * @param array $cart_items Array of cart items
     * @return array Result array with success status and order details data
     */
    private function insert_order_details($order_id, $cart_items)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO orderdetails (order_id, product_id, qty) VALUES (?, ?, ?)");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare order details insert statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_insert_order_details'
                    ]
                ];
            }

            $order_details = [];
            $total_items = 0;

            foreach ($cart_items as $item) {
                $product_id = $item['p_id'];
                $quantity = $item['qty'];

                if (!$stmt->bind_param("iii", $order_id, $product_id, $quantity)) {
                    $stmt->close();
                    return [
                        'success' => false,
                        'error_type' => 'database_error',
                        'error_message' => 'Failed to bind parameters for order details insert',
                        'error_details' => [
                            'mysql_error' => $stmt->error,
                            'operation' => 'bind_params_insert_order_details',
                            'product_id' => $product_id
                        ]
                    ];
                }

                if (!$stmt->execute()) {
                    $error_info = [
                        'mysql_error' => $stmt->error,
                        'mysql_errno' => $stmt->errno,
                        'operation' => 'execute_insert_order_details',
                        'product_id' => $product_id
                    ];
                    $stmt->close();
                    return $this->handle_mysql_error($stmt->errno, $stmt->error, 'insert_order_details', $error_info);
                }

                $order_details[] = [
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'product_title' => $item['product_title'] ?? 'Unknown Product',
                    'product_price' => $item['product_price'] ?? 0,
                    'subtotal' => ($item['product_price'] ?? 0) * $quantity
                ];

                $total_items += $quantity;
            }

            $stmt->close();

            return [
                'success' => true,
                'data' => [
                    'order_id' => $order_id,
                    'order_details' => $order_details,
                    'items_count' => count($order_details),
                    'total_items' => $total_items,
                    'action' => 'order_details_inserted'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order details insertion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'insert_order_details'
                ]
            ];
        }
    }

    /**
     * Insert payment record into payment table     * 
     * @param int $customer_id Customer ID
     * @param int $order_id Order ID
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @return array Result array with success status and payment data
     */
    private function insert_payment($customer_id, $order_id, $amount, $currency)
    {
        try {
            $payment_date = date('Y-m-d');
            
            $stmt = $this->db->prepare("INSERT INTO payment (amt, customer_id, order_id, currency, payment_date) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare payment insert statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_insert_payment'
                    ]
                ];
            }

            if (!$stmt->bind_param("diiss", $amount, $customer_id, $order_id, $currency, $payment_date)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for payment insert',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_insert_payment'
                    ]
                ];
            }

            if ($stmt->execute()) {
                $payment_id = $this->db->insert_id;
                $stmt->close();

                return [
                    'success' => true,
                    'data' => [
                        'payment_id' => $payment_id,
                        'customer_id' => $customer_id,
                        'order_id' => $order_id,
                        'amount' => $amount,
                        'currency' => $currency,
                        'payment_date' => $payment_date,
                        'action' => 'payment_inserted'
                    ]
                ];
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_insert_payment'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'insert_payment', $error_info);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Payment insertion failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'insert_payment'
                ]
            ];
        }
    }

    /**
     * Get order details by order ID     * 
     * @param int $order_id Order ID
     * @return array Result array with success status and order data
     */
    public function get_order_by_id($order_id)
    {
        // Validate input
        if (empty($order_id) || !is_numeric($order_id) || $order_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid order ID is required',
                'error_details' => ['order_id' => $order_id]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            // Get order information with payment details
            $order_sql = "
                SELECT o.order_id, o.customer_id, o.invoice_no, o.order_date, o.order_status,
                       p.pay_id, p.amt, p.currency, p.payment_date,
                       c.customer_name, c.customer_email
                FROM orders o
                LEFT JOIN payment p ON o.order_id = p.order_id
                LEFT JOIN customer c ON o.customer_id = c.customer_id
                WHERE o.order_id = ?
            ";

            $stmt = $this->db->prepare($order_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare order retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_order'
                    ]
                ];
            }

            if (!$stmt->bind_param("i", $order_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for order retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_order'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_order'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_order_by_id', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from order retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_order'
                    ]
                ];
            }

            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'not_found',
                    'error_message' => 'Order not found',
                    'error_details' => ['order_id' => $order_id]
                ];
            }

            $order_data = $result->fetch_assoc();
            $stmt->close();

            // Get order details
            $order_details_result = $this->get_order_details($order_id);
            if (!$order_details_result['success']) {
                return $order_details_result;
            }

            return [
                'success' => true,
                'data' => [
                    'order' => $order_data,
                    'order_details' => $order_details_result['data']['order_details'],
                    'items_count' => $order_details_result['data']['items_count'],
                    'total_items' => $order_details_result['data']['total_items'],
                    'action' => 'order_retrieved'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_order_by_id'
                ]
            ];
        }
    }

    /**
     * Get order details for a specific order     * 
     * @param int $order_id Order ID
     * @return array Result array with success status and order details data
     */
    public function get_order_details($order_id)
    {
        // Validate input
        if (empty($order_id) || !is_numeric($order_id) || $order_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid order ID is required',
                'error_details' => ['order_id' => $order_id]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $details_sql = "
                SELECT od.order_id, od.product_id, od.qty,
                       p.product_title, p.product_price, p.product_image, p.product_desc,
                       cat.cat_name, b.brand_name
                FROM orderdetails od
                INNER JOIN products p ON od.product_id = p.product_id
                LEFT JOIN categories cat ON p.product_cat = cat.cat_id
                LEFT JOIN brands b ON p.product_brand = b.brand_id
                WHERE od.order_id = ?
                ORDER BY p.product_title ASC
            ";

            $stmt = $this->db->prepare($details_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare order details retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_order_details'
                    ]
                ];
            }

            if (!$stmt->bind_param("i", $order_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for order details retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_order_details'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_order_details'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_order_details', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from order details retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_order_details'
                    ]
                ];
            }

            $order_details = [];
            $total_items = 0;

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $subtotal = $row['product_price'] * $row['qty'];
                    $row['subtotal'] = $subtotal;
                    $order_details[] = $row;
                    $total_items += $row['qty'];
                }
            }

            $stmt->close();

            return [
                'success' => true,
                'data' => [
                    'order_id' => $order_id,
                    'order_details' => $order_details,
                    'items_count' => count($order_details),
                    'total_items' => $total_items,
                    'action' => 'order_details_retrieved'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order details retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_order_details'
                ]
            ];
        }
    }

    /**
     * Get orders for a specific customer     * 
     * @param int $customer_id Customer ID
     * @param int $limit Number of orders to retrieve (default 10)
     * @param int $offset Offset for pagination (default 0)
     * @return array Result array with success status and orders data
     */
    public function get_customer_orders($customer_id, $limit = 10, $offset = 0)
    {
        // Validate input
        if (empty($customer_id) || !is_numeric($customer_id) || $customer_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid customer ID is required',
                'error_details' => ['customer_id' => $customer_id]
            ];
        }

        if (!is_numeric($limit) || $limit <= 0) {
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
            $orders_sql = "
                SELECT o.order_id, o.customer_id, o.invoice_no, o.order_date, o.order_status,
                       p.pay_id, p.amt, p.currency, p.payment_date
                FROM orders o
                LEFT JOIN payment p ON o.order_id = p.order_id
                WHERE o.customer_id = ?
                ORDER BY o.order_date DESC, o.order_id DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($orders_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare customer orders retrieval statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_customer_orders'
                    ]
                ];
            }

            if (!$stmt->bind_param("iii", $customer_id, $limit, $offset)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for customer orders retrieval',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_customer_orders'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_customer_orders'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_customer_orders', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from customer orders retrieval',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_customer_orders'
                    ]
                ];
            }

            $orders = [];
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $orders[] = $row;
                }
            }

            $stmt->close();

            return [
                'success' => true,
                'data' => [
                    'customer_id' => $customer_id,
                    'orders' => $orders,
                    'count' => count($orders),
                    'limit' => $limit,
                    'offset' => $offset,
                    'action' => 'customer_orders_retrieved'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Customer orders retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_customer_orders'
                ]
            ];
        }
    }

    /**
     * Update order status     * 
     * @param int $order_id Order ID
     * @param string $new_status New order status
     * @return array Result array with success status and update data
     */
    public function update_order_status($order_id, $new_status)
    {
        // Validate input
        if (empty($order_id) || !is_numeric($order_id) || $order_id <= 0) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid order ID is required',
                'error_details' => ['order_id' => $order_id]
            ];
        }

        if (empty($new_status) || !is_string($new_status)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Valid order status is required',
                'error_details' => ['new_status' => $new_status]
            ];
        }

        // Validate status values
        $valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Invalid order status',
                'error_details' => [
                    'new_status' => $new_status,
                    'valid_statuses' => $valid_statuses
                ]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $stmt = $this->db->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare order status update statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_update_order_status'
                    ]
                ];
            }

            if (!$stmt->bind_param("si", $new_status, $order_id)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for order status update',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_update_order_status'
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
                            'order_id' => $order_id,
                            'new_status' => $new_status,
                            'affected_rows' => $affected_rows,
                            'action' => 'order_status_updated'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error_type' => 'not_found',
                        'error_message' => 'Order not found for status update',
                        'error_details' => [
                            'order_id' => $order_id,
                            'new_status' => $new_status,
                            'affected_rows' => $affected_rows
                        ]
                    ];
                }
            } else {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_update_order_status'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'update_order_status', $error_info);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order status update failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'update_order_status'
                ]
            ];
        }
    }

    /**
     * Get order statistics for monitoring and reporting     * 
     * @param string $start_date Start date for statistics (Y-m-d format)
     * @param string $end_date End date for statistics (Y-m-d format)
     * @return array Result array with success status and statistics data
     */
    public function get_order_statistics($start_date = null, $end_date = null)
    {
        // Set default date range if not provided
        if (empty($start_date)) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (empty($end_date)) {
            $end_date = date('Y-m-d');
        }

        // Validate date format
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            return [
                'success' => false,
                'error_type' => 'validation_error',
                'error_message' => 'Invalid date format. Use Y-m-d format',
                'error_details' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]
            ];
        }

        // Connect to database with enhanced error handling
        $connection_result = $this->connect_with_error_handling();
        if (!$connection_result['success']) {
            return $connection_result;
        }

        try {
            $stats_sql = "
                SELECT 
                    COUNT(DISTINCT o.order_id) as total_orders,
                    COUNT(DISTINCT o.customer_id) as unique_customers,
                    SUM(p.amt) as total_revenue,
                    AVG(p.amt) as avg_order_value,
                    COUNT(CASE WHEN o.order_status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN o.order_status = 'confirmed' THEN 1 END) as confirmed_orders,
                    COUNT(CASE WHEN o.order_status = 'cancelled' THEN 1 END) as cancelled_orders
                FROM orders o
                LEFT JOIN payment p ON o.order_id = p.order_id
                WHERE o.order_date BETWEEN ? AND ?
            ";

            $stmt = $this->db->prepare($stats_sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to prepare order statistics statement',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'mysql_errno' => $this->db->errno,
                        'operation' => 'prepare_get_order_statistics'
                    ]
                ];
            }

            if (!$stmt->bind_param("ss", $start_date, $end_date)) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to bind parameters for order statistics',
                    'error_details' => [
                        'mysql_error' => $stmt->error,
                        'operation' => 'bind_params_get_order_statistics'
                    ]
                ];
            }

            if (!$stmt->execute()) {
                $error_info = [
                    'mysql_error' => $stmt->error,
                    'mysql_errno' => $stmt->errno,
                    'operation' => 'execute_get_order_statistics'
                ];
                $stmt->close();
                return $this->handle_mysql_error($stmt->errno, $stmt->error, 'get_order_statistics', $error_info);
            }

            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                return [
                    'success' => false,
                    'error_type' => 'database_error',
                    'error_message' => 'Failed to get result from order statistics',
                    'error_details' => [
                        'mysql_error' => $this->db->error,
                        'operation' => 'get_result_order_statistics'
                    ]
                ];
            }

            $stats = $result->fetch_assoc();
            $stmt->close();

            return [
                'success' => true,
                'data' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'total_orders' => (int)$stats['total_orders'],
                    'unique_customers' => (int)$stats['unique_customers'],
                    'total_revenue' => round((float)$stats['total_revenue'], 2),
                    'avg_order_value' => round((float)$stats['avg_order_value'], 2),
                    'pending_orders' => (int)$stats['pending_orders'],
                    'confirmed_orders' => (int)$stats['confirmed_orders'],
                    'cancelled_orders' => (int)$stats['cancelled_orders'],
                    'timestamp' => time(),
                    'action' => 'order_statistics_retrieved'
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_type' => 'database_exception',
                'error_message' => 'Order statistics retrieval failed with exception',
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'exception_code' => $e->getCode(),
                    'operation' => 'get_order_statistics'
                ]
            ];
        }
    }

    /**
     * Validate date format
     * 
     * @param string $date Date string to validate
     * @param string $format Expected date format (default 'Y-m-d')
     * @return bool True if valid, false otherwise
     */
    private function validate_date($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
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
        error_log("Order Database Error [{$errno}]: {$error} in operation: {$operation}");
        
        return $error_response;
    }
}

?>