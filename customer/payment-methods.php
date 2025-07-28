<?php
require_once '../config.php';
require_once '../functions.php';

secure_session_start();

if (!is_logged_in()) {
    redirect('../login.php');
}

if (has_role('admin')) {
    redirect('../admin/index.php');
}

// Fetch M-Pesa payment configuration from database
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
    // Fallback to default values if database query fails
    $paybill_number = '522522';
    $account_number = 'VUATOFUA001';
    $delivery_note = 'Payment is collected on delivery. Our team will collect payment when delivering your clean laundry.';
}

$page_title = 'Payment Information - VuaToFua';
require_once 'header.php';
?>

<div class="main-content">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-mobile-alt"></i> Payment Information</h1>
            <p>Simple and secure M-Pesa payment for your laundry services</p>
        </div>

        <!-- M-Pesa Payment Information -->
        <div class="payment-info-grid">
            <!-- M-Pesa Details Card -->
            <div class="card mpesa-card">
                <div class="card-header">
                    <h3><i class="fas fa-mobile-alt"></i> M-Pesa Payment Details</h3>
                </div>
                <div class="card-content">
                    <div class="mpesa-info">
                        <div class="payment-detail">
                            <div class="detail-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Business Number (PayBill)</h4>
                                <p class="highlight-number"><?php echo htmlspecialchars($paybill_number); ?></p>
                                <small>Use this number when making M-Pesa payments</small>
                            </div>
                        </div>

                        <div class="payment-detail">
                            <div class="detail-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Account Number</h4>
                                <p class="highlight-number"><?php echo htmlspecialchars($account_number); ?></p>
                                <small>Enter this as your account number</small>
                            </div>
                        </div>

                        <div class="payment-detail">
                            <div class="detail-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Payment Note</h4>
                                <p><?php echo htmlspecialchars($delivery_note); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Instructions Card -->
            <div class="card instructions-card">
                <div class="card-header">
                    <h3><i class="fas fa-list-ol"></i> How to Pay with M-Pesa</h3>
                </div>
                <div class="card-content">
                    <div class="payment-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Open M-Pesa Menu</h4>
                                <p>Dial *334# or open your M-Pesa app</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Select PayBill</h4>
                                <p>Choose "PayBill" option from the menu</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Enter Business Number</h4>
                                <p>Enter <strong><?php echo htmlspecialchars($paybill_number); ?></strong> as the business number</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Enter Account Number</h4>
                                <p>Enter <strong><?php echo htmlspecialchars($account_number); ?></strong> as the account number</p>
                            </div>
                        </div>

                        <div class="step">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <h4>Enter Amount & PIN</h4>
                                <p>Enter the amount and your M-Pesa PIN to complete</p>
                            </div>
                        </div>
                    </div>

                    <div class="payment-note">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>Note:</strong> <?php echo htmlspecialchars($delivery_note); ?></p>
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
    padding: 2rem;
    display: flex;
    flex-direction: column;
}

/* Ensure header container is not affected */
#main-header .container {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    max-width: 1200px !important;
    margin: 0 auto !important;
    padding: 0 20px !important;
}

/* Page Header */
.page-header {
    text-align: center;
    margin-bottom: 3rem;
    width: 100%;
    order: -1;
    background: linear-gradient(135deg, var(--dark-accent) 0%, #1a252b 100%);
    padding: 3rem 2rem;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.8s ease-out;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(196, 164, 132, 0.1) 0%, transparent 50%, rgba(196, 164, 132, 0.05) 100%);
    animation: shimmer 3s ease-in-out infinite;
}

.page-header h1 {
    color: var(--title-color);
    margin-bottom: 0.5rem;
    font-size: 2.8rem;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
    position: relative;
    z-index: 2;
    animation: slideInDown 0.8s ease-out 0.2s both;
}

.page-header p {
    color: var(--text-color);
    opacity: 0.9;
    font-size: 1.1rem;
    position: relative;
    z-index: 2;
    animation: slideInDown 0.8s ease-out 0.4s both;
}

/* Payment Info Grid */
.payment-info-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    width: 100%;
}

/* Cards */
.card {
    background: linear-gradient(135deg, var(--dark-accent) 0%, #1a252b 100%);
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    animation: slideInUp 0.6s ease-out;
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(196, 164, 132, 0.2);
    border-color: var(--accent-color);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(196, 164, 132, 0.1), transparent);
    transition: left 0.6s;
}

.card:hover::before {
    left: 100%;
}

.card-header {
    background: linear-gradient(135deg, var(--accent-color) 0%, #b8956a 100%);
    color: var(--dark-bg);
    padding: 2rem;
    position: relative;
    overflow: hidden;
}

.card-header::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.card-header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 2;
}

.card-content {
    padding: 2.5rem;
    position: relative;
    z-index: 1;
}

/* M-Pesa Info Styling */
.mpesa-info {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.payment-detail {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    background: rgba(196, 164, 132, 0.05);
    border: 1px solid rgba(196, 164, 132, 0.1);
    border-radius: 15px;
    transition: all 0.3s ease;
}

.payment-detail:hover {
    background: rgba(196, 164, 132, 0.1);
    border-color: var(--accent-color);
    transform: translateY(-2px);
}

.detail-icon {
    font-size: 2.5rem;
    color: var(--accent-color);
    min-width: 60px;
    text-align: center;
}

.detail-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--title-color);
    font-size: 1.2rem;
    font-weight: 600;
}

.highlight-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--accent-color);
    margin: 0.5rem 0;
    font-family: 'Courier New', monospace;
    letter-spacing: 2px;
}

.detail-content small {
    color: var(--text-color);
    opacity: 0.8;
    font-size: 0.9rem;
}

/* Payment Steps */
.payment-steps {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: rgba(196, 164, 132, 0.05);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.step:hover {
    background: rgba(196, 164, 132, 0.1);
    transform: translateX(5px);
}

.step-number {
    background: var(--accent-color);
    color: var(--dark-bg);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.step-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--title-color);
    font-size: 1.1rem;
    font-weight: 600;
}

.step-content p {
    margin: 0;
    color: var(--text-color);
    opacity: 0.9;
}

/* Payment Note */
.payment-note {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
    border-radius: 12px;
    color: #28a745;
}

.payment-note i {
    font-size: 1.5rem;
    margin-top: 0.2rem;
}

.payment-note p {
    margin: 0;
    font-size: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content .container {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .payment-detail {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .step {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .payment-note {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
}

/* Keyframe Animations */
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

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
</style>

<?php include '../templates/footer.php'; ?>
