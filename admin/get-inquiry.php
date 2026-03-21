<?php
/**
 * Get Individual Inquiry Details
 */

require_once '../config/database.php';

$id = intval($_GET['id']);
$view = $_GET['view'] ?? 'active';

// Query from appropriate table based on view
$table = ($view === 'deleted') ? 'deleted_inquiries' : 'inquiries';
$sql = "SELECT * FROM $table WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo '<p>Inquiry not found</p>';
    exit;
}

$inquiry = $result->fetch_assoc();
?>

<form method="POST" id="updateForm">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="id" value="<?php echo $inquiry['id']; ?>">

    <div class="inquiry-detail">
        <label>Company Name</label>
        <p><?php echo htmlspecialchars($inquiry['company_name']); ?></p>
    </div>

    <div class="inquiry-detail">
        <label>Contact Person</label>
        <p><?php echo htmlspecialchars($inquiry['contact_name']); ?></p>
    </div>

    <div class="inquiry-detail">
        <label>Email</label>
        <p><?php echo htmlspecialchars($inquiry['email']); ?></p>
    </div>

    <div class="inquiry-detail">
        <label>Phone</label>
        <p><?php echo htmlspecialchars($inquiry['phone']); ?></p>
    </div>

    <div class="inquiry-detail">
        <label>Product of Interest</label>
        <p><?php echo htmlspecialchars($inquiry['product_interest']); ?></p>
    </div>

    <div class="inquiry-detail">
        <label>Message</label>
        <p><?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></p>
    </div>

    <div class="inquiry-detail">
        <label><?php echo $view === 'deleted' ? 'Deleted' : 'Submitted'; ?></label>
        <p><?php echo date('F d, Y \a\t h:i A', strtotime($view === 'deleted' ? $inquiry['deleted_at'] : $inquiry['submitted_at'])); ?></p>
    </div>

    <!-- Information Section -->
    <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin: 15px 0;">
        <h3 style="color: #2c3e50; font-size: 14px; margin-bottom: 10px;">📊 Information</h3>
        
        <div class="inquiry-detail">
            <label>Inquiry ID</label>
            <p>#<?php echo $inquiry['id']; ?></p>
        </div>

        <div class="inquiry-detail">
            <label>Last Updated</label>
            <p><?php echo date('F d, Y \a\t h:i A', strtotime($view === 'deleted' ? $inquiry['deleted_at'] : $inquiry['updated_at'])); ?></p>
        </div>

        <?php if ($view === 'deleted' && isset($inquiry['deleted_by'])): ?>
        <div class="inquiry-detail">
            <label>Deleted By</label>
            <p><?php echo htmlspecialchars($inquiry['deleted_by']); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">

    <?php if ($view === 'active'): ?>
        <div class="form-group">
            <label>Status</label>
            <select name="status" required>
                <option value="new" <?php echo $inquiry['status'] === 'new' ? 'selected' : ''; ?>>🆕 New</option>
                <option value="contacted" <?php echo $inquiry['status'] === 'contacted' ? 'selected' : ''; ?>>💬 Contacted</option>
                <option value="completed" <?php echo $inquiry['status'] === 'completed' ? 'selected' : ''; ?>>✓ Completed</option>
                <option value="spam" <?php echo $inquiry['status'] === 'spam' ? 'selected' : ''; ?>>🚫 Spam</option>
            </select>
        </div>

        <div class="form-group">
            <label>Admin Notes</label>
            <textarea name="notes" placeholder="Add internal notes about this inquiry..."><?php echo htmlspecialchars($inquiry['admin_notes']); ?></textarea>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn" style="background: #dc2626; color: white;" onclick="deleteInquiry(<?php echo $inquiry['id']; ?>)">🗑️ Delete</button>
        </div>
    <?php else: ?>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            <button type="button" class="btn" style="background: #dc2626; color: white;" onclick="permanentlyDeleteInquiry(<?php echo $inquiry['id']; ?>)">🗑️ Permanently Delete</button>
        </div>
    <?php endif; ?>
</form>

<script>
document.getElementById('updateForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const response = await fetch('../admin/dashboard.php', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        alert('Inquiry updated successfully!');
        closeModal();
        location.reload();
    }
});

function permanentlyDeleteInquiry(id) {
    if (confirm('Are you sure you want to PERMANENTLY DELETE this inquiry? This action cannot be undone.')) {
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
</script>

