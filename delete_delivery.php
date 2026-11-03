<?php

session_start();

require_once "config/database.php";

// Allow administrators only.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Generate a CSRF token for the confirmation form.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error_message = "";
$success_message = "";

// Get the database record ID.
$record_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$record_id || $record_id < 1) {
    http_response_code(400);
    die("Invalid delivery record ID.");
}

// Retrieve the delivery details.
$select = $conn->prepare("
    SELECT id, delivery_id, customer_name,
           delivery_location, status
    FROM deliveries
    WHERE id = ?
    LIMIT 1
");

if (!$select) {
    die("Unable to prepare the delivery query.");
}

$select->bind_param("i", $record_id);
$select->execute();

$result = $select->get_result();
$delivery = $result->fetch_assoc();

$select->close();

if (!$delivery) {
    http_response_code(404);
    die("Delivery record not found.");
}

// Process the deletion only after form submission.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $submitted_token
        )
    ) {

        $error_message =
            "Security verification failed. Refresh the page and try again.";

    } elseif (
        ($_POST["confirmation"] ?? "") !== "DELETE"
    ) {

        $error_message =
            "Please confirm that you want to delete this delivery.";

    } else {

        // Delete only the selected delivery.
        // Foreign-key restrictions will protect related sync logs.
        $delete = $conn->prepare("
            DELETE FROM deliveries
            WHERE id = ?
        ");

        if (!$delete) {

            $error_message =
                "Unable to prepare the deletion request.";

        } else {

            $delete->bind_param("i", $record_id);

            if ($delete->execute()) {

                if ($delete->affected_rows === 1) {

                    $delete->close();

                    // Rotate the token after a successful operation.
                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));

                    header(
                        "Location: view_deliveries.php?deleted=1"
                    );

                    exit();

                } else {

                    $error_message =
                        "The delivery was not deleted because it no longer exists.";

                }

            } else {

                if ($delete->errno === 1451) {

                    $error_message =
                        "This delivery has related synchronization logs. " .
                        "It cannot be deleted until the related records " .
                        "are handled according to your data-retention rules.";

                } else {

                    error_log(
                        "Delivery deletion failed: " .
                        $delete->error
                    );

                    $error_message =
                        "The delivery could not be deleted. Please try again.";

                }
            }

            $delete->close();
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

    <title>Delete Delivery | Jambo Logistics</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        .delete-container {
            max-width: 700px;
            margin: 30px auto;
            padding: 25px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .warning-box {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .delivery-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .delivery-details th,
        .delivery-details td {
            border: 1px solid #dddddd;
            padding: 12px;
            text-align: left;
        }

        .delivery-details th {
            width: 40%;
            background: #f5f5f5;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .delete-button {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px 18px;
            cursor: pointer;
            font-size: 15px;
        }

        .cancel-button {
            display: inline-block;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            padding: 12px 18px;
            margin-left: 8px;
        }

        .confirmation-field {
            margin-bottom: 20px;
        }

        .confirmation-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .confirmation-field input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #cccccc;
            border-radius: 5px;
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

            <a href="view_deliveries.php" class="active">
                View Deliveries
            </a>

            <a href="reports.php">Reports</a>

            <a href="logout.php">Logout</a>

        </nav>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div>

                <h1>Delete Delivery</h1>

                <p>Review the record before confirming deletion.</p>

            </div>

        </header>

        <section class="delete-container">

            <?php if ($error_message !== ""): ?>

                <div class="message error">

                    <?= htmlspecialchars(
                        $error_message,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </div>

            <?php endif; ?>

            <div class="warning-box">

                <strong>Warning:</strong>

                You are about to permanently delete this delivery
                record. This action cannot be undone.

            </div>

            <table class="delivery-details">

                <tr>
                    <th>Database Record ID</th>
                    <td><?= (int) $delivery["id"]; ?></td>
                </tr>

                <tr>
                    <th>Delivery ID</th>
                    <td>
                        <?= htmlspecialchars(
                            $delivery["delivery_id"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </td>
                </tr>

                <tr>
                    <th>Customer Name</th>
                    <td>
                        <?= htmlspecialchars(
                            $delivery["customer_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </td>
                </tr>

                <tr>
                    <th>Delivery Location</th>
                    <td>
                        <?= htmlspecialchars(
                            $delivery["delivery_location"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </td>
                </tr>

                <tr>
                    <th>Status</th>
                    <td>
                        <?= htmlspecialchars(
                            $delivery["status"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </td>
                </tr>

            </table>

            <form
                method="POST"
                action="delete_delivery.php?id=<?= (int) $record_id; ?>"
                onsubmit="return confirm('Are you sure you want to permanently delete this delivery?');"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["csrf_token"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                >

                <div class="confirmation-field">

                    <label for="confirmation">
                        Type DELETE to confirm
                    </label>

                    <input
                        type="text"
                        id="confirmation"
                        name="confirmation"
                        placeholder="Type DELETE"
                        autocomplete="off"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="delete-button"
                >
                    Confirm Deletion
                </button>

                <a
                    href="view_deliveries.php"
                    class="cancel-button"
                >
                    Cancel
                </a>

            </form>

        </section>

    </main>

</div>

</body>

</html>

<?php
$conn->close();
?>