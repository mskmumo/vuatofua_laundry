<?php
require_once 'config.php';
require_once 'functions.php';

// Establish database connection
$conn = db_connect();

if (!is_logged_in() || has_role('admin')) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$service_type = $drop_off_id = "";
$errors = [];
$success_message = "";

// Fetch drop-off locations
$locations = [];
$result = $conn->query("SELECT location_id, name FROM drop_off_locations ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    $result->free();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $service_type = sanitize_input($_POST['service_type']);
    $drop_off_id = (int)sanitize_input($_POST['drop_off_id']);

    if (empty($service_type)) {
        $errors['service_type'] = "Please select a service type.";
    }
    if (empty($drop_off_id)) {
        $errors['drop_off_id'] = "Please select a drop-off location.";
    }

    if (empty($errors)) {
        $sql_insert = "INSERT INTO orders (user_id, service_type, drop_off_id, order_status) VALUES (?, ?, ?, 'pending')";
        if ($stmt = $conn->prepare($sql_insert)) {
            $stmt->bind_param("isi", $user_id, $service_type, $drop_off_id);
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;
                // Add loyalty points (e.g., 10 points per order)
                $points_to_add = 10;
                $sql_points = "INSERT INTO loyalty_points (user_id, points_earned, order_id) VALUES (?, ?, ?)";
                if ($stmt_points = $conn->prepare($sql_points)) {
                    $stmt_points->bind_param("iii", $user_id, $points_to_add, $order_id);
                    $stmt_points->execute();
                    $stmt_points->close();
                }
                // Simulate SMS
                $user_phone = ''; // Fetch if needed, for now use placeholder
                log_sms($conn, $user_id, $user_phone, "Your order #{$order_id} has been placed successfully.");
                $success_message = "Your order has been placed successfully! You've earned {$points_to_add} loyalty points.";
            } else {
                $errors['form'] = "Failed to place order. Please try again.";
            }
            $stmt->close();
        }
    }
}

$page_title = 'Place Order - VuaToFua';
require_once 'templates/header.php';
?>

    <div class="container" id="order-form-container">
        <div class="form-container">
            <h2>Place a New Laundry Order</h2>
            <?php if (!empty($errors['form'])): ?>
                <div class="alert alert-danger"><?php echo $errors['form']; ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <form action="order_form.php" method="post">
                <div class="form-group">
                    <label for="service_type">Service Type</label>
                    <select name="service_type" id="service_type" required>
                        <option value="">-- Select a Service --</option>
                        <option value="wash_dry_fold" <?php echo ($service_type == 'wash_dry_fold') ? 'selected' : ''; ?>>Wash, Dry & Fold</option>
                        <option value="dry_clean" <?php echo ($service_type == 'dry_clean') ? 'selected' : ''; ?>>Dry Cleaning</option>
                        <option value="ironing" <?php echo ($service_type == 'ironing') ? 'selected' : ''; ?>>Ironing Only</option>
                    </select>
                    <?php if (!empty($errors['service_type'])): ?><small class="error-text"><?php echo $errors['service_type']; ?></small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="drop_off_id">Drop-off Location</label>
                    <select name="drop_off_id" id="drop_off_id" required>
                        <option value="">-- Select a Location --</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo $loc['location_id']; ?>" <?php echo ($drop_off_id == $loc['location_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['drop_off_id'])): ?><small class="error-text"><?php echo $errors['drop_off_id']; ?></small><?php endif; ?>
                </div>
                <button type="submit" class="btn">Place Order</button>
            </form>
        </div>
    </div>
<?php 
$conn->close();
require_once 'templates/footer.php'; ?>
