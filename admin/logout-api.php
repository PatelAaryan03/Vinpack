<?php
session_start();

if (isset($_SESSION['admin_logged_in'])) {
    // Log the tab close
    $log_dir = __DIR__ . '/../logs';
    @mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/admin_activity.log';
    $username = $_SESSION['admin_username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_msg = "$timestamp | Action: TAB_CLOSED (Auto-logout) | User: $username | IP: $ip\n";
    @file_put_contents($log_file, $log_msg, FILE_APPEND);
    
    // Destroy session
    session_destroy();
}
?>
