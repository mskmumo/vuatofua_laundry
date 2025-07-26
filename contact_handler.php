<?php
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize user input
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $message = sanitize_input($_POST['message']);

    // Validate inputs
    if (empty($name) || empty($email) || empty($message)) {
        redirect('index.php#contact?status=empty_fields');
    }

    // Validate email format
    if (!validate_email($email)) {
        redirect('index.php#contact?status=invalid_email');
    }

    // Establish database connection
    $conn = db_connect();
    
    // Check if contact_messages table exists, create if it doesn't
    $create_table_sql = "CREATE TABLE IF NOT EXISTS contact_messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_sql);

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
    if ($stmt === false) {
        error_log("Failed to prepare statement: " . $conn->error);
        redirect('index.php#contact?status=error');
    }

    $stmt->bind_param("sss", $name, $email, $message);

    try {
        // Execute the statement
        if ($stmt->execute()) {
            // Send confirmation email
            $subject = "Thank you for contacting VuaToFua";
            $email_message = "Dear $name,\n\nThank you for contacting VuaToFua. We have received your message and will get back to you shortly.\n\nBest regards,\nVuaToFua Team";
            
            // Use PHPMailer to send confirmation email
            try {
                send_contact_confirmation_email($email, $name);
            } catch (Exception $e) {
                error_log("Failed to send confirmation email: " . $e->getMessage());
                // Continue execution even if email fails
            }

            // Redirect back to the homepage with a success message
            redirect('index.php#contact?status=success');
        } else {
            error_log("Failed to execute statement: " . $stmt->error);
            redirect('index.php#contact?status=error');
        }
    } catch (Exception $e) {
        error_log("Exception in contact form: " . $e->getMessage());
        redirect('index.php#contact?status=error');
    } finally {
        $stmt->close();
        $conn->close();
    }
} else {
    // If not a POST request, redirect to homepage
    redirect('index.php');
}
?>
