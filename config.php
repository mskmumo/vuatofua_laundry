<?php
// VuaToFua - Main Configuration File

// Start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Configuration ---
// IMPORTANT: Replace with your actual database credentials.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Your DB username
define('DB_PASSWORD', '');     // Your DB password
define('DB_NAME', 'vuatofua');

/**
 * Establishes a connection to the MySQL database using defined credentials.
 *
 * @return mysqli The mysqli connection object.
 */
function db_connect() {
    // Create connection
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        // Log the error for debugging, but don't show details to the user.
        error_log("Database connection failed: " . $conn->connect_error);
        // Display a generic error message.
        die("ERROR: Could not connect to the service. Please try again later.");
    }

    return $conn;
}
