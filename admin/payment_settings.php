<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        if ($_POST['action'] === 'update_payment_config') {
            try {
                $paybill = sanitize_input($_POST['mpesa_paybill_number']);
                $account = sanitize_input($_POST['mpesa_account_number']);
                $delivery_note = sanitize_input($_POST['payment_delivery_note']);
                
                // Validate inputs
                if (empty($paybill) || empty($account) || empty($delivery_note)) {
                    throw new Exception('All fields are required.');
                }
                
                if (!preg_match('/^\d{6}$/', $paybill)) {
                    throw new Exception('PayBill number must be exactly 6 digits.');
                }
                
                if (strlen($account) < 3 || strlen($account) > 20) {
                    throw new Exception('Account number must be between 3 and 20 characters.');
                }
                
                // Update configuration
                $conn = db_connect();
                $stmt = $conn->prepare("UPDATE payment_config SET config_value = ?, updated_by = ? WHERE config_key = ?");
                
                $stmt->bind_param('sis', $paybill, $_SESSION['user_id'], $key1);
                $key1 = 'mpesa_paybill_number';
                $stmt->execute();
                
                $stmt->bind_param('sis', $account, $_SESSION['user_id'], $key2);
                $key2 = 'mpesa_account_number';
                $stmt->execute();
                
                $stmt->bind_param('sis', $delivery_note, $_SESSION['user_id'], $key3);
                $key3 = 'payment_delivery_note';
                $stmt->execute();
                
                $conn->close();
                
                $success_message = 'Payment configuration updated successfully!';
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        }
    }
}

// Fetch current configuration
try {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT config_key, config_value FROM payment_config WHERE config_key IN ('mpesa_paybill_number', 'mpesa_account_number', 'payment_delivery_note')");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $configs = [];
    while ($row = $result->fetch_assoc()) {
        $configs[$row['config_key']] = $row['config_value'];
    }
    
    $paybill_number = $configs['mpesa_paybill_number'] ?? '522522';
    $account_number = $configs['mpesa_account_number'] ?? 'VUATOFUA001';
    $delivery_note = $configs['payment_delivery_note'] ?? 'Payment is collected on delivery. Our team will collect payment when delivering your clean laundry.';
    
    $conn->close();
    
} catch (Exception $e) {
    $error_message = 'Error loading configuration: ' . $e->getMessage();
    $paybill_number = '522522';
    $account_number = 'VUATOFUA001';
    $delivery_note = 'Payment is collected on delivery. Our team will collect payment when delivering your clean laundry.';
}

$page_title = 'Payment Settings';
$is_admin_page = true;
require_once '../templates/header.php';
?>

<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-cogs"></i> Payment Settings</h1>
            <p>Manage M-Pesa payment configuration and delivery instructions</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Payment Configuration Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-mobile-alt"></i> M-Pesa Payment Configuration</h3>
            </div>
            <div class="card-content">
                <form method="POST" class="payment-config-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_payment_config">
                    
                    <div class="form-group">
                        <label for="mpesa_paybill_number">
                            <i class="fas fa-building"></i> M-Pesa PayBill Number
                        </label>
                        <input type="text" 
                               name="mpesa_paybill_number" 
                               id="mpesa_paybill_number" 
                               value="<?php echo htmlspecialchars($paybill_number); ?>"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               placeholder="522522"
                               required>
                        <small>Enter the 6-digit PayBill business number</small>
                    </div>

                    <div class="form-group">
                        <label for="mpesa_account_number">
                            <i class="fas fa-hashtag"></i> Account Number
                        </label>
                        <input type="text" 
                               name="mpesa_account_number" 
                               id="mpesa_account_number" 
                               value="<?php echo htmlspecialchars($account_number); ?>"
                               maxlength="20"
                               placeholder="VUATOFUA001"
                               required>
                        <small>Enter the account number customers should use</small>
                    </div>

                    <div class="form-group">
                        <label for="payment_delivery_note">
                            <i class="fas fa-info-circle"></i> Payment Delivery Note
                        </label>
                        <textarea name="payment_delivery_note" 
                                  id="payment_delivery_note" 
                                  rows="4"
                                  placeholder="Enter instructions about payment on delivery..."
                                  required><?php echo htmlspecialchars($delivery_note); ?></textarea>
                        <small>This note will be displayed to customers about payment collection</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Payment Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Preview Card -->
        <div class="card preview-card">
            <div class="card-header">
                <h3><i class="fas fa-eye"></i> Customer View Preview</h3>
            </div>
            <div class="card-content">
                <div class="preview-content">
                    <div class="payment-detail-preview">
                        <div class="detail-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Business Number (PayBill)</h4>
                            <p class="highlight-number" id="preview-paybill"><?php echo htmlspecialchars($paybill_number); ?></p>
                            <small>Use this number when making M-Pesa payments</small>
                        </div>
                    </div>

                    <div class="payment-detail-preview">
                        <div class="detail-icon">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <div class="detail-content">
                            <h4>Account Number</h4>
                            <p class="highlight-number" id="preview-account"><?php echo htmlspecialchars($account_number); ?></p>
                            <small>Enter this as your account number</small>
                        </div>
                    </div>

                    <div class="payment-note-preview">
                        <i class="fas fa-info-circle"></i>
                        <p id="preview-note"><?php echo htmlspecialchars($delivery_note); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Container and Layout */
