<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "pass";
$dbname = "oyenishinningstar";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");
?>
