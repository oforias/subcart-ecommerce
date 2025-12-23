<?php
include_once 'db_cred.php';

/**
 *@version 1.1
 */
if (!class_exists('db_connection')) {
    class db_connection
    {
        //properties
        public $db = null;
        public $results = null;

        //connect
        /**
         * Database connection
         * @return boolean
         **/
        function db_connect()
        {
            //connection
            $this->db = mysqli_connect(SERVER, USERNAME, PASSWD, DATABASE);

            //test the connection
            if (mysqli_connect_errno()) {
                return false;
            } else {
                return true;
            }
        }

        function db_conn()
        {
            //connection
            $this->db = mysqli_connect(SERVER, USERNAME, PASSWD, DATABASE);

            //test the connection
            if (mysqli_connect_errno()) {
                return false;
            } else {
                return $this->db;
            }
        }

        //execute a query for SELECT statements
        /**
         * Query the Database for SELECT statements
         * @param string $sqlQuery
         * @return boolean
         **/
        function db_query($sqlQuery)
        {
            if (!$this->db_connect()) {
                return false;
            } elseif ($this->db == null) {
                return false;
            }

            //run query 
            $this->results = mysqli_query($this->db, $sqlQuery);

            if ($this->results == false) {
                return false;
            } else {
                return true;
            }
        }

        //execute a query for INSERT, UPDATE, DELETE statements
        /**
         * Query the Database for INSERT, UPDATE, DELETE statements
         * @param string $sqlQuery
         * @return boolean
         **/
        function db_write_query($sqlQuery)
        {
            if (!$this->db_connect()) {
                return false;
            } elseif ($this->db == null) {
                return false;
            }

            //run query 
            $result = mysqli_query($this->db, $sqlQuery);

            if ($result == false) {
                return false;
            } else {
                return true;
            }
        }

        //fetch a single record
        /**
         * Get a single record
         * @param string $sql
         * @return array|false
         **/
        function db_fetch_one($sql)
        {
            // if executing query returns false
            if (!$this->db_query($sql)) {
                return false;
            }
            //return a record
            return mysqli_fetch_assoc($this->results);
        }

        //fetch all records
        /**
         * Get all records
         * @param string $sql
         * @return array|false
         **/
        function db_fetch_all($sql)
        {
            // if executing query returns false
            if (!$this->db_query($sql)) {
                return false;
            }
            //return all records
            return mysqli_fetch_all($this->results, MYSQLI_ASSOC);
        }

        //count data
        /**
         * Get count of records
         * @return int|false
         **/
        function db_count()
        {
            //check if result was set
            if ($this->results == null) {
                return false;
            } elseif ($this->results == false) {
                return false;
            }

            //return count
            return mysqli_num_rows($this->results);
        }

        function last_insert_id()
        {
            return mysqli_insert_id($this->db);
        }

        /**
         * Enhanced database connection with comprehensive error handling
         * Requirements: 8.2, 8.5
         * 
         * @return array Connection result with success status and error details
         */
        protected function connect_with_error_handling()
        {
            try {
                // Attempt database connection
                $this->db = mysqli_connect(SERVER, USERNAME, PASSWD, DATABASE);
                
                // Check for connection errors
                if (mysqli_connect_errno()) {
                    $error_code = mysqli_connect_errno();
                    $error_message = mysqli_connect_error();
                    
                    // Log the connection error for debugging
                    error_log("Database connection failed: Error {$error_code} - {$error_message}");
                    
                    // Determine error type based on error code
                    $error_type = $this->categorize_connection_error($error_code);
                    
                    return [
                        'success' => false,
                        'error_type' => $error_type,
                        'error_message' => $this->get_user_friendly_connection_error($error_type),
                        'error_details' => [
                            'mysql_error_code' => $error_code,
                            'mysql_error_message' => $error_message,
                            'server' => SERVER,
                            'database' => DATABASE
                        ]
                    ];
                }
                
                // Connection successful
                return [
                    'success' => true,
                    'connection' => $this->db
                ];
                
            } catch (Exception $e) {
                // Log the exception for debugging
                error_log("Database connection exception: " . $e->getMessage());
                
                return [
                    'success' => false,
                    'error_type' => 'connection_exception',
                    'error_message' => 'Database connection failed due to system error',
                    'error_details' => [
                        'exception_message' => $e->getMessage(),
                        'exception_code' => $e->getCode()
                    ]
                ];
            }
        }

        /**
         * Enhanced query execution with comprehensive error handling
         * Requirements: 8.2, 8.5
         * 
         * @param string $sql SQL query to execute
         * @param string $operation_type Type of operation (SELECT, INSERT, UPDATE, DELETE)
         * @return array Query result with success status and data/error details
         */
        protected function execute_query_with_error_handling($sql, $operation_type = 'SELECT')
        {
            // Validate SQL query
            if (empty($sql) || !is_string($sql)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'Valid SQL query is required',
                    'error_details' => ['sql' => $sql]
                ];
            }
            
            // Ensure database connection
            if ($this->db === null) {
                $connection_result = $this->connect_with_error_handling();
                if (!$connection_result['success']) {
                    return $connection_result;
                }
            }
            
            try {
                // Execute the query
                $result = mysqli_query($this->db, $sql);
                
                if ($result === false) {
                    $error_code = mysqli_errno($this->db);
                    $error_message = mysqli_error($this->db);
                    
                    // Log the query error for debugging
                    error_log("Query execution failed: Error {$error_code} - {$error_message}. SQL: {$sql}");
                    
                    // Determine error type based on error code
                    $error_type = $this->categorize_query_error($error_code);
                    
                    return [
                        'success' => false,
                        'error_type' => $error_type,
                        'error_message' => $this->get_user_friendly_query_error($error_type),
                        'error_details' => [
                            'mysql_error_code' => $error_code,
                            'mysql_error_message' => $error_message,
                            'sql_query' => $sql,
                            'operation_type' => $operation_type
                        ]
                    ];
                }
                
                // Query successful - prepare response based on operation type
                $response = [
                    'success' => true,
                    'result' => $result,
                    'operation_type' => $operation_type
                ];
                
                // Add additional data for different operation types
                switch (strtoupper($operation_type)) {
                    case 'INSERT':
                    case 'UPDATE':
                    case 'DELETE':
                        $response['affected_rows'] = mysqli_affected_rows($this->db);
                        if (strtoupper($operation_type) === 'INSERT') {
                            $response['insert_id'] = mysqli_insert_id($this->db);
                        }
                        break;
                        
                    case 'SELECT':
                        $response['num_rows'] = mysqli_num_rows($result);
                        break;
                }
                
                return $response;
                
            } catch (Exception $e) {
                // Log the exception for debugging
                error_log("Query execution exception: " . $e->getMessage() . ". SQL: {$sql}");
                
                return [
                    'success' => false,
                    'error_type' => 'query_exception',
                    'error_message' => 'Query execution failed due to system error',
                    'error_details' => [
                        'exception_message' => $e->getMessage(),
                        'exception_code' => $e->getCode(),
                        'sql_query' => $sql,
                        'operation_type' => $operation_type
                    ]
                ];
            }
        }

        /**
         * Categorize MySQL connection errors into user-friendly types
         * Requirements: 8.2
         * 
         * @param int $error_code MySQL error code
         * @return string Error type category
         */
        private function categorize_connection_error($error_code)
        {
            switch ($error_code) {
                case 1045: // Access denied
                    return 'access_denied';
                case 1049: // Unknown database
                    return 'database_not_found';
                case 2002: // Can't connect to server
                case 2003: // Can't connect to MySQL server
                    return 'server_unreachable';
                case 1040: // Too many connections
                    return 'too_many_connections';
                case 2006: // MySQL server has gone away
                    return 'connection_lost';
                default:
                    return 'connection_error';
            }
        }

        /**
         * Categorize MySQL query errors into user-friendly types
         * Requirements: 8.2
         * 
         * @param int $error_code MySQL error code
         * @return string Error type category
         */
        private function categorize_query_error($error_code)
        {
            switch ($error_code) {
                case 1062: // Duplicate entry
                    return 'duplicate_entry';
                case 1452: // Foreign key constraint fails
                    return 'foreign_key_constraint';
                case 1146: // Table doesn't exist
                    return 'table_not_found';
                case 1054: // Unknown column
                    return 'column_not_found';
                case 1205: // Lock wait timeout
                    return 'lock_timeout';
                case 1213: // Deadlock found
                    return 'deadlock';
                case 2006: // MySQL server has gone away
                    return 'connection_lost';
                case 1040: // Too many connections
                    return 'too_many_connections';
                default:
                    return 'database_error';
            }
        }

        /**
         * Get user-friendly error messages for connection errors
         * Requirements: 8.2
         * 
         * @param string $error_type Error type category
         * @return string User-friendly error message
         */
        private function get_user_friendly_connection_error($error_type)
        {
            switch ($error_type) {
                case 'access_denied':
                    return 'Database access denied. Please contact administrator.';
                case 'database_not_found':
                    return 'Database not found. Please contact administrator.';
                case 'server_unreachable':
                    return 'Database server is unreachable. Please try again later.';
                case 'too_many_connections':
                    return 'Server is busy. Please try again later.';
                case 'connection_lost':
                    return 'Database connection lost. Please refresh the page and try again.';
                default:
                    return 'Database connection error. Please try again later.';
            }
        }

        /**
         * Get user-friendly error messages for query errors
         * Requirements: 8.2
         * 
         * @param string $error_type Error type category
         * @return string User-friendly error message
         */
        private function get_user_friendly_query_error($error_type)
        {
            switch ($error_type) {
                case 'duplicate_entry':
                    return 'Duplicate data detected. Please check your input.';
                case 'foreign_key_constraint':
                    return 'Data integrity constraint violation. Please refresh and try again.';
                case 'table_not_found':
                case 'column_not_found':
                    return 'Database schema error. Please contact administrator.';
                case 'lock_timeout':
                case 'deadlock':
                    return 'System is busy. Please try again in a moment.';
                case 'connection_lost':
                    return 'Database connection lost. Please refresh the page and try again.';
                case 'too_many_connections':
                    return 'Server is busy. Please try again later.';
                default:
                    return 'Database operation failed. Please try again.';
            }
        }

        /**
         * Validate and sanitize input data for database operations
         * Requirements: 8.1
         * 
         * @param mixed $value Input value to validate
         * @param string $type Expected data type (int, string, float, email, etc.)
         * @param array $options Validation options (min, max, required, etc.)
         * @return array Validation result with success status and sanitized value/error details
         */
        protected function validate_input($value, $type, $options = [])
        {
            // Check if value is required
            $required = $options['required'] ?? true;
            
            if ($required && (is_null($value) || $value === '')) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'Value is required',
                    'error_details' => ['type' => $type, 'value' => $value]
                ];
            }
            
            // If not required and empty, return success with null
            if (!$required && (is_null($value) || $value === '')) {
                return [
                    'success' => true,
                    'sanitized_value' => null
                ];
            }
            
            // Validate based on type
            switch (strtolower($type)) {
                case 'int':
                case 'integer':
                    return $this->validate_integer($value, $options);
                    
                case 'float':
                case 'decimal':
                    return $this->validate_float($value, $options);
                    
                case 'string':
                    return $this->validate_string($value, $options);
                    
                case 'email':
                    return $this->validate_email($value, $options);
                    
                case 'ip':
                case 'ip_address':
                    return $this->validate_ip_address($value, $options);
                    
                case 'date':
                    return $this->validate_date($value, $options);
                    
                default:
                    return [
                        'success' => false,
                        'error_type' => 'validation_error',
                        'error_message' => 'Unknown validation type',
                        'error_details' => ['type' => $type, 'value' => $value]
                    ];
            }
        }

        /**
         * Validate integer values
         * Requirements: 8.1
         */
        private function validate_integer($value, $options = [])
        {
            if (!is_numeric($value)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'Value must be a valid number',
                    'error_details' => ['value' => $value, 'expected_type' => 'integer']
                ];
            }
            
            $int_value = (int)$value;
            
            // Check minimum value
            if (isset($options['min']) && $int_value < $options['min']) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Value must be at least {$options['min']}",
                    'error_details' => ['value' => $int_value, 'min' => $options['min']]
                ];
            }
            
            // Check maximum value
            if (isset($options['max']) && $int_value > $options['max']) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Value must be at most {$options['max']}",
                    'error_details' => ['value' => $int_value, 'max' => $options['max']]
                ];
            }
            
            return [
                'success' => true,
                'sanitized_value' => $int_value
            ];
        }

        /**
         * Validate float values
         * Requirements: 8.1
         */
        private function validate_float($value, $options = [])
        {
            if (!is_numeric($value)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'Value must be a valid number',
                    'error_details' => ['value' => $value, 'expected_type' => 'float']
                ];
            }
            
            $float_value = (float)$value;
            
            // Check minimum value
            if (isset($options['min']) && $float_value < $options['min']) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Value must be at least {$options['min']}",
                    'error_details' => ['value' => $float_value, 'min' => $options['min']]
                ];
            }
            
            // Check maximum value
            if (isset($options['max']) && $float_value > $options['max']) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Value must be at most {$options['max']}",
                    'error_details' => ['value' => $float_value, 'max' => $options['max']]
                ];
            }
            
            return [
                'success' => true,
                'sanitized_value' => $float_value
            ];
        }

        /**
         * Validate string values
         * Requirements: 8.1
         */
        private function validate_string($value, $options = [])
        {
            $string_value = trim((string)$value);
            
            // Check minimum length
            if (isset($options['min_length']) && strlen($string_value) < $options['min_length']) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Value must be at least {$options['min_length']} characters long",
                    'error_details' => ['value' => $string_value, 'min_length' => $options['min_length']]
                ];
            }
            
            // Check maximum length
            if (isset($options['max_length']) && strlen($string_value) > $options['max_length']) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Value must be at most {$options['max_length']} characters long",
                    'error_details' => ['value' => $string_value, 'max_length' => $options['max_length']]
                ];
            }
            
            // Sanitize for database
            $sanitized_value = mysqli_real_escape_string($this->db ?? $this->db_conn(), $string_value);
            
            return [
                'success' => true,
                'sanitized_value' => $sanitized_value
            ];
        }

        /**
         * Validate email addresses
         * Requirements: 8.1
         */
        private function validate_email($value, $options = [])
        {
            $email = trim((string)$value);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'Invalid email address format',
                    'error_details' => ['value' => $email]
                ];
            }
            
            return [
                'success' => true,
                'sanitized_value' => $email
            ];
        }

        /**
         * Validate IP addresses
         * Requirements: 8.1
         */
        private function validate_ip_address($value, $options = [])
        {
            $ip = trim((string)$value);
            
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => 'Invalid IP address format',
                    'error_details' => ['value' => $ip]
                ];
            }
            
            return [
                'success' => true,
                'sanitized_value' => $ip
            ];
        }

        /**
         * Validate date values
         * Requirements: 8.1
         */
        private function validate_date($value, $options = [])
        {
            $date_string = trim((string)$value);
            $format = $options['format'] ?? 'Y-m-d';
            
            $date = DateTime::createFromFormat($format, $date_string);
            
            if (!$date || $date->format($format) !== $date_string) {
                return [
                    'success' => false,
                    'error_type' => 'validation_error',
                    'error_message' => "Invalid date format. Expected format: {$format}",
                    'error_details' => ['value' => $date_string, 'expected_format' => $format]
                ];
            }
            
            return [
                'success' => true,
                'sanitized_value' => $date->format($format)
            ];
        }
    }
}
