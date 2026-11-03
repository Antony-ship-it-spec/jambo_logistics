<?php
session_start();

require_once 'db_connect.php';

// Check whether the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

// Get the logged-in client's ID
$client_id = $_SESSION['user_id'];

// Retrieve this client's delivery records
$sql = "SELECT * FROM deliveries WHERE client_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param("i", $client_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Deliveries | Jambo Logistics</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
            color: #1e293b;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h1 {
            color: #1d4ed8;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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

        tr:nth-child(even) {
            background: #f8fafc;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>My Delivery Records</h1>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Client'); ?>!
    </p>

    <table>
        <thead>
            <tr>
                <th>Delivery ID</th>
                <th>Customer Name</th>
                <th>Customer Phone</th>
                <th>Delivery Location</th>
                <th>Delivery Date</th>
                <th>Status</th>
                <th>Remarks</th>
            </tr>
        </thead>

        <tbody>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>
                    <td>
                        <?php echo htmlspecialchars($row['delivery_id'] ?? ''); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['customer_name'] ?? ''); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['customer_phone'] ?? ''); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['delivery_location'] ?? ''); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['delivery_date'] ?? ''); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['status'] ?? ''); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row['remarks'] ?? ''); ?>
                    </td>
                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">
                    No delivery records were found for your account.
                </td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

    <a class="back" href="client_dashboard.php">
        Back to Dashboard
    </a>

</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>