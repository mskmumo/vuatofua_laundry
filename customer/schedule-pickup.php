<?php
require_once '../config.php';
require_once '../functions.php';

secure_session_start();

// Redirect to login if not logged in
if (!is_logged_in()) {
    redirect('../login.php');
}

// Redirect admin users to admin dashboard
if (has_role('admin')) {
    redirect('../admin/index.php');
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Security token validation failed. Please try again.';
    } else {
        $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
        $pickup_date = filter_input(INPUT_POST, 'pickup_date', FILTER_SANITIZE_STRING);
        $pickup_time_slot = filter_input(INPUT_POST, 'pickup_time_slot', FILTER_SANITIZE_STRING);
        $service_type = filter_input(INPUT_POST, 'service_type', FILTER_SANITIZE_STRING);
        $estimated_items = filter_input(INPUT_POST, 'estimated_items', FILTER_VALIDATE_INT);
        $special_instructions = filter_input(INPUT_POST, 'special_instructions', FILTER_SANITIZE_STRING);

        // Validation
        $errors = [];
        if (!$address_id) {
            $errors[] = 'Please select a pickup address.';
        }
        if (!$pickup_date) {
            $errors[] = 'Please select a pickup date.';
        } elseif (strtotime($pickup_date) < strtotime('today')) {
            $errors[] = 'Pickup date cannot be in the past.';
        }
        if (!$pickup_time_slot) {
            $errors[] = 'Please select a time slot.';
        }
        if (!$service_type) {
            $errors[] = 'Please select a service type.';
        }
        if (!$estimated_items || $estimated_items < 1) {
            $estimated_items = 1;
        }

        if (empty($errors)) {
            // Set time range based on slot
            $time_ranges = [
                'morning' => '8:00 AM - 12:00 PM',
                'afternoon' => '12:00 PM - 4:00 PM',
                'evening' => '4:00 PM - 8:00 PM'
            ];
            $pickup_time_range = $time_ranges[$pickup_time_slot];

            // Insert pickup schedule
            $stmt = $conn->prepare("INSERT INTO pickup_schedules (user_id, address_id, pickup_date, pickup_time_slot, pickup_time_range, service_type, estimated_items, special_instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssiss", $user_id, $address_id, $pickup_date, $pickup_time_slot, $pickup_time_range, $service_type, $estimated_items, $special_instructions);
            
            if ($stmt->execute()) {
                $pickup_id = $conn->insert_id;
                
                // Create notification for user
                $notification_title = "Pickup Scheduled Successfully";
                $notification_message = "Your pickup has been scheduled for " . date('F j, Y', strtotime($pickup_date)) . " during " . $pickup_time_range . ".";
                
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, 'order_update', ?, ?, ?, 'pickup')");
                $notif_stmt->bind_param("issi", $user_id, $notification_title, $notification_message, $pickup_id);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $success_message = 'Pickup scheduled successfully! We will contact you to confirm the appointment.';
                
                // Clear form data
                $_POST = [];
            } else {
                $error_message = 'Failed to schedule pickup. Please try again.';
            }
            $stmt->close();
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

// Get user's addresses
$addresses = [];
$addr_stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_label ASC");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addr_result = $addr_stmt->get_result();
while ($row = $addr_result->fetch_assoc()) {
    $addresses[] = $row;
}
$addr_stmt->close();

// Get user's recent pickups
$recent_pickups = [];
$pickup_stmt = $conn->prepare("SELECT ps.*, ua.address_label, ua.street_address 
                              FROM pickup_schedules ps 
                              JOIN user_addresses ua ON ps.address_id = ua.address_id 
                              WHERE ps.user_id = ? 
                              ORDER BY ps.created_at DESC 
                              LIMIT 5");
$pickup_stmt->bind_param("i", $user_id);
$pickup_stmt->execute();
$pickup_result = $pickup_stmt->get_result();
while ($row = $pickup_result->fetch_assoc()) {
    $recent_pickups[] = $row;
}
$pickup_stmt->close();

$conn->close();

$page_title = 'Schedule Pickup - VuaToFua';
require_once 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-plus"></i> Schedule Pickup</h1>
        <p>Schedule a convenient pickup time for your laundry</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="content-grid">
        <!-- Schedule Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Schedule New Pickup</h3>
            </div>
            <div class="card-content">
                <?php if (empty($addresses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>No Addresses Found</h4>
                        <p>You need to add an address before scheduling a pickup.</p>
                        <a href="manage-addresses.php" class="btn btn-primary">Add Address</a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="pickup-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label for="address_id">Pickup Address *</label>
                            <select name="address_id" id="address_id" required>
                                <option value="">Select pickup address</option>
                                <?php foreach ($addresses as $address): ?>
                                    <option value="<?php echo $address['address_id']; ?>" 
                                            <?php echo (isset($_POST['address_id']) && $_POST['address_id'] == $address['address_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($address['address_label']); ?>
                                        <?php if ($address['is_default']): ?> (Default)<?php endif; ?>
                                        - <?php echo htmlspecialchars($address['street_address']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pickup_date">Pickup Date *</label>
                                <input type="date" name="pickup_date" id="pickup_date" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo isset($_POST['pickup_date']) ? htmlspecialchars($_POST['pickup_date']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="pickup_time_slot">Time Slot *</label>
                                <select name="pickup_time_slot" id="pickup_time_slot" required>
                                    <option value="">Select time slot</option>
                                    <option value="morning" <?php echo (isset($_POST['pickup_time_slot']) && $_POST['pickup_time_slot'] == 'morning') ? 'selected' : ''; ?>>
                                        Morning (8:00 AM - 12:00 PM)
                                    </option>
                                    <option value="afternoon" <?php echo (isset($_POST['pickup_time_slot']) && $_POST['pickup_time_slot'] == 'afternoon') ? 'selected' : ''; ?>>
                                        Afternoon (12:00 PM - 4:00 PM)
                                    </option>
                                    <option value="evening" <?php echo (isset($_POST['pickup_time_slot']) && $_POST['pickup_time_slot'] == 'evening') ? 'selected' : ''; ?>>
                                        Evening (4:00 PM - 8:00 PM)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="service_type">Service Type *</label>
                                <select name="service_type" id="service_type" required>
                                    <option value="">Select service</option>
                                    <option value="wash_fold" <?php echo (isset($_POST['service_type']) && $_POST['service_type'] == 'wash_fold') ? 'selected' : ''; ?>>
                                        Wash & Fold
                                    </option>
                                    <option value="dry_cleaning" <?php echo (isset($_POST['service_type']) && $_POST['service_type'] == 'dry_cleaning') ? 'selected' : ''; ?>>
                                        Dry Cleaning
                                    </option>
                                    <option value="ironing" <?php echo (isset($_POST['service_type']) && $_POST['service_type'] == 'ironing') ? 'selected' : ''; ?>>
                                        Ironing Only
                                    </option>
                                    <option value="express" <?php echo (isset($_POST['service_type']) && $_POST['service_type'] == 'express') ? 'selected' : ''; ?>>
                                        Express Service
                                    </option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="estimated_items">Estimated Items</label>
                                <input type="number" name="estimated_items" id="estimated_items" 
                                       min="1" max="50" 
                                       value="<?php echo isset($_POST['estimated_items']) ? htmlspecialchars($_POST['estimated_items']) : '1'; ?>">
                                <small>Approximate number of clothing items</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="special_instructions">Special Instructions</label>
                            <textarea name="special_instructions" id="special_instructions" 
                                      placeholder="Any special handling instructions, gate codes, or notes for our pickup team..."><?php echo isset($_POST['special_instructions']) ? htmlspecialchars($_POST['special_instructions']) : ''; ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> Schedule Pickup
                            </button>
                            <a href="../dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Pickups -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Pickups</h3>
            </div>
            <div class="card-content">
                <?php if (empty($recent_pickups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <p>No pickups scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <div class="pickup-list">
                        <?php foreach ($recent_pickups as $pickup): ?>
                            <div class="pickup-item">
                                <div class="pickup-header">
                                    <span class="pickup-id">#<?php echo $pickup['pickup_id']; ?></span>
                                    <span class="status-badge status-<?php echo strtolower($pickup['status']); ?>">
                                        <?php echo ucfirst($pickup['status']); ?>
                                    </span>
                                </div>
                                <div class="pickup-details">
                                    <p><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($pickup['pickup_date'])); ?></p>
                                    <p><i class="fas fa-clock"></i> <?php echo $pickup['pickup_time_range']; ?></p>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pickup['address_label']); ?></p>
                                    <p><i class="fas fa-tshirt"></i> <?php echo ucfirst(str_replace('_', ' ', $pickup['service_type'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
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
    font-size: 1.1rem;
    opacity: 0.9;
    position: relative;
    z-index: 2;
    animation: slideInDown 0.8s ease-out 0.4s both;
}

.content-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    width: 100%;
}

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

.pickup-form .form-group {
    margin-bottom: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.pickup-form .form-group {
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease-out;
}

.form-group label {
    display: block;
    margin-bottom: 0.8rem;
    color: var(--title-color);
    font-weight: 600;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: color 0.3s ease;
}

.form-group:focus-within label {
    color: var(--accent-color);
    transform: translateY(-2px);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 1rem 1.2rem;
    border: 2px solid rgba(196, 164, 132, 0.2);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(135deg, rgba(26, 37, 43, 0.8) 0%, rgba(19, 28, 33, 0.9) 100%);
    color: var(--text-color);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    position: relative;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 4px rgba(196, 164, 132, 0.1), inset 0 2px 4px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
    background: linear-gradient(135deg, rgba(26, 37, 43, 0.9) 0%, rgba(19, 28, 33, 1) 100%);
}

.form-group input:hover,
.form-group select:hover,
.form-group textarea:hover {
    border-color: rgba(196, 164, 132, 0.4);
    transform: translateY(-1px);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group small {
    display: block;
    margin-top: 0.3rem;
    color: var(--text-color);
    opacity: 0.7;
    font-size: 0.85rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.6s;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-color) 0%, #b8956a 100%);
    color: var(--dark-bg);
    box-shadow: 0 6px 20px rgba(196, 164, 132, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #b8956a 0%, var(--accent-color) 100%);
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(196, 164, 132, 0.4);
}

.btn-primary:active {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(196, 164, 132, 0.3);
}

.btn-secondary {
    background: transparent;
    color: var(--text-color);
    border: 2px solid rgba(196, 164, 132, 0.3);
}

.btn-secondary:hover {
    background: rgba(196, 164, 132, 0.1);
    border-color: var(--accent-color);
    transform: translateY(-2px);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.alert-error {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-color);
}

.empty-state i {
    font-size: 3rem;
    color: var(--accent-color);
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: var(--title-color);
    margin-bottom: 0.5rem;
}

.pickup-list {
    space-y: 1rem;
}

.pickup-item {
    background: rgba(196, 164, 132, 0.05);
    border: 1px solid rgba(196, 164, 132, 0.1);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.pickup-item:hover {
    background: rgba(196, 164, 132, 0.1);
    border-color: var(--accent-color);
    transform: translateY(-1px);
}

.pickup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.pickup-id {
    font-weight: 600;
    color: var(--title-color);
}

.pickup-details p {
    margin: 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: var(--text-color);
    font-size: 0.9rem;
}

.pickup-details i {
    width: 16px;
    text-align: center;
    color: var(--accent-color);
}

.status-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-scheduled {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.status-confirmed {
    background: rgba(0, 123, 255, 0.2);
    color: #007bff;
    border: 1px solid rgba(0, 123, 255, 0.3);
}

.status-in_progress {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.status-completed {
    background: rgba(108, 117, 125, 0.2);
    color: #6c757d;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.status-cancelled {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

@media (max-width: 768px) {
    .content-grid {
        gap: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .main-content .container {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .form-actions {
        flex-direction: column;
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

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}
</style>

<?php include '../templates/footer.php'; ?>
