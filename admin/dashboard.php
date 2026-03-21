<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}



require_once '../config/database.php';

// Helper function to log activity
function log_activity($action, $details = '') {
    $log_dir = __DIR__ . '/../logs';
    @mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/admin_activity.log';
    $username = $_SESSION['admin_username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_msg = "$timestamp | Action: $action | User: $username | IP: $ip";
    if ($details) {
        $log_msg .= " | Details: $details";
    }
    $log_msg .= "\n";
    @file_put_contents($log_file, $log_msg, FILE_APPEND);
}

// Handle logout
if (isset($_POST['logout'])) {
    log_activity('LOGOUT', '');
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        $notes = htmlspecialchars($conn->real_escape_string($notes), ENT_QUOTES, 'UTF-8');
        $sql = "UPDATE inquiries SET status = '$status', admin_notes = '$notes', updated_at = NOW() WHERE id = $id";
        $conn->query($sql);
        
        log_activity('STATUS_UPDATED', "Inquiry #$id updated to $status");
        
    } elseif ($action === 'mark_all_read') {
        $sql = "UPDATE inquiries SET status = 'read', updated_at = NOW() WHERE status = 'new'";
        $conn->query($sql);
        
        log_activity('MARK_ALL_READ', 'All new inquiries marked as read');
        
        header('Location: dashboard.php');
        exit;
    }
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'active'; // active or deleted

// Build query
$where = "1=1";
if ($statusFilter !== 'all') {
    $where .= " AND status = '$statusFilter'";
}
if ($searchQuery) {
    $searchQuery = $conn->real_escape_string($searchQuery);
    $where .= " AND (company_name LIKE '%$searchQuery%' OR contact_name LIKE '%$searchQuery%' OR email LIKE '%$searchQuery%')";
}

// Choose table based on view
if ($view === 'deleted') {
    $sql = "SELECT * FROM deleted_inquiries WHERE $where ORDER BY deleted_at DESC";
    $result = $conn->query($sql);
    
    // Count deleted inquiries
    $deletedCount = $conn->query("SELECT COUNT(*) as count FROM deleted_inquiries")->fetch_assoc()['count'];
    $totalCount = $deletedCount;
    $statusCounts = [];
} else {
    $sql = "SELECT * FROM inquiries WHERE $where ORDER BY submitted_at DESC";
    $result = $conn->query($sql);
    
    // Count by status (active only)
    $statusCounts = [];
    $statusSql = "SELECT status, COUNT(*) as count FROM inquiries GROUP BY status";
    $statusResult = $conn->query($statusSql);
    while ($row = $statusResult->fetch_assoc()) {
        $statusCounts[$row['status']] = $row['count'];
    }
    $totalCount = $conn->query("SELECT COUNT(*) as count FROM inquiries")->fetch_assoc()['count'];
    $deletedCount = $conn->query("SELECT COUNT(*) as count FROM deleted_inquiries")->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Vinpack Inquiries</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS - Animate On Scroll -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    

    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #d4a574;
            --primary-dark: #b8935f;
            --primary-light: #e5bfa6;
            --dark: #1a1a2e;
            --darker: #0f0f1e;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #e9ecef;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --radius: 12px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ============ HEADER ============ */
        header {
            background: linear-gradient(135deg, var(--dark) 0%, #2d3e50 100%);
            color: white;
            padding: 25px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        header h1 i {
            color: var(--primary);
            font-size: 32px;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        /* ============ CONTAINER ============ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* ============ STATS SECTION ============ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-left: 5px solid var(--primary);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.1), rgba(212, 165, 116, 0.05));
            border-radius: 50%;
            transform: translate(40px, -40px);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.new {
            border-left-color: var(--warning);
        }

        .stat-card.read {
            border-left-color: var(--info);
        }

        .stat-card.total {
            border-left-color: var(--success);
        }

        .stat-card.deleted {
            border-left-color: var(--danger);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 15px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-radius: 10px;
        }

        .stat-card.new .stat-icon {
            background: linear-gradient(135deg, var(--warning), #fbbf24);
        }

        .stat-card.read .stat-icon {
            background: linear-gradient(135deg, var(--info), #60a5fa);
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, var(--success), #34d399);
        }

        .stat-card.deleted .stat-icon {
            background: linear-gradient(135deg, var(--danger), #f87171);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* ============ FILTERS SECTION ============ */
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 35px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-controls {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--dark);
            white-space: nowrap;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }

        .view-tabs {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .tab-btn {
            padding: 10px 20px;
            border: 2px solid var(--border);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            color: var(--gray);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: var(--primary);
        }

        .tab-btn:hover {
            border-color: var(--primary);
        }

        /* ============ TABLE SECTION ============ */
        .inquiries-table-wrapper {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .inquiries-table {
            width: 100%;
            border-collapse: collapse;
        }

        .inquiries-table thead {
            background: linear-gradient(135deg, var(--dark), #2d3e50);
            color: white;
        }

        .inquiries-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .inquiries-table td {
            padding: 16px 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .inquiries-table tbody tr {
            transition: all 0.2s ease;
        }

        .inquiries-table tbody tr:hover {
            background: linear-gradient(90deg, rgba(212, 165, 116, 0.05), transparent);
        }

        /* ============ STATUS BADGES ============ */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-new {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .badge-read {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #0c2340;
        }

        .badge-replied {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .badge-contacted {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #3730a3;
        }

        /* ============ ACTION BUTTONS ============ */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 165, 116, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }

        /* ============ MODAL ============ */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        .modal.open {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 0;
            border-radius: var(--radius);
            max-width: 650px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--dark), #2d3e50);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h2 i {
            color: var(--primary);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            background: var(--light);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            transform: rotate(90deg);
        }

        .inquiry-detail {
            margin-bottom: 20px;
        }

        .inquiry-detail label {
            display: block;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inquiry-detail p {
            color: var(--gray);
            line-height: 1.6;
            font-size: 15px;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }

        select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 12px;
            text-transform: uppercase;
        }

        /* ============ EMPTY STATE ============ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--border);
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        /* ============ ANIMATIONS ============ */
        [data-aos] {
            opacity: 0;
        }

        [data-aos].aos-animate {
            opacity: 1;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            header h1 {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group input,
            .filter-group select {
                width: 100%;
            }

            .view-tabs {
                margin-left: 0;
                width: 100%;
                justify-content: stretch;
            }

            .tab-btn {
                flex: 1;
            }

            .inquiries-table {
                font-size: 12px;
            }

            .inquiries-table th,
            .inquiries-table td {
                padding: 10px 8px;
            }

            .modal-content {
                width: 95%;
                max-height: 95vh;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* ============ LOADING ANIMATION ============ */
        .spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<!-- HEADER -->
<header>
    <div class="container">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            Vinpack Admin
        </h1>
        <form method="POST">
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </button>
        </form>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="container">
    <!-- STATS GRID -->
    <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <div class="stat-number"><?php echo $totalCount; ?></div>
            <div class="stat-label"><?php echo $view === 'deleted' ? 'Deleted' : 'Total'; ?> Inquiries</div>
        </div>

        <?php if ($view === 'active'): ?>
            <div class="stat-card new">
                <div class="stat-icon">
                    <i class="fas fa-envelope-open"></i>
                </div>
                <div class="stat-number"><?php echo $statusCounts['new'] ?? 0; ?></div>
                <div class="stat-label">New</div>
            </div>

            <div class="stat-card read">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-number"><?php echo $statusCounts['read'] ?? 0; ?></div>
                <div class="stat-label">Read</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <div class="stat-number"><?php echo $statusCounts['contacted'] ?? 0; ?></div>
                <div class="stat-label">Contacted</div>
            </div>
        <?php endif; ?>

        <div class="stat-card deleted">
            <div class="stat-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="stat-number"><?php echo $deletedCount ?? 0; ?></div>
            <div class="stat-label">Deleted</div>
        </div>
    </div>

    <!-- FILTERS & TABS -->
    <div class="filters-section" data-aos="fade-up" data-aos-delay="200">
        <div class="filter-controls">
            <!-- Tabs -->
            <div class="view-tabs">
                <a href="dashboard.php?view=active" class="tab-btn <?php echo $view === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i> Active
                </a>
                <a href="dashboard.php?view=deleted" class="tab-btn <?php echo $view === 'deleted' ? 'active' : ''; ?>">
                    <i class="fas fa-trash"></i> Deleted
                </a>
            </div>

            <!-- Search & Filters -->
            <div class="filter-group">
                <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <input type="text" name="search" placeholder="Search by company, name, or email..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="min-width: 250px;">
                    
                    <?php if ($view === 'active'): ?>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="read" <?php echo $statusFilter === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                        </select>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- INQUIRIES TABLE -->
    <div class="inquiries-table-wrapper" data-aos="fade-up" data-aos-delay="300">
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="inquiries-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Company</th>
                        <th style="width: 12%;">Contact</th>
                        <th style="width: 15%;">Email</th>
                        <th style="width: 12%;">Product</th>
                        <th style="width: 10%;">Status</th>
                        <?php if ($view === 'active'): ?>
                            <th style="width: 10%;">Date</th>
                        <?php else: ?>
                            <th style="width: 10%;">Deleted</th>
                        <?php endif; ?>
                        <th style="width: 16%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($inquiry = $result->fetch_assoc()): ?>
                        <tr data-aos="fade-in">
                            <td><strong><?php echo htmlspecialchars($inquiry['company_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($inquiry['contact_name']); ?></td>
                            <td style="font-size: 13px;"><?php echo htmlspecialchars($inquiry['email']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['product_interest']); ?></td>
                            <td>
                                <?php
                                $status = $inquiry['status'] ?? 'new';
                                $status_map = [
                                    'new' => ['badge-new', 'New'],
                                    'read' => ['badge-read', 'Read'],
                                    'contacted' => ['badge-contacted', 'Contacted'],
                                    'replied' => ['badge-replied', 'Replied']
                                ];
                                $badge_info = $status_map[$status] ?? ['badge-read', 'Unknown'];
                                ?>
                                <span class="status-badge <?php echo $badge_info[0]; ?>">
                                    <i class="fas fa-circle" style="font-size: 6px;"></i>
                                    <?php echo $badge_info[1]; ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: var(--gray);">
                                <?php 
                                $date = $view === 'deleted' ? $inquiry['deleted_at'] : ($inquiry['submitted_at'] ?? 'N/A');
                                echo !empty($date) ? date('M d, Y', strtotime($date)) : 'N/A';
                                ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary" onclick="openModal(<?php echo $inquiry['id']; ?>, '<?php echo htmlspecialchars(json_encode($inquiry), ENT_QUOTES); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($view === 'active'): ?>
                                        <button class="btn btn-danger" onclick="deleteInquiry(<?php echo $inquiry['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-danger" onclick="permanentDelete(<?php echo $inquiry['id']; ?>)">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- BULK ACTIONS -->
            <?php if ($view === 'active'): ?>
                <div style="padding: 15px; background: var(--light); border-top: 1px solid var(--border); text-align: right;">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="btn btn-success" onclick="return confirm('Mark all new inquiries as read?')">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No inquiries found</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL -->
<div id="inquiryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>
                <i class="fas fa-envelope"></i>
                <span id="modalTitle">Inquiry Details</span>
            </h2>
            <button type="button" class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="inquiry-detail">
                <label>Company Name</label>
                <p id="modalCompany"></p>
            </div>

            <div class="inquiry-detail">
                <label>Contact Person</label>
                <p id="modalContact"></p>
            </div>

            <div class="inquiry-detail">
                <label>Email</label>
                <p id="modalEmail"></p>
            </div>

            <div class="inquiry-detail">
                <label>Phone</label>
                <p id="modalPhone"></p>
            </div>

            <div class="inquiry-detail">
                <label>Product Interest</label>
                <p id="modalProduct"></p>
            </div>

            <div class="inquiry-detail">
                <label>Message</label>
                <p id="modalMessage" style="white-space: pre-wrap;"></p>
            </div>

            <?php if ($view === 'active'): ?>
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="id" id="modalInquiryId">
                    <input type="hidden" name="action" value="update_status">

                    <div class="form-group">
                        <label for="statusSelect">Update Status</label>
                        <select id="statusSelect" name="status" required>
                            <option value="new">New</option>
                            <option value="read">Read</option>
                            <option value="contacted">Contacted</option>
                            <option value="replied">Replied</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notesText"> Admin Notes</label>
                        <textarea id="notesText" name="notes" placeholder="Add your notes here..."></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn" onclick="closeModal()" style="background: var(--gray); color: white;">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()" style="background: var(--gray); color: white;">Close</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script>
    // Initialize AOS (Animate On Scroll)
    AOS.init({
        duration: 600,
        easing: 'ease-in-out',
        once: false,
        offset: 100
    });

    function openModal(id, inquiryJson) {
        const inquiry = JSON.parse(inquiryJson.replace(/&quot;/g, '"'));
        document.getElementById('modalInquiryId').value = id;
        document.getElementById('modalCompany').textContent = inquiry.company_name;
        document.getElementById('modalContact').textContent = inquiry.contact_name;
        document.getElementById('modalEmail').textContent = inquiry.email;
        document.getElementById('modalPhone').textContent = inquiry.phone;
        document.getElementById('modalProduct').textContent = inquiry.product_interest;
        document.getElementById('modalMessage').textContent = inquiry.message;
        document.getElementById('statusSelect').value = inquiry.status || 'new';
        document.getElementById('notesText').value = inquiry.admin_notes || '';
        document.getElementById('inquiryModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('inquiryModal').classList.remove('open');
    }

    function deleteInquiry(id) {
        if (confirm('Delete this inquiry? It will be archived in the deleted inquiries table.')) {
            fetch('/api/delete-inquiry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    setTimeout(() => location.reload(), 300);
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    }

    function permanentDelete(id) {
        if (confirm('Permanently delete this inquiry? This cannot be undone.')) {
            fetch('/api/permanent-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    setTimeout(() => location.reload(), 300);
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    }

    // Close modal when clicking outside
    document.getElementById('inquiryModal').addEventListener('click', (e) => {
        if (e.target.id === 'inquiryModal') closeModal();
    });


</script>
</body>
</html>
