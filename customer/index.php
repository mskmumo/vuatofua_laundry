<?php
require_once '../config.php';
require_once '../functions.php';

secure_session_start();

// Redirect to appropriate page based on login status
if (is_logged_in()) {
    redirect('../dashboard.php');
} else {
    redirect('../login.php');
}
?>
