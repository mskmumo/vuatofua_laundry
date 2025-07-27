<?php
require_once '../config.php';
require_once '../functions.php';

secure_session_start();

// Ensure user is admin
if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

$conn = db_connect();

// Handle POST requests for contact actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
    $action = $_POST['action'] ?? '';
    $contact_id = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : 0;

    if ($contact_id > 0) {
        switch ($action) {
            case 'mark_read':
                $stmt = $conn->prepare("UPDATE contact_requests SET status = 'read', read_by = ?, read_at = NOW() WHERE contact_id = ?");
                $stmt->bind_param("ii", $_SESSION['user_id'], $contact_id);
                if ($stmt->execute()) {
                    // Create notification for user if they have an account
                    $contact_stmt = $conn->prepare("SELECT user_id, name, subject FROM contact_requests WHERE contact_id = ?");
                    $contact_stmt->bind_param("i", $contact_id);
                    $contact_stmt->execute();
                    $contact_result = $contact_stmt->get_result();
                    $contact = $contact_result->fetch_assoc();
                    
                    if ($contact && $contact['user_id']) {
                        $notification_stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, title, message, related_id, related_type) 
                            VALUES (?, 'contact_read', ?, ?, ?, 'contact')
                        ");
                        $title = "Contact Request Read";
                        $message = "Your contact request '{$contact['subject']}' has been read by our team.";
                        $notification_stmt->bind_param("issi", $contact['user_id'], $title, $message, $contact_id);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                    }
                    
                    $contact_stmt->close();
                    $_SESSION['success_message'] = 'Contact marked as read successfully.';
                }
                $stmt->close();
                break;
                
            case 'mark_replied':
                $stmt = $conn->prepare("UPDATE contact_requests SET status = 'replied', replied_at = NOW() WHERE contact_id = ?");
                $stmt->bind_param("i", $contact_id);
                if ($stmt->execute()) {
                    // Create notification for user
                    $contact_stmt = $conn->prepare("SELECT user_id, name, subject FROM contact_requests WHERE contact_id = ?");
                    $contact_stmt->bind_param("i", $contact_id);
                    $contact_stmt->execute();
                    $contact_result = $contact_stmt->get_result();
                    $contact = $contact_result->fetch_assoc();
                    
                    if ($contact && $contact['user_id']) {
                        $notification_stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, title, message, related_id, related_type) 
                            VALUES (?, 'contact_replied', ?, ?, ?, 'contact')
                        ");
                        $title = "Contact Request Replied";
                        $message = "We have replied to your contact request '{$contact['subject']}'. Please check your email.";
                        $notification_stmt->bind_param("issi", $contact['user_id'], $title, $message, $contact_id);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                    }
                    
                    $contact_stmt->close();
                    $_SESSION['success_message'] = 'Contact marked as replied successfully.';
                }
                $stmt->close();
                break;
                
            case 'send_reply':
                $reply_message = $_POST['reply_message'] ?? '';
                $reply_subject = $_POST['reply_subject'] ?? '';
                
                if (!empty($reply_message) && !empty($reply_subject)) {
                    // Get contact details
                    $contact_stmt = $conn->prepare("SELECT user_id, name, email, subject FROM contact_requests WHERE contact_id = ?");
                    $contact_stmt->bind_param("i", $contact_id);
                    $contact_stmt->execute();
                    $contact_result = $contact_stmt->get_result();
                    $contact = $contact_result->fetch_assoc();
                    
                    if ($contact) {
                        // Insert reply into contact_replies table
                        $reply_stmt = $conn->prepare("
                            INSERT INTO contact_replies (contact_id, admin_id, reply_subject, reply_message, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $reply_stmt->bind_param("iiss", $contact_id, $_SESSION['user_id'], $reply_subject, $reply_message);
                        
                        if ($reply_stmt->execute()) {
                            // Update contact status to replied
                            $update_stmt = $conn->prepare("UPDATE contact_requests SET status = 'replied', replied_at = NOW() WHERE contact_id = ?");
                            $update_stmt->bind_param("i", $contact_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            // Create notification for user if they have an account
                            if ($contact['user_id']) {
                                $notification_stmt = $conn->prepare("
                                    INSERT INTO notifications (user_id, type, title, message, related_id, related_type) 
                                    VALUES (?, 'contact_replied', ?, ?, ?, 'contact')
                                ");
                                $title = "Reply to Your Contact Request";
                                $message = "We have replied to your contact request '{$contact['subject']}'. Please check your email or contact dashboard.";
                                $notification_stmt->bind_param("issi", $contact['user_id'], $title, $message, $contact_id);
                                $notification_stmt->execute();
                                $notification_stmt->close();
                            }
                            
                            // Here you could also send an actual email using a mail function
                            // mail($contact['email'], $reply_subject, $reply_message, $headers);
                            
                            $_SESSION['success_message'] = 'Reply sent successfully!';
                        } else {
                            $_SESSION['error_message'] = 'Failed to send reply. Please try again.';
                        }
                        
                        $reply_stmt->close();
                    }
                    
                    $contact_stmt->close();
                } else {
                    $_SESSION['error_message'] = 'Please provide both subject and message for the reply.';
                }
                break;
        }
    }
    redirect('manage_contacts.php');
}

// Fetch all contact requests
$contacts = $conn->query("
    SELECT cr.*, u.name as user_name, admin.name as read_by_name
    FROM contact_requests cr
    LEFT JOIN users u ON cr.user_id = u.user_id
    LEFT JOIN users admin ON cr.read_by = admin.user_id
    ORDER BY 
        CASE cr.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        cr.created_at DESC
");

// Get statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
    FROM contact_requests
");
$stats = $stats_result->fetch_assoc();

$page_title = 'Manage Contact Requests';
$is_admin_page = true;
require_once '../templates/header.php';
?>

<style>
/* === Dark Theme Core Styles === */

:root {
  --bg-dark: #131C21;
  --text-light: #ffffff;
  --accent: #C4A484;
  --accent-dark: #1A252B;
}

body {
  background-color: var(--bg-dark);
  color: var(--text-light);
  font-family: 'Segoe UI', sans-serif;
}

/* === Layout === */
.container {
  max-width: 1600px;
  padding: 20px;
  width: 95%;
}

/* === Stats Cards === */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent) 100%);
  color: var(--text-light);
  padding: 1.5rem;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.stat-card h3 {
  margin: 0 0 0.5rem 0;
  font-size: 2rem;
  font-weight: bold;
}
.stat-card p {
  margin: 0;
  opacity: 0.9;
  font-size: 0.9rem;
}

