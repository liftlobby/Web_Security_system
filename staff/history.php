<?php
session_start();
require_once '../config/database.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Get verification history with ticket and staff details
$sql = "SELECT vl.*, t.ticket_number, ts.train_number, ts.departure_station, 
        ts.arrival_station, ts.departure_time, s.username as staff_name
        FROM verification_logs vl
        JOIN tickets t ON vl.ticket_id = t.id
        JOIN train_schedule ts ON t.train_id = ts.id
        JOIN staff s ON vl.staff_id = s.id
        ORDER BY vl.verification_time DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification History - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            overflow-x: hidden;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            width: calc(100% - 250px);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-onboard {
            background-color: #28a745;
            color: white;
        }
        .status-used {
            background-color: #6c757d;
            color: white;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .verification-time {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Include the sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content">
                <div class="container">
                    <h2 class="mb-4">Verification History</h2>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Verification Time</th>
                                            <th>Ticket Number</th>
                                            <th>Train</th>
                                            <th>Route</th>
                                            <th>Departure</th>
                                            <th>Status</th>
                                            <th>Verified By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="verification-time">
                                                    <?php echo date('Y-m-d H:i:s', strtotime($row['verification_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['ticket_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['train_number']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['departure_station']); ?> →
                                                    <?php echo htmlspecialchars($row['arrival_station']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('Y-m-d H:i', strtotime($row['departure_time'])); ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
