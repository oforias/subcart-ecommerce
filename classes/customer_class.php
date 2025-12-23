<?php

require_once '../settings/db_class.php';

/**
 * Customer Class
 * Handles customer data access and management operations
 * Requirements: 6.1
 */
class Customer extends db_connection
{
    private $user_id;
    private $name;
    private $email;
    private $password;
    private $role;
    private $date_created;
    private $phone_number;

    public function __construct($user_id = null)
    {
        parent::db_connect();
        if ($user_id) {
            $this->user_id = $user_id;
            $this->loadUser();
        }
    }

    private function loadUser($user_id = null)
    {
        if ($user_id) {
            $this->user_id = $user_id;
        }
        if (!$this->user_id) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT * FROM customer WHERE customer_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result) {
            $this->name = $result['customer_name'];
            $this->email = $result['customer_email'];
            $this->role = $result['user_role'];
            $this->date_created = isset($result['date_created']) ? $result['date_created'] : null;
            $this->phone_number = $result['customer_contact'];
        }
    }

    public function createUser($name, $email, $password, $country, $city, $phone_number, $role)
    {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO customer (customer_name, customer_email, customer_pass, customer_country, customer_city, customer_contact, user_role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $name, $email, $hashed_password, $country, $city, $phone_number, $role);
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    public function getUserByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT * FROM customer WHERE customer_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function checkEmailExists($email)
    {
        $stmt = $this->db->prepare("SELECT customer_id FROM customer WHERE customer_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    public function getCustomerById($customer_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM customer WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return false;
    }

    public function updateCustomer($customer_id, $fields)
    {
        if (empty($fields) || !is_array($fields)) {
            return false;
        }

        $allowed_fields = ['customer_name', 'customer_email', 'customer_pass', 'customer_country', 'customer_city', 'customer_contact', 'customer_image', 'user_role'];
        $set_parts = [];
        $values = [];
        $types = '';

        foreach ($fields as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_parts[] = "$field = ?";
                $values[] = $value;
                $types .= 's';
            }
        }

        if (empty($set_parts)) {
            return false;
        }

        $values[] = $customer_id;
        $types .= 'i';

        $sql = "UPDATE customer SET " . implode(', ', $set_parts) . " WHERE customer_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        return $stmt->execute();
    }

    public function deleteCustomer($customer_id)
    {
        $stmt = $this->db->prepare("DELETE FROM customer WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        return $stmt->execute();
    }

    /**
     * Retrieve customer by email address for authentication
     * Requirements: 6.1
     * 
     * @param string $email Customer email address
     * @return array|false Customer data array or false if not found
     */
    public function get_customer_by_email($email)
    {
        $stmt = $this->db->prepare("SELECT * FROM customer WHERE customer_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return false;
    }

    /**
     * Verify customer login credentials
     * Combines email lookup and password verification
     * Requirements: 6.2, 2.1
     * 
     * @param string $email Customer email address
     * @param string $password Plain text password to verify
     * @return array|false Customer data array on success, false on failure
     */
    public function verify_customer_login($email, $password)
    {
        // Get customer by email
        $customer = $this->get_customer_by_email($email);
        
        if (!$customer) {
            return false;
        }
        
        // Verify password against stored hash
        if (password_verify($password, $customer['customer_pass'])) {
            // Remove password from returned data for security
            unset($customer['customer_pass']);
            return $customer;
        }
        
        return false;
    }

}
