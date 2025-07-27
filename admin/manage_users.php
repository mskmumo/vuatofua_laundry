<?php
require_once '../config.php';
require_once '../functions.php';

secure_session_start();

// Ensure user is admin
if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

$conn = db_connect();

// Handle POST requests for user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
    $action = $_POST['action'] ?? '';
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    // Prevent actions on other admin users for security
    $user_role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $user_role_stmt->bind_param("i", $user_id);
    $user_role_stmt->execute();
    $user_role_result = $user_role_stmt->get_result();
    $user_to_modify = $user_role_result->fetch_assoc();
    $user_role_stmt->close();

    if ($user_to_modify && $user_to_modify['role'] === 'admin' && $user_id !== $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'For security reasons, actions on other admin accounts are restricted.';
    } else if ($user_id > 0) {
        switch ($action) {
            case 'lock':
                $locked_until = date('Y-m-d H:i:s', strtotime("+24 hours"));
                $stmt = $conn->prepare("UPDATE users SET account_locked = 1, account_locked_until = ? WHERE user_id = ?");
                $stmt->bind_param("si", $locked_until, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Account locked successfully.';
                }
                break;
            case 'unlock':
                $stmt = $conn->prepare("UPDATE users SET account_locked = 0, account_locked_until = NULL WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Account unlocked successfully.';
                }
                break;
            case 'update_user':
                $name = sanitize_input($_POST['name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $role = in_array($_POST['role'], ['customer', 'admin']) ? $_POST['role'] : 'customer';
                $status = in_array($_POST['status'], ['active', 'inactive', 'suspended', 'verified']) ? $_POST['status'] : 'active';

                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $role, $status, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'User updated successfully.';
                }
                break;
            case 'delete_user':
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'User deleted successfully.';
                }
                break;
        }
        if (isset($stmt)) {
            $stmt->close();
        }
    }
    redirect('manage_users.php');
}

// Fetch all users for display
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

$page_title = 'Manage Users';
$is_admin_page = true;
require_once '../templates/header.php';
?>

<style>
/* Enhanced styling for better visibility and structure */
.container {
    max-width: 1600px;
    padding: 20px;
    width: 95%;
}

.card {
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-radius: 12px;
    margin-bottom: 2rem;
    overflow: hidden;
    width: 100%;
}

.card-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border-bottom: none;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 0;
}

/* Enhanced table styling for better visibility */
.table-responsive {
    border-radius: 0 0 12px 12px;
    overflow: hidden;
    width: 100%;
}

.table {
    margin-bottom: 0;
    font-size: 0.95rem;
    width: 100%;
    table-layout: fixed;
}

.table thead th {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    color: white;
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
}

/* Specific column widths for better spacing */
.table th:nth-child(1), .table td:nth-child(1) { width: 8%; }  /* ID */
.table th:nth-child(2), .table td:nth-child(2) { width: 18%; } /* Name */
.table th:nth-child(3), .table td:nth-child(3) { width: 25%; } /* Contact */
.table th:nth-child(4), .table td:nth-child(4) { width: 12%; } /* Role */
.table th:nth-child(5), .table td:nth-child(5) { width: 12%; } /* Status */
.table th:nth-child(6), .table td:nth-child(6) { width: 12%; } /* Joined */
.table th:nth-child(7), .table td:nth-child(7) { width: 13%; } /* Actions */

.table tbody tr {
    border-bottom: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background-color: rgba(52, 73, 94, 0.08);
    transform: none;
    box-shadow: none;
}

.table td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-top: none;
    white-space: nowrap;
    color: #ffffff;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* User ID styling */
.user-id {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
}

/* Contact info styling */
.contact-info {
    min-width: 250px;
    max-width: 300px;
}

