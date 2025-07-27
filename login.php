<?php
require_once 'config.php';
require_once 'functions.php';

// Start secure session
secure_session_start();

// Check if already logged in
if (is_logged_in()) {
    if (has_role('admin')) {
        redirect('admin/index.php');
    } else {
        redirect('dashboard.php');
    }
}

$email = $password = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
        error_log("CSRF token validation failed for IP: " . $_SERVER['REMOTE_ADDR']);
    } else {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password']; // Don't sanitize password before verification

        if (empty($email) || empty($password)) {
            $error = "Email and password are required.";
        } else if (!validate_email($email)) {
            $error = "Invalid email format.";
        } else {
            $conn = db_connect();
            
            // Check for too many login attempts
            if (check_login_attempts($conn, $email)) {
                $error = "Too many failed attempts. Please try again later.";
                error_log("Login rate limit reached for email: $email, IP: " . $_SERVER['REMOTE_ADDR']);
            } else {
                $stmt = $conn->prepare("SELECT user_id, name, password, role, status, account_locked, account_locked_until FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
        
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    if ($user['status'] !== 'active') {
                        $error = "This account is " . strtolower($user['status']) . ". Please contact support.";
                    } else if ($user['account_locked'] && ($user['account_locked_until'] === NULL || strtotime($user['account_locked_until']) > time())) {
                        $error = "This account is temporarily locked. Please try again later or contact support.";
                        error_log("Login attempt on locked account: $email, IP: " . $_SERVER['REMOTE_ADDR']);
                    } else if (password_verify($password, $user['password'])) {
                        // Successful login
                        session_regenerate_id(true); // Prevent session fixation
                        
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                        $_SESSION['token'] = bin2hex(random_bytes(32));
                        
                        // Update last login time
                        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        $update_stmt->bind_param("i", $user['user_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);

                        // Log the new session
                        $session_token = $_SESSION['token']; // Use the token we generated earlier
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); // Expires in 30 days

                        $session_stmt = $conn->prepare("INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
                        $session_stmt->bind_param("issss", $user['user_id'], $session_token, $ip_address, $user_agent, $expires_at);
                        $session_stmt->execute();
                        $session_stmt->close();
                        
                        // Handle "Remember Me" functionality
                        $token = bin2hex(random_bytes(32)); // Always generate a token
                        if (isset($_POST['remember_me'])) {
                            $expiry_date = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                            $remember_stmt = $conn->prepare("INSERT INTO remember_me_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                            $remember_stmt->bind_param("issss", $user['user_id'], $token, $expiry_date, $token, $expiry_date);
                            $remember_stmt->execute();
                            $remember_stmt->close();

                            setcookie('remember_me', $token, time() + (86400 * 30), "/", "", true, true);
                        }
                        if ($user['role'] == 'admin') {
                            redirect('admin/index.php');
                        } else {
                            redirect('dashboard.php');
                        }
                    } else {
                        // Failed login attempt
                        $error = "Invalid email or password."; // Generic error message
                        log_login_attempt($conn, $email);
                        error_log("Failed login attempt for email: $email, IP: " . $_SERVER['REMOTE_ADDR']);
                    }
                } else {
                    // No account found but give generic error
                    $error = "Invalid email or password.";
                    error_log("Login attempt with non-existent email: $email, IP: " . $_SERVER['REMOTE_ADDR']);
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
}

$page_title = 'Login - VuaToFua';
include 'templates/header.php';
?>

<main class="main-content">
    <div class="container" id="login-form-container">
        <div class="form-container">
            <h2>Login to Your Account</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['registration']) && $_GET['registration'] == 'success'): ?>
                <div class="alert alert-success">Registration successful! Please log in.</div>
            <?php endif; ?>
            <form action="login.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           required autocomplete="email" 
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           title="Please enter a valid email address">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-group">
                        <input type="password" name="password" id="password" class="form-control" 
                               required minlength="8" autocomplete="current-password">
                        <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="remember_me"> Remember me
                    </label>
                </div>
                <button type="submit" class="btn">Login</button>
                <div class="form-links">
                    <a href="forgot-password.php">Forgot Password?</a>
                    <p class="text-center">Don't have an account? <a href="register.php">Sign up</a></p>
                </div>
            </form>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Password visibility toggle
                const toggleButton = document.querySelector('.btn-toggle-password');
                const passwordInput = document.querySelector('#password');
                
                toggleButton.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type');
                    passwordInput.setAttribute('type', type === 'password' ? 'text' : 'password');
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
                
                // Form submission validation
                const form = document.querySelector('form');
                form.addEventListener('submit', function(e) {
                    const email = document.querySelector('#email').value;
                    const password = document.querySelector('#password').value;
                    
                    if (!email || !password) {
                        e.preventDefault();
                        alert('Please fill in all fields');
                    }
                });
            });
            </script>
        </div>
    </div>
</main>

<?php include 'templates/footer.php'; ?>
