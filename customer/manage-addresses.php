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

$conn = db_connect();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $address_type = filter_input(INPUT_POST, 'address_type', FILTER_SANITIZE_STRING);
            $address_label = filter_input(INPUT_POST, 'address_label', FILTER_SANITIZE_STRING);
            $street_address = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_STRING);
            $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
            $postal_code = filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_STRING);
            $landmark = filter_input(INPUT_POST, 'landmark', FILTER_SANITIZE_STRING);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            $is_default = isset($_POST['is_default']) ? 1 : 0;

            if ($address_label && $street_address && $city) {
                if ($is_default) {
                    $conn->query("UPDATE user_addresses SET is_default = 0 WHERE user_id = $user_id");
                }
                
                $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, address_type, address_label, street_address, city, postal_code, landmark, phone, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssssi", $user_id, $address_type, $address_label, $street_address, $city, $postal_code, $landmark, $phone, $is_default);
                
                if ($stmt->execute()) {
                    $success_message = 'Address added successfully!';
                } else {
                    $error_message = 'Failed to add address.';
                }
                $stmt->close();
            } else {
                $error_message = 'Please fill in all required fields.';
            }
        } elseif ($action === 'edit') {
            $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
            $address_type = filter_input(INPUT_POST, 'address_type', FILTER_SANITIZE_STRING);
            $address_label = filter_input(INPUT_POST, 'address_label', FILTER_SANITIZE_STRING);
            $street_address = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_STRING);
            $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
            $postal_code = filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_STRING);
            $landmark = filter_input(INPUT_POST, 'landmark', FILTER_SANITIZE_STRING);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            $is_default = isset($_POST['is_default']) ? 1 : 0;

            if ($address_id && $address_label && $street_address && $city) {
                if ($is_default) {
                    $conn->query("UPDATE user_addresses SET is_default = 0 WHERE user_id = $user_id");
                }
                
                $stmt = $conn->prepare("UPDATE user_addresses SET address_type = ?, address_label = ?, street_address = ?, city = ?, postal_code = ?, landmark = ?, phone = ?, is_default = ? WHERE address_id = ? AND user_id = ?");
                $stmt->bind_param("sssssssiiii", $address_type, $address_label, $street_address, $city, $postal_code, $landmark, $phone, $is_default, $address_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Address updated successfully!';
                } else {
                    $error_message = 'Failed to update address.';
                }
                $stmt->close();
            }
        } elseif ($action === 'delete') {
            $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
            
            if ($address_id) {
                $stmt = $conn->prepare("DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $address_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Address deleted successfully!';
                } else {
                    $error_message = 'Failed to delete address.';
                }
                $stmt->close();
            }
        } elseif ($action === 'set_default') {
            $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
            
            if ($address_id) {
                $conn->query("UPDATE user_addresses SET is_default = 0 WHERE user_id = $user_id");
                
                $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $address_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Default address updated!';
                } else {
                    $error_message = 'Failed to update default address.';
                }
                $stmt->close();
            }
        }
    }
}

// Get user's addresses
$addresses = [];
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_label ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}
$stmt->close();
$conn->close();

