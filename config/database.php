<?php
/**
 * Vinpack Database Configuration
 * MySQL Connection Setup with Error Handling & Retries
 */

// Ensure error logging is enabled
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
@mkdir(__DIR__ . '/../logs', 0755, true);

/**
 * Load .env file with error handling
 */
function load_env_file() {
    $env_file = __DIR__ . '/../.env';
    
    if (!file_exists($env_file)) {
        error_log('CRITICAL: .env file not found at ' . $env_file);
        return false;
    }
    
    $env = @parse_ini_file($env_file);
    
    if ($env === false) {
        error_log('CRITICAL: Failed to parse .env file');
        return false;
    }
    
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
    
    return true;
}

// Load environment variables
if (!load_env_file()) {
    http_response_code(500);
    die('Configuration Error: Unable to load .env file. Please check file permissions and format.');
}

// Get database credentials from environment variables
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
$db_port = getenv('DB_PORT') ?: 3306;

// Validate credentials are set
if (!$db_host || !$db_user || !$db_name) {
    error_log('CRITICAL: Missing database credentials in .env file');
    http_response_code(500);
    die('Configuration Error: Database credentials not configured. Please check your .env file.');
}

// Define constants for use in other files
define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);
define('DB_PORT', $db_port);
define('DB_RETRY_COUNT', 3);
define('DB_RETRY_DELAY', 1); // seconds

/**
 * Connect to database with retry logic
 */
function connect_to_database($retry = 0) {
    $conn = @new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        DB_PORT
    );
    
    // Check connection
    if ($conn->connect_error) {
        $error_msg = 'Database connection failed (Attempt ' . ($retry + 1) . '): ' . $conn->connect_error;
        error_log($error_msg);
        
        // Retry logic for temporary connection failures
        if ($retry < DB_RETRY_COUNT && in_array($conn->connect_errno, [2002, 2003, 2006, 1040])) {
            sleep(DB_RETRY_DELAY);
            return connect_to_database($retry + 1);
        }
        
        return null;
    }
    
    // Set charset to utf8mb4
    @$conn->set_charset('utf8mb4');
    
    return $conn;
}

// Create connection with retries
$conn = connect_to_database();

// Handle connection failure
if (!$conn || !$conn instanceof mysqli) {
    error_log('CRITICAL: Unable to establish database connection after ' . DB_RETRY_COUNT . ' attempts');
    
    // For admin pages, show error
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) {
        http_response_code(503);
        die('Service Unavailable: Database connection failed. The system is temporarily unavailable. Please try again in a moment.');
    }
    
    // For API endpoints, they'll handle the null $conn
}
