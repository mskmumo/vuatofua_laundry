<?php
require_once 'config.php';
require_once 'functions.php';

// Start secure session
secure_session_start();

$error = $success = "";
$token = isset($_GET['token']) ? sanitize_input($_GET['token']) : '';
$valid_token = false;
$user_id = null;

// Verify token validity
if (!empty($token)) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND account_locked = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $valid_token = true;
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
    }
    $stmt->close();
    $conn->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
        error_log("CSRF token validation failed for password reset IP: " . $_SERVER['REMOTE_ADDR']);
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($password) || empty($confirm_password)) {
            $error = "Both password fields are required.";
        } else if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else if (!validate_password($password)) {
            $error = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
        } else {
            $conn = db_connect();
            
            // Hash the new password
            $hashed_password = hash_password($password);
            
            // Update password and clear reset token
            $stmt = $conn->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL, last_password_change = NOW() WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Your password has been successfully reset. You can now login with your new password.";
                
                // Log the password change
                error_log("Password successfully reset for user ID: $user_id, IP: " . $_SERVER['REMOTE_ADDR']);
            } else {
                $error = "Unable to reset password. Please try again later.";
                error_log("Failed to reset password for user ID: $user_id");
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}

$page_title = 'Reset Password - VuaToFua';
include 'templates/header.php';
?>
<main>
    <div class="container" id="reset-password-container">
        <div class="form-container">
            <h2>Reset Your Password</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($valid_token && empty($success)): ?>
                <p class="form-description">Enter your new password below.</p>
                
                <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="password-input-group">
                            <input type="password" name="password" id="password" class="form-control" 
                                   required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text">Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                                   required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php elseif (!$valid_token && empty($success)): ?>
                <div class="alert alert-danger">
                    Invalid or expired password reset link. Please request a new password reset.
                </div>
            <?php endif; ?>
            
            <div class="form-links">
                <a href="login.php">Back to Login</a>
                <?php if (!$valid_token && empty($success)): ?>
                    <span class="separator">|</span>
                    <a href="forgot-password.php">Request New Reset Link</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const toggleButtons = document.querySelectorAll('.btn-toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const type = input.getAttribute('type');
            input.setAttribute('type', type === 'password' ? 'text' : 'password');
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
    
    // Password match validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.querySelector('#password');
            const confirmPassword = document.querySelector('#confirm_password');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>
