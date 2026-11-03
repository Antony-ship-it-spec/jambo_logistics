<?php

session_start();

require_once "config/database.php";


// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}


// Function to safely get a count
function getCount($conn, $sql)
{
    $result = $conn->query($sql);

    if ($result === false) {
        die(
            "<h2>Database Error</h2>
            <p><strong>SQL Query:</strong> " . htmlspecialchars($sql) . "</p>
            <p><strong>Error:</strong> " . htmlspecialchars($conn->error) . "</p>
            <p>Please check that your database and tables have been created correctly.</p>"
        );
    }

    $row = $result->fetch_assoc();

    return $row["total"];
}


// Total deliveries
$total_deliveries = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM deliveries"
);


// Delivered deliveries
$delivered = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM deliveries
     WHERE delivery_status = 'Delivered'"
);


// Pending deliveries
$pending = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM deliveries
     WHERE delivery_status = 'Pending'"
);


// Pending conflicts
$conflicts = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM conflicts
     WHERE status = 'Pending'"
);

?>

<!DOCTYPE html>

<html>

<head>

    <title>Jambo Logistics Dashboard</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>


<div class="header">

    <h1>Jambo Logistics</h1>

    <a class="logout" href="logout.php">
        Logout
    </a>

</div>


<div class="container">

    <div class="welcome">

        <h2>Dashboard</h2>

        <p>
            Welcome,
            <?php echo htmlspecialchars($_SESSION["full_name"] ?? "User"); ?>
        </p>

        <p>
            Role:
            <?php echo htmlspecialchars($_SESSION["role"] ?? "User"); ?>
        </p>

    </div>


    <div class="cards">


        <div class="card">

            <h3>Total Deliveries</h3>

            <p>
                <?php echo $total_deliveries; ?>
            </p>

        </div>


        <div class="card">

            <h3>Delivered</h3>

            <p>
                <?php echo $delivered; ?>
            </p>

        </div>


        <div class="card">

            <h3>Pending</h3>

            <p>
                <?php echo $pending; ?>
            </p>

        </div>


        <div class="card">

            <h3>Conflicts</h3>

            <p>
                <?php echo $conflicts; ?>
            </p>

        </div>


    </div>


    <h2>System Management</h2>

    <br>


    <div class="menu">

        <!-- Add Delivery -->
        <a href="add_delivery.php">
            Add Delivery
        </a>


        <!-- Delivery Records -->
        <a href="view_deliveries.php">
            Delivery Records
        </a>


        <!-- Conflicts -->
        <a href="conflicts.php">
            Conflicts
        </a>


        <!-- Reports -->
        <a href="reports.php">
            Reports
        </a>


        <!-- Logout -->
        <a href="logout.php">
            Logout
        </a>

    </div>


</div>


</body>

</html>