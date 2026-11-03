<?php

require_once "config/database.php";

$username = "admin";
$password = "admin123";
$full_name = "System Administrator";
$role = "admin";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, password, full_name, role)
        VALUES (?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssss",
    $username,
    $hashed_password,
    $full_name,
    $role
);

if ($stmt->execute()) {
    echo "Admin user created successfully.<br>";
    echo "Username: admin<br>";
    echo "Password: admin123";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>