$page_title = 'Manage Addresses - VuaToFua';
require_once 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-map-marker-alt"></i> Manage Addresses</h1>
        <p>Add and manage your pickup and delivery addresses</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="content-grid">
        <!-- Add New Address -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus"></i> Add New Address</h3>
            </div>
            <div class="card-content">
                <form method="POST" class="address-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address_type">Address Type</label>
                            <select name="address_type" id="address_type" required>
                                <option value="home">Home</option>
                                <option value="office">Office</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="address_label">Address Label *</label>
                            <input type="text" name="address_label" id="address_label" 
                                   placeholder="e.g., My Home, Office, etc." required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="street_address">Street Address *</label>
                        <textarea name="street_address" id="street_address" 
                                  placeholder="Enter full street address" required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" name="city" id="city" placeholder="City" required>
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" name="postal_code" id="postal_code" placeholder="Postal Code">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="landmark">Landmark</label>
                        <input type="text" name="landmark" id="landmark" 
                               placeholder="Nearby landmark for easy identification">
                    </div>

                    <div class="form-group">
                        <label for="phone">Contact Phone</label>
                        <input type="tel" name="phone" id="phone" 
                               placeholder="Alternative contact number">
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_default">
                            <span class="checkmark"></span>
                            Set as default address
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Address
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Address List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Your Addresses</h3>
            </div>
            <div class="card-content">
                <?php if (empty($addresses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>No Addresses Added</h4>
                        <p>Add your first address to start scheduling pickups.</p>
                    </div>
                <?php else: ?>
                    <div class="address-list">
                        <?php foreach ($addresses as $address): ?>
                            <div class="address-item" data-id="<?php echo $address['address_id']; ?>">
                                <div class="address-header">
                                    <div class="address-title">
                                        <h4><?php echo htmlspecialchars($address['address_label']); ?></h4>
                                        <span class="address-type"><?php echo ucfirst($address['address_type']); ?></span>
                                        <?php if ($address['is_default']): ?>
                                            <span class="default-badge">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="address-actions">
                                        <button class="btn-icon edit-btn" onclick="editAddress(<?php echo $address['address_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$address['is_default']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="set_default">
                                                <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                                <button type="submit" class="btn-icon default-btn" title="Set as default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn-icon delete-btn" onclick="deleteAddress(<?php echo $address['address_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="address-details">
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($address['street_address']); ?></p>
                                    <p><i class="fas fa-city"></i> <?php echo htmlspecialchars($address['city']); ?>
                                        <?php if ($address['postal_code']): ?>, <?php echo htmlspecialchars($address['postal_code']); ?><?php endif; ?>
                                    </p>
                                    <?php if ($address['landmark']): ?>
                                        <p><i class="fas fa-landmark"></i> <?php echo htmlspecialchars($address['landmark']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($address['phone']): ?>
                                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($address['phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Address Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Address</h3>
            <span class="close">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="address_id" id="edit_address_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_address_type">Address Type</label>
                    <select name="address_type" id="edit_address_type" required>
                        <option value="home">Home</option>
                        <option value="office">Office</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_address_label">Address Label *</label>
                    <input type="text" name="address_label" id="edit_address_label" required>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_street_address">Street Address *</label>
                <textarea name="street_address" id="edit_street_address" required></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_city">City *</label>
                    <input type="text" name="city" id="edit_city" required>
                </div>
                <div class="form-group">
                    <label for="edit_postal_code">Postal Code</label>
                    <input type="text" name="postal_code" id="edit_postal_code">
                </div>
            </div>

            <div class="form-group">
                <label for="edit_landmark">Landmark</label>
                <input type="text" name="landmark" id="edit_landmark">
            </div>

            <div class="form-group">
                <label for="edit_phone">Contact Phone</label>
                <input type="tel" name="phone" id="edit_phone">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_default" id="edit_is_default">
                    <span class="checkmark"></span>
                    Set as default address
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Address</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Address</h3>
            <span class="close">&times;</span>
        </div>
        <p>Are you sure you want to delete this address? This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="address_id" id="delete_address_id">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Delete</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
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
    opacity: 0.9;
    font-size: 1.1rem;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
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

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
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
    border: 2px solid #e1e5e9;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.address-list {
    space-y: 1rem;
}

.address-item {
    background: rgba(196, 164, 132, 0.05);
    border: 1px solid rgba(196, 164, 132, 0.1);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.address-title {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.address-title h4 {
    margin: 0;
    color: var(--title-color);
}

.address-type {
    background: rgba(196, 164, 132, 0.2);
    color: var(--accent-color);
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.default-badge {
    background: #28a745;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.address-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    background: none;
    border: none;
    padding: 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-color);
}

.btn-icon:hover {
    background: rgba(196, 164, 132, 0.2);
    color: var(--accent-color);
}

.address-details p {
    margin: 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: var(--text-color);
    font-size: 0.9rem;
}

.address-details i {
    width: 16px;
    text-align: center;
    color: var(--accent-color);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: var(--card-bg);
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    background: var(--accent-color);
    color: var(--dark-bg);
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close {
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
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

@media (max-width: 768px) {
    .content-grid {
        gap: 1.5rem;
    }
    
    .main-content .container {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .address-header {
        flex-direction: column;
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

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}
</style>

<script>
const addresses = <?php echo json_encode($addresses); ?>;

function editAddress(addressId) {
    const address = addresses.find(a => a.address_id == addressId);
    if (!address) return;
    
    document.getElementById('edit_address_id').value = address.address_id;
    document.getElementById('edit_address_type').value = address.address_type;
    document.getElementById('edit_address_label').value = address.address_label;
    document.getElementById('edit_street_address').value = address.street_address;
    document.getElementById('edit_city').value = address.city;
    document.getElementById('edit_postal_code').value = address.postal_code || '';
    document.getElementById('edit_landmark').value = address.landmark || '';
    document.getElementById('edit_phone').value = address.phone || '';
    document.getElementById('edit_is_default').checked = address.is_default == 1;
    
    document.getElementById('editModal').style.display = 'block';
}

function deleteAddress(addressId) {
    document.getElementById('delete_address_id').value = addressId;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
    if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
    }
}

// Close modal with X button
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.onclick = closeModal;
});
</script>

<?php include '../templates/footer.php'; ?>
