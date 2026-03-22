<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}



require_once '../../config/database.php';

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
        
        // Validate status against allowed values
        $allowed_statuses = ['new', 'read', 'contacted', 'replied'];
        if (!in_array($status, $allowed_statuses)) {
            $status = 'new';
        }
        
        // Use prepared statement
        $sql = "UPDATE inquiries SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $status, $notes, $id);
        if ($stmt->execute()) {
            $conn->commit();
        } else {
            $conn->rollback();
            error_log('Failed to update status: ' . $stmt->error);
        }
        $stmt->close();
        
        log_activity('STATUS_UPDATED', "Inquiry #$id updated to $status");
        
    } elseif ($action === 'mark_all_read') {
        $sql = "UPDATE inquiries SET status = 'read', updated_at = NOW() WHERE status = 'new'";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute()) {
            $conn->commit();
        } else {
            $conn->rollback();
            error_log('Failed to mark all as read: ' . $stmt->error);
        }
        $stmt->close();
        
        log_activity('MARK_ALL_READ', 'All new inquiries marked as read');
        
        // Redirect back to dashboard
        header('Location: dashboard.php', true, 302);
        exit;
    }
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'active'; // active or deleted

// Validate status filter against allowed values
$allowed_statuses = ['all', 'new', 'read', 'contacted', 'replied'];
if (!in_array($statusFilter, $allowed_statuses)) {
    $statusFilter = 'all';
}

// Build query using prepared statements
$where_params = [];
$where_types = '';

