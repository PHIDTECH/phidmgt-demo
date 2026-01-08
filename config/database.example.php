<?php
/**
 * Database Configuration Example
 * ==============================
 * Copy this file to database.php and update with your database credentials
 */

// Database Settings - UPDATE THESE WITH YOUR CREDENTIALS
define('DB_HOST', 'localhost');           // Database server (usually localhost)
define('DB_USER', 'your_db_username');    // Your database username
define('DB_PASS', 'your_db_password');    // Your database password
define('DB_NAME', 'your_database_name');  // Your database name

class Database {
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
