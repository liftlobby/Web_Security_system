
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';

// Check if user is logged in and has transaction ID
if (!isset($_SESSION['user_id']) || !isset($_GET['transaction_id'])) {
    header("Location: ticketing.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_GET['transaction_id'];

try {
    // Fetch ticket details for display
    $sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
                   s.departure_time, s.arrival_time, s.price, p.payment_method,
                   p.transaction_id
            FROM tickets t
            JOIN schedules s ON t.schedule_id = s.schedule_id
            JOIN payments p ON t.ticket_id = p.ticket_id
            WHERE p.transaction_id = ?
            ORDER BY t.ticket_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No tickets found");
    }

    $tickets = $result->fetch_all(MYSQLI_ASSOC);
    $total_amount = array_sum(array_column($tickets, 'price'));

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: history.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Railway System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style_paymentsuccess.css">
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Payment Successful!</h1>
        <p>Your tickets have been confirmed and are now active.</p>

        <div class="transaction-info">
            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transaction_id); ?></p>
            <p><strong>Payment Method:</strong> <?php echo ucwords(str_replace('_', ' ', $tickets[0]['payment_method'])); ?></p>
            <p><strong>Date:</strong> <?php echo date('d M Y, h:i A'); ?></p>
            <p><strong>Status:</strong> <span style="color: #28a745;">Confirmed</span></p>
        </div>

        <div class="tickets-container">
            <h2>Your Tickets</h2>
            <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-card">
                    <div class="ticket-details">
                        <h3>Train <?php echo htmlspecialchars($ticket['train_number']); ?></h3>
                        <p><strong>From:</strong> <?php echo htmlspecialchars($ticket['departure_station']); ?></p>
                        <p><strong>To:</strong> <?php echo htmlspecialchars($ticket['arrival_station']); ?></p>
                        <p><strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></p>
                        <p><strong>Arrival:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['arrival_time'])); ?></p>
                        <?php if (isset($ticket['seat_number'])): ?>
                            <p><strong>Seat:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                        <?php endif; ?>
                        <p><strong>Price:</strong> RM <?php echo number_format($ticket['price'], 2); ?></p>
                    </div>
                    <div class="qr-code-container">
                        <img src="generate_qr.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" 
                             alt="Ticket QR Code" 
                             class="qr-code">
                            <br>
                        <small>Show at gate</small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="total-amount">
            Total Amount Paid: RM <?php echo number_format($total_amount, 2); ?>
        </div>

        <div class="payment-info">
            <p>A confirmation email has been sent to your registered email address.</p>
            <p>Please show your QR code(s) at the station gate for entry.</p>
        </div>

        <div class="btn-group">
            <a href="history.php" class="btn btn-primary">View All Tickets</a>
            <a href="ticketing.php" class="btn btn-primary">Book Another Ticket</a>
        </div>
    </div>

    <?php require_once 'Head_and_Foot/footer.php'; ?>
</body>
</html>
