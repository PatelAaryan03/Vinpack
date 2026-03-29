<?php
session_start();

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
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$inquiry_id = intval($input['id']);

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Permanently delete from deleted_inquiries table
    $sql = "DELETE FROM deleted_inquiries WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $inquiry_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to permanently delete inquiry');
    }
    
    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        throw new Exception('Inquiry not found');
    }
    
    // Commit the transaction
    $conn->commit();
    $stmt->close();

    log_activity('PERMANENT_DELETE', "Inquiry #$inquiry_id permanently deleted");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Inquiry permanently deleted',
        'inquiry_id' => $inquiry_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