if ($view === 'deleted') {
    // Deleted view query
    $sql = "SELECT * FROM deleted_inquiries";
    $count_sql = "SELECT COUNT(*) as count FROM deleted_inquiries";
    
    if ($searchQuery) {
        $sql .= " WHERE company_name LIKE ? OR contact_name LIKE ? OR email LIKE ?";
        $count_sql .= " WHERE company_name LIKE ? OR contact_name LIKE ? OR email LIKE ?";
        $search_param = '%' . $searchQuery . '%';
        $where_params = [$search_param, $search_param, $search_param];
        $where_types = 'sss';
    }
    
    $sql .= " ORDER BY deleted_at DESC";
    
    // Execute deleted inquiries query
    $stmt = $conn->prepare($sql);
    if ($where_params) {
        $stmt->bind_param($where_types, ...$where_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    // Count deleted inquiries
    $stmt = $conn->prepare($count_sql);
    if ($where_params) {
        $stmt->bind_param($where_types, ...$where_params);
    }
    $stmt->execute();
    $deletedCount = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    $totalCount = $deletedCount;
    $statusCounts = [];
} else {
    // Active view query
    $sql = "SELECT * FROM inquiries";
    $conditions = [];
    $where_params = [];
    $where_types = '';
    
    // Build WHERE clause with status filter
    if ($statusFilter !== 'all') {
        $conditions[] = "status = ?";
        $where_params[] = $statusFilter;
        $where_types .= 's';
    }
    
    // Add search filter
    if ($searchQuery) {
        $conditions[] = "(company_name LIKE ? OR contact_name LIKE ? OR email LIKE ?)";
        $search_param = '%' . $searchQuery . '%';
        $where_params[] = $search_param;
        $where_params[] = $search_param;
        $where_params[] = $search_param;
        $where_types .= 'sss';
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY submitted_at DESC";
    
    // Execute inquiries query
    $stmt = $conn->prepare($sql);
    if ($where_params) {
        $stmt->bind_param($where_types, ...$where_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    // Count by status (active only)
    $statusCounts = [];
    $statusResult = $conn->query("SELECT status, COUNT(*) as count FROM inquiries GROUP BY status");
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
    <link rel="icon" type="image/png" href="../assets/images/logo.JPG">
    <link rel="apple-touch-icon" href="../assets/images/logo.jpg">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<!-- HEADER -->
<header class="admin-header">
    <div class="admin-header-content">
        <div class="admin-brand">
            <i class="fas fa-box-open"></i>
            <span>Vinpack Admin</span>
        </div>
        <form method="POST" class="admin-logout">
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </button>
        </form>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="admin-main">
    <!-- DASHBOARD HEADER -->
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <p>Manage customer inquiries and track responses</p>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $totalCount; ?></div>
                <div class="stat-label"><?php echo $view === 'deleted' ? 'Deleted' : 'Total'; ?> Inquiries</div>
            </div>
        </div>

        <?php if ($view === 'active'): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope-open"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $statusCounts['new'] ?? 0; ?></div>
                    <div class="stat-label">New</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $statusCounts['read'] ?? 0; ?></div>
                    <div class="stat-label">Read</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $deletedCount ?? 0; ?></div>
                <div class="stat-label">Deleted</div>
            </div>
        </div>
    </div>

    <!-- FILTERS & TABS -->
    <div class="filters-section">
        <div class="tab-navigation">
            <a href="dashboard.php?view=active" class="tab-btn <?php echo $view === 'active' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> Active
            </a>
            <a href="dashboard.php?view=deleted" class="tab-btn <?php echo $view === 'deleted' ? 'active' : ''; ?>">
                <i class="fas fa-trash"></i> Deleted
            </a>
        </div>

        <form method="GET" class="filter-controls">
            <input type="hidden" name="view" value="<?php echo $view; ?>">
            <input type="text" name="search" placeholder="Search by company, name, or email..." value="<?php echo htmlspecialchars($searchQuery); ?>" class="search-input">
            
            <?php if ($view === 'active'): ?>
                <select name="status" class="status-filter" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="read" <?php echo $statusFilter === 'read' ? 'selected' : ''; ?>>Read</option>
                    <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- INQUIRIES TABLE -->
    <div class="table-section">
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="inquiries-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($inquiry = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($inquiry['company_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($inquiry['contact_name']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['email']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['product_interest']); ?></td>
                            <td>
                                <?php
                                $status = $inquiry['status'] ?? 'new';
                                $status_classes = [
                                    'new' => 'badge-new',
                                    'read' => 'badge-read',
                                    'contacted' => 'badge-contacted',
                                    'replied' => 'badge-replied'
                                ];
                                $status_text = ['new' => 'New', 'read' => 'Read', 'contacted' => 'Contacted', 'replied' => 'Replied'];
                                ?>
                                <span class="status-badge <?php echo $status_classes[$status] ?? 'badge-read'; ?>">
                                    <?php echo $status_text[$status] ?? 'Unknown'; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $date = $view === 'deleted' ? $inquiry['deleted_at'] : ($inquiry['submitted_at'] ?? 'N/A');
                                echo !empty($date) ? date('M d, Y', strtotime($date)) : 'N/A';
                                ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-view" onclick="openModal(<?php echo $inquiry['id']; ?>, '<?php echo htmlspecialchars(json_encode($inquiry), ENT_QUOTES); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($view === 'active'): ?>
                                        <button class="btn-delete" onclick="deleteInquiry(<?php echo $inquiry['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-delete" onclick="permanentDelete(<?php echo $inquiry['id']; ?>)">
                                            <i class="fas fa-times"></i>
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
                <div class="bulk-actions">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="action" value="mark_all_read" class="btn-bulk" onclick="return confirm('Mark all new inquiries as read?')">
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
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h2>
                <i class="fas fa-envelope"></i>
                Inquiry Details
            </h2>
            <button type="button" class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="inquiry-field">
                <label>Company Name</label>
                <p id="modalCompany"></p>
            </div>

            <div class="inquiry-field">
                <label>Contact Person</label>
                <p id="modalContact"></p>
            </div>

            <div class="inquiry-field">
                <label>Email</label>
                <p id="modalEmail"></p>
            </div>

            <div class="inquiry-field">
                <label>Phone</label>
                <p id="modalPhone"></p>
            </div>

            <div class="inquiry-field">
                <label>Product Interest</label>
                <p id="modalProduct"></p>
            </div>

            <div class="inquiry-field">
                <label>Message</label>
                <p id="modalMessage" style="white-space: pre-wrap;"></p>
            </div>

            <?php if ($view === 'active'): ?>
                <form method="POST" class="modal-form">
                    <input type="hidden" name="id" id="modalInquiryId">
                    <input type="hidden" name="action" value="update_status">

                    <div class="form-field">
                        <label for="statusSelect">Update Status</label>
                        <select id="statusSelect" name="status" required>
                            <option value="new">New</option>
                            <option value="read">Read</option>
                            <option value="contacted">Contacted</option>
                            <option value="replied">Replied</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="notesText">Admin Notes</label>
                        <textarea id="notesText" name="notes" placeholder="Add your notes here..."></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Close</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
    if (confirm('Delete this inquiry? It will be archived.')) {
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
        })
        .catch(e => alert('Error: ' + e.message));
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
        })
        .catch(e => alert('Error: ' + e.message));
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// ===== SESSION TIMEOUT & ACTIVITY CHECKING =====
let sessionCheckInterval;
let logoutWarningTimer;
let sessionExpired = false;

function initializeSessionChecking() {
    // Check session status every 30 seconds
    sessionCheckInterval = setInterval(checkSession, 30000);
    
    // Update activity on user interaction
    document.addEventListener('click', updateActivity);
    document.addEventListener('keypress', updateActivity);
    document.addEventListener('mousemove', debounce(updateActivity, 5000));
    
    // Check session immediately on page load
    checkSession();
}

function updateActivity() {
    // Ping the server to update last activity time
    fetch('/admin/check-session.php')
        .catch(() => {}); // Silent fail if network issue
}

function debounce(func, delay) {
    let timeout;
    return function() {
        clearTimeout(timeout);
        timeout = setTimeout(func, delay);
    };
}

function checkSession() {
    if (sessionExpired) return;
    
    fetch('/admin/check-session.php')
        .then(r => r.json())
        .then(data => {
            if (!data.logged_in) {
                if (data.expired) {
                    handleSessionExpired();
                } else {
                    window.location.href = '/admin/login.php';
                }
                return;
            }
            
            // Show warning if close to timeout
            if (data.show_warning) {
                showSessionWarning(data.time_remaining);
            } else {
                // Hide warning if session was extended
                const warningModal = document.getElementById('sessionWarningModal');
                if (warningModal) {
                    warningModal.classList.remove('open');
                }
                clearTimeout(logoutWarningTimer);
            }
        })
        .catch(() => {}); // Silent fail
}

function showSessionWarning(timeRemaining) {
    let warningModal = document.getElementById('sessionWarningModal');
    
    if (!warningModal) {
        // Create modal if it doesn't exist
        warningModal = document.createElement('div');
        warningModal.id = 'sessionWarningModal';
        warningModal.className = 'session-warning-modal';
        warningModal.innerHTML = `
            <div class="session-warning-content">
                <div class="session-warning-header">
                    <i class="fas fa-hourglass-end"></i>
                    <h2>Session Expiring Soon</h2>
                </div>
                <p>Your session will expire in <span id="timeCountdown">5 minutes</span> due to inactivity.</p>
                <p>Would you like to continue working?</p>
                <div class="session-warning-actions">
                    <button class="btn-secondary" onclick="logoutNow()">Logout</button>
                    <button class="btn-primary" onclick="extendSession()">Continue Working</button>
                </div>
            </div>
        `;
        document.body.appendChild(warningModal);
    }
    
    warningModal.classList.add('open');
    
    // Start countdown
    let secondsRemaining = timeRemaining;
    clearTimeout(logoutWarningTimer);
    
    function updateCountdown() {
        const minutes = Math.floor(secondsRemaining / 60);
        const seconds = secondsRemaining % 60;
        const countdownEl = document.getElementById('timeCountdown');
        
        if (countdownEl) {
            if (minutes > 0) {
                countdownEl.textContent = `${minutes} minute${minutes !== 1 ? 's' : ''} ${seconds} second${seconds !== 1 ? 's' : ''}`;
            } else {
                countdownEl.textContent = `${seconds} second${seconds !== 1 ? 's' : ''}`;
            }
        }
        
        secondsRemaining--;
        
        if (secondsRemaining <= 0) {
            handleSessionExpired();
        } else {
            logoutWarningTimer = setTimeout(updateCountdown, 1000);
        }
    }
    
    updateCountdown();
}

function extendSession() {
    // User clicked continue - reset inactivity timer by making a request
    fetch('/admin/check-session.php')
        .then(r => r.json())
        .then(data => {
            if (data.logged_in) {
                const warningModal = document.getElementById('sessionWarningModal');
                if (warningModal) {
                    warningModal.classList.remove('open');
                }
                clearTimeout(logoutWarningTimer);
            }
        });
}

function logoutNow() {
    sessionExpired = true;
    clearInterval(sessionCheckInterval);
    clearTimeout(logoutWarningTimer);
    
    fetch('/admin/logout-api.php')
        .then(() => {
            window.location.href = '/admin/login.php';
        })
        .catch(() => {
            window.location.href = '/admin/login.php';
        });
}

function handleSessionExpired() {
    sessionExpired = true;
    clearInterval(sessionCheckInterval);
    clearTimeout(logoutWarningTimer);
    
    alert('Your session has expired due to inactivity. Please login again.');
    window.location.href = '/admin/login.php?expired=1';
}

// Initialize session checking when page loads
window.addEventListener('load', initializeSessionChecking);

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    clearInterval(sessionCheckInterval);
    clearTimeout(logoutWarningTimer);
});
</script>
</body>
</html>
