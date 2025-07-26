<?php
require_once 'config.php';
require_once 'functions.php';

if (is_logged_in()) {
    if (has_role('admin')) {
        redirect('admin/index.php');
    } else {
        redirect('dashboard.php');
    }
}

$name = $email = $phone = $password = $confirm_password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = $_POST['password']; // Don't sanitize password
    $confirm_password = $_POST['confirm_password'];

    if (empty($name)) {
        $errors['name'] = "Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "A valid email is required.";
    }
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } else if (!validate_password($password)) {
        $errors['password'] = "Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.";
    }
    if (empty($confirm_password)) {
        $errors['confirm_password'] = "Please confirm your password.";
    } else if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $conn = db_connect();
        // Check if email or phone already exists
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR phone = ?");
        $stmt_check->bind_param("ss", $email, $phone);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $errors['form'] = "An account with this email or phone already exists.";
        } else {
            // If no errors, insert into database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt_insert->bind_param("ssss", $name, $email, $phone, $hashed_password);
            if ($stmt_insert->execute()) {
                $user_id = $conn->insert_id;
                log_sms($conn, $user_id, $phone, "Welcome to VuaToFua! Your account has been created.");
                redirect('login.php?registration=success');
            } else {
                $errors['form'] = "Something went wrong. Please try again later.";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
        $conn->close();
    }
}

include 'templates/header.php';
?>
<main class="main-content">
    <div class="container" id="register-form-container">
        <div class="form-container">
            <h2>Create Your Account</h2>
        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert-danger"><?php echo $errors['form']; ?></div>
        <?php endif; ?>
        <form action="register.php" method="post">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo $errors['name']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                <?php if (!empty($errors['email'])): ?><small class="text-danger"><?php echo $errors['email']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
                <?php if (!empty($errors['phone'])): ?><small class="text-danger"><?php echo $errors['phone']; ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-group">
                    <input type="password" name="password" id="password" class="form-control" 
                           required minlength="8" 
                           title="Must contain at least 8 characters, including uppercase, lowercase, and numbers">
                    <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <small class="text-danger"><?php echo $errors['password']; ?></small>
                <?php endif; ?>
                <div class="password-strength-meter">
                    <div class="strength-bar"></div>
                </div>
                <small class="password-requirements">
                    Password must contain:
                    <ul>
                        <li id="length">At least 8 characters</li>
                        <li id="uppercase">One uppercase letter</li>
                        <li id="lowercase">One lowercase letter</li>
                        <li id="number">One number</li>
                    </ul>
                </small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-input-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <?php if (!empty($errors['confirm_password'])): ?>
                    <small class="text-danger"><?php echo $errors['confirm_password']; ?></small>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn">Register</button>
            <div class="form-links">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
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

    // Password strength checker
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.querySelector('.strength-bar');
    const requirements = {
        length: document.getElementById('length'),
        uppercase: document.getElementById('uppercase'),
        lowercase: document.getElementById('lowercase'),
        number: document.getElementById('number')
    };

    function checkPasswordStrength(value) {
        let strength = 0;
        let checks = {
            length: value.length >= 8,
            uppercase: /[A-Z]/.test(value),
            lowercase: /[a-z]/.test(value),
            number: /[0-9]/.test(value)
        };

        // Update requirements list
        Object.keys(checks).forEach(key => {
            if (checks[key]) {
                requirements[key].classList.add('met');
                strength++;
            } else {
                requirements[key].classList.remove('met');
            }
        });

        // Update strength bar
        strengthBar.style.width = (strength * 25) + '%';
        if (strength === 0) strengthBar.style.backgroundColor = '#dc3545';
        else if (strength <= 2) strengthBar.style.backgroundColor = '#ffc107';
        else if (strength === 3) strengthBar.style.backgroundColor = '#28a745';
        else strengthBar.style.backgroundColor = '#20c997';
    }

    password.addEventListener('input', function() {
        checkPasswordStrength(this.value);
    });

    // Confirm password match
    confirmPassword.addEventListener('input', function() {
        if (this.value !== password.value) {
            this.setCustomValidity("Passwords don't match");
        } else {
            this.setCustomValidity('');
        }
    });
});
</script>
</main>
<?php include 'templates/footer.php'; ?>
