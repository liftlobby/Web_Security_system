
<?php
require_once 'includes/Session.php';
if (!Session::initialize()) {
    // Session was timed out, user will be redirected
    exit();
}

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_time = date('Y-m-d H:i:s');

// Fetch user's tickets with schedule details
$sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
               s.departure_time, s.arrival_time, s.price,
               CASE 
                   WHEN s.departure_time <= NOW() THEN 'departed'
                   WHEN t.status = 'cancelled' THEN 'cancelled'
                   ELSE 'active'
               END as ticket_status
        FROM tickets t
        JOIN schedules s ON t.schedule_id = s.schedule_id
        WHERE t.user_id = ?
        ORDER BY s.departure_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$active_tickets = [];
$past_tickets = [];

while ($ticket = $result->fetch_assoc()) {
    if ($ticket['ticket_status'] === 'active') {
        $active_tickets[] = $ticket;
    } else {
        $past_tickets[] = $ticket;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Ticket History - Railway System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style/style_history.css">
    <!-- Session Management -->
    <script src="js/session-manager.js"></script>
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <div class="container">
        <h1 class="page-title">My Ticket History</h1>

        <div class="tickets-grid">
            <!-- Active Tickets Column -->
            <div class="tickets-column">
                <h2 class="column-header">Active Tickets</h2>
                <?php if (empty($active_tickets)): ?>
                    <div class="no-tickets">
                        <p>No active tickets</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_tickets as $ticket): ?>
                        <div class="ticket-container">
                            <div class="ticket-status status-active">Active</div>
                            <div class="ticket-details">
                                <h3>Train <?php echo htmlspecialchars($ticket['train_number']); ?></h3>
                                <p><strong>From:</strong> <?php echo htmlspecialchars($ticket['departure_station']); ?></p>
                                <p><strong>To:</strong> <?php echo htmlspecialchars($ticket['arrival_station']); ?></p>
                                <p><strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></p>
                                <p><strong>Arrival:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['arrival_time'])); ?></p>
                                <p><strong>Seat:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                                <p><strong>Price:</strong> RM <?php echo number_format($ticket['price'], 2); ?></p>
                                <p><strong>Booking Date:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['booking_date'])); ?></p>
                            </div>
                            <div class="ticket-qr">
                                <img src="generate_qr.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" 
                                     alt="Ticket QR Code" 
                                     class="qr-code"
                                     onclick="showQRModal(<?php echo $ticket['ticket_id']; ?>)">
                                <button class="btn btn-view-qr" onclick="showQRModal(<?php echo $ticket['ticket_id']; ?>)">
                                    View QR Code
                                </button>
                                <a href="download_ticket.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-download">
                                    Download Ticket
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Past Tickets Column -->
            <div class="tickets-column">
                <h2 class="column-header">Past & Cancelled Tickets</h2>
                <?php if (empty($past_tickets)): ?>
                    <div class="no-tickets">
                        <p>No past tickets</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($past_tickets as $ticket): ?>
                        <div class="ticket-container">
                            <div class="ticket-status <?php 
                                echo $ticket['ticket_status'] === 'cancelled' ? 'status-cancelled' : 'status-departed';
                            ?>">
                                <?php echo ucfirst($ticket['ticket_status']); ?>
                            </div>
                            <div class="ticket-details">
                                <h3>Train <?php echo htmlspecialchars($ticket['train_number']); ?></h3>
                                <p><strong>From:</strong> <?php echo htmlspecialchars($ticket['departure_station']); ?></p>
                                <p><strong>To:</strong> <?php echo htmlspecialchars($ticket['arrival_station']); ?></p>
                                <p><strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></p>
                                <p><strong>Arrival:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['arrival_time'])); ?></p>
                                <p><strong>Seat:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                                <p><strong>Price:</strong> RM <?php echo number_format($ticket['price'], 2); ?></p>
                                <p><strong>Booking Date:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['booking_date'])); ?></p>
                                <?php if ($ticket['status'] === 'cancelled'): ?>
                                    <p><strong>Status:</strong> <span class="text-danger">Cancelled</span></p>
                                <?php elseif ($ticket['ticket_status'] === 'departed'): ?>
                                    <p><strong>Status:</strong> <span class="text-secondary">Departed</span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeQRModal()">&times;</span>
            <img id="modalQRCode" src="" alt="QR Code" style="width: 100%; height: auto;">
        </div>
    </div>

    <?php require_once 'Head_and_Foot/footer.php'; ?>

    <script>
    function showQRModal(ticketId) {
        const modal = document.getElementById('qrModal');
        const modalImg = document.getElementById('modalQRCode');
        modal.style.display = "block";
        modalImg.src = `generate_qr.php?ticket_id=${ticketId}`;
    }

    function closeQRModal() {
        const modal = document.getElementById('qrModal');
        modal.style.display = "none";
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('qrModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>
</body>
</html>
