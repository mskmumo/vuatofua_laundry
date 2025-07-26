<?php
require_once 'config.php';
require_once 'functions.php';

if (!is_logged_in() || has_role('admin')) {
    redirect('login.php');
}

// Establish database connection
$conn = db_connect();

$user_id = $_SESSION['user_id'];
$orders = [];

$sql = "SELECT o.order_id, o.service_type, o.order_status, o.created_at, d.name as drop_off_location FROM orders o JOIN drop_off_locations d ON o.drop_off_id = d.location_id WHERE o.user_id = ? ORDER BY o.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

$conn->close();

$page_title = 'My Orders - VuaToFua';
require_once 'templates/header.php';
?>

    <div class="container" id="view-orders-container">
        <h2>My Orders</h2>
        <?php if (empty($orders)): ?>
            <p>You have not placed any orders yet. <a href="order_form.php">Place one now!</a></p>
        <?php else: ?>
            <table class="data-table" id="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Service Type</th>
                        <th>Drop-off Location</th>
                        <th>Status</th>
                        <th>Date Placed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['order_id']; ?></td>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['service_type']))); ?></td>
                            <td><?php echo htmlspecialchars($order['drop_off_location']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php require_once 'templates/footer.php'; ?>
