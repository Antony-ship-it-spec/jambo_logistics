<?php

session_start();

require_once "config/database.php";

// Allow only administrators
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

$success_message = "";
$error_message = "";

// Get available drivers
$drivers_sql = "
    SELECT id, username, full_name
    FROM users
    WHERE role = 'driver'
    ORDER BY full_name ASC
";

$drivers_result = $conn->query($drivers_sql);

if (!$drivers_result) {
    die("Unable to retrieve drivers: " . $conn->error);
}

// Get available clients
$clients_sql = "
    SELECT id, username, full_name
    FROM users
    WHERE role = 'client'
    ORDER BY full_name ASC
";

$clients_result = $conn->query($clients_sql);

if (!$clients_result) {
    die("Unable to retrieve clients: " . $conn->error);
}

// Process submitted form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $delivery_id = trim($_POST["delivery_id"] ?? "");
    $driver_id_input = trim($_POST["driver_id"] ?? "");
    $driver_name_input = trim($_POST["driver_name"] ?? "");
    $client_id_input = trim($_POST["client_id"] ?? "");
    $customer_name = trim($_POST["customer_name"] ?? "");
    $customer_phone = trim($_POST["customer_phone"] ?? "");
    $delivery_location = trim($_POST["delivery_location"] ?? "");
    $delivery_date = trim($_POST["delivery_date"] ?? "");
    $status = trim($_POST["status"] ?? "Pending");
    $remarks = trim($_POST["remarks"] ?? "");

    $allowed_statuses = [
        "Pending",
        "In Transit",
        "Delivered",
        "Cancelled"
    ];

    // Validate required fields
    if (
        $delivery_id === "" ||
        $driver_id_input === "" ||
        $driver_name_input === "" ||
        $client_id_input === "" ||
        $customer_name === "" ||
        $customer_phone === "" ||
        $delivery_location === "" ||
        $delivery_date === ""
    ) {

        $error_message = "Please complete all required fields.";

    } elseif (
        !ctype_digit($driver_id_input) ||
        !ctype_digit($client_id_input)
    ) {

        $error_message = "Driver ID and Client ID must be valid numeric IDs.";

    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error_message = "Please select a valid delivery status.";

    } else {

        $driver_id = (int) $driver_id_input;
        $client_id = (int) $client_id_input;

        // Verify driver ID and name match an existing driver
        $driver_check = $conn->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND full_name = ?
              AND role = 'driver'
            LIMIT 1
        ");

        $driver_check->bind_param(
            "is",
            $driver_id,
            $driver_name_input
        );

        $driver_check->execute();
        $driver_result = $driver_check->get_result();

        if ($driver_result->num_rows === 0) {

            $error_message =
                "The Driver ID and Driver Name do not match an existing driver.";

        }

        $driver_check->close();

        // Verify selected client exists
        if ($error_message === "") {

            $client_check = $conn->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                  AND role = 'client'
                LIMIT 1
            ");

            $client_check->bind_param("i", $client_id);
            $client_check->execute();

            $client_result = $client_check->get_result();

            if ($client_result->num_rows === 0) {

                $error_message =
                    "The selected client does not exist.";

            }

            $client_check->close();
        }

        // Validate date format
        if ($error_message === "") {

            $date_object = DateTime::createFromFormat(
                "!Y-m-d",
                $delivery_date
            );

            if (
                !$date_object ||
                $date_object->format("Y-m-d") !== $delivery_date
            ) {

                $error_message = "Please enter a valid delivery date.";

            }
        }

        // Save delivery
        if ($error_message === "") {

            $insert = $conn->prepare("
                INSERT INTO deliveries (
                    delivery_id,
                    driver_id,
                    client_id,
                    customer_name,
                    customer_phone,
                    delivery_location,
                    delivery_date,
                    status,
                    remarks,
                    sync_status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            if (!$insert) {

                $error_message =
                    "Unable to prepare the delivery record.";

            } else {

                $insert->bind_param(
                    "siissssss",
                    $delivery_id,
                    $driver_id,
                    $client_id,
                    $customer_name,
                    $customer_phone,
                    $delivery_location,
                    $delivery_date,
                    $status,
                    $remarks
                );

                if ($insert->execute()) {

                    $success_message =
                        "Delivery added successfully!";

                } else {

                    if ($insert->errno === 1062) {

                        $error_message =
                            "This Delivery ID already exists. Use a different ID.";

                    } else {

                        $error_message =
                            "Failed to add delivery. Please check your database structure.";
                    }
                }

                $insert->close();
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

    <title>Add Delivery | Jambo Logistics</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        .form-container {
            max-width: 900px;
            margin: 25px auto;
            padding: 25px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #cccccc;
            border-radius: 5px;
            font-size: 15px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .submit-button {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 650px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }
        }

    </style>

</head>

<body>

<div class="dashboard-container">

    <aside class="sidebar">

        <h2>Jambo Logistics</h2>

        <p>Admin Panel</p>

        <nav>

            <a href="admin_dashboard.php">
                Dashboard
            </a>

            <a href="users.php">
                Manage Users
            </a>

            <a href="add_delivery.php" class="active">
                Add Delivery
            </a>

            <a href="view_deliveries.php">
                View Deliveries
            </a>

            <a href="reports.php">
                Reports
            </a>

            <a href="logout.php">
                Logout
            </a>

        </nav>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div>

                <h1>Add New Delivery</h1>

                <p>Enter the delivery information below.</p>

            </div>

        </header>

        <section class="form-container">

            <?php if ($success_message !== ""): ?>

                <div class="message success">

                    <?= htmlspecialchars(
                        $success_message,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                    <br><br>

                    <a href="view_deliveries.php">
                        View Delivery Records
                    </a>

                </div>

            <?php endif; ?>

            <?php if ($error_message !== ""): ?>

                <div class="message error">

                    <?= htmlspecialchars(
                        $error_message,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </div>

            <?php endif; ?>

            <form method="POST" action="add_delivery.php">

                <div class="form-grid">

                    <div class="form-group">

                        <label for="delivery_id">
                            Delivery ID *
                        </label>

                        <input
                            type="text"
                            id="delivery_id"
                            name="delivery_id"
                            placeholder="Example: DEL001"
                            value="<?= htmlspecialchars(
                                $_POST["delivery_id"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="driver_id">
                            Driver ID *
                        </label>

                        <input
                            type="number"
                            id="driver_id"
                            name="driver_id"
                            min="1"
                            placeholder="Enter driver's user ID"
                            value="<?= htmlspecialchars(
                                $_POST["driver_id"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="driver_name">
                            Driver Name *
                        </label>

                        <input
                            type="text"
                            id="driver_name"
                            name="driver_name"
                            placeholder="Enter driver's full name"
                            value="<?= htmlspecialchars(
                                $_POST["driver_name"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="client_id">
                            Client *
                        </label>

                        <select
                            id="client_id"
                            name="client_id"
                            required
                        >

                            <option value="">
                                Select a client
                            </option>

                            <?php while (
                                $client = $clients_result->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?= (int) $client["id"]; ?>"
                                    <?= (
                                        isset($_POST["client_id"]) &&
                                        (string) $_POST["client_id"] ===
                                        (string) $client["id"]
                                    ) ? "selected" : ""; ?>
                                >

                                    <?= htmlspecialchars(
                                        $client["full_name"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                    (ID: <?= (int) $client["id"]; ?>)

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="customer_name">
                            Customer Name *
                        </label>

                        <input
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            value="<?= htmlspecialchars(
                                $_POST["customer_name"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="customer_phone">
                            Customer Phone *
                        </label>

                        <input
                            type="tel"
                            id="customer_phone"
                            name="customer_phone"
                            placeholder="Enter phone number"
                            value="<?= htmlspecialchars(
                                $_POST["customer_phone"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group full-width">

                        <label for="delivery_location">
                            Delivery Location *
                        </label>

                        <input
                            type="text"
                            id="delivery_location"
                            name="delivery_location"
                            placeholder="Enter destination"
                            value="<?= htmlspecialchars(
                                $_POST["delivery_location"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="delivery_date">
                            Delivery Date *
                        </label>

                        <input
                            type="date"
                            id="delivery_date"
                            name="delivery_date"
                            value="<?= htmlspecialchars(
                                $_POST["delivery_date"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="status">
                            Delivery Status *
                        </label>

                        <select id="status" name="status" required>

                            <?php
                            $selected_status =
                                $_POST["status"] ?? "Pending";

                            foreach (
                                [
                                    "Pending",
                                    "In Transit",
                                    "Delivered",
                                    "Cancelled"
                                ] as $option
                            ):
                            ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $option,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    <?= $selected_status === $option
                                        ? "selected"
                                        : ""; ?>
                                >

                                    <?= htmlspecialchars(
                                        $option,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group full-width">

                        <label for="remarks">
                            Remarks
                        </label>

                        <textarea
                            id="remarks"
                            name="remarks"
                            rows="4"
                            placeholder="Enter additional information"
                        ><?= htmlspecialchars(
                            $_POST["remarks"] ?? "",
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?></textarea>

                    </div>

                    <div class="form-group full-width">

                        <button
                            type="submit"
                            class="submit-button action-button"
                        >
                            Save Delivery
                        </button>

                    </div>

                </div>

            </form>

        </section>

    </main>

</div>

</body>

</html>

<?php
$conn->close();
?>