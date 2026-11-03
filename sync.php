```php
<?php
session_start();

require_once "config/database.php";

// Only administrators can manage synchronization
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "success";

// Escape output
function escapeOutput($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Process synchronization actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION["csrf_token"], $submitted_token)
    ) {
        $message = "Invalid request. Refresh the page and try again.";
        $message_type = "error";

    } else {

        $record_id = filter_input(
            INPUT_POST,
            "record_id",
            FILTER_VALIDATE_INT
        );

        $action = $_POST["action"] ?? "";

        if (
            !$record_id ||
            !in_array(
                $action,
                ["mark_synced", "mark_conflict", "retry"],
                true
            )
        ) {

            $message = "Invalid synchronization action.";
            $message_type = "error";

        } else {

            // Retrieve the selected delivery
            $check = $conn->prepare(
                "SELECT id, delivery_id, sync_status
                 FROM deliveries
                 WHERE id = ?
                 LIMIT 1"
            );

            $check->bind_param("i", $record_id);
            $check->execute();

            $record = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$record) {

                $message = "Delivery record not found.";
                $message_type = "error";

            } else {

                $new_status = "";
                $log_status = "";
                $log_message = "";

                if ($action === "mark_synced") {

                    $new_status = "Synced";
                    $log_status = "Success";

                    $log_message =
                        "Administrator marked the record as synchronized. " .
                        "No external cloud transfer was performed.";

                } elseif ($action === "mark_conflict") {

                    $new_status = "Conflict";
                    $log_status = "Conflict";

                    $log_message =
                        "Record flagged for conflict review by an administrator.";

                } elseif ($action === "retry") {

                    $new_status = "Pending";
                    $log_status = "Pending";

                    $log_message =
                        "Record returned to Pending status for a future synchronization attempt.";
                }

                // Update record and create its log in one transaction
                $conn->begin_transaction();

                try {

                    $update = $conn->prepare(
                        "UPDATE deliveries
                         SET sync_status = ?
                         WHERE id = ?"
                    );

                    $update->bind_param(
                        "si",
                        $new_status,
                        $record_id
                    );

                    if (!$update->execute()) {
                        throw new Exception(
                            "Unable to update the delivery."
                        );
                    }

                    $update->close();

                    $log = $conn->prepare(
                        "INSERT INTO sync_logs
                         (delivery_id, sync_status, message)
                         VALUES (?, ?, ?)"
                    );

                    $log->bind_param(
                        "iss",
                        $record_id,
                        $log_status,
                        $log_message
                    );

                    if (!$log->execute()) {
                        throw new Exception(
                            "Unable to save the synchronization log."
                        );
                    }

                    $log->close();

                    $conn->commit();

                    // Prevent duplicate submissions on page refresh
                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));

                    header("Location: sync.php?result=" . urlencode($new_status));
                    exit();

                } catch (Throwable $e) {

                    $conn->rollback();

                    error_log(
                        "Synchronization module error: " .
                        $e->getMessage()
                    );

                    $message =
                        "The action could not be completed. " .
                        "Check the database configuration and try again.";

                    $message_type = "error";
                }
            }
        }
    }
}

// Display a message after a successful redirect
if (isset($_GET["result"])) {

    $allowed_results = [
        "Synced",
        "Conflict",
        "Pending"
    ];

    if (
        in_array($_GET["result"], $allowed_results, true)
    ) {
        $message = "Delivery synchronization status updated to: " .
            $_GET["result"];

        $message_type = "success";
    }
}

// Count synchronization statuses
$count_sql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(sync_status = 'Pending'), 0) AS pending,
        COALESCE(SUM(sync_status = 'Synced'), 0) AS synced,
        COALESCE(SUM(sync_status = 'Conflict'), 0) AS conflicts
    FROM deliveries
";

$count_result = $conn->query($count_sql);

if (!$count_result) {
    die("Unable to load synchronization statistics.");
}

$counts = $count_result->fetch_assoc();

// Retrieve records needing attention
$records_sql = "
    SELECT
        d.id,
        d.delivery_id,
        d.customer_name,
        d.delivery_location,
        d.delivery_date,
        d.status,
        d.sync_status,
        d.updated_at,
        u.full_name AS driver_name
    FROM deliveries d
    LEFT JOIN users u
        ON d.driver_id = u.id
    WHERE d.sync_status IN ('Pending', 'Conflict')
    ORDER BY
        CASE
            WHEN d.sync_status = 'Conflict' THEN 0
            ELSE 1
        END,
        d.updated_at DESC
";

$records_result = $conn->query($records_sql);

if (!$records_result) {
    die("Unable to load delivery synchronization records.");
}

// Retrieve recent synchronization logs
$logs_sql = "
    SELECT
        l.id,
        d.delivery_id AS reference_id,
        l.sync_status,
        l.message,
        l.synced_at
    FROM sync_logs l
    INNER JOIN deliveries d
        ON l.delivery_id = d.id
    ORDER BY l.synced_at DESC, l.id DESC
    LIMIT 20
";

$logs_result = $conn->query($logs_sql);

if (!$logs_result) {
    die("Unable to load synchronization logs.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Synchronization | Jambo Logistics</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            margin: 0;
            color: #212529;
        }

        .sync-container {
            max-width: 1450px;
            margin: auto;
            padding: 25px;
        }

        .sync-header,
        .sync-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .sync-header h1 {
            margin-top: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #087f5b;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #555;
        }

        .stat-card p {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 0;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f3f5;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 12px;
            white-space: nowrap;
        }

        .pending {
            background: #fff3bf;
            color: #8a6100;
        }

        .synced {
            background: #d3f9d8;
            color: #2b8a3e;
        }

        .conflict {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .success {
            background: #d3f9d8;
            color: #2b8a3e;
        }

        .failed {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d3f9d8;
        }

        .message.error {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .action-form {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            min-width: 180px;
        }

        .action-button {
            padding: 7px 10px;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            font-size: 12px;
        }

        .mark-synced {
            background: #087f5b;
        }

        .mark-conflict {
            background: #c92a2a;
        }

        .retry {
            background: #1864ab;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #087f5b;
            text-decoration: none;
        }

        .notice {
            background: #fff3bf;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .sync-container {
                padding: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="sync-container">

    <a href="admin_dashboard.php" class="back-link">
        &larr; Back to Admin Dashboard
    </a>

    <div class="sync-header">

        <h1>Delivery Synchronization</h1>

        <p>
            Monitor synchronization status, review potential conflicts,
            and inspect recent synchronization activity.
        </p>

    </div>

    <div class="notice">
        <strong>Development notice:</strong>
        This page tracks synchronization status within the local database.
        Marking a record as Synced does not transfer data to another device
        or cloud server.
    </div>

    <?php if ($message !== ""): ?>

        <div class="message <?= escapeOutput($message_type) ?>">
            <?= escapeOutput($message) ?>
        </div>

    <?php endif; ?>

    <!-- Synchronization Statistics -->
    <div class="stats-grid">

        <div class="stat-card">
            <h3>Total Deliveries</h3>
            <p><?= (int)$counts["total"] ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>
            <p><?= (int)$counts["pending"] ?></p>
        </div>

        <div class="stat-card">
            <h3>Synced</h3>
            <p><?= (int)$counts["synced"] ?></p>
        </div>

        <div class="stat-card">
            <h3>Conflicts</h3>
            <p><?= (int)$counts["conflicts"] ?></p>
        </div>

    </div>

    <!-- Records Requiring Attention -->
    <div class="sync-card">

        <h2>Records Requiring Attention</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Customer</th>
                        <th>Driver</th>
                        <th>Location</th>
                        <th>Delivery Date</th>
                        <th>Delivery Status</th>
                        <th>Sync Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($records_result->num_rows > 0): ?>

                    <?php while ($row = $records_result->fetch_assoc()): ?>

                        <?php
                        $sync_class = $row["sync_status"] === "Conflict"
                            ? "conflict"
                            : "pending";
                        ?>

                        <tr>

                            <td>
                                <?= escapeOutput($row["delivery_id"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["customer_name"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput(
                                    $row["driver_name"] ?? "Not assigned"
                                ) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["delivery_location"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["delivery_date"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["status"]) ?>
                            </td>

                            <td>
                                <span class="badge <?= $sync_class ?>">
                                    <?= escapeOutput($row["sync_status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeOutput($row["updated_at"]) ?>
                            </td>

                            <td>

                                <form
                                    method="POST"
                                    action="sync.php"
                                    class="action-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapeOutput($_SESSION["csrf_token"]) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="record_id"
                                        value="<?= (int)$row["id"] ?>"
                                    >

                                    <?php if ($row["sync_status"] === "Pending"): ?>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="mark_synced"
                                            class="action-button mark-synced"
                                            onclick="return confirm('Mark this record as synchronized? No external cloud transfer will occur.');"
                                        >
                                            Mark Synced
                                        </button>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="mark_conflict"
                                            class="action-button mark-conflict"
                                            onclick="return confirm('Flag this record for conflict review?');"
                                        >
                                            Flag Conflict
                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="retry"
                                            class="action-button retry"
                                        >
                                            Return to Pending
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9">
                            No pending records or conflicts require attention.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Recent Synchronization Logs -->
    <div class="sync-card">

        <h2>Recent Synchronization Logs</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Delivery ID</th>
                        <th>Log Status</th>
                        <th>Message</th>
                        <th>Date and Time</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($logs_result->num_rows > 0): ?>

                    <?php while ($log = $logs_result->fetch_assoc()): ?>

                        <?php
                        $log_class = "pending";

                        if ($log["sync_status"] === "Success") {
                            $log_class = "success";
                        } elseif ($log["sync_status"] === "Conflict") {
                            $log_class = "conflict";
                        } elseif ($log["sync_status"] === "Failed") {
                            $log_class = "failed";
                        }
                        ?>

                        <tr>

                            <td>
                                <?= (int)$log["id"] ?>
                            </td>

                            <td>
                                <?= escapeOutput($log["reference_id"]) ?>
                            </td>

                            <td>
                                <span class="badge <?= $log_class ?>">
                                    <?= escapeOutput($log["sync_status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeOutput($log["message"] ?? "") ?>
                            </td>

                            <td>
                                <?= escapeOutput($log["synced_at"]) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">
                            No synchronization activity has been recorded yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>
```
