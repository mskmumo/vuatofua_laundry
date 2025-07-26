<?php
// PHPMailer SMTP configuration for Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your-email');
define('SMTP_PASSWORD', 'your-app-password'); // Replace with your app password 
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');

// Email 'From' address
define('MAIL_FROM', 'your-same email-as up there');
define('MAIL_FROM_NAME', 'VuaToFua Laundry Services');

// Debug level (0 for production, 2 for testing)
define('MAIL_SMTP_DEBUG', 0);
?>
