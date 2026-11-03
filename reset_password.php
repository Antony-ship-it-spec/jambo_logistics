<?php

session_start();

require_once "config/database.php";

$message = "";
$success = false;

$token = $_GET["token"] ?? $_POST["token"] ?? "";

$token_hash = "";

if (
    is_string($token) &&
    preg_match('/\A[a-f0-9]{64}\z/', $token)
) {
    $token_hash = hash("sha256", $token);
}

if ($token_hash === "") {

    $message = "This password reset link is invalid.";

} else {

    $stmt = $conn->prepare(
        "SELECT user_id
         FROM password_reset_tokens
         WHERE token_hash = ?
         AND expires_at > NOW()
         LIMIT 1"
    );

    if (!$stmt) {
        die("Unable to validate the reset link.");
    }

    $stmt->bind_param("s", $token_hash);
    $stmt->execute();

    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();

    $stmt->close();

    if (!$reset) {

        $message =
            "This reset link has expired or has already been used.";

    } elseif ($_SERVER["REQUEST_METHOD"] === "POST") {

        $password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if (
            strlen($password) < 12 ||
            strlen($password) > 72
        ) {

            $message =
                "Use a password containing 12 to 72 characters.";

        } elseif ($password !== $confirm_password) {

            $message = "The passwords do not match.";

        } else {

            $user_id = (int) $reset["user_id"];

            // Hash the new password securely
            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $conn->begin_transaction();

            try {

                // Lock and validate the reset token again
                $check = $conn->prepare(
                    "SELECT id
                     FROM password_reset_tokens
                     WHERE token_hash = ?
                     AND user_id = ?
                     AND expires_at > NOW()
                     LIMIT 1
                     FOR UPDATE"
                );

                $check->bind_param(
                    "si",
                    $token_hash,
                    $user_id
                );

                $check->execute();

                $check_result = $check->get_result();
                $valid_token = $check_result->fetch_assoc();

                $check->close();

                if (!$valid_token) {

                    throw new RuntimeException(
                        "The reset link has expired or has already been used."
                    );

                }

                $update = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE id = ?"
                );

                $update->bind_param(
                    "si",
                    $password_hash,
                    $user_id
                );

                $update->execute();

                if ($update->affected_rows !== 1) {

                    $update->close();

                    throw new RuntimeException(
                        "The password could not be updated."
                    );
                }

                $update->close();

                // Invalidate all reset tokens for this user
                $delete = $conn->prepare(
                    "DELETE FROM password_reset_tokens
                     WHERE user_id = ?"
                );

                $delete->bind_param("i", $user_id);
                $delete->execute();
                $delete->close();

                $conn->commit();

                $success = true;

                $message =
                    "Your password has been changed successfully. "
                    . "You can now log in with your new password.";

            } catch (Throwable $e) {

                $conn->rollback();

                error_log($e->getMessage());

                $message = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : "Unable to change your password. Please try again.";
            }
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

    <title>Reset Password | Jambo Logistics</title>

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
        }

        a {
            color: #1d4ed8;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Reset Your Password</h2>

    <p class="message">
        <?= htmlspecialchars(
            $message,
            ENT_QUOTES,
            "UTF-8"
        ); ?>
    </p>

    <?php if (!$success && $token_hash !== "" && isset($reset) && $reset): ?>

        <form method="POST" action="reset_password.php">

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars(
                    $token,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
            >

            <label for="password">New Password</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <button type="submit">
                Change Password
            </button>

        </form>

    <?php endif; ?>

    <p>
        <a href="login.php">Back to Login</a>
    </p>

</div>

</body>

</html>

<?php
$conn->close();
?>