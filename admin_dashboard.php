```php
<?php
session_start();

require_once "config/database.php";

// Allow administrators only.
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Count deliveries by status.
function getCount($conn, $sql)
{
    $result = $conn->query($sql);

    if (!$result) {
        error_log("Dashboard query failed: " . $conn->error);
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int) ($row["total"] ?? 0);
}

$totalDeliveries = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM deliveries"
);

$pendingDeliveries = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM deliveries
     WHERE status = 'Pending'"
);

$transitDeliveries = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM deliveries
     WHERE status = 'In Transit'"
);

$completedDeliveries = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM deliveries
     WHERE status = 'Delivered'"
);

$totalDrivers = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'driver'"
);

// Fetch the most recent delivery records.
$sql = "
    SELECT
        d.delivery_id,
        d.customer_name,
        d.delivery_location,
        d.delivery_date,
        d.status,
        u.full_name AS driver_name
    FROM deliveries d
    LEFT JOIN users u ON d.driver_id = u.id
    ORDER BY d.id DESC
    LIMIT 10
";

$deliveries = $conn->query($sql);

if (!$deliveries) {
    error_log("Recent deliveries query failed: " . $conn->error);
    $deliveries = false;
}

function escape($value)
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function deliveryStatusClass($status)
{
    $statusClasses = [
        "pending" => "status-pending",
        "in transit" => "status-transit",
        "delivered" => "status-delivered",
        "cancelled" => "status-cancelled"
    ];

    return $statusClasses[strtolower((string) $status)]
        ?? "status-neutral";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Jambo Logistics</title>

    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
</head>

<body class="admin-dashboard">

<div class="dashboard-container">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <h2>JAMBO LOGISTICS</h2>

        <a href="admin_dashboard.php" class="active">Dashboard</a>

        <a href="users.php">Manage Users</a>

        <a href="add_delivery.php">Add Delivery</a>

        <a href="view_deliveries.php">View Deliveries</a>

        <a href="reports.php">Reports</a>

        <a href="sync.php">Synchronization</a>

        <a href="logout.php" class="logout-link">Logout</a>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <h1>Admin Dashboard</h1>

            <div class="user-info">

                Welcome,
                <strong>
                    <?= escape($_SESSION["full_name"]) ?>
                </strong>

            </div>

        </div>

        <!-- STATISTICS -->

        <div class="stats-grid">

            <div class="stat-card">

                <h3>Total Deliveries</h3>

                <p><?= $totalDeliveries ?></p>

            </div>

            <div class="stat-card">

                <h3>Pending Deliveries</h3>

                <p><?= $pendingDeliveries ?></p>

            </div>

            <div class="stat-card">

                <h3>In Transit</h3>

                <p><?= $transitDeliveries ?></p>

            </div>

            <div class="stat-card">

                <h3>Completed Deliveries</h3>

                <p><?= $completedDeliveries ?></p>

            </div>

        </div>

        <!-- DRIVER SUMMARY -->

        <div class="content-card">

            <div class="driver-summary">

                <div>
                    <h2>Driver Summary</h2>
                    <p>Registered drivers</p>
                </div>

                <strong class="driver-count"><?= $totalDrivers ?></strong>

            </div>

        </div>

        <!-- RECENT DELIVERIES -->

        <div class="content-card">

            <div class="section-heading">
                <div>
                    <h2>Recent Deliveries</h2>
                    <p>Latest delivery activity across your fleet</p>
                </div>
                <a href="add_delivery.php" class="action-button">
                    + Add Delivery
                </a>
            </div>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Delivery ID</th>
                            <th>Customer</th>
                            <th>Driver</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($deliveries && $deliveries->num_rows > 0): ?>

                    <?php while ($row = $deliveries->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= escape($row["delivery_id"]) ?>
                            </td>

                            <td>
                                <?= escape($row["customer_name"]) ?>
                            </td>

                            <td>
                                <?= escape($row["driver_name"] ?? "Unassigned") ?>
                            </td>

                            <td>
                                <?= escape($row["delivery_location"]) ?>
                            </td>

                            <td>
                                <?= escape($row["delivery_date"]) ?>
                            </td>

                            <td>
                                <span class="status-badge <?= deliveryStatusClass($row["status"]) ?>">
                                    <?= escape($row["status"]) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            No delivery records found.
                        </td>
                    </tr>

                <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>
</html>
```
