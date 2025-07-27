<?php
require_once '../config.php';
require_once '../functions.php';

secure_session_start();

// Ensure user is logged in
if (!is_logged_in()) {
    redirect('../login.php');
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    redirect('my_contacts.php');
}

// Fetch user's contact requests
$contacts_query = "
    SELECT * FROM contact_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC
";
$contacts_stmt = $conn->prepare($contacts_query);
$contacts_stmt->bind_param("i", $user_id);
$contacts_stmt->execute();
$contacts = $contacts_stmt->get_result();

// Fetch user's notifications related to contacts
$notifications_query = "
    SELECT * FROM notifications 
    WHERE user_id = ? AND type IN ('contact_read', 'contact_replied') 
    ORDER BY created_at DESC 
    LIMIT 10
";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result();

// Get unread notifications count
$unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_count_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['count'];

$page_title = 'My Contact Requests';
require_once 'header.php';
?>

<style>
/* Simple, Clean Layout */
body {
    background-color: #131C21;
    color: #FFFFFF;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
}

.dashboard-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

/* Header */
.dashboard-header {
    background: #1a252b;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    text-align: center;
    border: 1px solid #C4A484;
}

.dashboard-header h1 {
    color: #E5D1B8;
    margin: 0;
    font-size: 2.5rem;
}

.dashboard-header p {
    color: #FFFFFF;
    margin: 10px 0 0 0;
    opacity: 0.8;
}

/* Two Stats Cards Side by Side */
.top-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    flex: 1 1 45%;
    min-width: 250px;
    background: #1a252b;
    padding: 25px;
    border-radius: 10px;
    border: 1px solid #C4A484;
    text-align: center;
}

.stat-card h3 {
    color: #C4A484;
    font-size: 2.5rem;
    margin: 0 0 10px 0;
}

.stat-card p {
    color: #FFFFFF;
    margin: 0;
    font-size: 1.1rem;
}

/* Section Cards - Stacked Vertically */
.section-card {
    background: #1a252b;
    padding: 30px;
    border-radius: 10px;
    border: 1px solid #C4A484;
    margin-bottom: 30px;
}

.section-card h2 {
    color: #E5D1B8;
    margin: 0 0 20px 0;
    font-size: 1.8rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 1.2rem;
    color: #E5D1B8;
}

/* Info Cards */
.info-card {
    background: rgba(196, 164, 132, 0.1);
    border: 1px solid rgba(196, 164, 132, 0.3);
    padding: 25px;
    border-radius: 8px;
    text-align: center;
    margin: 20px 0;
}

.info-card .icon {
    font-size: 2.5rem;
    color: #C4A484;
    margin-bottom: 15px;
}

.info-card h3 {
    color: #E5D1B8;
    margin: 0 0 10px 0;
}

.info-card p {
    color: #FFFFFF;
    margin: 0;
    opacity: 0.8;
}

