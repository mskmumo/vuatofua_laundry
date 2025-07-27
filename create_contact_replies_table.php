<?php
// Migration script to create contact_replies table
require_once 'config.php';

try {
    $conn = db_connect();
    
    // Create contact_replies table
    $sql = "CREATE TABLE IF NOT EXISTS `contact_replies` (
        `reply_id` int(11) NOT NULL AUTO_INCREMENT,
        `contact_id` int(11) NOT NULL,
        `admin_id` int(11) NOT NULL,
        `reply_subject` varchar(255) NOT NULL,
        `reply_message` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`reply_id`),
        KEY `idx_contact_id` (`contact_id`),
        KEY `idx_admin_id` (`admin_id`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `contact_replies_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contact_requests` (`contact_id`) ON DELETE CASCADE,
        CONSTRAINT `contact_replies_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table 'contact_replies' created successfully or already exists.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
