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
/* VuaToFua Project Colors - Dark Theme */
:root {
    --dark-bg: #131C21;
    --title-color: #E5D1B8;
    --text-color: #FFFFFF;
    --accent-color: #C4A484;
    --dark-accent: #1a252b;
    --light-accent: rgba(196, 164, 132, 0.1);
    --border-color: rgba(196, 164, 132, 0.2);
    --success-green: #28a745;
    --warning-yellow: #ffc107;
    --danger-red: #dc3545;
}

/* Global Animations */
* {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Enhanced Container */
.container {
    max-width: 1600px;
    padding: 20px;
    width: 95%;
    animation: fadeInUp 0.6s ease-out;
}

/* VuaToFua Card Design */
.card {
    border: none;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    border-radius: 10px;
    margin-bottom: 2rem;
    overflow: hidden;
    width: 100%;
    background: var(--dark-accent);
    animation: scaleIn 0.5s ease-out;
    position: relative;
    border: 1px solid var(--border-color);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-color) 0%, var(--title-color) 100%);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-color);
}

/* VuaToFua Header */
.card-header {
    background: linear-gradient(135deg, var(--dark-bg) 0%, var(--dark-accent) 100%);
    color: var(--title-color);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    border-radius: 10px 10px 0 0;
    position: relative;
    overflow: hidden;
}

.card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(196, 164, 132, 0.1) 50%, transparent 70%);
    transform: rotate(45deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) rotate(45deg); }
    100% { transform: translateX(100%) rotate(45deg); }
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--title-color);
}

.card-header h5 i {
    font-size: 1.5rem;
    color: var(--accent-color);
    animation: pulse 2s infinite;
}

.card-body {
    padding: 0;
    background: var(--dark-accent);
}

/* Enhanced Table Styling with Animations */
.table-responsive {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    margin: 0;
    animation: slideInRight 0.7s ease-out;
}

.table {
    margin: 0;
    background: white;
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    font-size: 0.95rem;
}

.table thead th {
    background: linear-gradient(135deg, var(--dark-bg) 0%, var(--dark-accent) 100%);
    color: var(--title-color);
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid var(--border-color);
}

.table thead th::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}

.table thead th:hover::before {
    left: 100%;
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
    border-bottom: 1px solid #e5e7eb;
    background: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInUp 0.5s ease-out;
    animation-fill-mode: both;
}

.table tbody tr:nth-child(1) { animation-delay: 0.1s; }
.table tbody tr:nth-child(2) { animation-delay: 0.2s; }
.table tbody tr:nth-child(3) { animation-delay: 0.3s; }
.table tbody tr:nth-child(4) { animation-delay: 0.4s; }
.table tbody tr:nth-child(5) { animation-delay: 0.5s; }

.table tbody tr:hover {
    background: linear-gradient(135deg, var(--light-accent) 0%, rgba(196, 164, 132, 0.15) 100%);
    transform: translateX(4px) scale(1.005);
    box-shadow: 0 4px 16px rgba(196, 164, 132, 0.2);
    border-left: 4px solid var(--accent-color);
}

.table td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-top: 1px solid var(--border-color);
    color: var(--text-color);
    font-weight: 500;
    position: relative;
    background: var(--dark-accent);
}

/* User ID styling */
.user-id {
    color: var(--muted-text);
    font-size: 0.85rem;
    font-weight: 500;
}