.contact-email {
    font-weight: 500;
    color: #ffffff;
    margin-bottom: 0.25rem;
    word-break: break-word;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.contact-phone {
    color: #b8c5d1;
    font-size: 0.85rem;
}

/* Badge styling */
.badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.badge-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.badge-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.badge-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

/* Action buttons styling */
.action-buttons {
    min-width: 220px;
    text-align: center;
}

.btn {
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

.btn-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    margin: 0.125rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9 0%, #1f5f8b 100%);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #229954 0%, #1e8449 100%);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
    color: white;
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #7f8c8d 0%, #6c7b7d 100%);
    color: white;
}

/* Modal enhancements */
.modal {
    display: none !important; /* Ensure modals are hidden by default */
}

.modal.show {
    display: block !important; /* Show modal when triggered */
}

.modal-dialog {
    max-width: 800px;
    width: 90%;
    margin: 1.75rem auto;
}

.modal-dialog.modal-lg {
    max-width: 900px;
    width: 95%;
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    width: 100%;
}

.modal-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
}

.modal-title {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-body {
    padding: 2rem 2.5rem;
}

.modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    padding: 1rem 2.5rem;
}

.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-backdrop.show {
    opacity: 0.5;
}

/* Ensure modal fade animation works properly */
.modal.fade {
    opacity: 0;
    transition: opacity 0.15s linear;
}

.modal.fade.show {
    opacity: 1;
}

