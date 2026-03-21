<?php
/**
 * Vinpack Contact Form API
 * Simple form submission to MySQL database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

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
    exit;
}

// Sanitize inputs
$companyName = htmlspecialchars(trim($data['companyName']), ENT_QUOTES, 'UTF-8');
$contactName = htmlspecialchars(trim($data['contactName']), ENT_QUOTES, 'UTF-8');
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL) ?: 'not provided';
$phone = htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8');
$product = htmlspecialchars(trim($data['product']), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($data['message']), ENT_QUOTES, 'UTF-8');
$submittedAt = date('Y-m-d H:i:s');

// Connect to database
require_once '../config/database.php';

// Check if connection successful
if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit;
}

// Prepare and execute query
$companyNameEscaped = $conn->real_escape_string($companyName);
$contactNameEscaped = $conn->real_escape_string($contactName);
$phoneEscaped = $conn->real_escape_string($phone);
$productEscaped = $conn->real_escape_string($product);
$messageEscaped = $conn->real_escape_string($message);

$sql = "INSERT INTO inquiries (company_name, contact_name, email, phone, product_interest, message, submitted_at, status) 
        VALUES ('$companyNameEscaped', '$contactNameEscaped', '$email', '$phoneEscaped', '$productEscaped', '$messageEscaped', '$submittedAt', 'new')";

if ($conn->query($sql) === TRUE) {
    $inquiryId = $conn->insert_id;
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Inquiry submitted successfully!',
        'inquiryId' => $inquiryId
    ]);
} else {
    $conn->close();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving inquiry. Please try again.'
    ]);
}
