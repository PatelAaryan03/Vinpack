<?php
// Start session
session_start();

require_once '../../config/database.php';

// Helper function to log activity
function log_activity($action, $details = '') {
    $log_dir = __DIR__ . '/../logs';
    @mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/admin_activity.log';
    $username = $_SESSION['admin_username'] ?? $_POST['username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_msg = "$timestamp | Action: $action | User: $username | IP: $ip";
    if ($details) {
        $log_msg .= " | Details: $details";
    }
    $log_msg .= "\n";
    @file_put_contents($log_file, $log_msg, FILE_APPEND);
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (isset($_GET['expired'])) {
    $error = 'Session expired. Please login again.';
}

// Check for login attempts
if (empty($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

// Rate limiting: 5 attempts per 15 minutes
$max_attempts = 5;
$time_window = 900; // 15 minutes

if (time() - $_SESSION['login_attempt_time'] > $time_window) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

if ($_SESSION['login_attempts'] >= $max_attempts) {
    $error = '❌ Too many login attempts. Please try again in 15 minutes.';
    log_activity('LOGIN_BLOCKED', 'Too many attempts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $admin_username = getenv('ADMIN_USERNAME') ?: 'admin';
    $admin_password = getenv('ADMIN_PASSWORD') ?: 'admin123';
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_attempts'] = 0;
        log_activity('LOGIN_SUCCESS', '');
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $remaining = $max_attempts - $_SESSION['login_attempts'];
        $error = $remaining > 0 
            ? "❌ Invalid credentials. ({$remaining} attempts remaining)"
            : '❌ Too many failed attempts. Please try again later.';
        log_activity('LOGIN_FAILED', "Remaining attempts: $remaining");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Vinpack</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body">
<div class="login-wrapper">
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-box-open"></i>
            </div>
            <h1>Admin Login</h1>
            <p>Vinpack Inquiry Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i>
                    Username
                </label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i>
                    Password
                </label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </button>
        </form>

        <div class="login-info">
            <p><strong>Demo Credentials:</strong></p>
            <p>Username: <code>admin</code></p>
            <p>Password: <code>admin123</code></p>
        </div>
    </div>
</div>
</body>
</html>
