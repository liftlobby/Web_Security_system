
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';
require_once 'includes/NotificationManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Process ticket cancellation if ticket_id is provided
if (isset($_GET['ticket_id'])) {
    $ticket_id = $_GET['ticket_id'];
    
    try {
        $conn->begin_transaction();

        // Verify ticket belongs to user and is not already cancelled
        $check_sql = "SELECT t.*, s.available_seats, s.schedule_id, s.departure_time,
                            s.train_number, s.departure_station, s.arrival_station, s.price,
                            p.payment_id, p.amount as paid_amount 
                     FROM tickets t 
                     JOIN schedules s ON t.schedule_id = s.schedule_id 
                     JOIN payments p ON t.ticket_id = p.ticket_id
                     WHERE t.ticket_id = ? AND t.user_id = ? AND t.status != 'cancelled'
                     AND p.status = 'completed'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $ticket_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Invalid ticket or already cancelled");
        }

        $ticket = $result->fetch_assoc();
        
        // Check if cancellation is within 24 hours of departure
        $departure_time = strtotime($ticket['departure_time']);
        $current_time = time();
        $hours_until_departure = round(($departure_time - $current_time) / 3600);
        
        if ($hours_until_departure < 24) {
            throw new Exception("Cancellations must be made at least 24 hours before departure. Your train departs in " . $hours_until_departure . " hours.");
        }

        if (isset($_POST['confirm_cancel'])) {
            // Update ticket status
            $update_ticket = $conn->prepare("UPDATE tickets SET status = 'cancelled', payment_status = 'refunded' WHERE ticket_id = ?");
            $update_ticket->bind_param("i", $ticket_id);
            
            if ($update_ticket->execute()) {
                // Calculate refund amount (100% refund if more than 24 hours before departure)
                $refund_amount = $ticket['paid_amount'];
                
                // Create refund record
                $refund_sql = "INSERT INTO refunds (ticket_id, amount, refund_date, status) VALUES (?, ?, NOW(), 'processed')";
                $refund_stmt = $conn->prepare($refund_sql);
                $refund_stmt->bind_param("id", $ticket_id, $refund_amount);
                $refund_stmt->execute();
                
                // Update available seats
                $update_seats = $conn->prepare("UPDATE schedules SET available_seats = available_seats + ? WHERE schedule_id = ?");
                $update_seats->bind_param("ii", $ticket['num_seats'], $ticket['schedule_id']);
                $update_seats->execute();
                
                // Send cancellation notification
                $notificationManager = new NotificationManager($conn);
                
                // Create detailed message
                $message = "Ticket Cancellation Confirmation\n\n";
                $message .= "Your ticket has been successfully cancelled:\n\n";
                $message .= "Ticket Details:\n";
                $message .= "Train: " . $ticket['train_number'] . "\n";
                $message .= "From: " . $ticket['departure_station'] . "\n";
                $message .= "To: " . $ticket['arrival_station'] . "\n";
                $message .= "Departure: " . date('d M Y, h:i A', strtotime($ticket['departure_time'])) . "\n";
                $message .= "Refund Amount: RM " . number_format($refund_amount, 2) . "\n\n";
                $message .= "Your refund will be processed within 3-5 business days.";
                
                // Send cancellation notification
                $notificationManager->sendTicketStatusNotification(
                    $ticket_id,
                    'cancelled',
                    $message
                );
                
                $conn->commit();
                $_SESSION['success_message'] = "Ticket cancelled successfully. Refund will be processed within 3-5 business days.";
                header("Location: history.php");
                exit();
            } else {
                throw new Exception("Error cancelling ticket");
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        if (isset($_POST['confirm_cancel'])) {
            header("Location: history.php");
            exit();
        }
    }
}

// Fetch user's active tickets that are eligible for cancellation (>24 hours before departure)
$tickets_sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
                       s.departure_time, s.arrival_time, s.price,
                       TIMESTAMPDIFF(HOUR, NOW(), s.departure_time) as hours_until_departure,
                       p.amount as paid_amount
                FROM tickets t
                JOIN schedules s ON t.schedule_id = s.schedule_id
                JOIN payments p ON t.ticket_id = p.ticket_id
                WHERE t.user_id = ? AND t.status != 'cancelled'
                AND s.departure_time > NOW()
                AND p.status = 'completed'
                HAVING hours_until_departure >= 24
                ORDER BY s.departure_time ASC";
$tickets_stmt = $conn->prepare($tickets_sql);
$tickets_stmt->bind_param("i", $user_id);
$tickets_stmt->execute();
$tickets_result = $tickets_stmt->get_result();

// Fetch tickets that are too late to cancel
$late_tickets_sql = "SELECT t.*, s.train_number, s.departure_station, s.arrival_station, 
                            s.departure_time, s.arrival_time, s.price,
                            TIMESTAMPDIFF(HOUR, NOW(), s.departure_time) as hours_until_departure,
                            p.amount as paid_amount
                     FROM tickets t
                     JOIN schedules s ON t.schedule_id = s.schedule_id
                     JOIN payments p ON t.ticket_id = p.ticket_id
                     WHERE t.user_id = ? AND t.status != 'cancelled'
                     AND s.departure_time > NOW()
                     AND p.status = 'completed'
                     HAVING hours_until_departure < 24
                     ORDER BY s.departure_time ASC";
$late_tickets_stmt = $conn->prepare($late_tickets_sql);
$late_tickets_stmt->bind_param("i", $user_id);
$late_tickets_stmt->execute();
$late_tickets_result = $late_tickets_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Cancellation - Railway System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style/style_ticketcancellation.css">
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <div class="container">
        <h1 class="page-title">Ticket Cancellation</h1>

        <!-- Policy Section -->
        <div class="policy-section">
            <h2>Cancellation and Refund Policy</h2>
            <ul class="policy-list">
                <li>Cancellation requests must be made at least 24 hours before departure time</li>
                <li>Service charges will apply for all refunds</li>
                <li>Refunds will be processed within 7 working days</li>
                <li>No refunds will be provided for missed departures</li>
            </ul>
            <div class="important-note">
                <strong>Important:</strong> Refunds can only be physically collected at any station. 
                Please bring your booking confirmation and valid ID.
            </div>
        </div>

        <!-- Steps Section -->
        <div class="steps-section">
            <h2>Steps to Cancel Your Ticket</h2>
            <ul class="steps-list">
                <li>Select the ticket you wish to cancel from your active tickets below</li>
                <li>Review the ticket details and cancellation policy</li>
                <li>Confirm your cancellation request</li>
                <li>Visit any station with your booking confirmation and valid ID to collect your refund</li>
            </ul>
        </div>

        <!-- Active Tickets Section -->
        <div class="tickets-section">
            <h2>Your Active Tickets</h2>
            <?php if ($tickets_result->num_rows > 0): ?>
                <?php while ($ticket = $tickets_result->fetch_assoc()): ?>
                    <div class="ticket-card">
                        <div class="ticket-details">
                            <h3>Train <?php echo htmlspecialchars($ticket['train_number']); ?></h3>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($ticket['departure_station']); ?></p>
                            <p><strong>To:</strong> <?php echo htmlspecialchars($ticket['arrival_station']); ?></p>
                            <p><strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($ticket['departure_time'])); ?></p>
                            <p><strong>Seat:</strong> <?php echo htmlspecialchars($ticket['seat_number']); ?></p>
                            <p><strong>Price:</strong> RM <?php echo number_format($ticket['price'], 2); ?></p>
                        </div>
                        <div class="ticket-actions">
                            <button class="btn btn-cancel" onclick="showCancelModal(<?php echo $ticket['ticket_id']; ?>)">
                                Cancel Ticket
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-tickets">
                    <p>You have no active tickets that can be cancelled.</p>
                </div>
            <?php endif; ?>

            <?php if ($late_tickets_result->num_rows > 0): ?>
                <h2>Tickets That Are Too Late to Cancel</h2>
                <?php while ($late_ticket = $late_tickets_result->fetch_assoc()): ?>
                    <div class="ticket-card">
                        <div class="ticket-details">
                            <h3>Train <?php echo htmlspecialchars($late_ticket['train_number']); ?></h3>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($late_ticket['departure_station']); ?></p>
                            <p><strong>To:</strong> <?php echo htmlspecialchars($late_ticket['arrival_station']); ?></p>
                            <p><strong>Departure:</strong> <?php echo date('d M Y, h:i A', strtotime($late_ticket['departure_time'])); ?></p>
                            <p><strong>Seat:</strong> <?php echo htmlspecialchars($late_ticket['seat_number']); ?></p>
                            <p><strong>Price:</strong> RM <?php echo number_format($late_ticket['price'], 2); ?></p>
                        </div>
                        <div class="ticket-actions">
                            <span class="btn btn-secondary" style="cursor: not-allowed;">
                                Cannot Cancel (< 24h)
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCancelModal()">&times;</span>
            <h2>Confirm Cancellation</h2>
            <p>Are you sure you want to cancel this ticket? This action cannot be undone.</p>
            <form method="POST" id="cancelForm">
                <div style="margin: 20px 0;">
                    <label for="cancellation_reason" style="display: block; margin-bottom: 10px; color: #003366; font-weight: bold;">Please tell us why you're cancelling this ticket:</label>
                    <select name="cancellation_reason" id="cancellation_reason" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                        <option value="">Select a reason</option>
                        <option value="Change of plans">Change of plans</option>
                        <option value="Emergency">Emergency</option>
                        <option value="Found better alternative">Found better alternative</option>
                        <option value="Schedule conflict">Schedule conflict</option>
                        <option value="Weather concerns">Weather concerns</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="refund-info" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h3 style="color: #003366; margin-bottom: 10px;">Refund Information</h3>
                    <ul style="list-style-type: none; padding: 0;">
                        <li style="margin-bottom: 5px;">• Your refund will be processed within 7 working days</li>
                        <li style="margin-bottom: 5px;">• Visit any station with your booking confirmation and ID</li>
                        <li style="margin-bottom: 5px;">• Service charges may apply</li>
                    </ul>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">No, Keep Ticket</button>
                    <button type="submit" name="confirm_cancel" class="btn btn-cancel">Yes, Cancel Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once 'Head_and_Foot/footer.php'; ?>

    <script>
        function showCancelModal(ticketId) {
            const modal = document.getElementById('cancelModal');
            const form = document.getElementById('cancelForm');
            form.action = 'ticket_cancellation.php?ticket_id=' + ticketId;
            modal.style.display = "block";
        }

        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
