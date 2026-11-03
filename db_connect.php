<?php

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "jambo_logistics"
);

if ($conn->connect_error) {
    die("Database connection failed.");
}
?>