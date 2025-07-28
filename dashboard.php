<?php
require_once 'config.php';
require_once 'functions.php';

// Start secure session and check login
secure_session_start();

// Redirect to login if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Redirect admin users to admin dashboard
if (has_role('admin')) {
    redirect('admin/index.php');
}

// Database connection
$conn = db_connect();
$user_id = $_SESSION['user_id'];

// Get user's orders with service details
$orders = [];
$order_query = "SELECT 
                    o.order_id,
                    o.service_type,
                    o.order_status as status,
                    o.created_at,
                    d.name as dropoff_location
                FROM orders o 
                LEFT JOIN drop_off_locations d ON o.drop_off_id = d.location_id
                WHERE o.user_id = ? 
                ORDER BY o.created_at DESC 
                LIMIT 5";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Ensure all expected fields have values
    $order = [
        'order_id' => $row['order_id'] ?? 0,
        'service_type' => $row['service_type'] ?? 'N/A',
        'status' => $row['status'] ?? 'pending',
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'dropoff_location' => $row['dropoff_location'] ?? 'N/A'
    ];
    $orders[] = $order;
}
$stmt->close();

// Get user's recent contact requests
$recent_contacts = [];
$contact_query = "SELECT * FROM contact_requests 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 3";
$stmt = $conn->prepare($contact_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$contacts_result = $stmt->get_result();
while ($row = $contacts_result->fetch_assoc()) {
    $recent_contacts[] = $row;
}
$stmt->close();

// Get order status counts
$status_counts = [
    'pending' => 0,
    'processing' => 0,
    'ready' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$status_query = "SELECT order_status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY order_status";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$status_result = $stmt->get_result();
while ($row = $status_result->fetch_assoc()) {
    $status = strtolower($row['order_status']);
    if (array_key_exists($status, $status_counts)) {
        $status_counts[$status] = (int)$row['count'];
    }
}
$stmt->close();
$conn->close();

// Set page title
$page_title = 'My Dashboard - VuaToFua';

// Include customer header
require_once 'customer/header.php';

// Get user's name for greeting
$user_name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Customer';
?>

<div class="dashboard-container-wrapper">
    <div class="dashboard-header">
        <h1>Welcome back, <?php echo $user_name; ?>!</h1>
        <a href="order_form.php" class="btn btn-primary">+ Place New Order</a>
    </div>
    
    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card">
            <h3>Pending</h3>
            <p class="stat-number"><?php echo $status_counts['pending']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Processing</h3>
            <p class="stat-number"><?php echo $status_counts['processing']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Ready for Pickup</h3>
            <p class="stat-number"><?php echo $status_counts['ready']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Completed</h3>
            <p class="stat-number"><?php echo $status_counts['completed']; ?></p>
        </div>
    </div>
    
    <!-- Recent Orders Table -->
    <div class="card full-width">
            <div class="card-header">
                <h3>Recent Orders</h3>
                <a href="customer/orders.php" class="btn btn-text">View All</a>
            </div>
            <?php if (!empty($orders)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td>
                                        <div class="order-date"><?php echo !empty($order['created_at']) ? date('M d, Y', strtotime($order['created_at'])) : 'N/A'; ?></div>
                                        <small class="text-muted"><?php echo !empty($order['service_type']) ? ucfirst($order['service_type']) : 'N/A'; ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['dropoff_location'])): ?>
                                            <div><?php echo htmlspecialchars($order['dropoff_location']); ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Reference: #<?php echo $order['order_id'] ?? 'N/A'; ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo !empty($order['status']) ? strtolower($order['status']) : 'pending'; ?>">
                                            <?php echo !empty($order['status']) ? ucfirst($order['status']) : 'Pending'; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a href="view_orders.php?id=<?php echo $order['order_id'] ?? ''; ?>" class="btn btn-small">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>You haven't placed any orders yet.</p>
                    <a href="new-order.php" class="btn btn-primary">Place Your First Order</a>
                </div>
            <?php endif; ?>
    </div>

    <!-- Dashboard Cards -->
    <div class="dashboard-grid">
        <!-- Quick Actions -->
        <div class="card">
            <h3>Quick Actions</h3>
            <ul class="quick-actions">
                <li><a href="order_form.php" class="btn btn-block">+ New Order</a></li>
                <li><a href="customer/schedule-pickup.php" class="btn btn-block">Schedule Pickup</a></li>
                <li><a href="customer/manage-addresses.php" class="btn btn-block">Manage Addresses</a></li>
                <li><a href="customer/payment-methods.php" class="btn btn-block">Payment Methods</a></li>
            </ul>
        </div>
        
        <!-- My Profile Summary -->
        <div class="card">
            <div class="profile-summary">
                <div class="profile-header">
                    <h3>My Profile</h3>
                    <a href="customer/profile.php" class="btn-text">Edit</a>
                </div>
                <div class="profile-details">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['email'] ?? 'No email'); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo !empty($_SESSION['phone']) ? htmlspecialchars($_SESSION['phone']) : 'Not set'; ?></p>
                </div>
            </div>
            <div class="profile-actions">
                <a href="customer/change-password.php" class="btn btn-block">Change Password</a>
                <a href="customer/profile.php" class="btn btn-block">Edit Profile</a>
            </div>
        </div>
        
        <!-- Recent Contacts -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Contacts</h3>
                <a href="customer/my_contacts.php" class="btn-text">View All</a>
            </div>
            <div class="recent-contacts">
                <?php if (!empty($recent_contacts)): ?>
                    <ul class="contact-list">
                        <?php foreach ($recent_contacts as $contact): ?>
                            <li class="contact-item">
                                <div class="contact-header">
                                    <span class="contact-subject"><?php echo htmlspecialchars($contact['subject']); ?></span>
                                    <span class="contact-date"><?php echo date('M d, Y', strtotime($contact['created_at'])); ?></span>
                                </div>
                                <div class="contact-status status-<?php echo strtolower($contact['status']); ?>">
                                    <?php echo ucfirst($contact['status']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>You haven't made any contact requests yet.</p>
                <?php endif; ?>
                <a href="contact.php" class="btn btn-block mt-3">Contact Support</a>
            </div>
        </div>
    </div>
</div>

<style>
/* Override main CSS flex properties */
.main-content {
    display: block !important;
    flex-direction: unset !important;
}

/* Dashboard Container Layout */
.dashboard-container-wrapper {
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 0;
    display: block;
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--dark-bg) 0%, #1a252b 100%);
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--accent-color);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.dashboard-header h1 {
    color: var(--title-color);
    margin: 0;
    font-size: 2.2rem;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.dashboard-header .btn {
    background: transparent;
    color: var(--text-color);
    border: 2px solid var(--accent-color);
    padding: 12px 24px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dashboard-header .btn:hover {
    background: var(--accent-color);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(196, 164, 132, 0.4);
}

/* Quick Stats Cards */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--dark-bg) 0%, #1a252b 100%);
    padding: 2rem;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--accent-color);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-color), #E5D1B8);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(196, 164, 132, 0.3);
    border-color: #E5D1B8;
}

