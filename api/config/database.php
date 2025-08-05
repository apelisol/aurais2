<?php
/**
 * Database Configuration for Ukunahi AI Backend
 * PostgreSQL connection settings
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'ukunahi_ai';
    private $username = 'postgres';
    private $password = 'your_postgres_password';
    private $port = '5432';
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }

        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Environment-based configuration
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    
    if (isset($env['DB_HOST'])) {
        Database::$host = $env['DB_HOST'];
    }
    if (isset($env['DB_NAME'])) {
        Database::$db_name = $env['DB_NAME'];
    }
    if (isset($env['DB_USER'])) {
        Database::$username = $env['DB_USER'];
    }
    if (isset($env['DB_PASS'])) {
        Database::$password = $env['DB_PASS'];
    }
    if (isset($env['DB_PORT'])) {
        Database::$port = $env['DB_PORT'];
    }
}
?>
