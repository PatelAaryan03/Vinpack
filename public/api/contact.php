<?php
/**
 * Vinpack Contact Form API
 * Form submission to MySQL database with comprehensive error handling
 */

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
@mkdir(__DIR__ . '/../../logs', 0755, true);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set timeout and memory
set_time_limit(10);
ini_set('memory_limit', '128M');

try {
    // Validate HTTP method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
        error_log('Invalid method: ' . $_SERVER['REQUEST_METHOD']);
        exit;
    }

    // Get and decode JSON data
    $input = file_get_contents('php://input');
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Empty request body']);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
        error_log('Invalid JSON: ' . json_last_error_msg());
        exit;
    }

    // Validate required fields
    $requiredFields = ['companyName', 'contactName', 'phone', 'product', 'message'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        error_log('Missing fields: ' . implode(', ', $missingFields));
        exit;
    }

    // Sanitize inputs
    $companyName = htmlspecialchars(trim($data['companyName']), ENT_QUOTES, 'UTF-8');
    $contactName = htmlspecialchars(trim($data['contactName']), ENT_QUOTES, 'UTF-8');
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: 'not provided';
    $phone = htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8');
    $product = htmlspecialchars(trim($data['product']), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(trim($data['message']), ENT_QUOTES, 'UTF-8');
    $submittedAt = date('Y-m-d H:i:s');

    // Connect to database
    require_once '../../config/database.php';

    // Check if connection successful
    if (!isset($conn) || !$conn || !($conn instanceof mysqli)) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Database service unavailable. Please try again later.'
        ]);
        error_log('Database connection failed');
        exit;
    }

    // Prepare data for database
    $companyNameEscaped = $conn->real_escape_string($companyName);
    $contactNameEscaped = $conn->real_escape_string($contactName);
    $phoneEscaped = $conn->real_escape_string($phone);
    $productEscaped = $conn->real_escape_string($product);
    $messageEscaped = $conn->real_escape_string($message);

    // Build SQL query
    $sql = "INSERT INTO inquiries (company_name, contact_name, email, phone, product_interest, message, submitted_at, status) 
            VALUES ('$companyNameEscaped', '$contactNameEscaped', '$email', '$phoneEscaped', '$productEscaped', '$messageEscaped', '$submittedAt', 'new')";

    if (!$conn->query($sql)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error saving inquiry. Please try again later.'
        ]);
        error_log('Database query failed: ' . $conn->error);
        $conn->close();
        exit;
    }

    // Get inserted ID
    $inquiryId = $conn->insert_id;
    error_log("Inquiry submitted successfully | ID: $inquiryId | Company: $companyName");

    $conn->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Inquiry submitted successfully!',
        'inquiryId' => $inquiryId
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
    error_log('Exception: ' . $e->getMessage());
    exit;
}