/* Enhanced Badge Styling with Animations */
.badge {
    font-size: 0.75rem;
    padding: 0.6rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.badge::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.badge:hover::before {
    left: 100%;
}

.badge:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.badge-success {
    background: linear-gradient(135deg, var(--success-green) 0%, #059669 100%);
    color: white;
}

.badge-warning {
    background: linear-gradient(135deg, var(--warning-yellow) 0%, #d97706 100%);
    color: white;
}

.badge-danger {
    background: linear-gradient(135deg, var(--danger-red) 0%, #dc2626 100%);
    color: white;
}

.badge-secondary {
    background: linear-gradient(135deg, var(--muted-text) 0%, #6b7280 100%);
    color: white;
}

.badge-primary {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
    color: white;
}

/* Enhanced Action Buttons with Animations */
.action-buttons {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    min-width: 280px;
    max-width: 320px;
    animation: slideInRight 0.6s ease-out;
}

/* Modern Button Styling with Micro-interactions */
.btn {
    border-radius: 12px;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    font-size: 0.875rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s, height 0.3s;
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.btn:active {
    transform: translateY(0) scale(0.98);
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-color) 0%, #b8956f 100%);
    color: var(--dark-bg);
    font-weight: 600;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #b8956f 0%, var(--accent-color) 100%);
    color: var(--dark-bg);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-green) 0%, #1e7e34 100%);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #1e7e34 0%, var(--success-green) 100%);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning-yellow) 0%, #e0a800 100%);
    color: var(--dark-bg);
    font-weight: 600;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e0a800 0%, var(--warning-yellow) 100%);
    color: var(--dark-bg);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-red) 0%, #c82333 100%);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c82333 0%, var(--danger-red) 100%);
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

/* Enhanced Modal Styling with Animations */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal.show {
    display: block;
}

.modal-dialog {
    margin: 1.75rem auto;
    max-width: 650px;
    width: 90%;
    position: relative;
    pointer-events: auto;
    animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
    color: var(--light-text);
    border-bottom: none;
    padding: 2rem 2.5rem;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}

.modal-header:hover::before {
    left: 100%;
}

.modal-title {
    font-weight: 700;
    font-size: 1.5rem;
}

.modal.fade.show {
    opacity: 1;
}

/* Enhanced Form Styling with Animations */
.form-group {
    margin-bottom: 1.75rem;
    animation: fadeInUp 0.5s ease-out;
}

.form-label {
    font-weight: 700;
    color: var(--dark-bg);
    margin-bottom: 0.75rem;
    display: block;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: var(--dark-bg);
    color: var(--light-text);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1), 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.form-control:hover {
    border-color: var(--secondary-blue);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.form-select {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.form-select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1), 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.form-select:hover {
    border-color: var(--secondary-blue);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

/* Loading Animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

/* Hover Effects for Table Rows */
.table tbody tr {
    cursor: pointer;
}

.table tbody tr:hover .contact-email {
    color: var(--accent-orange);
}

.table tbody tr:hover .badge {
    transform: scale(1.05);
}

/* Enhanced Close Button */
.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: rgba(255,255,255,0.8);
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0.5rem;
    border-radius: 50%;
}

.btn-close:hover {
    color: white;
    background: rgba(255,255,255,0.1);
    transform: scale(1.1);
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
    
    /* Reduce animations on mobile for performance */
    .card:hover {
        transform: none;
    }
    
    .table tbody tr:hover {
        transform: none;
    }
    
    .btn:hover {
        transform: none;
    }
    
    .badge:hover {
        transform: none;
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
    
    
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-users-cog"></i> User Management</h5>
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
                            <td>
                                <div class="contact-info">
                                    <div class="contact-email">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div class="contact-phone">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?>
                                    </div>
                                </div>
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
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-primary btn-sm edit-btn" 
                                            data-id="<?php echo $user['user_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                            data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                            data-status="<?php echo htmlspecialchars($user['status']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <?php if ($user['account_locked']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-unlock"></i> Unlock
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="lock">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <i class="fas fa-lock"></i> Lock
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-danger btn-sm delete-btn" 
                                            data-id="<?php echo $user['user_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($user['name']); ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
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
                <div class="modal-header" style="background: linear-gradient(135deg, var(--accent-color) 0%, #b8956f 100%); color: var(--dark-bg); border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fas fa-user-edit"></i> Edit User Details
                    </h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" style="background: var(--dark-accent); color: var(--text-color); padding: 2.5rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-name" class="form-label" style="color: var(--title-color); font-weight: 600; margin-bottom: 0.75rem; display: block;">
                                    <i class="fas fa-user" style="color: var(--accent-color); margin-right: 0.5rem;"></i> Full Name
                                </label>
                                <input type="text" name="name" id="edit-name" class="form-control" required 
                                       placeholder="Enter full name" style="background: var(--dark-bg); color: var(--text-color); border: 2px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-email" class="form-label" style="color: var(--title-color); font-weight: 600; margin-bottom: 0.75rem; display: block;">
                                    <i class="fas fa-envelope" style="color: var(--accent-color); margin-right: 0.5rem;"></i> Email Address
                                </label>
                                <input type="email" name="email" id="edit-email" class="form-control" required 
                                       placeholder="Enter email address" style="background: var(--dark-bg); color: var(--text-color); border: 2px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-phone" class="form-label" style="color: var(--title-color); font-weight: 600; margin-bottom: 0.75rem; display: block;">
                                    <i class="fas fa-phone" style="color: var(--accent-color); margin-right: 0.5rem;"></i> Phone Number
                                </label>
                                <input type="text" name="phone" id="edit-phone" class="form-control" required 
                                       placeholder="Enter phone number" style="background: var(--dark-bg); color: var(--text-color); border: 2px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-role" class="form-label" style="color: var(--title-color); font-weight: 600; margin-bottom: 0.75rem; display: block;">
                                    <i class="fas fa-user-tag" style="color: var(--accent-color); margin-right: 0.5rem;"></i> User Role
                                </label>
                                <select name="role" id="edit-role" class="form-control" style="background: var(--dark-bg); color: var(--text-color); border: 2px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem;">
                                    <option value="customer" style="background: var(--dark-bg); color: var(--text-color);">👤 Customer</option>
                                    <option value="admin" style="background: var(--dark-bg); color: var(--text-color);">👑 Administrator</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-status" class="form-label" style="color: var(--title-color); font-weight: 600; margin-bottom: 0.75rem; display: block;">
                            <i class="fas fa-toggle-on" style="color: var(--accent-color); margin-right: 0.5rem;"></i> Account Status
                        </label>
                        <select name="status" id="edit-status" class="form-control" style="background: var(--dark-bg); color: var(--text-color); border: 2px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem;">
                            <option value="active" style="background: var(--dark-bg); color: var(--text-color);">✅ Active</option>
                            <option value="inactive" style="background: var(--dark-bg); color: var(--text-color);">⏸️ Inactive</option>
                            <option value="suspended" style="background: var(--dark-bg); color: var(--text-color);">🚫 Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="background: linear-gradient(145deg, var(--dark-bg) 0%, var(--dark-accent) 100%); border-top: 1px solid var(--border-color); padding: 1.5rem 2.5rem;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; margin-right: 1rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, var(--accent-color) 0%, #b8956f 100%); color: var(--dark-bg); font-weight: 600;">
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
                <div class="modal-header" style="background: linear-gradient(135deg, var(--danger-red) 0%, #c82333 100%); color: var(--text-color); border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="deleteUserModalLabel">
                        <i class="fas fa-user-times"></i> Delete User Account
                    </h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" style="background: var(--dark-accent); color: var(--text-color); padding: 2.5rem;">
                    <div class="text-center">
                        <div class="warning-icon" style="background: rgba(220, 53, 69, 0.1); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; color: var(--danger-red);"></i>
                        </div>
                        <h4 style="margin-bottom: 1rem; color: var(--title-color); font-weight: 600;">Confirm User Deletion</h4>
                        <div class="alert alert-danger" style="background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); border-left: 5px solid var(--danger-red); color: #991b1b; margin-bottom: 1.5rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                        <p style="margin-bottom: 1rem; color: var(--text-color);">Are you sure you want to permanently delete the user account for:</p>
                        <div class="user-delete-info" style="background: var(--light-accent); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin: 1rem 0;">
                            <i class="fas fa-user" style="color: var(--accent-color); margin-right: 0.5rem;"></i>
                            <strong id="delete-user-name" style="color: var(--title-color);"></strong>
                        </div>
                        <p class="text-muted" style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                            <i class="fas fa-info-circle" style="color: var(--accent-color); margin-right: 0.5rem;"></i>
                            All user data, orders, and history will be permanently removed.
                        </p>
                    </div>
                </div>
                <div class="modal-footer" style="background: linear-gradient(145deg, var(--dark-bg) 0%, var(--dark-accent) 100%); border-top: 1px solid var(--border-color); padding: 1.5rem 2.5rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete-user-id">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; margin-right: 1rem;">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" style="background: linear-gradient(135deg, var(--danger-red) 0%, #c82333 100%); color: white; font-weight: 600;">
                        <i class="fas fa-trash-alt"></i> Delete Permanently
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
            // Get data from individual data attributes
            const userId = $(this).data('id');
            const userName = $(this).data('name');
            const userEmail = $(this).data('email');
            const userPhone = $(this).data('phone');
            const userRole = $(this).data('role');
            const userStatus = $(this).data('status');
            
            console.log('Edit user data:', {
                id: userId,
                name: userName,
                email: userEmail,
                phone: userPhone,
                role: userRole,
                status: userStatus
            });
            
            if (!userId) {
                console.error('No user ID found');
                alert('Error: No user ID found');
                return;
            }
            
            // Populate form fields
            $('#edit-user-id').val(userId);
            $('#edit-name').val(userName || '');
            $('#edit-email').val(userEmail || '');
            $('#edit-phone').val(userPhone || '');
            $('#edit-role').val(userRole || 'customer');
            $('#edit-status').val(userStatus || 'active');
            
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
    console.log('Initializing vanilla JavaScript modal handlers...');
    
    // Add event listener to the document for event delegation
    document.addEventListener('click', function(e) {
        console.log('Click detected:', e.target);
        
        if (e.target.closest('.edit-btn')) {
            e.preventDefault();
            console.log('Edit button clicked (vanilla JS)');
            
            const button = e.target.closest('.edit-btn');
            const userId = button.getAttribute('data-id');
            const userName = button.getAttribute('data-name');
            const userEmail = button.getAttribute('data-email');
            const userPhone = button.getAttribute('data-phone');
            const userRole = button.getAttribute('data-role');
            const userStatus = button.getAttribute('data-status');
            
            try {
                console.log('Edit user data:', {
                    id: userId,
                    name: userName,
                    email: userEmail,
                    phone: userPhone,
                    role: userRole,
                    status: userStatus
                });
                
                if (!userId) {
                    console.error('No user ID found');
                    alert('Error: No user ID found. Please refresh the page and try again.');
                    return;
                }
                
                // Validate required fields
                if (!userName || !userEmail) {
                    console.warn('Missing user data, using defaults');
                }
                
                // Populate form fields with fallbacks
                document.getElementById('edit-user-id').value = userId;
                document.getElementById('edit-name').value = userName || '';
                document.getElementById('edit-email').value = userEmail || '';
                document.getElementById('edit-phone').value = userPhone || '';
                document.getElementById('edit-role').value = userRole || 'customer';
                document.getElementById('edit-status').value = userStatus || 'active';
                
                console.log('Form populated, showing modal...');
                
                // Show modal (vanilla JS)
                const modal = document.getElementById('editUserModal');
                if (modal) {
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    createBackdrop();
                } else {
                    console.error('Edit modal not found');
                    alert('Error: Edit form not available');
                }
                
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
                console.log('Delete user:', { id: userId, name: userName });
                
                if (!userId) {
                    console.error('No user ID found');
                    alert('Error: No user ID found. Please refresh the page and try again.');
                    return;
                }
                
                // Populate form fields
                document.getElementById('delete-user-id').value = userId;
                document.getElementById('delete-user-name').textContent = userName || 'Unknown User';
                
                console.log('Delete form populated, showing modal...');
                
                // Show modal (vanilla JS)
                const modal = document.getElementById('deleteUserModal');
                if (modal) {
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    createBackdrop();
                } else {
                    console.error('Delete modal not found');
                    alert('Error: Delete confirmation not available');
                }
                
            } catch (error) {
                console.error('Error opening delete modal:', error);
                alert('Error opening delete confirmation: ' + error.message);
            }
        }
        
        // Close modal when clicking close button or backdrop
        if (e.target.matches('[data-dismiss="modal"]') || e.target.classList.contains('modal-backdrop') || e.target.closest('.close')) {
            closeAllModals();
        }
    });
    
    // Add keyboard support for closing modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
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
