<?php
// VuaToFua - Core Functions

// Enhanced input sanitization and validation
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email format and safety
function validate_email($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate password strength
function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    
    return strlen($password) >= 8 && $uppercase && $lowercase && $number;
}

// Secure password hashing with modern algorithm
function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// Rate limiting for login attempts
function check_login_attempts($conn, $email) {
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                           FROM login_attempts 
                           WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data['attempts'] >= 5;
}

// Log failed login attempt
function log_login_attempt($conn, $email) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, attempt_time) VALUES (?, NOW())");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
}

// Secure session management
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters before starting the session
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Strict');
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }
        
        session_start();
        
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 3600) {
            // Regenerate session ID every hour
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// Function to check if a user is logged in with CSRF protection
function is_logged_in() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
        return false;
    }
    // Validate user agent hasn't changed (prevent session hijacking)
    if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        return false;
    }
    return true;
}

// Function to check for a specific role with additional security
function has_role($role) {
    if (!is_logged_in()) {
        return false;
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to redirect to a page
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Function to log a simulated SMS
function log_sms($conn, $user_id, $phone, $message) {
    $stmt = $conn->prepare("INSERT INTO sms_logs (user_id, phone, message, status) VALUES (?, ?, ?, 'sent')");
    $stmt->bind_param("iss", $user_id, $phone, $message);
    $stmt->execute();
    $stmt->close();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/src/Exception.php';
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';
require 'mail_config.php';

function send_password_reset_email($to, $customer_name, $reset_link) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        //Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $customer_name);
        
        //Content
        $mail->isHTML(true);
        $mail->Subject = "Reset Your VuaToFua Password";
        $mail->Body    = "<p>Dear {$customer_name},</p>"
                      . "<p>We received a request to reset your VuaToFua account password. "
                      . "Click the button below to reset your password:</p>"
                      . "<p style='margin: 30px 0;'>"
                      . "<a href='{$reset_link}' style='background-color: #C4A484; color: #131C21; "
                      . "padding: 12px 30px; text-decoration: none; border-radius: 5px; "
                      . "display: inline-block; font-weight: bold;'>Reset Password</a></p>"
                      . "<p>This link will expire in 1 hour for security reasons.</p>"
                      . "<p>If you didn't request this password reset, please ignore this email "
                      . "or contact us if you have concerns.</p>"
                      . "<p>Best regards,<br>VuaToFua Team</p>";
        $mail->AltBody = "Dear {$customer_name},\n\n"
                      . "We received a request to reset your VuaToFua account password. "
                      . "Click the link below to reset your password:\n\n"
                      . "{$reset_link}\n\n"
                      . "This link will expire in 1 hour for security reasons.\n\n"
                      . "If you didn't request this password reset, please ignore this email "
                      . "or contact us if you have concerns.\n\n"
                      . "Best regards,\nVuaToFua Team";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password reset email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function send_order_status_email($to, $customer_name, $order_id, $new_status) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        //Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $customer_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your VuaToFua Order Status Update (#$order_id)";
        $mail->Body    = "<p>Dear $customer_name,</p>"
                       . "<p>The status of your order #$order_id has been updated to: <strong>$new_status</strong>.</p>"
                       . "<p>Thank you for using VuaToFua Laundry Services.</p>";
        $mail->AltBody = "Dear $customer_name,\n\nThe status of your order #$order_id has been updated to: $new_status.\n\nThank you for using VuaToFua Laundry Services.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error, but don't expose details to the user
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to get user's total loyalty points
function get_loyalty_points($conn, $user_id) {
    $total_earned = 0;
    $total_redeemed = 0;

    // Get total earned
    $stmt_earned = $conn->prepare("SELECT SUM(points_earned) as total FROM loyalty_points WHERE user_id = ?");
    $stmt_earned->bind_param("i", $user_id);
    $stmt_earned->execute();
    $result_earned = $stmt_earned->get_result();
    if ($row = $result_earned->fetch_assoc()) {
        $total_earned = $row['total'] ? $row['total'] : 0;
    }
    $stmt_earned->close();

    // Get total redeemed
    $stmt_redeemed = $conn->prepare("SELECT SUM(points_redeemed) as total FROM loyalty_points WHERE user_id = ?");
    $stmt_redeemed->bind_param("i", $user_id);
    $stmt_redeemed->execute();
    $result_redeemed = $stmt_redeemed->get_result();
    if ($row = $result_redeemed->fetch_assoc()) {
        $total_redeemed = $row['total'] ? $row['total'] : 0;
    }
    $stmt_redeemed->close();

    return $total_earned - $total_redeemed;
}

// Function to send contact form confirmation email
function send_contact_confirmation_email($to, $name) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        //Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $name);
        
        //Content
        $mail->isHTML(true);
        $mail->Subject = "Thank you for contacting VuaToFua";
        $mail->Body    = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>"
                      . "<h2 style='color: #C4A484;'>Thank You for Reaching Out!</h2>"
                      . "<p>Dear {$name},</p>"
                      . "<p>Thank you for contacting VuaToFua Laundry Services. We have received your message "
                      . "and our team will review it promptly.</p>"
                      . "<p>We typically respond within 24 hours during business days.</p>"
                      . "<p>If you have any urgent concerns, please feel free to call us at: +254 700 000 000</p>"
                      . "<p style='margin-top: 20px;'>Best regards,<br>VuaToFua Team</p>"
                      . "</div>";
        
        $mail->AltBody = "Dear {$name},\n\n"
                      . "Thank you for contacting VuaToFua Laundry Services. We have received your message "
                      . "and our team will review it promptly.\n\n"
                      . "We typically respond within 24 hours during business days.\n\n"
                      . "If you have any urgent concerns, please feel free to call us at: +254 700 000 000\n\n"
                      . "Best regards,\nVuaToFua Team";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Contact confirmation email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
/**
 * Checks if the logged-in user is an administrator.
 *
 * @return bool True if user is an admin, false otherwise.
 */
function is_admin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>
