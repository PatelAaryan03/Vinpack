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
    $requiredFields = ['companyName', 'contactName', 'email', 'phone', 'product', 'message'];
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
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8');
    $product = htmlspecialchars(trim($data['product']), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(trim($data['message']), ENT_QUOTES, 'UTF-8');
    $submittedAt = date('Y-m-d H:i:s');

    // Validate email format (required field)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }

    // Validate phone format (basic: at least 10 digits or + sign)
    if (!preg_match('/^[+\d\s\-\(\)]{10,}$/', $phone)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid phone format'
        ]);
        exit;
    }

    // Validate field lengths
    if (strlen($companyName) > 255 || strlen($contactName) > 255 || strlen($phone) > 20 || strlen($product) > 255) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'One or more fields exceed maximum length'
        ]);
        exit;
    }

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

    // Use prepared statement to prevent SQL injection
    $sql = "INSERT INTO inquiries (company_name, contact_name, email, phone, product_interest, message, submitted_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'new')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error. Please try again later.'
        ]);
        error_log('Prepare failed: ' . $conn->error);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param('sssssss', $companyName, $contactName, $email, $phone, $product, $message, $submittedAt);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error saving inquiry. Please try again later.'
        ]);
        error_log('Database query failed: ' . $stmt->error);
        $conn->rollback();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Commit the transaction
    if (!$conn->commit()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error saving inquiry. Please try again later.'
        ]);
        error_log('Commit failed: ' . $conn->error);
        $conn->rollback();
        $stmt->close();
        $conn->close();
        exit;
    }

    // Get inserted ID
    $inquiryId = $conn->insert_id;
    error_log("Inquiry submitted successfully | ID: $inquiryId | Company: $companyName");
    
    $stmt->close();
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