/* Contact Items */
.contact-item {
    background: rgba(196, 164, 132, 0.1);
    border: 1px solid rgba(196, 164, 132, 0.3);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.contact-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.contact-info h4 {
    color: #E5D1B8;
    margin: 0 0 5px 0;
}

.contact-info p {
    color: #FFFFFF;
    margin: 0;
    opacity: 0.8;
    font-size: 0.9rem;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
}

.status-pending {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.status-read {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.status-replied {
    background: rgba(0, 123, 255, 0.2);
    color: #007bff;
}

.contact-message {
    color: #FFFFFF;
    margin-bottom: 15px;
    line-height: 1.5;
}

.contact-meta {
    color: #FFFFFF;
    opacity: 0.7;
    font-size: 0.9rem;
}

/* Notifications */
.notification-item {
    background: rgba(196, 164, 132, 0.1);
    border: 1px solid rgba(196, 164, 132, 0.3);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.notification-item.unread {
    border-left: 4px solid #C4A484;
}

.notification-icon {
    width: 35px;
    height: 35px;
    background: #C4A484;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #131C21;
}

.notification-content p {
    margin: 0 0 5px 0;
    color: #FFFFFF;
}

.notification-time {
    border: 1px solid var(--accent-color);
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-mark-read:hover {
    background: var(--accent-color);
    color: var(--dark-bg);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(196, 164, 132, 0.3);
}

.empty-state {
    text-align: center;
    padding: 5rem 3rem;
    color: var(--text-color);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.05) 100%);
    border-radius: 16px;
    border: 2px dashed var(--border-color);
    margin: 2rem 0;
    transition: all 0.3s ease;
}

.empty-state:hover {
    border-color: var(--accent-color);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.08) 100%);
    transform: translateY(-2px);
}

.empty-state i { 
    font-size: 5rem; 
    margin-bottom: 2rem; 
    color: var(--accent-color);
    opacity: 0.7;
    text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.empty-state h5 {
    font-size: 1.8rem;
    margin-bottom: 1.2rem;
    color: var(--title-color);
    font-weight: 700;
}

.empty-state p {
    font-size: 1.2rem;
    margin-bottom: 2.5rem;
    color: var(--text-color);
    opacity: 0.9;
    line-height: 1.6;
}

/* Consistent button styling matching site theme */
.btn, .dashboard-link {
    display: inline-block;
    background: transparent;
    color: var(--text-color);
    padding: 12px 30px;
    border: 2px solid var(--accent-color);
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    font-weight: 500;
}

.btn:hover, .dashboard-link:hover {
    background: var(--accent-color);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(196, 164, 132, 0.3);
    text-decoration: none;
}

.btn-primary {
    background: var(--accent-color);
    color: var(--dark-bg);
}

.btn-primary:hover {
    background: var(--title-color);
    color: var(--dark-bg);
    box-shadow: 0 4px 15px rgba(229, 209, 184, 0.3);
}

.text-success {
    color: #27ae60 !important;
    font-weight: 500;
}

.mt-2 {
    margin-top: 1rem;
}

/* === Admin Replies Styling === */
.admin-replies {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: rgba(196, 164, 132, 0.08);
    border-radius: 10px;
    border-left: 4px solid #C4A484;
}

.replies-title {
    color: #C4A484;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-reply {
    background: rgba(26, 37, 43, 0.8);
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(196, 164, 132, 0.2);
    transition: all 0.3s ease;
}

.admin-reply:hover {
    border-color: #C4A484;
    background: rgba(26, 37, 43, 0.9);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.admin-reply:last-child {
    margin-bottom: 0;
}

.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.reply-subject {
    color: #E5D1B8;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

.reply-date {
    color: #C4A484;
    font-size: 0.85rem;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.reply-message {
    color: #FFFFFF;
    line-height: 1.6;
    font-size: 0.95rem;
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(196, 164, 132, 0.1);
}

@media (max-width: 768px) {
    .container { padding: 15px; width: 100%; }
    .contact-header { flex-direction: column; align-items: stretch; }
    .notification-item { flex-direction: column; gap: 0.5rem; }
    .notification-icon { align-self: flex-start; }
    .stats-row { grid-template-columns: 1fr; }
    .dashboard-header { padding: 1.5rem; }
    .dashboard-header h2 { font-size: 1.5rem; }
}
</style>

<div class="container">
  <div class="dashboard-container">

    <!-- Header -->
    <div class="dashboard-header">
        <h2><i class="fas fa-envelope"></i> My Contact Requests</h2>
        <p>Track your contact requests and view responses from our team</p>
    </div>

    <!-- Top Statistics Cards -->
    <div class="top-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $contacts->num_rows; ?></h3>
                <p>Total Requests</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $unread_count; ?></h3>
                <p>Unread Notifications</p>
            </div>
        </div>
    </div>

    <!-- My Contact Requests Section -->
    <?php if ($contacts->num_rows > 0): ?>
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-envelope-open"></i>
                <h4>My Contact Requests</h4>
            </div>
            <div class="section-body">
                <?php 
                // Reset the result pointer
                $contacts->data_seek(0);
                while($contact = $contacts->fetch_assoc()): 
                ?>
                    <div class="contact-item">
                        <div class="contact-header">
                            <div>
                                <h5 class="contact-title"><?php echo htmlspecialchars($contact['subject']); ?></h5>
                                <div class="contact-date">
                                    <i class="fas fa-clock"></i> 
                                    Submitted on <?php echo date('M d, Y H:i', strtotime($contact['created_at'])); ?>
                                </div>
                            </div>
                            <span class="badge badge-<?php echo $contact['status']; ?>">
                                <?php echo ucfirst($contact['status']); ?>
                            </span>
                        </div>
                        
                        <div class="contact-message">
                            <?php echo nl2br(htmlspecialchars($contact['message'])); ?>
                        </div>
                        
                        <?php if ($contact['status'] === 'read'): ?>
                            <div class="mt-2">
                                <small class="text-success">
                                    <i class="fas fa-check"></i> Your request has been read by our team
                                    <?php if ($contact['read_at']): ?>
                                        on <?php echo date('M d, Y H:i', strtotime($contact['read_at'])); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                        // Get admin replies for this contact
                        $replies_query = $conn->prepare("
                            SELECT cr.*, u.name as admin_name 
                            FROM contact_replies cr 
                            LEFT JOIN users u ON cr.admin_id = u.user_id 
                            WHERE cr.contact_id = ? 
                            ORDER BY cr.created_at ASC
                        ");
                        $replies_query->bind_param("i", $contact['contact_id']);
                        $replies_query->execute();
                        $replies_result = $replies_query->get_result();
                        ?>
                        
                        <!-- Display Admin Replies -->
                        <?php if ($replies_result->num_rows > 0): ?>
                            <div class="admin-replies">
                                <h6 class="replies-title">
                                    <i class="fas fa-comments"></i> Admin Replies (<?php echo $replies_result->num_rows; ?>)
                                </h6>
                                <?php while($reply = $replies_result->fetch_assoc()): ?>
                                    <div class="admin-reply">
                                        <div class="reply-header">
                                            <strong class="reply-subject"><?php echo htmlspecialchars($reply['reply_subject']); ?></strong>
                                            <span class="reply-date">
                                                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($reply['admin_name']); ?> 
                                                • <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="reply-message">
                                            <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php elseif ($contact['status'] === 'replied'): ?>
                            <div class="mt-2">
                                <small class="text-success">
                                    <i class="fas fa-reply"></i> We have replied to your request
                                    <?php if ($contact['replied_at']): ?>
                                        on <?php echo date('M d, Y H:i', strtotime($contact['replied_at'])); ?>
                                    <?php endif; ?>
                                    . Please check your email.
                                </small>
                            </div>
                        <?php endif; ?>
                        <?php $replies_query->close(); ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="info-card empty-card">
            <div class="info-header">
                <i class="fas fa-envelope"></i>
                <div class="info-content">
                    <h4>No Contact Requests Yet</h4>
                    <p>You haven't submitted any contact requests yet.</p>
                    <a href="../index.php#contact" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Submit a Contact Request
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

        <!-- Notifications Section -->
        <?php if ($notifications->num_rows > 0): ?>
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-bell"></i>
                <h4 style="margin: 0;">Recent Notifications</h4>
            </div>
            <div class="section-body" style="padding: 0;">
                <?php while($notification = $notifications->fetch_assoc()): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-icon <?php echo $notification['type'] === 'contact_replied' ? 'replied' : 'read'; ?>">
                            <i class="fas <?php echo $notification['type'] === 'contact_replied' ? 'fa-reply' : 'fa-eye'; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h6 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h6>
                            <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-time">
                                <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" class="d-inline ml-2">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit" name="mark_notification_read" class="btn-mark-read">
                                            Mark as read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

  </div>
</div>

<?php
$contacts_stmt->close();
$notifications_stmt->close();
$unread_stmt->close();
$conn->close();
?>

<?php include '../templates/footer.php'; ?>

