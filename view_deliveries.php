```php
<?php

session_start();

require_once "config/database.php";

// Allow administrators and drivers to access the page.
// Remove the driver role if this page is admin-only.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "driver"], true)
) {
    header("Location: login.php");
    exit();
}

// Retrieve delivery records using the existing database columns
$sql = "SELECT
            id,
            delivery_id,
            driver_id,
            customer_name,
            customer_phone,
            delivery_location,
            delivery_date,
            status,
            sync_status,
            remarks,
            client_id
        FROM deliveries
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    die(
        "Unable to load delivery records. Database error: " .
        htmlspecialchars($conn->error, ENT_QUOTES, "UTF-8")
    );
}

$is_admin = ($_SESSION["role"] ?? "") === "admin";
$success_message = "";

if (isset($_GET["updated"]) && $_GET["updated"] === "1") {
    $success_message = "Delivery updated successfully.";
}

if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $success_message = "Delivery deleted successfully.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>View Deliveries | Jambo Logistics</title>

    <link rel="stylesheet" href="style.css">

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            color: #1e293b;
        }

        .main-content {
            padding: 30px;
        }

        .content-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #1d4ed8;
            color: white;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back-button:hover {
            background: #1e40af;
        }

        .message {
            padding: 12px 14px;
            margin-bottom: 18px;
            border-radius: 6px;
            font-weight: 600;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .actions-cell {
            white-space: nowrap;
        }

        .action-button {
            display: inline-block;
            margin-right: 6px;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .edit-button {
            background: #0d6efd;
            color: #fff;
        }

        .delete-button {
            background: #dc3545;
            color: #fff;
        }

        .text-muted {
            color: #64748b;
        }

    </style>

</head>

<body>

<div class="main-content">

    <h1>View Deliveries</h1>

    <p>
        View and monitor logistics delivery records.
    </p>

    <section class="content-card">

        <h2>All Delivery Records</h2>

        <?php if ($success_message !== ""): ?>
            <div class="message success">
                <?= htmlspecialchars($success_message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Delivery ID</th>
                        <th>Driver ID</th>
                        <th>Customer Name</th>
                        <th>Customer Phone</th>
                        <th>Location</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                        <th>Sync Status</th>
                        <th>Remarks</th>
                        <th>Client ID</th>
                        <?php if ($is_admin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $row["delivery_id"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) ($row["driver_id"] ?? ""),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["customer_name"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["customer_phone"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["delivery_location"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["delivery_date"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["status"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["sync_status"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["remarks"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) ($row["client_id"] ?? ""),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </td>

                            <?php if ($is_admin): ?>
                                <td class="actions-cell">
                                    <a
                                        href="edit_delivery.php?id=<?= (int) ($row["id"] ?? 0); ?>"
                                        class="action-button edit-button"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_delivery.php?id=<?= (int) ($row["id"] ?? 0); ?>"
                                        class="action-button delete-button"
                                        onclick="return confirm('Are you sure you want to delete this delivery?');"
                                    >
                                        Delete
                                    </a>
                                </td>
                            <?php endif; ?>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="<?= $is_admin ? 11 : 10; ?>">
                            No delivery records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <a href="admin_dashboard.php" class="back-btn">
            Back to Dashboard
        </a>

    </section>

</div>

</body>

</html>

<?php

$result->free();
$conn->close();

?>
```
