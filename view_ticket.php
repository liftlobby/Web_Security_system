<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if ticket_id is provided
if (!isset($_GET['ticket_id'])) {
    header("Location: history.php");
    exit();
}

$ticket_id = $_GET['ticket_id'];

// Fetch ticket details
$sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
               s.departure_time, s.arrival_time, s.price,
               p.payment_date, p.payment_method
        FROM tickets t
        JOIN schedules s ON t.schedule_id = s.schedule_id
        LEFT JOIN payments p ON t.ticket_id = p.ticket_id
        WHERE t.ticket_id = ? AND t.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $ticket_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: history.php");
    exit();
}

$ticket = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ticket - Railway System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style_viewticket.css">
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="ticket-container">
            <div class="ticket-status <?php echo $ticket['payment_status'] === 'paid' ? 'status-paid' : 'status-pending'; ?>">
                <?php echo ucfirst($ticket['payment_status']); ?>
            </div>

            <div class="ticket-header">
                <h1>Railway Ticket</h1>
                <p>Ticket #<?php echo str_pad($ticket['ticket_id'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>

            <div class="ticket-details">
                <div class="detail-group">
                    <label>Train Number</label>
                    <div class="value"><?php echo htmlspecialchars($ticket['train_number']); ?></div>
                </div>

                <div class="detail-group">
                    <label>Seat Number</label>
                    <div class="value"><?php echo htmlspecialchars($ticket['seat_number']); ?></div>
                </div>

                <div class="detail-group">
                    <label>From</label>
                    <div class="value"><?php echo htmlspecialchars($ticket['departure_station']); ?></div>
                </div>

                <div class="detail-group">
                    <label>To</label>
                    <div class="value"><?php echo htmlspecialchars($ticket['arrival_station']); ?></div>
                </div>

                <div class="detail-group">
                    <label>Departure</label>
                    <div class="value"><?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></div>
                </div>

                <div class="detail-group">
                    <label>Arrival</label>
                    <div class="value"><?php echo date('d M Y, h:i A', strtotime($ticket['arrival_time'])); ?></div>
                </div>

                <div class="detail-group">
                    <label>Amount Paid</label>
                    <div class="value">RM <?php echo number_format($ticket['payment_amount'], 2); ?></div>
                </div>

                <div class="detail-group">
                    <label>Payment Method</label>
                    <div class="value"><?php echo ucfirst($ticket['payment_method']); ?></div>
                </div>
            </div>

            <div class="qr-code">
                <img src="generate_qr.php?ticket_id=<?php echo urlencode($ticket['ticket_id']); ?>" alt="Ticket QR Code">
            </div>

            <div class="ticket-footer">
                <p><small>Please show this ticket and valid ID during boarding</small></p>
                <div class="ticket-actions">
                    <a href="javascript:window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Ticket
                    </a>
                    <a href="history.php" class="btn btn-secondary">
                        <i class="fas fa-history"></i> View History
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'Head_and_Foot/footer.php'; ?>
</body>
</html>
