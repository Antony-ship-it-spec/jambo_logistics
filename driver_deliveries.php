```php
<?php

session_start();

require_once "config/database.php";

// Allow logged-in drivers only
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "driver"
) {
    header("Location: login.php");
    exit();
}

// Get the logged-in driver's details
$driver_id = (int) $_SESSION["user_id"];

$driver_name = $_SESSION["full_name"]
    ?? $_SESSION["username"]
    ?? "Driver";

// Retrieve deliveries assigned to this driver
$sql = "SELECT
            id,
            delivery_id,
            customer_name,
            customer_phone,
            delivery_location,
            delivery_date,
            status,
            sync_status,
            remarks
        FROM deliveries
        WHERE driver_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to prepare deliveries query: " .
        htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $driver_id);

if (!$stmt->execute()) {
    die("Unable to retrieve assigned deliveries.");
}

$deliveries = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Assigned Deliveries | Jambo Logistics</title>

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
            margin-top: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .content-card h2 {
            color: #1d4ed8;
            margin-top: 0;
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
            padding: 12px;
            border: 1px solid #ddd;
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

    </style>

</head>

<body>

<div class="dashboard-container">

    <aside class="sidebar">

        <h2>Jambo Logistics</h2>

        <p>Driver Panel</p>

        <nav>

            <a href="driver_dashboard.php">
                Dashboard
            </a>

            <a href="driver_deliveries.php" class="active">
                My Assigned Deliveries
            </a>

            <a href="logout.php">
                Logout
            </a>

        </nav>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div>

                <h1>My Assigned Deliveries</h1>

                <p>
                    Welcome,
                    <?= htmlspecialchars(
                        $driver_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>

            </div>

        </header>

        <section class="content-card">

            <h2>Assigned Delivery Records</h2>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Delivery ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Delivery Date</th>
                            <th>Status</th>
                            <th>Sync Status</th>
                            <th>Remarks</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($deliveries->num_rows > 0): ?>

                        <?php while (
                            $row = $deliveries->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["delivery_id"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["customer_name"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["customer_phone"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["delivery_location"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["delivery_date"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["status"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["sync_status"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["remarks"] ?? "",
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8">
                                No deliveries have been assigned
                                to your account yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <a href="driver_dashboard.php"
               class="back-button">
                Back to Dashboard
            </a>

        </section>

    </main>

</div>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>
```
