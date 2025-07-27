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

// Include header
include 'templates/header.php';

// Get user's name for greeting
$user_name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Customer';
?>

<div class="container">
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
                <li><a href="new-order.php" class="btn btn-block">+ New Order</a></li>
                <li><a href="schedule-pickup.php" class="btn btn-block">Schedule Pickup</a></li>
                <li><a href="customer/address-book.php" class="btn btn-block">Manage Addresses</a></li>
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
/* Main Layout */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

/* Full width card for orders table */
.card.full-width {
    width: 100%;
    margin-bottom: 2rem;
    overflow-x: auto;
}

/* Dashboard grid for cards */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    width: 100%;
    margin-top: 1.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .card {
        margin-bottom: 1.5rem;
    }
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    margin: 0.5rem 0 0;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #cce5ff; color: #004085; }
.status-ready { background: #d4edda; color: #155724; }
.status-completed { background: #e2e3e5; color: #383d41; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.quick-actions {
    list-style: none;
    padding: 0;
    margin: 0;
}

.quick-actions li {
    margin-bottom: 0.75rem;
}

.quick-actions .btn {
    width: 100%;
    text-align: left;
}

.profile-summary {
    margin-bottom: 1.5rem;
}

.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.profile-details p {
    margin: 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-details i {
    width: 20px;
    text-align: center;
    color: #6c757d;
}

.empty-state {
    text-align: center;
    padding: 2rem 1rem;
}

.empty-state p {
    margin-bottom: 1rem;
    color: #6c757d;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .quick-stats {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<?php include 'templates/footer.php'; ?>
