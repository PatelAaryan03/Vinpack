<?php
/**
 * Session Activity Check & Timeout Handler
 * Validates session and checks for inactivity
 */

session_start();

header('Content-Type: application/json');

// Session timeout in seconds (20 minutes)
$inactivity_timeout = 1200;

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'logged_in' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

// Get current time and last activity time
$current_time = time();
$last_activity = $_SESSION['last_activity'] ?? $current_time;
$time_since_activity = $current_time - $last_activity;

// Check if session has expired due to inactivity
if ($time_since_activity > $inactivity_timeout) {
    // Session expired - destroy it
    session_destroy();
    http_response_code(401);
    echo json_encode([
        'logged_in' => false,
        'expired' => true,
        'message' => 'Session expired due to inactivity'
    ]);
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = $current_time;

// Calculate time remaining before logout
$time_remaining = $inactivity_timeout - $time_since_activity;
$warning_threshold = 300; // Show warning when 5 minutes remain

echo json_encode([
    'logged_in' => true,
    'time_remaining' => $time_remaining,
    'show_warning' => $time_remaining <= $warning_threshold,
    'inactivity_timeout' => $inactivity_timeout,
    'username' => $_SESSION['admin_username'] ?? 'Admin'
]);
exit;
?>
