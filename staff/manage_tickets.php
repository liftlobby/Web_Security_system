<?php
session_start();
require_once '../config/database.php';
require_once '../includes/MessageUtility.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

// Handle ticket actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $ticket_id = $_POST['ticket_id'];
        
        if ($_POST['action'] === 'verify') {
            // Verify ticket
            $stmt = $conn->prepare("UPDATE tickets SET status = 'completed' WHERE ticket_id = ?");
            $stmt->bind_param("i", $ticket_id);
            
            if ($stmt->execute()) {
                // Add verification record
                $staff_id = $_SESSION['staff_id'];
                $stmt = $conn->prepare("INSERT INTO ticket_verifications (ticket_id, staff_id, verification_time, status) VALUES (?, ?, NOW(), 'success')");
                $stmt->bind_param("ii", $ticket_id, $staff_id);
                $stmt->execute();
                
                MessageUtility::setSuccessMessage("Ticket verified successfully!");
            } else {
                MessageUtility::setErrorMessage("Failed to verify ticket.");
            }
        } elseif ($_POST['action'] === 'cancel') {
            // Cancel ticket
            $stmt = $conn->prepare("UPDATE tickets SET status = 'cancelled' WHERE ticket_id = ?");
            $stmt->bind_param("i", $ticket_id);
            
            if ($stmt->execute()) {
                // Create refund record
                $stmt = $conn->prepare("SELECT payment_amount FROM tickets WHERE ticket_id = ?");
                $stmt->bind_param("i", $ticket_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $ticket = $result->fetch_assoc();
                
                $staff_id = $_SESSION['staff_id'];
                $amount = $ticket['payment_amount'];
                $stmt = $conn->prepare("INSERT INTO refunds (ticket_id, amount, refund_date, status, processed_by) VALUES (?, ?, NOW(), 'pending', ?)");
                $stmt->bind_param("idi", $ticket_id, $amount, $staff_id);
                $stmt->execute();
                
                MessageUtility::setSuccessMessage("Ticket cancelled and refund initiated!");
            } else {
                MessageUtility::setErrorMessage("Failed to cancel ticket.");
            }
        }
    }
}

// Fetch all tickets with user details
$sql = "SELECT t.*, u.username, s.train_number, s.departure_station, s.arrival_station, 
        s.departure_time, s.arrival_time, s.price 
        FROM tickets t 
        JOIN users u ON t.user_id = u.user_id 
        JOIN schedules s ON t.schedule_id = s.schedule_id 
        ORDER BY t.booking_date DESC";
$tickets = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tickets - Railway System</title>
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
        .ticket-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .ticket-status.active {
            background-color: #d4edda;
            color: #155724;
        }
        .ticket-status.completed {
            background-color: #cce5ff;
            color: #004085;
        }
        .ticket-status.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .dashboard-header {
            margin-bottom: 30px;
        }
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: none;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .modal-content {
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .modal-header {
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        .modal-body h6 {
            color: #0056b3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col content">
                <div class="dashboard-header">
                    <h2>Manage Tickets</h2>
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="text-muted mb-0">View and manage all ticket bookings</p>
                        <div>
                            <button class="btn btn-primary me-2" onclick="window.location.href='scan_qr.php'">
                                <i class='bx bx-qr-scan'></i> Scan QR Code
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#verifyTicketModal">
                                <i class='bx bx-check-circle'></i> Verify Ticket
                            </button>
                        </div>
                    </div>
                </div>

                <?php MessageUtility::displayMessages(); ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>User</th>
                                        <th>Train</th>
                                        <th>Route</th>
                                        <th>Departure</th>
                                        <th>Status</th>
                                        <th>Booking Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($ticket = $tickets->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $ticket['ticket_id']; ?></td>
                                        <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                                        <td><?php echo htmlspecialchars($ticket['train_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($ticket['departure_station']); ?> →
                                            <?php echo htmlspecialchars($ticket['arrival_station']); ?>
                                        </td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></td>
                                        <td>
                                            <span class="ticket-status <?php echo $ticket['status']; ?>">
                                                <?php echo ucfirst($ticket['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($ticket['booking_date'])); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-action" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $ticket['ticket_id']; ?>">
                                                <i class='bx bx-info-circle'></i>
                                            </button>
                                            <?php if ($ticket['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <button type="submit" class="btn btn-success btn-action">
                                                    <i class='bx bx-check'></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this ticket?');">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-danger btn-action">
                                                    <i class='bx bx-x'></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Ticket Details Modal -->
                                    <div class="modal fade" id="detailsModal<?php echo $ticket['ticket_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Ticket Details #<?php echo $ticket['ticket_id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-4">
                                                        <h6>Passenger Information</h6>
                                                        <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($ticket['username']); ?></p>
                                                        <p class="mb-1"><strong>Passenger Name:</strong> <?php echo htmlspecialchars($ticket['passenger_name'] ?? 'Not specified'); ?></p>
                                                        <p class="mb-0"><strong>Seat Number:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                                                    </div>
                                                    <div class="mb-4">
                                                        <h6>Journey Details</h6>
                                                        <p class="mb-1"><strong>Train:</strong> <?php echo htmlspecialchars($ticket['train_number']); ?></p>
                                                        <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($ticket['departure_station']); ?></p>
                                                        <p class="mb-1"><strong>To:</strong> <?php echo htmlspecialchars($ticket['arrival_station']); ?></p>
                                                        <p class="mb-1"><strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></p>
                                                        <p class="mb-0"><strong>Arrival:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['arrival_time'])); ?></p>
                                                    </div>
                                                    <div class="mb-4">
                                                        <h6>Payment Information</h6>
                                                        <p class="mb-1"><strong>Amount:</strong> RM<?php echo number_format($ticket['payment_amount'], 2); ?></p>
                                                        <p class="mb-1"><strong>Status:</strong> <?php echo ucfirst($ticket['payment_status']); ?></p>
                                                        <p class="mb-0"><strong>Booking Date:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['booking_date'])); ?></p>
                                                    </div>
                                                    <?php if ($ticket['special_requests']): ?>
                                                    <div>
                                                        <h6>Special Requests</h6>
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($ticket['special_requests'])); ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <?php if ($ticket['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                                        <input type="hidden" name="action" value="verify">
                                                        <button type="submit" class="btn btn-success">Verify Ticket</button>
                                                    </form>
                                                    <?php endif; ?>
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

    <!-- Verify Ticket Modal -->
    <div class="modal fade" id="verifyTicketModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="verify">
                        <div class="mb-3">
                            <label for="ticketId" class="form-label">Ticket ID</label>
                            <input type="number" class="form-control" id="ticketId" name="ticket_id" required>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Verify Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
