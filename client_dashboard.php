<?php

session_start();

require_once "config/database.php";

// Allow only logged-in clients
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "client"
) {
    header("Location: login.php");
    exit();
}

$client_id = (int) $_SESSION["user_id"];

$client_name = $_SESSION["full_name"]
    ?? $_SESSION["username"];

// Function to count the client's deliveries
function getClientCount($conn, $client_id, $status = null)
{
    if ($status === null) {
        $sql = "SELECT COUNT(*) AS total
                FROM deliveries
                WHERE client_id = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("i", $client_id);

    } else {
        $sql = "SELECT COUNT(*) AS total
                FROM deliveries
                WHERE client_id = ?
                AND status = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("is", $client_id, $status);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $total = (int) ($row["total"] ?? 0);

    $stmt->close();

    return $total;
}

// Client delivery statistics
$total_deliveries = getClientCount(
    $conn,
    $client_id
);

$pending_deliveries = getClientCount(
    $conn,
    $client_id,
    "Pending"
);

$in_transit_deliveries = getClientCount(
    $conn,
    $client_id,
    "In Transit"
);

$completed_deliveries = getClientCount(
    $conn,
    $client_id,
    "Delivered"
);

// Retrieve only this client's deliveries
$sql = "SELECT
            delivery_id,
            customer_name,
            customer_phone,
            delivery_location,
            delivery_date,
            status,
            sync_status,
            remarks
        FROM deliveries
        WHERE client_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to prepare the deliveries query.");
}

$stmt->bind_param("i", $client_id);
$stmt->execute();

$deliveries = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Client Dashboard | Jambo Logistics</title>

    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">

</head>

<body>

<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar">

        <h2>Jambo Logistics</h2>

        <p>Client Portal</p>

        <nav>

            <a href="client_deliveries.php">
                My Deliveries
            </a>

            <a href="logout.php">
                Logout
            </a>

        </nav>

    </aside>

    <!-- Main content -->
    <main class="main-content">

        <header class="topbar">

            <div>

                <h1>Client Dashboard</h1>

                <p>
                    Welcome,
                    <?= htmlspecialchars(
                        $client_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>

            </div>

            <a href="logout.php" class="action-button">
                Logout
            </a>

        </header>

        <!-- Delivery statistics -->
        <section class="stats-grid">

            <div class="stat-card">

                <h3>Total Deliveries</h3>

                <p><?= $total_deliveries; ?></p>

            </div>

            <div class="stat-card">

                <h3>Pending</h3>

                <p><?= $pending_deliveries; ?></p>

            </div>

            <div class="stat-card">

                <h3>In Transit</h3>

                <p><?= $in_transit_deliveries; ?></p>

            </div>

            <div class="stat-card">

                <h3>Delivered</h3>

                <p><?= $completed_deliveries; ?></p>

            </div>

        </section>

        <!-- Client deliveries -->
        <section class="content-card">

            <h2>My Delivery Records</h2>

            <div style="overflow-x: auto;">

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
                                No deliveries have been assigned to your account yet.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>