/* === Contact Cards === */
.contact-card {
  background: var(--accent-dark);
  color: var(--text-light);
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.contact-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--accent);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
}

.contact-info h4 {
  margin: 0 0 0.5rem 0;
  color: var(--accent);
}

.contact-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.9rem;
  color: #bbbbbb;
}

/* === Badges === */
.badge {
  padding: 0.4rem 0.8rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: white;
}

.badge-unread   { background: #e74c3c; }
.badge-read     { background: #f39c12; }
.badge-replied  { background: #27ae60; }
.badge-urgent   { background: #c0392b; }
.badge-high     { background: #e67e22; }
.badge-medium   { background: #3498db; }
.badge-low      { background: #7f8c8d; }

/* === Contact Body & Message === */
.contact-body {
  padding: 1.5rem;
}

.contact-message {
  background: #1E2B31;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid var(--accent);
  margin-bottom: 1rem;
}

/* === Actions === */
.contact-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 1rem;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
  border-radius: 6px;
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.btn-sm:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

/* === Buttons === */
.btn-primary {
  background: linear-gradient(135deg, #2980b9 0%, #1a5276 100%);
  color: white;
}
.btn-success {
  background: linear-gradient(135deg, #229954 0%, #196F3D 100%);
  color: white;
}
.btn-secondary {
  background: linear-gradient(135deg, #7f8c8d 0%, #5d6d7e 100%);
  color: white;
}

/* === Reply System Styles === */
.replies-section {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #1E2B31;
  border-radius: 8px;
  border-left: 4px solid var(--accent);
}

.replies-section h6 {
  color: var(--accent);
  margin-bottom: 1rem;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.reply-item {
  background: var(--accent-dark);
  border-radius: 6px;
  padding: 1rem;
  margin-bottom: 0.75rem;
  border: 1px solid rgba(196, 164, 132, 0.2);
}

.reply-item:last-child {
  margin-bottom: 0;
}

.reply-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.5rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.reply-header strong {
  color: var(--accent);
  font-size: 0.95rem;
}

.reply-meta {
  font-size: 0.8rem;
  color: #bbbbbb;
  opacity: 0.8;
}

.reply-content {
  color: var(--text-light);
  line-height: 1.5;
  font-size: 0.9rem;
}

/* === Reply Form Styles === */
.reply-form {
  margin-top: 1.5rem;
  padding: 1.5rem;
  background: #1E2B31;
  border-radius: 8px;
  border: 2px solid var(--accent);
}

.reply-form h6 {
  color: var(--accent);
  margin-bottom: 1rem;
  font-size: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: var(--text-light);
  font-weight: 500;
  font-size: 0.9rem;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  background: var(--accent-dark);
  border: 1px solid rgba(196, 164, 132, 0.3);
  border-radius: 6px;
  color: var(--text-light);
  font-size: 0.9rem;
  transition: border-color 0.2s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 2px rgba(196, 164, 132, 0.2);
}

.form-control::placeholder {
  color: #888;
}

.reply-form-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
  flex-wrap: wrap;
}
</style>

<div class="container">
    <h2 class="mt-4 mb-4">
        <i class="fas fa-envelope"></i> Contact Requests Management
    </h2>
    
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Requests</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['unread']; ?></h3>
            <p>Unread</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['read']; ?></h3>
            <p>Read</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['replied']; ?></h3>
            <p>Replied</p>
        </div>
    </div>

    <!-- Contact Requests -->
    <div class="contacts-list">
        <?php if ($contacts->num_rows > 0): ?>
            <?php while($contact = $contacts->fetch_assoc()): ?>
                <div class="contact-card">
                    <div class="contact-header">
                        <div class="contact-info">
                            <h4><?php echo htmlspecialchars($contact['name']); ?></h4>
                            <div class="contact-meta">
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact['email']); ?></span>
                                <?php if ($contact['phone']): ?>
                                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact['phone']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($contact['created_at'])); ?></span>
                                <?php if ($contact['user_name']): ?>
                                    <span><i class="fas fa-user"></i> User: <?php echo htmlspecialchars($contact['user_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="contact-badges">
                            <span class="badge badge-<?php echo $contact['status']; ?>">
                                <?php echo ucfirst($contact['status']); ?>
                            </span>
                            <span class="badge badge-<?php echo $contact['priority']; ?>">
                                <?php echo ucfirst($contact['priority']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="contact-body">
                        <h5><i class="fas fa-tag"></i> <?php echo htmlspecialchars($contact['subject']); ?></h5>
                        <div class="contact-message">
                            <?php echo nl2br(htmlspecialchars($contact['message'])); ?>
                        </div>
                        
                        <?php if ($contact['read_by_name']): ?>
                            <p class="text-muted">
                                <i class="fas fa-eye"></i> Read by <?php echo htmlspecialchars($contact['read_by_name']); ?> 
                                on <?php echo date('M d, Y H:i', strtotime($contact['read_at'])); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php
                        // Get existing replies for this contact
                        $replies_query = $conn->prepare("
                            SELECT cr.*, u.name as admin_name 
                            FROM contact_replies cr 
                            LEFT JOIN users u ON cr.admin_id = u.user_id 
                            WHERE cr.contact_id = ? 
                            ORDER BY cr.created_at DESC
                        ");
                        $replies_query->bind_param("i", $contact['contact_id']);
                        $replies_query->execute();
                        $replies_result = $replies_query->get_result();
                        ?>
                        
                        <!-- Display Existing Replies -->
                        <?php if ($replies_result->num_rows > 0): ?>
                            <div class="replies-section">
                                <h6><i class="fas fa-comments"></i> Admin Replies (<?php echo $replies_result->num_rows; ?>)</h6>
                                <?php while($reply = $replies_result->fetch_assoc()): ?>
                                    <div class="reply-item">
                                        <div class="reply-header">
                                            <strong><?php echo htmlspecialchars($reply['reply_subject']); ?></strong>
                                            <span class="reply-meta">
                                                by <?php echo htmlspecialchars($reply['admin_name']); ?> 
                                                on <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="reply-content">
                                            <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                        <?php $replies_query->close(); ?>
                        
                        <!-- Quick Actions -->
                        <div class="contact-actions">
                            <?php if ($contact['status'] === 'unread'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="contact_id" value="<?php echo $contact['contact_id']; ?>">
                                    <button type="submit" class="btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Mark as Read
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-sm btn-success" onclick="toggleReplyForm(<?php echo $contact['contact_id']; ?>)">
                                <i class="fas fa-reply"></i> Send Reply
                            </button>
                        </div>
                        
                        <!-- Reply Form (Initially Hidden) -->
                        <div id="reply-form-<?php echo $contact['contact_id']; ?>" class="reply-form" style="display: none;">
                            <h6><i class="fas fa-paper-plane"></i> Send Reply</h6>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="send_reply">
                                <input type="hidden" name="contact_id" value="<?php echo $contact['contact_id']; ?>">
                                
                                <div class="form-group">
                                    <label for="reply_subject_<?php echo $contact['contact_id']; ?>">Subject:</label>
                                    <input type="text" 
                                           id="reply_subject_<?php echo $contact['contact_id']; ?>" 
                                           name="reply_subject" 
                                           class="form-control" 
                                           value="Re: <?php echo htmlspecialchars($contact['subject']); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="reply_message_<?php echo $contact['contact_id']; ?>">Message:</label>
                                    <textarea id="reply_message_<?php echo $contact['contact_id']; ?>" 
                                              name="reply_message" 
                                              class="form-control" 
                                              rows="4" 
                                              placeholder="Type your reply message here..." 
                                              required></textarea>
                                </div>
                                
                                <div class="reply-form-actions">
                                    <button type="submit" class="btn-sm btn-success">
                                        <i class="fas fa-paper-plane"></i> Send Reply
                                    </button>
                                    <button type="button" class="btn-sm btn-secondary" onclick="toggleReplyForm(<?php echo $contact['contact_id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h4>No contact requests found</h4>
                <p class="text-muted">No contact requests have been submitted yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleReplyForm(contactId) {
    const replyForm = document.getElementById('reply-form-' + contactId);
    if (replyForm.style.display === 'none' || replyForm.style.display === '') {
        replyForm.style.display = 'block';
        // Focus on the message textarea
        const textarea = document.getElementById('reply_message_' + contactId);
        if (textarea) {
            setTimeout(() => textarea.focus(), 100);
        }
    } else {
        replyForm.style.display = 'none';
    }
}

// Auto-hide success/error messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});
</script>

<?php
$conn->close();
include '../templates/footer.php';
?>
