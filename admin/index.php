<?php
require_once '../config.php';
require_once '../functions.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

$conn = db_connect();

// Fetch statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'")->fetch_assoc()['count'];
$completed_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'completed'")->fetch_assoc()['count'];

// New monthly stats
$current_month = date('m');
$current_year = date('Y');

$new_users_this_month = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND MONTH(created_at) = $current_month AND YEAR(created_at) = $current_year")->fetch_assoc()['count'];
$orders_this_month = $conn->query("SELECT COUNT(*) as count FROM orders WHERE MONTH(created_at) = $current_month AND YEAR(created_at) = $current_year")->fetch_assoc()['count'];

// Fetch recent orders
$recent_orders_sql = "SELECT o.order_id, u.name as customer_name, o.order_status as status, o.created_at 
                      FROM orders o 
                      JOIN users u ON o.user_id = u.user_id 
                      ORDER BY o.created_at DESC 
                      LIMIT 5";
$recent_orders_result = $conn->query($recent_orders_sql);
$recent_orders = $recent_orders_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

$page_title = 'Admin Dashboard';
$is_admin_page = true;
require_once '../templates/header.php';
?>

<div class="container" id="admin-dashboard">
    <h2 class="text-center my-4">Admin Dashboard</h2>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <p class="display-4"><?php echo $total_users; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Orders</h3>
            <p class="display-4"><?php echo $total_orders; ?></p>
        </div>
        <div class="stat-card">
            <h3>Pending Orders</h3>
            <p class="display-4"><?php echo $pending_orders; ?></p>
        </div>
        <div class="stat-card">
            <h3>Completed Orders</h3>
            <p class="display-4"><?php echo $completed_orders; ?></p>
        </div>
        <div class="stat-card">
            <h3>New Users (This Month)</h3>
            <p class="display-4"><?php echo $new_users_this_month; ?></p>
        </div>
        <div class="stat-card">
            <h3>Orders (This Month)</h3>
            <p class="display-4"><?php echo $orders_this_month; ?></p>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="mt-5">
        <h3 class="mb-3">Recent Orders</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_orders)):
                        ?>
                        <tr>
                            <td colspan="4" class="text-center">No recent orders.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['status']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
