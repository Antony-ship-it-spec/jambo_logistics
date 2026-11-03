<?php
session_start();

require_once "config/database.php";

// Only administrators can manage users
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "error";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Register a new driver or client
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $submitted_token)) {

        $message = "Invalid request. Please refresh the page and try again.";

    } else {

        $full_name = trim($_POST["full_name"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "";

        // Validate the information
        if (
            $full_name === "" ||
            $username === "" ||
            $password === "" ||
            !in_array($role, ["driver", "client"], true)
        ) {

            $message = "Please fill in all fields and select a valid role.";

        } elseif (strlen($full_name) > 100) {

            $message = "Full name must not exceed 100 characters.";

        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {

            $message = "Username must contain 3–50 letters, numbers, dots, underscores or hyphens.";

        } elseif (strlen($password) < 8) {

            $message = "Password must contain at least 8 characters.";

        } else {

            // Check whether the username already exists
            $check = $conn->prepare(
                "SELECT id FROM users WHERE username = ?"
            );

            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $message = "This username already exists. Choose another username.";

            } else {

                // Hash the password before saving it
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert the new user
                $insert = $conn->prepare(
                    "INSERT INTO users
                    (username, password, full_name, role)
                    VALUES (?, ?, ?, ?)"
                );

                $insert->bind_param(
                    "ssss",
                    $username,
                    $hashed_password,
                    $full_name,
                    $role
                );

                if ($insert->execute()) {

                    $message = ucfirst($role) .
                        " account registered successfully.";

                    $message_type = "success";

                    // Clear the form after successful registration
                    $_POST = [];

                    // Generate a new token
                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));

                } else {

                    $message = "Failed to register the account. Please try again.";
                }

                $insert->close();
            }

            $check->close();
        }
    }
}

// Retrieve users without exposing their passwords
$sql = "SELECT id, username, full_name, role, created_at
        FROM users
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to retrieve users: " . $conn->error);
}

// Escape output before displaying it
function escapeOutput($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Users | Jambo Logistics</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .users-container {
            padding: 25px;
        }

        .users-card {
            background: white;
            padding: 25px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .users-card h2 {
            margin-bottom: 20px;
        }

        .users-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }

        .register-button {
            grid-column: 1 / -1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background: #087f5b;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .register-button:hover {
            background: #066747;
        }

        .users-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .users-table th {
            background: #f1f3f5;
        }

        .role-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            background: #e7f5ff;
            color: #1864ab;
            font-size: 13px;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .message.success {
            background: #d3f9d8;
            color: #2b8a3e;
        }

        .message.error {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #087f5b;
        }

        @media (max-width: 650px) {
            .users-form {
                grid-template-columns: 1fr;
            }

            .register-button {
                grid-column: auto;
            }

            .users-container {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<div class="users-container">

    <a class="back-link" href="admin_dashboard.php">
        &larr; Back to Admin Dashboard
    </a>

    <h1>Manage Users</h1>

    <p>
        Register drivers and clients and view existing user accounts.
    </p>

    <?php if ($message !== ""): ?>

        <div class="message <?= escapeOutput($message_type) ?>">
            <?= escapeOutput($message) ?>
        </div>

    <?php endif; ?>

    <!-- Register User Form -->
    <div class="users-card">

        <h2>Register New User</h2>

        <form method="POST" action="users.php" class="users-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeOutput($_SESSION["csrf_token"]) ?>"
            >

            <div class="form-group">

                <label for="full_name">Full Name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="100"
                    value="<?= escapeOutput($_POST["full_name"] ?? "") ?>"
                    placeholder="Enter full name"
                    required
                >

            </div>

            <div class="form-group">

                <label for="username">Username</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    minlength="3"
                    maxlength="50"
                    pattern="[a-zA-Z0-9_.-]+"
                    value="<?= escapeOutput($_POST["username"] ?? "") ?>"
                    placeholder="Enter username"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    placeholder="At least 8 characters"
                    autocomplete="new-password"
                    required
                >

            </div>

            <div class="form-group">

                <label for="role">User Role</label>

                <select id="role" name="role" required>

                    <option value="">Select Role</option>

                    <option value="driver"
                        <?= (($_POST["role"] ?? "") === "driver")
                            ? "selected" : "" ?>>
                        Driver
                    </option>

                    <option value="client"
                        <?= (($_POST["role"] ?? "") === "client")
                            ? "selected" : "" ?>>
                        Client
                    </option>

                </select>

            </div>

            <button type="submit" class="register-button">
                Register User
            </button>

        </form>

    </div>

    <!-- Registered Users Table -->
    <div class="users-card">

        <h2>Registered Users</h2>

        <div class="users-table-wrapper">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Date Registered</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($user = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= escapeOutput($user["id"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($user["full_name"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($user["username"]) ?>
                            </td>

                            <td>
                                <span class="role-badge">
                                    <?= escapeOutput(ucfirst($user["role"])) ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeOutput($user["created_at"]) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">No users found.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>