.main-content .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

/* Page Header */
.page-header {
    text-align: center;
    margin-bottom: 3rem;
    animation: fadeInUp 0.8s ease-out;
}

.page-header h1 {
    font-size: 2.5rem;
    color: var(--title-color);
    margin-bottom: 0.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.page-header h1 i {
    color: var(--accent-color);
    font-size: 2.2rem;
}

.page-header p {
    color: var(--text-color);
    opacity: 0.8;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Card Styles */
.card {
    background: rgba(26, 37, 43, 0.8);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    margin-bottom: 2rem;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    animation: slideInUp 0.6s ease-out;
}

.card-header {
    background: linear-gradient(135deg, var(--dark-accent) 0%, rgba(196, 164, 132, 0.1) 100%);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
}

.card-header h3 {
    color: var(--title-color);
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.card-header h3 i {
    color: var(--accent-color);
    font-size: 1.2rem;
}

.card-content {
    padding: 2rem;
}

/* Form Styles */
.payment-config-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    color: var(--title-color);
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group label i {
    color: var(--accent-color);
    font-size: 0.9rem;
}

.form-group input,
.form-group textarea {
    padding: 1rem 1.5rem;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: rgba(19, 28, 33, 0.8);
    color: var(--text-color);
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(196, 164, 132, 0.1);
    background: rgba(19, 28, 33, 0.95);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.form-group small {
    color: var(--text-color);
    opacity: 0.7;
    font-size: 0.9rem;
    margin-top: 0.3rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: center;
    margin-top: 1rem;
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-color) 0%, #b8956a 100%);
    color: var(--dark-bg);
    box-shadow: 0 4px 15px rgba(196, 164, 132, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(196, 164, 132, 0.4);
}

/* Preview Card */
.preview-card {
    margin-top: 2rem;
}

.preview-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.payment-detail-preview {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    background: rgba(196, 164, 132, 0.05);
    border: 1px solid rgba(196, 164, 132, 0.1);
    border-radius: 15px;
    transition: all 0.3s ease;
}

.payment-detail-preview:hover {
    background: rgba(196, 164, 132, 0.08);
    transform: translateY(-2px);
}

.detail-icon {
    font-size: 2rem;
    color: var(--accent-color);
    min-width: 50px;
    text-align: center;
}

.detail-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--title-color);
    font-size: 1.1rem;
    font-weight: 600;
}

.highlight-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-color);
    margin: 0.5rem 0;
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
}

.detail-content small {
    color: var(--text-color);
    opacity: 0.8;
    font-size: 0.9rem;
}

.payment-note-preview {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
    border-radius: 12px;
    color: #4CAF50;
    transition: all 0.3s ease;
}

.payment-note-preview:hover {
    background: rgba(40, 167, 69, 0.15);
    transform: translateY(-1px);
}

.payment-note-preview i {
    font-size: 1.5rem;
    margin-top: 0.2rem;
}

.payment-note-preview p {
    margin: 0;
    font-size: 1rem;
    line-height: 1.6;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: slideInDown 0.5s ease-out;
    font-weight: 500;
}

.alert-success {
    background: rgba(76, 175, 80, 0.1);
    border: 1px solid rgba(76, 175, 80, 0.3);
    color: #4CAF50;
}

.alert-error {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #f44336;
}

.alert i {
    font-size: 1.2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content .container {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .card-content {
        padding: 1.5rem;
    }
    
    .payment-detail-preview {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .payment-note-preview {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Live preview updates
document.addEventListener('DOMContentLoaded', function() {
    const paybillInput = document.getElementById('mpesa_paybill_number');
    const accountInput = document.getElementById('mpesa_account_number');
    const noteInput = document.getElementById('payment_delivery_note');
    
    const previewPaybill = document.getElementById('preview-paybill');
    const previewAccount = document.getElementById('preview-account');
    const previewNote = document.getElementById('preview-note');
    
    paybillInput.addEventListener('input', function() {
        previewPaybill.textContent = this.value || '522522';
    });
    
    accountInput.addEventListener('input', function() {
        previewAccount.textContent = this.value || 'VUATOFUA001';
    });
    
    noteInput.addEventListener('input', function() {
        previewNote.textContent = this.value || 'Payment is collected on delivery.';
    });
});
</script>

<?php include '../templates/footer.php'; ?>