/* Form styling */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-control {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.95rem;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Alert styling */
.alert {
    border: none;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-left-color: #28a745;
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border-left-color: #dc3545;
    color: #721c24;
}

/* Responsive design improvements */
@media (max-width: 1400px) {
    .container {
        max-width: 100%;
        padding: 15px;
        width: 98%;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-sm {
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
        margin: 0.1rem;
    }
    
    .contact-info {
        min-width: 200px;
        max-width: 250px;
    }
    
    .action-buttons {
        min-width: 200px;
    }
    
    .modal-dialog {
        max-width: 700px;
        width: 85%;
    }
    
    .modal-dialog.modal-lg {
        max-width: 800px;
        width: 90%;
    }
}

@media (max-width: 768px) {
    .container {
        width: 100%;
        padding: 10px;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.3rem 0.5rem;
        font-size: 0.7rem;
        margin: 0.05rem;
    }
    
    .btn-sm i {
        display: none;
    }
    
    .contact-info {
        min-width: 150px;
        max-width: 180px;
    }
    
    .action-buttons {
        min-width: 150px;
    }
    
    .modal-dialog {
        max-width: 95%;
        width: 95%;
        margin: 0.5rem auto;
    }
    
    .modal-dialog.modal-lg {
        max-width: 95%;
        width: 95%;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-header, .modal-footer {
        padding: 1rem 1.5rem;
    }
    
    .card-header {
        padding: 1rem;
    }
    
    /* Allow table columns to wrap on mobile */
    .table th, .table td {
        white-space: normal;
        word-wrap: break-word;
    }
}

@media (max-width: 576px) {
    .container {
        padding: 5px;
    }
    
    .table {
        font-size: 0.8rem;
        table-layout: auto;
    }
    
    .table td, .table th {
        padding: 0.5rem 0.25rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.4rem;
        font-size: 0.65rem;
    }
    
    .contact-info, .action-buttons {
        min-width: auto;
    }
    
    .modal-dialog {
        margin: 0.25rem;
        width: calc(100% - 0.5rem);
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-header, .modal-footer {
        padding: 0.75rem 1rem;
    }
}
</style>

<div class="container">
    <h2 class="mt-4 mb-4">
        <i class="fas fa-users"></i> User Management
    </h2>
    
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-table"></i> All Users
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-envelope"></i> Contact</th>
                            <th><i class="fas fa-user-tag"></i> Role</th>
                            <th><i class="fas fa-toggle-on"></i> Status</th>
                            <th><i class="fas fa-calendar"></i> Joined</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><span class="user-id">#<?php echo $user['user_id']; ?></span></td>
                            <td>
                                <strong style="color: #ffffff;"><?php echo htmlspecialchars($user['name']); ?></strong>
                            </td>
                            <td class="contact-info">
                                <div class="contact-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                <small class="contact-phone">
                                    <i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] == 'admin' ? 'warning' : 'info'; ?>">
                                    <?php echo $user['role'] == 'admin' ? '👑' : '👤'; ?> 
                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['account_locked']): ?>
                                    <span class="badge badge-danger">🔒 Locked</span>
                                <?php else: ?>
                                    <span class="badge badge-success">✓ <?php echo ucfirst(htmlspecialchars($user['status'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted" style="color: #b8c5d1;">
                                    <i class="fas fa-calendar-alt fa-xs"></i> 
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </small>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-primary edit-btn" 
                                        data-user='<?php echo json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT); ?>' 
                                        title="Edit User">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <?php if ($user['account_locked']): ?>
                                    <form action="manage_users.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Unlock User">
                                            <i class="fas fa-unlock"></i> Unlock
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form action="manage_users.php" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="lock">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Lock User">
                                            <i class="fas fa-lock"></i> Lock
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-danger delete-btn" 
                                        data-id="<?php echo $user['user_id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                        title="Delete User">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="manage_users.php" method="POST" id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fas fa-user-edit"></i> Edit User Details
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-name">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" name="name" id="edit-name" class="form-control" required 
                                       placeholder="Enter full name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-email">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" name="email" id="edit-email" class="form-control" required 
                                       placeholder="Enter email address">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-phone">
                                    <i class="fas fa-phone"></i> Phone Number
                                </label>
                                <input type="text" name="phone" id="edit-phone" class="form-control" required 
                                       placeholder="Enter phone number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-role">
                                    <i class="fas fa-user-tag"></i> User Role
                                </label>
                                <select name="role" id="edit-role" class="form-control">
                                    <option value="customer">👤 Customer</option>
                                    <option value="admin">👑 Administrator</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-status">
                            <i class="fas fa-toggle-on"></i> Account Status
                        </label>
                        <select name="status" id="edit-status" class="form-control">
                            <option value="active">✅ Active</option>
                            <option value="inactive">⏸️ Inactive</option>
                            <option value="suspended">🚫 Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="manage_users.php" method="POST" id="deleteUserForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Confirm User Deletion
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 48px; margin-bottom: 20px;"></i>
                        <h6 style="margin-bottom: 15px;">Are you sure you want to delete this user?</h6>
                        <p class="text-muted">
                            <strong id="delete-user-name"></strong><br>
                            This action cannot be undone. All user data and associated records will be permanently removed.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete-user-id">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Enhanced JavaScript for better modal handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing modal handlers...');
    
    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded! Modal functionality requires jQuery.');
        // Fallback to vanilla JavaScript if jQuery is not available
        initializeVanillaJS();
        return;
    }
    
    console.log('jQuery is available, setting up event handlers...');
    
    // Initialize modal handlers with jQuery
    initializeJQueryHandlers();
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});

function initializeJQueryHandlers() {
    // Edit User Modal
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        console.log('Edit button clicked');
        
        try {
            const user = $(this).data('user');
            console.log('Edit user data:', user);
            
            if (!user) {
                console.error('No user data found');
                alert('Error: No user data found');
                return;
            }
            
            // Populate form fields
            $('#edit-user-id').val(user.user_id);
            $('#edit-name').val(user.name);
            $('#edit-email').val(user.email);
            $('#edit-phone').val(user.phone);
            $('#edit-role').val(user.role);
            $('#edit-status').val(user.status);
            
            console.log('Form populated, showing modal...');
            
            // Show modal
            $('#editUserModal').modal('show');
            
        } catch (error) {
            console.error('Error opening edit modal:', error);
            alert('Error opening edit form: ' + error.message);
        }
    });

    // Delete User Modal
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        console.log('Delete button clicked');
        
        try {
            const userId = $(this).data('id');
            const userName = $(this).data('name');
            
            console.log('Delete user:', userId, userName);
            
            if (!userId) {
                console.error('No user ID found');
                alert('Error: No user ID found');
                return;
            }
            
            // Populate form fields
            $('#delete-user-id').val(userId);
            $('#delete-user-name').text(userName || 'Unknown User');
            
            console.log('Delete form populated, showing modal...');
            
            // Show modal
            $('#deleteUserModal').modal('show');
            
        } catch (error) {
            console.error('Error opening delete modal:', error);
            alert('Error opening delete confirmation: ' + error.message);
        }
    });
    
    // Form validation for edit form
    $('#editUserForm').on('submit', function(e) {
        console.log('Edit form submitted');
        
        const name = $('#edit-name').val().trim();
        const email = $('#edit-email').val().trim();
        const phone = $('#edit-phone').val().trim();
        
        if (!name || !email || !phone) {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address.');
            return false;
        }
        
        console.log('Form validation passed, submitting...');
    });
}

