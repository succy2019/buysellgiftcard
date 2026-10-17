<?php
/**
 * Database Configuration for DFQ Exchange
 * Created: October 31, 2025
 *
 * Secrets and environment-specific values can be supplied through environment
 * variables. Sensible local-development defaults are used when they are absent.
 */

// Load project-root .env before anything reads configuration.
require_once __DIR__ . '/env.php';

// ---------------------------------------------------------------------------
// Environment helpers
// ---------------------------------------------------------------------------
function envValue($key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

class Database {
    private $host;
    private $database;
    private $username;
    private $password;
    private $connection;

    public function __construct() {
        $this->host = envValue('DFQ_DB_HOST', 'localhost');
        $this->database = envValue('DFQ_DB_NAME', 'dfqexchange');
        $this->username = envValue('DFQ_DB_USER', 'root');
        $this->password = envValue('DFQ_DB_PASS', '');
    }

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
define('DB_HOST', envValue('DFQ_DB_HOST', 'localhost'));
define('DB_NAME', envValue('DFQ_DB_NAME', 'dfqexchange'));
define('DB_USER', envValue('DFQ_DB_USER', 'root'));
define('DB_PASS', envValue('DFQ_DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'DFQ Exchange');
define('APP_URL', envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'));
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('ADMIN_SESSION_LIFETIME', 3600 * 8); // 8 hours

// Security settings
define('BCRYPT_COST', 12);
define('PRODUCTION', envValue('DFQ_PRODUCTION', '0') === '1');
define('SESSION_COOKIE_SECURE', PRODUCTION); // HTTPS-only cookies in production
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// Encryption key for sensitive stored data (gift card codes, etc.).
// IMPORTANT: changing this after data has been stored will make previously
// encrypted values unreadable. Set DFQ_ENCRYPTION_KEY in the environment.
define('ENCRYPTION_KEY', envValue('DFQ_ENCRYPTION_KEY', 'DFQ_ENCRYPTION_KEY_2025'));

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
]);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Trading settings
define('MIN_TRADE_AMOUNT', 10.00);
define('MAX_TRADE_AMOUNT', 50000.00);
define('DEFAULT_TRADING_FEE', 0.005); // 0.5%

// Error reporting
if (PRODUCTION) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set timezone
date_default_timezone_set(envValue('DFQ_TIMEZONE', 'Africa/Lagos'));

// Basic security headers (ignored by the CLI).
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Start session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_secure', SESSION_COOKIE_SECURE ? '1' : '0');
    ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY ? '1' : '0');
    ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}
