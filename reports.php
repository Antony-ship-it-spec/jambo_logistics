```php
<?php
session_start();

require_once "config/database.php";

// Only administrators can access reports
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Escape output to help prevent XSS
function escapeOutput($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Read report filters
$status = trim($_GET["status"] ?? "");
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");

// Validate status
$allowed_statuses = [
    "Pending",
    "In Transit",
    "Delivered",
    "Cancelled"
];

if (
    $status !== "" &&
    !in_array($status, $allowed_statuses, true)
) {
    $status = "";
}

// Validate dates
if (
    $date_from !== "" &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)
) {
    $date_from = "";
}

if (
    $date_to !== "" &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)
) {
    $date_to = "";
}

if (
    $date_from !== "" &&
    $date_to !== "" &&
    $date_from > $date_to
) {
    $date_error = "The start date cannot be later than the end date.";
} else {
    $date_error = "";
}

// Build filters safely
$where = [];
$params = [];
$types = "";

if ($status !== "") {
    $where[] = "d.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($date_from !== "") {
    $where[] = "d.delivery_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== "") {
    $where[] = "d.delivery_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_sql = "";

if (!empty($where)) {
    $where_sql = " WHERE " . implode(" AND ", $where);
}

// Summary statistics
$summary_sql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(d.status = 'Pending'), 0) AS pending,
        COALESCE(SUM(d.status = 'In Transit'), 0) AS in_transit,
        COALESCE(SUM(d.status = 'Delivered'), 0) AS delivered,
        COALESCE(SUM(d.status = 'Cancelled'), 0) AS cancelled
    FROM deliveries d
" . $where_sql;

$summary_stmt = $conn->prepare($summary_sql);

if (!$summary_stmt) {
    die("Unable to prepare report summary: " . $conn->error);
}

if (!empty($params)) {
    $summary_stmt->bind_param($types, ...$params);
}

$summary_stmt->execute();

$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();

$summary_stmt->close();

// Retrieve delivery report records
$report_sql = "
    SELECT
        d.id,
        d.delivery_id,
        d.customer_name,
        d.customer_phone,
        d.delivery_location,
        d.delivery_date,
        d.status,
        d.sync_status,
        d.remarks,
        driver.full_name AS driver_name,
        client.full_name AS client_name
    FROM deliveries d
    LEFT JOIN users driver
        ON d.driver_id = driver.id
    LEFT JOIN users client
        ON d.client_id = client.id
" . $where_sql . "
    ORDER BY d.delivery_date DESC, d.id DESC
";

$report_stmt = $conn->prepare($report_sql);

if (!$report_stmt) {
    die("Unable to prepare delivery report: " . $conn->error);
}

if (!empty($params)) {
    $report_stmt->bind_param($types, ...$params);
}

$report_stmt->execute();

$report_result = $report_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Delivery Reports | Jambo Logistics</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .reports-container {
            padding: 25px;
        }

        .report-heading {
            margin-bottom: 20px;
        }

        .filter-card,
        .report-table-card {
            background: #fff;
            padding: 22px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: bold;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .filter-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 11px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-button {
            background: #087f5b;
            color: white;
        }

        .reset-button {
            background: #e9ecef;
            color: #212529;
        }

        .print-button {
            background: #1864ab;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 9px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #087f5b;
        }

        .stat-card h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #555;
        }

        .stat-card p {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .report-table th,
        .report-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        .report-table th {
            background: #f1f3f5;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 12px;
            font-size: 12px;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3bf;
            color: #8a6100;
        }

        .status-transit {
            background: #d0ebff;
            color: #1864ab;
        }

        .status-delivered {
            background: #d3f9d8;
            color: #2b8a3e;
        }

        .status-cancelled {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .status-default {
            background: #e9ecef;
            color: #343a40;
        }

        .date-error {
            color: #c92a2a;
            margin-top: 15px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #087f5b;
            text-decoration: none;
        }

        .print-header {
            display: none;
        }

        @media (max-width: 1000px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .reports-container {
                padding: 12px;
            }

            .filter-form,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                grid-column: auto;
            }
        }

        @media print {
            body {
                background: white !important;
            }

            .no-print,
            .filter-card,
            .back-link {
                display: none !important;
            }

            .reports-container {
                padding: 0;
            }

            .print-header {
                display: block;
                margin-bottom: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 5px;
            }

            .stat-card,
            .report-table-card {
                box-shadow: none;
                border: 1px solid #ccc;
                padding: 8px;
            }

            .stat-card p {
                font-size: 18px;
            }

            .report-table {
                font-size: 10px;
            }

            .report-table th,
            .report-table td {
                padding: 5px;
            }

            @page {
                size: landscape;
                margin: 10mm;
            }
        }
    </style>
</head>

<body>

<div class="reports-container">

    <a href="admin_dashboard.php" class="back-link no-print">
        &larr; Back to Admin Dashboard
    </a>

    <div class="report-heading">
        <h1>Delivery Reports</h1>

        <p>
            View delivery performance and monitor logistics records.
        </p>
    </div>

    <div class="print-header">
        <h1>Jambo Logistics Ltd</h1>
        <h2>Delivery Report</h2>

        <p>
            Generated on:
            <?= date("d M Y H:i") ?>
        </p>
    </div>

    <!-- Filters -->
    <div class="filter-card no-print">

        <h2>Filter Reports</h2>

        <form method="GET" action="reports.php" class="filter-form">

            <div class="filter-group">

                <label for="status">Delivery Status</label>

                <select name="status" id="status">

                    <option value="">All Statuses</option>

                    <?php foreach ($allowed_statuses as $option): ?>

                        <option
                            value="<?= escapeOutput($option) ?>"
                            <?= $status === $option ? "selected" : "" ?>
                        >
                            <?= escapeOutput($option) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="filter-group">

                <label for="date_from">Start Date</label>

                <input
                    type="date"
                    name="date_from"
                    id="date_from"
                    value="<?= escapeOutput($date_from) ?>"
                >

            </div>

            <div class="filter-group">

                <label for="date_to">End Date</label>

                <input
                    type="date"
                    name="date_to"
                    id="date_to"
                    value="<?= escapeOutput($date_to) ?>"
                >

            </div>

            <div class="filter-actions">

                <button type="submit" class="button filter-button">
                    Apply Filters
                </button>

                <a href="reports.php" class="button reset-button">
                    Reset
                </a>

                <button
                    type="button"
                    class="button print-button"
                    onclick="window.print()"
                >
                    Print Report
                </button>

            </div>

        </form>

        <?php if ($date_error !== ""): ?>

            <p class="date-error">
                <?= escapeOutput($date_error) ?>
            </p>

        <?php endif; ?>

    </div>

    <!-- Statistics -->
    <div class="stats-grid">

        <div class="stat-card">
            <h3>Total Deliveries</h3>
            <p><?= (int) $summary["total"] ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>
            <p><?= (int) $summary["pending"] ?></p>
        </div>

        <div class="stat-card">
            <h3>In Transit</h3>
            <p><?= (int) $summary["in_transit"] ?></p>
        </div>

        <div class="stat-card">
            <h3>Delivered</h3>
            <p><?= (int) $summary["delivered"] ?></p>
        </div>

        <div class="stat-card">
            <h3>Cancelled</h3>
            <p><?= (int) $summary["cancelled"] ?></p>
        </div>

    </div>

    <!-- Delivery Table -->
    <div class="report-table-card">

        <h2>Delivery Records</h2>

        <div class="table-wrapper">

            <table class="report-table">

                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Driver</th>
                        <th>Client Account</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Sync Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($report_result->num_rows > 0): ?>

                    <?php while ($row = $report_result->fetch_assoc()): ?>

                        <?php
                        $status_class = "status-default";

                        if ($row["status"] === "Pending") {
                            $status_class = "status-pending";
                        } elseif ($row["status"] === "In Transit") {
                            $status_class = "status-transit";
                        } elseif ($row["status"] === "Delivered") {
                            $status_class = "status-delivered";
                        } elseif ($row["status"] === "Cancelled") {
                            $status_class = "status-cancelled";
                        }
                        ?>

                        <tr>

                            <td>
                                <?= escapeOutput($row["delivery_id"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["customer_name"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["customer_phone"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput(
                                    $row["driver_name"] ?? "Not assigned"
                                ) ?>
                            </td>

                            <td>
                                <?= escapeOutput(
                                    $row["client_name"] ?? "Not linked"
                                ) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["delivery_location"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["delivery_date"]) ?>
                            </td>

                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= escapeOutput($row["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeOutput($row["sync_status"]) ?>
                            </td>

                            <td>
                                <?= escapeOutput($row["remarks"] ?? "") ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10">
                            No delivery records match the selected filters.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p>
            Report generated by Jambo Logistics.
        </p>

    </div>

</div>

</body>
</html>

<?php
$report_stmt->close();
$conn->close();
?>
```
