<?php
// db_config.php
// Database configuration settings

define('DB_SERVER', 'localhost'); // Your database server (usually localhost)
define('DB_USERNAME', 'root');    // Your database username (default for XAMPP is often 'root')
define('DB_PASSWORD', '');           // Your database password (default for XAMPP is often empty)
define('DB_NAME', 'exceed');   // Your database name (make sure this matches the DB you created)

// Attempt to connect to MySQL database
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($mysqli === false){
    // If connection fails, stop script execution and display an error.
    // In a production environment, you'd handle this more gracefully (e.g., log the error).
    die("ERROR: Could not connect. " . $mysqli->connect_error);
}

// Optional: Set character set to utf8mb4 for full Unicode support
if (!$mysqli->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $mysqli->error);
    // For simplicity, we'll not die here but it's good to be aware of.
}
?>
