
<?php
// Database connection for Purple Blend

// Connection settings for the local Purple Blend MySQL database.
$host = "localhost";
$username = "root";
$password = "";
$database = "purple_blend";

$conn = new mysqli($host, $username, $password, $database);

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Support special characters
$conn->set_charset("utf8mb4");
?>
