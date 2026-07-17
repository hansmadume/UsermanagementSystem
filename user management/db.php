<?php
// Ensure errors are reported clearly during debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $host = '127.0.0.1'; // Using IP is faster and more stable than 'localhost' on AMPPS
    $db_user = 'root';   // AMPPS default MySQL username
    
    // FIXED: Fallback to 'mysql' instead of ''
    $db_pass = getenv('DB_PASSWORD') ?: 'mysql';
    
    // FIXED: Fallback to your actual database name (change 'user_management' if yours is named differently)
    $db_name = getenv('DB_NAME') ?: 'login_system';

    $conn = new mysqli($host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

} catch (Exception $e) {
    // Stop the 500 crash and print the actual message on screen
    die('Database Connection Failed: ' . $e->getMessage());
}
