<?php

session_start();

require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {

        $error = "Please enter both username and password.";

    } else {

        $sql = "SELECT id, username, password, full_name, role
                FROM users
                WHERE username = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                // Redirect users according to their roles.
                switch ($user["role"]) {

                    case "admin":
                        header("Location: admin_dashboard.php");
                        break;

                    case "driver":
                        header("Location: driver_dashboard.php");
                        break;

                    case "client":
                        header("Location: client_dashboard.php");
                        break;

                    default:
                        session_destroy();
                        header("Location: login.php");
                        exit();
                }

                exit();

            } else {

                $error = "Invalid username or password.";

            }

        } else {

            $error = "Invalid username or password.";

        }

        $stmt->close();
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Jambo Logistics</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 400px;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo h1 {
            color: #1e3a8a;
            font-size: 25px;
        }

        .logo p {
            color: #64748b;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            border: none;
            background: #2563eb;
            color: white;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 18px;
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 13px;
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="logo">

        <h1>Jambo Logistics</h1>

        <p>Cloud Delivery Management System</p>

    </div>

    <?php if (!empty($error)): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="">

        <div class="form-group">

            <label for="username">Username</label>

            <input
                type="text"
                id="username"
                name="username"
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
                placeholder="Enter password"
                required
            >

        </div>

        <button type="submit" class="login-btn">
            Sign In
        </button>

    </form>

    <p style="text-align: center;">
        <a href="forgot_password.php">
            Forgot Password?
        </a>
    </p>

    <div class="footer">
        Jambo Logistics Ltd
    </div>

</div>

</body>

</html>