<?php

session_start();

require_once "config/database.php";

$message = "";
$reset_link = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Enter a valid email address.";

    } else {

        // Find the account using its registered email
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$stmt) {
            die("Database query failed.");
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {

            // Generate a secure, random token
            $token = bin2hex(random_bytes(32));

            // Store only the token hash
            $token_hash = hash("sha256", $token);

            // Token expires after 15 minutes
            $expires_at = date(
                "Y-m-d H:i:s",
                time() + 900
            );

            $user_id = (int) $user["id"];

            $conn->begin_transaction();

            try {

                // Invalidate existing reset tokens for this user
                $delete = $conn->prepare(
                    "DELETE FROM password_reset_tokens
                     WHERE user_id = ?"
                );

                $delete->bind_param("i", $user_id);
                $delete->execute();
                $delete->close();

                // Save the new token
                $insert = $conn->prepare(
                    "INSERT INTO password_reset_tokens
                     (user_id, token_hash, expires_at)
                     VALUES (?, ?, ?)"
                );

                $insert->bind_param(
                    "iss",
                    $user_id,
                    $token_hash,
                    $expires_at
                );

                $insert->execute();
                $insert->close();

                $conn->commit();

                // Local development only:
                // Display the reset link for testing.
                $reset_link =
                    "http://localhost/jambo_logistics/reset_password.php?token="
                    . urlencode($token);

            } catch (Throwable $e) {

                $conn->rollback();

                error_log($e->getMessage());

                $message =
                    "Unable to process your request. Please try again.";
            }

        }

        $stmt->close();

        if ($message === "") {
            $message =
                "If an account matches that email address, "
                . "a password reset link can be generated.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Forgot Password | Jambo Logistics</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 420px;
            margin: 60px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #1d4ed8;
        }

        label {
            display: block;
            margin-top: 18px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-top: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .message {
            margin-top: 20px;
            overflow-wrap: anywhere;
        }

        a {
            color: #1d4ed8;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Forgot Password?</h2>

    <p>
        Enter the email address registered to your
        Jambo Logistics account.
    </p>

    <form method="POST" action="forgot_password.php">

        <label for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <button type="submit">
            Reset Password
        </button>

    </form>

    <?php if ($message !== ""): ?>

        <div class="message">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>
        </div>

    <?php endif; ?>

    <?php if ($reset_link !== ""): ?>

        <p>
            <strong>Local testing link:</strong>
        </p>

        <p>
            <a href="<?= htmlspecialchars(
                $reset_link,
                ENT_QUOTES,
                "UTF-8"
            ); ?>">
                Open Password Reset Page
            </a>
        </p>

        <p>
            This link is displayed for local testing only.
        </p>

    <?php endif; ?>

    <p>
        <a href="login.php">Back to Login</a>
    </p>

</div>

</body>

</html>