
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['cancel_ticket']) && isset($_POST['ticket_id'])) {
    $ticket_id = $_POST['ticket_id'];
    $user_id = $_SESSION['user_id'];

    // First verify that this ticket belongs to the user and is active
    $verify_sql = "SELECT t.*, s.departure_time 
                  FROM tickets t 
                  JOIN schedules s ON t.schedule_id = s.schedule_id 
                  WHERE t.ticket_id = ? AND t.user_id = ? AND t.status = 'active'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $ticket_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();

    if ($result->num_rows === 1) {
        $ticket = $result->fetch_assoc();
        
        // Check if cancellation is allowed (24 hours before departure)
        $departure = new DateTime($ticket['departure_time']);
        $now = new DateTime();
        $interval = $now->diff($departure);
        $hours_until_departure = ($interval->days * 24) + $interval->h;

        if ($hours_until_departure >= 24) {
            // Update ticket status to cancelled and set refund status
            $update_sql = "UPDATE tickets 
                          SET status = 'cancelled', 
                              payment_status = 'refunded',
                              updated_at = NOW() 
                          WHERE ticket_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $ticket_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Ticket cancelled successfully. Your refund will be processed within 7 working days.";
            } else {
                $_SESSION['error_message'] = "Error cancelling ticket. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Cannot cancel ticket - less than 24 hours before departure.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid ticket or ticket already cancelled.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

header("Location: Cancellation_And_refund_page.php");
exit();
