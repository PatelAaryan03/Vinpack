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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #999;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #d4a574;
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #d4a574;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #c09560;
        }

        .login-btn:active {
            transform: scale(0.98);
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc2626;
        }

        .info-box {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }

        .info-box strong {
            color: #2c3e50;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>🔐 Admin Login</h1>
        <p>Vinpack Inquiry Management</p>
    </div>

    <?php if ($error): ?>
        <div class="error-message">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <!-- CSRF Token -->

        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="login-btn">🔐 Sign In</button>
    </form>
</div>
</body>
</html>
