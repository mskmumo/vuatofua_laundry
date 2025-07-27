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

// Get user details
$user = [];
$sql = "SELECT user_id, name, email, phone, created_at FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $error_message = 'Name and email are required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if email already exists for another user
        $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = 'This email is already registered to another account.';
        } else {
            // Update user profile
            $update_sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssi", $name, $email, $phone, $user_id);
            
            if ($update_stmt->execute()) {
                // Update session data
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                
                // Update the displayed user data
                $user['name'] = $name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                
                $success_message = 'Profile updated successfully!';
            } else {
                $error_message = 'Failed to update profile. Please try again.';
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

$page_title = 'My Profile - VuaToFua';
include 'header.php';
?>

<div class="profile-container-wrapper">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <h1>My Profile</h1>
            <p>Manage your account information and preferences</p>
            <a href="change-password.php" class="btn btn-outline">Change Password</a>
        </div>
    </div>
    
    <!-- Center Form Section -->
    <div class="form-section">
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="profile-form">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
    
    <!-- Bottom Stats Section -->
    <div class="stats-section">
        <div class="profile-stats">
            <div class="stat-card">
                <h3>Member Since</h3>
                <p><?php echo !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Account Status</h3>
                <p>Active</p>
            </div>
            
            <div class="stat-card">
                <h3>Contact Preferences</h3>
                <p>Email & SMS Notifications</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Override main CSS flex properties */
.main-content {
    display: block !important;
    flex-direction: unset !important;
}

/* Profile Container Layout */
.profile-container-wrapper {
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
    margin-bottom: 4rem;
    display: flex;
    justify-content: center;
    width: 100%;
}

/* Profile Form */
.profile-form {
    background: var(--dark-accent);
    padding: 2.5rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    position: relative;
    width: 100%;
    max-width: 600px;
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

.form-group input,
.form-group textarea,
.form-group select {
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

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
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

.form-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Stats Section */
.stats-section {
    margin-top: 3rem;
    padding: 2rem 0;
    width: 100%;
}

/* Profile Stats */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 1rem;
}

.stat-card {
    background: var(--dark-accent);
    padding: 2rem 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 0 20px rgba(0,0,0,0.3);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-color), transparent);
    opacity: 0.7;
    transition: all 0.3s ease;
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    border-color: rgba(196, 164, 132, 0.3);
}

.stat-card h3 {
    color: var(--accent-color);
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card p {
    color: var(--title-color);
    margin: 0;
    font-size: 1.2rem;
    font-weight: 500;
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
    .profile-container-wrapper {
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
        font-size: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .form-section {
        margin-bottom: 3rem;
        padding: 0;
    }
    
    .profile-form {
        padding: 2rem 1.5rem;
        margin: 0 1rem;
    }
    
    .stats-section {
        margin-top: 2rem;
        padding: 1rem 0;
    }
    
    .profile-stats {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        padding: 0 1rem;
    }
    
    .stat-card {
        padding: 1.5rem;
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
        font-size: 1.8rem;
    }
    
    .profile-form {
        margin: 0 0.5rem;
        padding: 1.5rem 1rem;
    }
    
    .profile-stats {
        padding: 0 0.5rem;
    }
}
</style>

<?php include '../templates/footer.php'; ?>
