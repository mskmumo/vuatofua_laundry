<?php
require_once 'config.php';
require_once 'functions.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$is_admin = has_role('admin');

$loyalty_points = 0;
if (!$is_admin) {
    $conn = db_connect();
    $loyalty_points = get_loyalty_points($conn, $user_id);
    $conn->close();
}

$page_title = 'Dashboard - VuaToFua';
require_once 'templates/header.php';
?>

    <div class="container" id="dashboard-container">
        <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <h3>Your Dashboard</h3>
        <div class="dashboard-grid">
            <?php if ($is_admin): ?>
                <div class="card" id="admin-actions-card">
                    <h3>Admin Panel</h3>
                    <p>Manage the system.</p>
                    <a href="admin/manage_orders.php" class="btn">Manage Orders</a>
                    <a href="admin/dropoffs.php" class="btn">Manage Drop-offs</a>
                    <a href="admin/loyalty.php" class="btn">Go to Loyalty Stats</a>
                </div>
                <div class="card">
                    <h3>View Messages</h3>
                    <p>Read messages submitted through the contact form.</p>
                    <a href="admin/view_messages.php" class="btn">View Messages</a>
                </div>
            <?php else: ?>
                <div class="card" id="customer-orders-card">
                    <h3>My Orders</h3>
                    <p>Place a new order or view your existing ones.</p>
                    <a href="order_form.php" class="btn">Place New Order</a>
                    <a href="view_orders.php" class="btn">View My Orders</a>
                </div>
                <div class="card" id="customer-loyalty-card">
                    <h3>Loyalty Points</h3>
                    <p>Your current balance is: <strong><?php echo $loyalty_points; ?></strong> points.</p>
                    <a href="redeem_points.php" class="btn">Redeem Points</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once 'templates/footer.php'; ?>