function initializeVanillaJS() {
    console.log('Initializing vanilla JavaScript handlers...');
    
    // Edit User Modal (Vanilla JS)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-btn')) {
            e.preventDefault();
            console.log('Edit button clicked (vanilla JS)');
            
            const button = e.target.closest('.edit-btn');
            const userDataStr = button.getAttribute('data-user');
            
            try {
                const user = JSON.parse(userDataStr);
                console.log('Edit user data:', user);
                
                if (!user) {
                    console.error('No user data found');
                    alert('Error: No user data found');
                    return;
                }
                
                // Populate form fields
                document.getElementById('edit-user-id').value = user.user_id;
                document.getElementById('edit-name').value = user.name;
                document.getElementById('edit-email').value = user.email;
                document.getElementById('edit-phone').value = user.phone;
                document.getElementById('edit-role').value = user.role;
                document.getElementById('edit-status').value = user.status;
                
                console.log('Form populated, showing modal...');
                
                // Show modal (vanilla JS)
                const modal = document.getElementById('editUserModal');
                modal.style.display = 'block';
                modal.classList.add('show');
                
                // Create backdrop
                createBackdrop();
                
            } catch (error) {
                console.error('Error opening edit modal:', error);
                alert('Error opening edit form: ' + error.message);
            }
        }
        
        if (e.target.closest('.delete-btn')) {
            e.preventDefault();
            console.log('Delete button clicked (vanilla JS)');
            
            const button = e.target.closest('.delete-btn');
            const userId = button.getAttribute('data-id');
            const userName = button.getAttribute('data-name');
            
            try {
                console.log('Delete user:', userId, userName);
                
                if (!userId) {
                    console.error('No user ID found');
                    alert('Error: No user ID found');
                    return;
                }
                
                // Populate form fields
                document.getElementById('delete-user-id').value = userId;
                document.getElementById('delete-user-name').textContent = userName || 'Unknown User';
                
                console.log('Delete form populated, showing modal...');
                
                // Show modal (vanilla JS)
                const modal = document.getElementById('deleteUserModal');
                modal.style.display = 'block';
                modal.classList.add('show');
                
                // Create backdrop
                createBackdrop();
                
            } catch (error) {
                console.error('Error opening delete modal:', error);
                alert('Error opening delete confirmation: ' + error.message);
            }
        }
        
        // Close modal when clicking close button or backdrop
        if (e.target.matches('[data-dismiss="modal"]') || e.target.classList.contains('modal-backdrop')) {
            closeAllModals();
        }
    });
}

function createBackdrop() {
    // Remove existing backdrop
    const existingBackdrop = document.querySelector('.modal-backdrop');
    if (existingBackdrop) {
        existingBackdrop.remove();
    }
    
    // Create new backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(backdrop);
    
    // Close modal when clicking backdrop
    backdrop.addEventListener('click', closeAllModals);
}

function closeAllModals() {
    console.log('Closing all modals...');
    
    // Close all modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
        modal.classList.remove('show');
    });
    
    // Remove backdrop
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.remove();
    }
}

// Test function to verify everything is working
function testModalFunctionality() {
    console.log('Testing modal functionality...');
    console.log('Edit buttons found:', document.querySelectorAll('.edit-btn').length);
    console.log('Delete buttons found:', document.querySelectorAll('.delete-btn').length);
    console.log('Edit modal exists:', !!document.getElementById('editUserModal'));
    console.log('Delete modal exists:', !!document.getElementById('deleteUserModal'));
}

// Run test after DOM is loaded
setTimeout(testModalFunctionality, 1000);
</script>

<?php
$conn->close();
include '../templates/footer.php';
?>
