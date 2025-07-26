<?php
// VuaToFua - Database Configuration
// IMPORTANT: Replace with your actual database credentials.

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Your DB username
define('DB_PASSWORD', ''); // Your DB password
define('DB_NAME', 'vuatofua');

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
