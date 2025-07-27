<?php
require_once '../config.php';
require_once '../functions.php';

// Establish database connection
$conn = db_connect();

if (!is_logged_in() || has_role('admin')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'New password must be at least 8 characters long.';
    } else {
        // Verify current password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Password changed successfully!';
                // Clear form fields
                $_POST = [];
            } else {
                $error_message = 'Failed to update password. Please try again.';
            }
            $update_stmt->close();
        } else {
            $error_message = 'Current password is incorrect.';
        }
    }
}

$page_title = 'Change Password - VuaToFua';
include 'header.php';
?>

<div class="password-container-wrapper">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <h1>Change Password</h1>
            <p>Update your account password for security</p>
            <a href="profile.php" class="btn btn-outline">Back to Profile</a>
        </div>
    </div>
    
    <!-- Form Section -->
    <div class="form-section">
    
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
        <form method="POST" action="" class="password-form">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
                <small class="form-hint">Minimum 8 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <div class="form-actions">
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Override main CSS flex properties */
.main-content {
    display: block !important;
    flex-direction: unset !important;
}

/* Password Container Layout */
.password-container-wrapper {
    width: 90%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 0;
    display: block;
}

/* Hero Section */
.hero-section {
    margin-bottom: 3rem;
    padding: 2rem 2rem 1.5rem 2rem;
    text-align: center;
    background: linear-gradient(135deg, var(--dark-accent), var(--dark-bg));
    border-radius: 10px;
    border: 1px solid var(--border-color);
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--accent-color), transparent);
    opacity: 0.8;
}

.hero-content {
    max-width: 600px;
    margin: 0 auto;
}

.hero-section h1 {
    color: var(--title-color);
    font-size: 2rem;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
    letter-spacing: -0.5px;
}

.hero-section p {
    color: var(--text-color);
    font-size: 1rem;
    margin-bottom: 1.5rem;
    opacity: 0.9;
    line-height: 1.5;
}

/* Form Section */
.form-section {
    margin-bottom: 3rem;
    display: flex;
    justify-content: center;
    width: 100%;
}

/* Password Form */
.password-form {
    background: var(--dark-accent);
    padding: 2.5rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    position: relative;
    width: 100%;
    max-width: 600px;
}

/* Form Elements */
.password-form {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}

.form-group {
    margin-bottom: 1.2rem;
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-color);
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-color);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
}

.form-group input:focus {
    outline: none;
    border-color: var(--accent-color);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(196, 164, 132, 0.1);
}

/* Style for autofill */
.form-group input:-webkit-autofill,
.form-group input:-webkit-autofill:hover,
.form-group input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--text-color);
    -webkit-box-shadow: 0 0 0px 1000px var(--dark-bg) inset;
    transition: background-color 5000s ease-in-out 0s;
}

.form-hint {
    color: var(--text-color);
    font-size: 0.85rem;
    margin-top: 5px;
    opacity: 0.7;
}

.form-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Buttons */
.btn {
    display: inline-block;
    background: transparent;
    color: var(--text-color);
    padding: 12px 30px;
    border: 2px solid var(--accent-color);
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn:hover {
    background: var(--accent-color);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(196, 164, 132, 0.3);
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--accent-color);
    color: var(--accent-color);
}

.btn-outline:hover {
    background: var(--accent-color);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(196, 164, 132, 0.3);
}

/* Alerts */
.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    border: 1px solid;
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border-color: rgba(40, 167, 69, 0.2);
}

.alert-error {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border-color: rgba(220, 53, 69, 0.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .password-container-wrapper {
        width: 95%;
        padding: 10px 0;
    }
    
    .hero-section {
        padding: 1.5rem 1rem;
        margin-bottom: 2rem;
    }
    
    .hero-section h1 {
        font-size: 1.8rem;
    }
    
    .hero-section p {
        font-size: 0.9rem;
        margin-bottom: 1.25rem;
    }
    
    .form-section {
        margin-bottom: 2rem;
        padding: 0;
    }
    
    .password-form {
        padding: 2rem 1.5rem;
        margin: 0 1rem;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .btn {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .hero-section h1 {
        font-size: 1.6rem;
    }
    
    .password-form {
        margin: 0 0.5rem;
        padding: 1.5rem 1rem;
    }
}
</style>

<?php include '../templates/footer.php'; ?>