.stat-card h3 {
    color: var(--accent-color);
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: var(--title-color);
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* Cards */
.card {
    background: linear-gradient(135deg, var(--dark-bg) 0%, #1a252b 100%);
    border: 1px solid var(--accent-color);
    border-radius: 10px;
    padding: 0;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(196, 164, 132, 0.2);
    border-color: #E5D1B8;
}

.card-header {
    background: rgba(196, 164, 132, 0.1);
    padding: 1.5rem;
    border-bottom: 1px solid rgba(196, 164, 132, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    color: var(--title-color);
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
}

.card-header .btn-text {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.card-header .btn-text:hover {
    color: #E5D1B8;
    text-decoration: underline;
}

/* Table Styling */
.table-responsive {
    padding: 1.5rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: transparent;
}

.data-table th {
    background: rgba(196, 164, 132, 0.1);
    color: var(--accent-color);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9rem;
    border-bottom: 2px solid var(--accent-color);
}

.data-table td {
    padding: 1rem;
    color: var(--text-color);
    border-bottom: 1px solid rgba(196, 164, 132, 0.1);
    vertical-align: middle;
}

.data-table tr:hover {
    background: rgba(196, 164, 132, 0.05);
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { 
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.status-processing { 
    background: rgba(0, 123, 255, 0.2);
    color: #007bff;
    border: 1px solid rgba(0, 123, 255, 0.3);
}

.status-ready { 
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.status-completed { 
    background: rgba(108, 117, 125, 0.2);
    color: #6c757d;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.status-cancelled { 
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

/* Quick Actions */
.quick-actions {
    list-style: none;
    padding: 1.5rem;
    margin: 0;
}

.quick-actions li {
    margin-bottom: 1rem;
}

.quick-actions .btn {
    display: block;
    width: 100%;
    background: transparent;
    color: var(--text-color);
    border: 2px solid var(--accent-color);
    padding: 12px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    text-align: left;
}

.quick-actions .btn:hover {
    background: var(--accent-color);
    color: var(--dark-bg);
    transform: translateX(5px);
}

/* Profile Summary */
.profile-summary {
    padding: 1.5rem;
}

.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.profile-details p {
    margin: 0.8rem 0;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: var(--text-color);
    font-size: 0.95rem;
}

.profile-details i {
    width: 20px;
    text-align: center;
    color: var(--accent-color);
    font-size: 1.1rem;
}

/* Recent Contacts */
.recent-contacts {
    padding: 1.5rem;
}

.contact-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem 0;
}

.contact-item {
    background: rgba(196, 164, 132, 0.05);
    border: 1px solid rgba(196, 164, 132, 0.1);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.contact-item:hover {
    background: rgba(196, 164, 132, 0.1);
    border-color: var(--accent-color);
    transform: translateY(-1px);
}

.contact-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.contact-subject {
    color: var(--title-color);
    font-weight: 600;
    font-size: 0.95rem;
}

.contact-date {
    color: var(--accent-color);
    font-size: 0.8rem;
    opacity: 0.8;
}

.contact-status {
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-color);
}

.empty-state p {
    margin-bottom: 1.5rem;
    opacity: 0.8;
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
        padding: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.8rem;
    }
    
    .quick-stats {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
    }
}

@media (max-width: 480px) {
    .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .dashboard-container-wrapper {
        width: 95%;
        padding: 10px 0;
    }
}
</style>

<?php include 'templates/footer.php'; ?>
