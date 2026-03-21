<?php
/**
 * Vinpack Database Configuration
 * MySQL Connection Setup
 */

// Load .env file
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Get database credentials from environment variables
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
$db_port = getenv('DB_PORT') ?: 3306;

// Validate credentials are set
if (!$db_host || !$db_user || !$db_pass || !$db_name) {
    die('Error: Database credentials not configured. Please check your .env file.');
}

// Define constants for use in other files
define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);
define('DB_PORT', $db_port);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, getenv('DB_PORT') ?: 3306);

// Check connection - don't die here, let calling code handle offline mode
if ($conn->connect_error) {
    // Connection failed but don't die - let API endpoints handle gracefully
    // Log the error for debugging
    error_log('Database connection error: ' . $conn->connect_error);
    
    // For dashboard/admin pages, still show error
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false && strpos($_SERVER['REQUEST_URI'], 'get-inquiry.php') === false && strpos($_SERVER['REQUEST_URI'], 'sync-inquiries.php') === false) {
        http_response_code(500);
        die('Database Connection Error: ' . $conn->connect_error);
    }
    // For API endpoints, let them handle the error by checking $conn->connect_error
} else {
    // Set charset to utf8mb4 only if connection successful
    $conn->set_charset('utf8mb4');
}
