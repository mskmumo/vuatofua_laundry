<?php
require_once '../config.php';
require_once '../functions.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

$conn = db_connect();

// Handle user deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $current_user_id = $_SESSION['user_id'];

    if ($delete_id === 1) { // Prevent deleting main admin
        $_SESSION['error_message'] = "Cannot delete the main administrator.";
    } elseif ($delete_id === $current_user_id) { // Prevent self-deletion
        $_SESSION['error_message'] = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete user.";
        }
        $stmt->close();
    }
    redirect('manage_users.php');
}

// Fetch all users
$users_result = $conn->query("SELECT user_id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC");
$users = $users_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

$page_title = 'Manage Users - Admin';
$is_admin_page = true;
require_once '../templates/header.php';
?>

<div class="container" id="manage-users-container">
    <h2>Manage Customer Accounts</h2>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="data-table" id="all-users-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Registered On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php if ($user['role'] === 'customer'): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm">Edit</a>
                                <a href="manage_users.php?delete_id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
