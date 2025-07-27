<?php
require_once 'config.php';
require_once 'functions.php';

// Start secure session
secure_session_start();

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
        error_log("CSRF token validation failed for password reset IP: " . $_SERVER['REMOTE_ADDR']);
    } else {
        $email = sanitize_input($_POST['email']);

        if (empty($email)) {
            $error = "Email address is required.";
        } else if (!validate_email($email)) {
            $error = "Invalid email format.";
        } else {
            $conn = db_connect();
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE user_id = ?");
                $update_stmt->bind_param("ssi", $token, $expires, $user['user_id']);
                $update_stmt->execute();
                
                // Send password reset email
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
                $to = $email;
                $subject = "Reset Your VuaToFua Password";
                $message = "Dear " . $user['name'] . ",\n\n";
                $message .= "We received a request to reset your VuaToFua account password. ";
                $message .= "Click the link below to reset your password:\n\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you didn't request this, please ignore this email.\n\n";
                $message .= "Best regards,\nVuaToFua Team";
                
                if (send_password_reset_email($to, $user['name'], $reset_link)) {
                    $success = "Password reset instructions have been sent to your email.";
                } else {
                    $error = "Unable to send reset email. Please try again later.";
                    error_log("Failed to send password reset email to: $email");
                }
                
                $update_stmt->close();
            } else {
                // Don't reveal whether the email exists
                $success = "If the email exists in our system, you will receive password reset instructions.";
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}

$page_title = 'Forgot Password - VuaToFua';
include 'templates/header.php';
?>
<main>
    <main class="main-content">
<div class="container" id="forgot-password-container">
        <div class="form-container">
            <h2>Reset Your Password</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <p class="form-description">Enter your email address and we'll send you instructions to reset your password.</p>
            
            <form action="forgot-password.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" 
        </div>
    </div>
</main>

<?php require_once 'templates/footer.php'; ?>
