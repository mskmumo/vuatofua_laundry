<?php
require_once '../config.php';
require_once '../functions.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

$conn = db_connect();

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = sanitize_input($_POST['order_status']);
    
    $sql_update = "UPDATE orders SET order_status = ? WHERE order_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_update)) {
        mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
        if (mysqli_stmt_execute($stmt)) {
            // Fetch customer info for notification
            $customer_query = mysqli_query($conn, "SELECT u.user_id, u.email, u.name FROM users u JOIN orders o ON u.user_id = o.user_id WHERE o.order_id = $order_id");
            $customer = mysqli_fetch_assoc($customer_query);
            
            if ($customer) {
                // Send email notification
                send_order_status_email($customer['email'], $customer['name'], $order_id, $new_status);

                // Log SMS notification for the user
                $message = "Your order #$order_id status has been updated to '$new_status'.";
                log_sms($conn, $customer['user_id'], $message, $order_id);
            }

            redirect('manage_orders.php?update=success');
        } else {
            redirect('manage_orders.php?update=error');
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch all orders
$orders = [];
$sql = "SELECT o.order_id, u.name as customer_name, o.service_type, d.name as drop_off_location, o.order_status, o.created_at 
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN drop_off_locations d ON o.drop_off_id = d.location_id
        ORDER BY o.created_at DESC";

$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}
mysqli_free_result($result);
$page_title = 'Manage Orders - Admin';
$is_admin_page = true;
require_once '../templates/header.php';
?>

    <div class="container" id="manage-orders-container">
        <h2>Manage All Customer Orders</h2>
        <table class="data-table" id="all-orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Drop-off</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['service_type']))); ?></td>
                        <td><?php echo htmlspecialchars($order['drop_off_location']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                        <td>
                            <form action="manage_orders.php" method="post" style="display:flex;">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <select name="order_status" style="margin-right:5px;">
                                    <option value="pending" <?php echo ($order['order_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo ($order['order_status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo ($order['order_status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($order['order_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php require_once '../templates/footer.php'; ?>
