<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "jambo_logistics";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>