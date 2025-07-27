<?php
require_once 'config.php';
require_once 'functions.php';

secure_session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get and sanitize input data
$name = sanitize_input($_POST['name'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$phone = sanitize_input($_POST['phone'] ?? '');
$subject = sanitize_input($_POST['subject'] ?? '');
$message = sanitize_input($_POST['message'] ?? '');

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

if (empty($subject)) {
    $errors[] = 'Subject is required';
}

if (empty($message)) {
    $errors[] = 'Message is required';
} elseif (strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters long';
}

if (!empty($phone) && !preg_match('/^[+]?[\d\s\-\(\)]{10,}$/', $phone)) {
    $errors[] = 'Please enter a valid phone number';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $conn = db_connect();
    
    // Check if user is logged in to link contact to user account
    $user_id = null;
    if (is_logged_in()) {
        $user_id = $_SESSION['user_id'];
    }
    
    // Determine priority based on keywords in subject/message
    $priority = 'medium';
    $urgent_keywords = ['urgent', 'emergency', 'asap', 'immediately', 'critical'];
    $high_keywords = ['important', 'priority', 'soon', 'quickly'];
    
    $combined_text = strtolower($subject . ' ' . $message);
    
    foreach ($urgent_keywords as $keyword) {
        if (strpos($combined_text, $keyword) !== false) {
            $priority = 'urgent';
            break;
        }
    }
    
    if ($priority === 'medium') {
        foreach ($high_keywords as $keyword) {
            if (strpos($combined_text, $keyword) !== false) {
                $priority = 'high';
                break;
            }
        }
    }
    
    // Insert contact request
    $stmt = $conn->prepare("
        INSERT INTO contact_requests (user_id, name, email, phone, subject, message, priority) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("issssss", $user_id, $name, $email, $phone, $subject, $message, $priority);
    
    if ($stmt->execute()) {
        $contact_id = $conn->insert_id;
        
        // Create notification for admins about new contact request
        $admin_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin'");
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        
        $notification_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, related_type) 
            VALUES (?, 'contact_read', ?, ?, ?, 'contact')
        ");
        
        while ($admin = $admin_result->fetch_assoc()) {
            $title = "New Contact Request";
            $notification_message = "New contact request from {$name} with subject: {$subject}";
            $notification_stmt->bind_param("issi", $admin['user_id'], $title, $notification_message, $contact_id);
            $notification_stmt->execute();
        }
        
        $stmt->close();
        $admin_stmt->close();
        $notification_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thank you for contacting us! We will get back to you soon.',
            'contact_id' => $contact_id
        ]);
    } else {
        throw new Exception('Failed to save contact request');
    }
    
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
