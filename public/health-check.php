<?php
/**
 * Health Check Endpoint
 * Monitor system status - use for monitoring tools & load balancers
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'uptime' => time(),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database' => 'unknown',
    'checks' => []
];

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    $health['checks']['php_version'] = ['status' => 'ok', 'message' => 'PHP ' . PHP_VERSION];
} else {
    $health['checks']['php_version'] = ['status' => 'warning', 'message' => 'PHP version < 7.4'];
    $health['status'] = 'degraded';
}

// Check extension availability
$requiredExtensions = ['mysqli', 'json', 'filter'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $health['checks'][$ext] = ['status' => 'ok', 'message' => "Extension $ext loaded"];
    } else {
        $health['checks'][$ext] = ['status' => 'error', 'message' => "Extension $ext missing"];
        $health['status'] = 'unhealthy';
    }
}

// Check database connectivity
try {
    require_once '../config/database.php';
    
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        // Test query
        $result = $conn->query('SELECT 1');
        if ($result) {
            $health['database'] = 'connected';
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Database connection successful'];
        } else {
            $health['database'] = 'error';
            $health['checks']['database'] = ['status' => 'error', 'message' => 'Database query failed'];
            $health['status'] = 'unhealthy';
        }
        $conn->close();
    } else {
        $health['database'] = 'disconnected';
        $health['checks']['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
        $health['status'] = 'unhealthy';
    }
} catch (Exception $e) {
    $health['checks']['database'] = ['status' => 'error', 'message' => 'Database check error: ' . $e->getMessage()];
    $health['status'] = 'unhealthy';
}

// Check file permissions
$filestoCheck = [
    'logs' => 'logs',
    'config' => 'config',
    '.env' => '.env'
];

foreach ($filestoCheck as $name => $path) {
    if (file_exists($path)) {
        if (is_writable($path) || is_writable(__DIR__ . '/' . $path)) {
            $health['checks'][$name . '_writable'] = ['status' => 'ok', 'message' => "$name is writable"];
        } else {
            $health['checks'][$name . '_writable'] = ['status' => 'warning', 'message' => "$name is read-only"];
        }
    } else {
        $health['checks'][$name . '_exists'] = ['status' => 'error', 'message' => "$name not found"];
    }
}

// Set HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : ($health['status'] === 'degraded' ? 206 : 503));

// Return health status
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
