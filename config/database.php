<?php
/**
 * Database Configuration for FEX Trading Platform
 * Created: October 31, 2025
 */

class Database {
    private $host = 'localhost';
    private $database = 'fex_trading_platform';
    private $username = 'root';
    private $password = '';
    private $connection;
    
    /**
     * Get database connection
     */
    public function getConnection() {
        $this->connection = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->database . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
        
        return $this->connection;
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->connection = null;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return true;
        } catch(Exception $e) {
            return false;
        }
    }
}

// Global database instance
function getDB() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database->getConnection();
}

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'fex_trading_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'FEX Trading Platform');
define('APP_URL', 'http://localhost');
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('ADMIN_SESSION_LIFETIME', 3600 * 8); // 8 hours

// Security settings
define('BCRYPT_COST', 12);
define('SESSION_COOKIE_SECURE', false); // Set to true in production with HTTPS
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Trading settings
define('MIN_TRADE_AMOUNT', 10.00);
define('MAX_TRADE_AMOUNT', 50000.00);
define('DEFAULT_TRADING_FEE', 0.005); // 0.5%

// Error reporting
if (!defined('PRODUCTION')) {
    define('PRODUCTION', false);
}

if (PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set timezone
date_default_timezone_set('UTC');

// Start session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_secure', SESSION_COOKIE_SECURE);
    ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY);
    ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
    ini_set('session.use_strict_mode', 1);
    session_start();
}
?>