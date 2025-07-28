<?php
require_once '../config.php';
require_once '../functions.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

$conn = db_connect();

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$time_slot_filter = isset($_GET['time_slot']) ? $_GET['time_slot'] : 'all';

// Build the query with filters
$where_conditions = [];
$params = [];
$param_types = '';

// Date filter
if ($date_filter) {
    $where_conditions[] = "ps.pickup_date = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

// Status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "ps.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

// Time slot filter
if ($time_slot_filter !== 'all') {
    $where_conditions[] = "ps.pickup_time_slot = ?";
    $params[] = $time_slot_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch pickup schedules with user and address information
$sql = "SELECT ps.*, 
               u.name as customer_name, 
               u.phone as customer_phone,
               ua.address_label,
               ua.street_address,
               ua.city,
               ua.phone as address_phone
        FROM pickup_schedules ps
        JOIN users u ON ps.user_id = u.user_id
        JOIN user_addresses ua ON ps.address_id = ua.address_id
        $where_clause
        ORDER BY ps.pickup_date ASC, 
                 FIELD(ps.pickup_time_slot, 'morning', 'afternoon', 'evening') ASC,
                 ps.created_at ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$pickups = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics for the selected date
$stats_sql = "SELECT 
                COUNT(*) as total_pickups,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN pickup_time_slot = 'morning' THEN 1 ELSE 0 END) as morning_slots,
                SUM(CASE WHEN pickup_time_slot = 'afternoon' THEN 1 ELSE 0 END) as afternoon_slots,
                SUM(CASE WHEN pickup_time_slot = 'evening' THEN 1 ELSE 0 END) as evening_slots
              FROM pickup_schedules 
              WHERE pickup_date = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('s', $date_filter);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$conn->close();

$page_title = 'Manage Pickups';
$is_admin_page = true;
require_once '../templates/header.php';
?>

<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-truck"></i> Pickup Schedule Management</h1>
            <p>View and organize scheduled pickups for efficient delivery planning</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Pickups</h3>
                    <p class="stat-number"><?php echo $stats['total_pickups']; ?></p>
                    <small>for <?php echo date('M j, Y', strtotime($date_filter)); ?></small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Time Slots</h3>
                    <p class="stat-breakdown">
                        <span class="time-slot">🌅 <?php echo $stats['morning_slots']; ?></span>
                        <span class="time-slot">☀️ <?php echo $stats['afternoon_slots']; ?></span>
                        <span class="time-slot">🌆 <?php echo $stats['evening_slots']; ?></span>
                    </p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <h3>Status Overview</h3>
                    <p class="stat-breakdown">
                        <span class="status-count scheduled"><?php echo $stats['scheduled']; ?> Scheduled</span>
                        <span class="status-count confirmed"><?php echo $stats['confirmed']; ?> Confirmed</span>
                        <span class="status-count completed"><?php echo $stats['completed']; ?> Completed</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Pickups</h3>
            </div>
            <div class="card-content">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="date">Date</label>
                            <input type="date" 
                                   id="date" 
                                   name="date" 
                                   value="<?php echo htmlspecialchars($date_filter); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="time_slot">Time Slot</label>
                            <select id="time_slot" name="time_slot">
                                <option value="all" <?php echo $time_slot_filter === 'all' ? 'selected' : ''; ?>>All Times</option>
                                <option value="morning" <?php echo $time_slot_filter === 'morning' ? 'selected' : ''; ?>>Morning (8AM-12PM)</option>
                                <option value="afternoon" <?php echo $time_slot_filter === 'afternoon' ? 'selected' : ''; ?>>Afternoon (12PM-4PM)</option>
                                <option value="evening" <?php echo $time_slot_filter === 'evening' ? 'selected' : ''; ?>>Evening (4PM-8PM)</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="manage_pickups.php" class="btn btn-secondary">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pickup List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Scheduled Pickups 
                    <?php if ($date_filter): ?>
                        - <?php echo date('F j, Y', strtotime($date_filter)); ?>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-content">
                <?php if (empty($pickups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Pickups Scheduled</h4>
                        <p>No pickups found for the selected filters.</p>
                    </div>
                <?php else: ?>
                    <div class="pickup-timeline">
                        <?php 
                        $current_time_slot = '';
                        foreach ($pickups as $pickup): 
                            if ($pickup['pickup_time_slot'] !== $current_time_slot):
                                $current_time_slot = $pickup['pickup_time_slot'];
                                $slot_icons = [
                                    'morning' => '🌅',
                                    'afternoon' => '☀️', 
                                    'evening' => '🌆'
                                ];
                        ?>
                            <div class="time-slot-header">
                                <h4><?php echo $slot_icons[$current_time_slot]; ?> <?php echo ucfirst($current_time_slot); ?> Pickups</h4>
                                <span class="time-range"><?php echo $pickup['pickup_time_range']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pickup-item">
                            <div class="pickup-header">
                                <div class="pickup-id">
                                    <strong>Pickup #<?php echo $pickup['pickup_id']; ?></strong>
                                    <span class="status-badge status-<?php echo $pickup['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $pickup['status'])); ?>
                                    </span>
                                </div>
                                <div class="pickup-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $pickup['pickup_time_range']; ?>
                                </div>
                            </div>
                            
                            <div class="pickup-details">
                                <div class="customer-info">
                                    <h5><i class="fas fa-user"></i> <?php echo htmlspecialchars($pickup['customer_name']); ?></h5>
                                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($pickup['customer_phone']); ?></p>
                                </div>
                                
                                <div class="address-info">
                                    <h6><i class="fas fa-map-marker-alt"></i> Pickup Address</h6>
                                    <p><strong><?php echo htmlspecialchars($pickup['address_label']); ?></strong></p>
                                    <p><?php echo htmlspecialchars($pickup['street_address']); ?>, <?php echo htmlspecialchars($pickup['city']); ?></p>
                                    <?php if ($pickup['address_phone']): ?>
                                        <p><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($pickup['address_phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="service-info">
                                    <p><i class="fas fa-tshirt"></i> <strong>Service:</strong> <?php echo htmlspecialchars($pickup['service_type']); ?></p>
                                    <p><i class="fas fa-boxes"></i> <strong>Estimated Items:</strong> <?php echo $pickup['estimated_items']; ?></p>
                                    <?php if ($pickup['special_instructions']): ?>
                                        <p><i class="fas fa-sticky-note"></i> <strong>Instructions:</strong> <?php echo htmlspecialchars($pickup['special_instructions']); ?></p>
                                    <?php endif; ?>
                                </div>
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
/* Pickup Management Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(26, 37, 43, 0.8);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(196, 164, 132, 0.15);
}

.stat-icon {
    font-size: 2.5rem;
    color: var(--accent-color);
    min-width: 60px;
    text-align: center;
}

.stat-content h3 {
    color: var(--title-color);
    font-size: 1rem;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-color);
    margin: 0;
}

.stat-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    margin: 0;
}

.time-slot {
    font-size: 0.9rem;
    color: var(--text-color);
}

.status-count {
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-weight: 500;
}

.status-count.scheduled {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.status-count.confirmed {
    background: rgba(0, 123, 255, 0.2);
    color: #007bff;
}

.status-count.completed {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

/* Filter Form */
.filter-form {
    width: 100%;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    color: var(--title-color);
    font-weight: 600;
    font-size: 0.9rem;
}

.filter-group input,
.filter-group select {
    padding: 0.8rem;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: rgba(19, 28, 33, 0.8);
    color: var(--text-color);
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(196, 164, 132, 0.1);
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

/* Pickup Timeline */
.pickup-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.time-slot-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 2px solid var(--border-color);
    margin-top: 1.5rem;
}

.time-slot-header:first-child {
    margin-top: 0;
}

.time-slot-header h4 {
    color: var(--title-color);
    font-size: 1.2rem;
    margin: 0;
}

.time-range {
    color: var(--accent-color);
    font-weight: 600;
    font-size: 0.9rem;
}

.pickup-item {
    background: rgba(196, 164, 132, 0.05);
    border: 1px solid rgba(196, 164, 132, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.pickup-item:hover {
    background: rgba(196, 164, 132, 0.08);
    border-color: var(--accent-color);
    transform: translateY(-1px);
}

.pickup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(196, 164, 132, 0.1);
}

.pickup-id {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pickup-id strong {
    color: var(--title-color);
    font-size: 1.1rem;
}

.pickup-time {
    color: var(--accent-color);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pickup-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.customer-info h5,
.address-info h6 {
    color: var(--title-color);
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.customer-info p,
.address-info p,
.service-info p {
    color: var(--text-color);
    margin: 0.3rem 0;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.customer-info i,
.address-info i,
.service-info i {
    color: var(--accent-color);
    width: 16px;
    text-align: center;
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

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        justify-content: center;
    }
    
    .pickup-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .pickup-details {
        grid-template-columns: 1fr;
    }
    
    .time-slot-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php include '../templates/footer.php'; ?>
