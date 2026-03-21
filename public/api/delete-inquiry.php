<?php
header('Content-Type: application/json');

require_once '../../config/database.php';

// Helper function to log activity
function log_activity($action, $details = '') {
    $log_dir = __DIR__ . '/../logs';
    @mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/admin_activity.log';
    $username = $_SESSION['admin_username'] ?? 'API';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_msg = "$timestamp | Action: $action | User: $username | IP: $ip";
    if ($details) {
        $log_msg .= " | Details: $details";
    }
    $log_msg .= "\n";
    @file_put_contents($log_file, $log_msg, FILE_APPEND);
}

// Get JSON data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    http_response_code(400);
    exit;
}

$inquiry_id = intval($input['id']);

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    http_response_code(500);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get the inquiry data
    $sql = "SELECT * FROM inquiries WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $inquiry_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inquiry = $result->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        throw new Exception('Inquiry not found');
    }

    // Insert into deleted_inquiries table
    $sql = "INSERT INTO deleted_inquiries (original_inquiry_id, company_name, contact_name, email, phone, product_interest, message, status, submitted_at, admin_notes, deleted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $deleted_by = $_SESSION['admin_username'] ?? 'unknown';
    
    // Extract variables for binding
    $company_name = $inquiry['company_name'];
    $contact_name = $inquiry['contact_name'];
    $email = $inquiry['email'];
    $phone = $inquiry['phone'];
    $product_interest = $inquiry['product_interest'];
    $message = $inquiry['message'];
    $status = $inquiry['status'];
    $submitted_at = $inquiry['submitted_at'];
    $admin_notes = $inquiry['admin_notes'];
    
    $stmt->bind_param(
        'issssssssss',
        $inquiry_id,
        $company_name,
        $contact_name,
        $email,
        $phone,
        $product_interest,
        $message,
        $status,
        $submitted_at,
        $admin_notes,
        $deleted_by
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to archive inquiry: ' . $stmt->error);
    }
    $stmt->close();

    // Delete from inquiries table
    $sql = "DELETE FROM inquiries WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $inquiry_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete inquiry');
    }
    $stmt->close();

    // Commit transaction
    $conn->commit();

    log_activity('DELETE_INQUIRY', "Inquiry #$inquiry_id deleted - Company: {$inquiry['company_name']}");

    echo json_encode([
        'success' => true,
        'message' => 'Inquiry deleted and archived',
        'inquiry_id' => $inquiry_id
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
