<?php
require_once '../config.php';
require_once '../functions.php';

// Ensure user is logged in and is an admin
if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

$conn = db_connect();

// Fetch all contact messages
$messages_result = $conn->query("SELECT m.message_id, u.name as user_name, m.subject, m.created_at 
                                FROM contact_messages m 
                                JOIN users u ON m.user_id = u.id 
                                ORDER BY m.created_at DESC");
$messages = $messages_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - View Contact Messages</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header id="main-header">
        <div class="container">
            <div id="branding">
                <h1><a href="../dashboard.php">VuaToFua | Admin Panel</a></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Contact Form Submissions</h2>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>From</th>
                        <th>Subject</th>
                        <th>Received</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No messages found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($message['message_id']); ?></td>
                                <td><?php echo htmlspecialchars($message['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></td>
                                <td>
                                    <a href="view_message_detail.php?id=<?php echo $message['message_id']; ?>" class="btn btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p style="margin-top: 20px;"><a href="../dashboard.php" class="btn">Back to Dashboard</a></p>
    </div>

    <footer id="main-footer">
        <p>Copyright &copy; 2023 VuaToFua</p>
    </footer>
</body>
</html>
