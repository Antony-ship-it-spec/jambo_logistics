<?php

session_start();

require_once "config/database.php";

// Only administrators may edit deliveries.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Validate the database record ID.
$record_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$record_id || $record_id < 1) {
    die("Invalid delivery record ID.");
}

$success_message = "";
$error_message = "";

// Fetch available drivers.
$drivers_result = $conn->query("
    SELECT id, username, full_name
    FROM users
    WHERE role = 'driver'
    ORDER BY full_name ASC
");

if (!$drivers_result) {
    die("Unable to retrieve drivers.");
}

$drivers = $drivers_result->fetch_all(MYSQLI_ASSOC);

// Fetch available clients.
$clients_result = $conn->query("
    SELECT id, username, full_name
    FROM users
    WHERE role = 'client'
    ORDER BY full_name ASC
");

if (!$clients_result) {
    die("Unable to retrieve clients.");
}

$clients = $clients_result->fetch_all(MYSQLI_ASSOC);

// Retrieve the existing delivery.
$select = $conn->prepare("
    SELECT *
    FROM deliveries
    WHERE id = ?
    LIMIT 1
");

$select->bind_param("i", $record_id);
$select->execute();

$result = $select->get_result();
$delivery = $result->fetch_assoc();

$select->close();

if (!$delivery) {
    die("Delivery record not found.");
}

// Process the update form.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $delivery_id = trim($_POST["delivery_id"] ?? "");
    $driver_id_input = trim($_POST["driver_id"] ?? "");
    $driver_name = trim($_POST["driver_name"] ?? "");
    $client_id_input = trim($_POST["client_id"] ?? "");
    $customer_name = trim($_POST["customer_name"] ?? "");
    $customer_phone = trim($_POST["customer_phone"] ?? "");
    $delivery_location = trim($_POST["delivery_location"] ?? "");
    $delivery_date = trim($_POST["delivery_date"] ?? "");
    $status = trim($_POST["status"] ?? "");
    $remarks = trim($_POST["remarks"] ?? "");

    $allowed_statuses = [
        "Pending",
        "In Transit",
        "Delivered",
        "Cancelled"
    ];

    // Validate required fields.
    if (
        $delivery_id === "" ||
        $driver_id_input === "" ||
        $driver_name === "" ||
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

        $error_message = "Driver ID and Client must be valid.";

    } elseif (
        !in_array($status, $allowed_statuses, true)
    ) {

        $error_message = "Please select a valid delivery status.";

    } else {

        $driver_id = (int) $driver_id_input;
        $client_id = (int) $client_id_input;

        // Verify the driver ID and name match.
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
            $driver_name
        );

        $driver_check->execute();
        $driver_result = $driver_check->get_result();

        if ($driver_result->num_rows === 0) {
            $error_message =
                "Driver ID and Driver Name do not match.";
        }

        $driver_check->close();

        // Verify the client account.
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
                    "The selected client account does not exist.";
            }

            $client_check->close();
        }

        // Validate the date.
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

        // Update the delivery if all checks pass.
        if ($error_message === "") {

            $log_status = "Pending";
            $log_message =
                "Delivery record updated by administrator. " .
                "Synchronization is required.";

            $conn->begin_transaction();

            try {
                $update = $conn->prepare("
                    UPDATE deliveries
                    SET
                        delivery_id = ?,
                        driver_id = ?,
                        client_id = ?,
                        customer_name = ?,
                        customer_phone = ?,
                        delivery_location = ?,
                        delivery_date = ?,
                        status = ?,
                        remarks = ?,
                        sync_status = 'Pending',
                        version_number = version_number + 1
                    WHERE id = ?
                ");

                if (!$update) {
                    throw new Exception(
                        "Unable to prepare the delivery update."
                    );
                }

                $update->bind_param(
                    "siissssssi",
                    $delivery_id,
                    $driver_id,
                    $client_id,
                    $customer_name,
                    $customer_phone,
                    $delivery_location,
                    $delivery_date,
                    $status,
                    $remarks,
                    $record_id
                );

                if (!$update->execute()) {
                    throw new Exception(
                        "Unable to update delivery.",
                        $update->errno
                    );
                }

                $update->close();

                $log = $conn->prepare("
                    INSERT INTO sync_logs
                        (delivery_id, sync_status, message)
                    VALUES (?, ?, ?)
                ");

                if (!$log) {
                    throw new Exception(
                        "Unable to prepare synchronization log."
                    );
                }

                $log->bind_param(
                    "iss",
                    $record_id,
                    $log_status,
                    $log_message
                );

                if (!$log->execute()) {
                    throw new Exception(
                        "Unable to save synchronization log.",
                        $log->errno
                    );
                }

                $log->close();
                $conn->commit();

                header("Location: view_deliveries.php?updated=1");
                exit();

            } catch (Throwable $e) {
                $conn->rollback();

                error_log(
                    "Delivery update error: " . $e->getMessage()
                );

                if ($e->getCode() === 1062) {
                    $error_message =
                        "That Delivery ID is already in use.";
                } else {
                    $error_message =
                        "Unable to update the delivery. " .
                        "Please check the information and try again.";
                }
            }
        }
    }

    // Preserve submitted values when validation fails.
    if ($error_message !== "") {

        $delivery["delivery_id"] = $delivery_id;
        $delivery["driver_id"] = $driver_id_input;
        $delivery["client_id"] = $client_id_input;
        $delivery["customer_name"] = $customer_name;
        $delivery["customer_phone"] = $customer_phone;
        $delivery["delivery_location"] = $delivery_location;
        $delivery["delivery_date"] = $delivery_date;
        $delivery["status"] = $status;
        $delivery["remarks"] = $remarks;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Delivery | Jambo Logistics</title>

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

        .button {
            padding: 12px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .back-button {
            background: #6c757d;
            color: white;
            margin-left: 8px;
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
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="users.php">Manage Users</a>
            <a href="add_delivery.php">Add Delivery</a>
            <a href="view_deliveries.php" class="active">View Deliveries</a>
            <a href="reports.php">Reports</a>
            <a href="logout.php">Logout</a>
        </nav>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Edit Delivery</h1>

                <p>
                    Database Record ID:
                    <?= (int) $record_id; ?>
                </p>
            </div>

        </header>

        <section class="form-container">

            <?php if ($success_message !== ""): ?>

                <div class="message success">

                    <?= htmlspecialchars(
                        $success_message,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </div>

            <?php endif; ?>

            <?php if ($error_message !== ""): ?>

                <div class="message error">

                    <?= htmlspecialchars(
                        $error_message,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </div>

            <?php endif; ?>

            <form
                method="POST"
                action="edit_delivery.php?id=<?= (int) $record_id; ?>"
            >

                <div class="form-grid">

                    <div class="form-group">

                        <label for="delivery_id">
                            Delivery ID *
                        </label>

                        <input
                            type="text"
                            id="delivery_id"
                            name="delivery_id"
                            value="<?= htmlspecialchars(
                                $delivery["delivery_id"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
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
                            value="<?= htmlspecialchars(
                                (string) ($delivery["driver_id"] ?? ""),
                                ENT_QUOTES,
                                "UTF-8"
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
                            value="<?php
                                $current_driver_name = "";

                                foreach ($drivers as $driver) {
                                    if (
                                        (string) $driver["id"] ===
                                        (string) ($delivery["driver_id"] ?? "")
                                    ) {
                                        $current_driver_name =
                                            $driver["full_name"];
                                        break;
                                    }
                                }

                                echo htmlspecialchars(
                                    $_POST["driver_name"] ??
                                    $current_driver_name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
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

                            <option value="">Select Client</option>

                            <?php foreach ($clients as $client): ?>

                                <option
                                    value="<?= (int) $client["id"]; ?>"
                                    <?= (
                                        (string) (
                                            $delivery["client_id"] ?? ""
                                        ) ===
                                        (string) $client["id"]
                                    ) ? "selected" : ""; ?>
                                >

                                    <?= htmlspecialchars(
                                        $client["full_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                    (ID: <?= (int) $client["id"]; ?>)

                                </option>

                            <?php endforeach; ?>

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
                                $delivery["customer_name"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
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
                            value="<?= htmlspecialchars(
                                $delivery["customer_phone"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
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
                            value="<?= htmlspecialchars(
                                $delivery["delivery_location"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
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
                                $delivery["delivery_date"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="status">
                            Delivery Status *
                        </label>

                        <select
                            id="status"
                            name="status"
                            required
                        >

                            <?php foreach (
                                [
                                    "Pending",
                                    "In Transit",
                                    "Delivered",
                                    "Cancelled"
                                ] as $option
                            ): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $option,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    <?= (
                                        ($delivery["status"] ?? "Pending")
                                        === $option
                                    ) ? "selected" : ""; ?>
                                >

                                    <?= htmlspecialchars(
                                        $option,
                                        ENT_QUOTES,
                                        "UTF-8"
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
                        ><?= htmlspecialchars(
                            $delivery["remarks"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?></textarea>

                    </div>

                    <div class="form-group full-width">

                        <button
                            type="submit"
                            class="button action-button"
                        >
                            Update Delivery
                        </button>

                        <a
                            href="view_deliveries.php"
                            class="button back-button"
                        >
                            Back to Deliveries
                        </a>

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