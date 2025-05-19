<?php
session_start();
require_once '../config/database.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all schedules
$sql = "SELECT * FROM schedules ORDER BY departure_time DESC";
$schedules = $conn->query($sql);

// Get status badge color
function getStatusBadgeColor($status) {
    switch ($status) {
        case 'on_time':
            return 'success';
        case 'delayed':
            return 'warning';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Railway System</title>
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-10 content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Schedules</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <i class='bx bx-plus'></i> Add New Schedule
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Train</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Departure</th>
                                        <th>Arrival</th>
                                        <th>Platform</th>
                                        <th>Price</th>
                                        <th>Seats</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['train_number']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['departure_station']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['arrival_station']); ?></td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($schedule['departure_time'])); ?></td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($schedule['arrival_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['platform_number'] ?? 'TBA'); ?></td>
                                        <td>RM <?php echo number_format($schedule['price'], 2); ?></td>
                                        <td><?php echo $schedule['available_seats']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeColor($schedule['train_status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $schedule['train_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $schedule['schedule_id']; ?>">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['schedule_id']; ?>)">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Schedule Modal -->
                                    <div class="modal fade" id="editModal<?php echo $schedule['schedule_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="schedule_actions.php" method="POST">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label>Train Number</label>
                                                            <input type="text" class="form-control" name="train_number" value="<?php echo htmlspecialchars($schedule['train_number']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Departure Station</label>
                                                            <input type="text" class="form-control" name="departure_station" value="<?php echo htmlspecialchars($schedule['departure_station']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Arrival Station</label>
                                                            <input type="text" class="form-control" name="arrival_station" value="<?php echo htmlspecialchars($schedule['arrival_station']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Departure Time</label>
                                                            <input type="datetime-local" class="form-control" name="departure_time" value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['departure_time'])); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Arrival Time</label>
                                                            <input type="datetime-local" class="form-control" name="arrival_time" value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['arrival_time'])); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Platform Number</label>
                                                            <input type="text" class="form-control" name="platform_number" value="<?php echo htmlspecialchars($schedule['platform_number'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Price (RM)</label>
                                                            <input type="number" class="form-control" name="price" value="<?php echo $schedule['price']; ?>" step="0.01" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Available Seats</label>
                                                            <input type="number" class="form-control" name="available_seats" value="<?php echo $schedule['available_seats']; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Status</label>
                                                            <select class="form-control" name="train_status" required>
                                                                <option value="on_time" <?php echo $schedule['train_status'] === 'on_time' ? 'selected' : ''; ?>>On Time</option>
                                                                <option value="delayed" <?php echo $schedule['train_status'] === 'delayed' ? 'selected' : ''; ?>>Delayed</option>
                                                                <option value="cancelled" <?php echo $schedule['train_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="schedule_actions.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label>Train Number</label>
                            <input type="text" class="form-control" name="train_number" required>
                        </div>
                        <div class="mb-3">
                            <label>Departure Station</label>
                            <input type="text" class="form-control" name="departure_station" required>
                        </div>
                        <div class="mb-3">
                            <label>Arrival Station</label>
                            <input type="text" class="form-control" name="arrival_station" required>
                        </div>
                        <div class="mb-3">
                            <label>Departure Time</label>
                            <input type="datetime-local" class="form-control" name="departure_time" required>
                        </div>
                        <div class="mb-3">
                            <label>Arrival Time</label>
                            <input type="datetime-local" class="form-control" name="arrival_time" required>
                        </div>
                        <div class="mb-3">
                            <label>Platform Number</label>
                            <input type="text" class="form-control" name="platform_number">
                        </div>
                        <div class="mb-3">
                            <label>Price (RM)</label>
                            <input type="number" class="form-control" name="price" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Available Seats</label>
                            <input type="number" class="form-control" name="available_seats" value="100" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'schedule_actions.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'schedule_id';
                idInput.value = scheduleId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
