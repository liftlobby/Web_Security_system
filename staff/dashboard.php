<?php
session_start();
require_once '../config/database.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Get total users
$sql = "SELECT COUNT(*) as total FROM users";
$result = $conn->query($sql);
$total_users = $result->fetch_assoc()['total'];

// Get total tickets sold today
$today = date('Y-m-d');
$sql = "SELECT COUNT(*) as total FROM tickets WHERE DATE(booking_date) = '$today'";
$result = $conn->query($sql);
$tickets_today = $result->fetch_assoc()['total'];

// Get total tickets
$sql = "SELECT COUNT(*) as total FROM tickets";
$result = $conn->query($sql);
$total_tickets = $result->fetch_assoc()['total'];

// Get total schedules
$sql = "SELECT COUNT(*) as total FROM schedules";
$result = $conn->query($sql);
$total_schedules = $result->fetch_assoc()['total'];

// Get new reports count
$sql = "SELECT COUNT(*) as total FROM reports WHERE status = 'new'";
$result = $conn->query($sql);
$new_reports = $result->fetch_assoc()['total'];

// Get recent tickets
$sql = "SELECT t.*, u.username, s.departure_station, s.arrival_station, s.departure_time 
        FROM tickets t 
        JOIN users u ON t.user_id = u.user_id 
        JOIN schedules s ON t.schedule_id = s.schedule_id 
        ORDER BY t.booking_date DESC LIMIT 5";
$recent_tickets = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            background-color: #343a40;
            color: white;
            width: 250px;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: white;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-link:hover {
            color: #17a2b8;
        }
        .nav-link.active {
            background-color: #0056b3;
            color: white;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #666;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #0056b3;
            display: block;
            margin-top: 10px;
        }
        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-10 content">
                <h2 class="mb-4">Dashboard</h2>

                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h3>Tickets Sold Today</h3>
                            <span class="number"><?php echo $tickets_today; ?></span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card">
                            <h3>Total Users</h3>
                            <span class="number"><?php echo $total_users; ?></span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card">
                            <h3>Total Tickets</h3>
                            <span class="number"><?php echo $total_tickets; ?></span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card">
                            <h3>Total Schedules</h3>
                            <span class="number"><?php echo $total_schedules; ?></span>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h3>New Reports</h3>
                            <span class="number"><?php echo $new_reports; ?></span>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="recent-activity">
                            <h3 class="mb-4">Recent Ticket Purchases</h3>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Departure</th>
                                            <th>Booking Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($ticket = $recent_tickets->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                                            <td><?php echo htmlspecialchars($ticket['departure_station']); ?></td>
                                            <td><?php echo htmlspecialchars($ticket['arrival_station']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($ticket['booking_date'])); ?></td>
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
