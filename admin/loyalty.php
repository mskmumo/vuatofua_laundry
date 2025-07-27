<?php
require_once '../config.php';
require_once '../functions.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

// Set page title
$page_title = 'Loyalty Stats';
$is_admin_page = true;

// Fetch loyalty stats
$loyalty_data = [];
$conn = db_connect();

$sql = "SELECT 
            u.user_id, 
            u.name, 
            u.email, 
            COALESCE((SELECT SUM(points_earned) FROM loyalty_points WHERE user_id = u.user_id), 0) as total_earned, 
            COALESCE((SELECT SUM(points_redeemed) FROM loyalty_points WHERE user_id = u.user_id), 0) as total_redeemed
        FROM users u
        WHERE u.role = 'customer'
        GROUP BY u.user_id
        ORDER BY total_earned DESC";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['balance'] = $row['total_earned'] - $row['total_redeemed'];
        $loyalty_data[] = $row;
    }
    mysqli_free_result($result);
}

$conn->close();

// Include header
require_once '../templates/header.php';
?>
</head>
<body>
    <header id="main-header">
        <div class="container">
            <div id="branding">
                <h1><a href="index.php">VuaToFua Admin</a></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container" id="loyalty-stats-container">
        <h2>Customer Loyalty Statistics</h2>
        <table class="data-table" id="loyalty-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Points Earned</th>
                    <th>Points Redeemed</th>
                    <th>Current Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loyalty_data as $data): ?>
                    <tr>
                        <td><?php echo $data['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($data['name']); ?></td>
                        <td><?php echo htmlspecialchars($data['email']); ?></td>
                        <td><?php echo (int)$data['total_earned']; ?></td>
                        <td><?php echo (int)$data['total_redeemed']; ?></td>
                        <td><strong><?php echo $data['balance']; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
