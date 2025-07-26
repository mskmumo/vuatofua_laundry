<?php
require_once 'config.php';
require_once 'functions.php';

if (!is_logged_in() || has_role('admin')) {
    redirect('login.php');
}

// Establish database connection
$conn = db_connect();

$user_id = $_SESSION['user_id'];
$points_to_redeem = 0;
$current_points = get_loyalty_points($conn, $user_id);
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $points_to_redeem = (int)sanitize_input($_POST['points_to_redeem']);

    if ($points_to_redeem <= 0) {
        $error = "Please enter a valid number of points to redeem.";
    } elseif ($points_to_redeem > $current_points) {
        $error = "You do not have enough points to redeem that amount.";
    } else {
        // Use a transaction for safety
        $conn->begin_transaction();

        try {
            // Process redemption
            $stmt = $conn->prepare("INSERT INTO loyalty_points (user_id, points_redeemed) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $points_to_redeem);
            $stmt->execute();
            $stmt->close();

            // If all good, commit the transaction
            $conn->commit();

            $success = "You have successfully redeemed {$points_to_redeem} points! A discount code has been sent to your phone (simulated).";
            // Simulate SMS with discount code
            log_sms($conn, $user_id, '', "Your discount code for {$points_to_redeem} points is VUA-{$user_id}-".time());
            // Refresh points balance
            $current_points = get_loyalty_points($conn, $user_id);
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $error = "Failed to redeem points. Please try again.";
        }
    }
}

$conn->close();

$page_title = 'Redeem Points - VuaToFua';
require_once 'templates/header.php';
?>

    <div class="container" id="redeem-points-container">
        <div class="form-container">
            <h2>Redeem Loyalty Points</h2>
            <div class="card" id="points-balance-card">
                <h3>Your Available Balance</h3>
                <p><strong><?php echo $current_points; ?></strong> points</p>
            </div>
            
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

            <form action="redeem_points.php" method="post">
                <div class="form-group">
                    <label for="points_to_redeem">Points to Redeem</label>
                    <input type="number" name="points_to_redeem" id="points_to_redeem" min="1" max="<?php echo $current_points; ?>" required>
                </div>
                <button type="submit" class="btn" <?php echo ($current_points <= 0) ? 'disabled' : ''; ?>>Redeem Now</button>
            </form>
        </div>
    </div>
<?php require_once 'templates/footer.php'; ?>
