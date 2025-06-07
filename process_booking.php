
<?php
require_once 'includes/Session.php';
if (!Session::initialize()) {
    exit();
}
require_once 'config/database.php';
require_once 'includes/TokenManager.php';
require_once 'includes/NotificationManager.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['schedule_id']) || !isset($_POST['ticket_quantity']) || !isset($_POST['price'])) {
    header("Location: ticketing.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$schedule_id = $_POST['schedule_id'];
$ticket_quantity = intval($_POST['ticket_quantity']);
$price = floatval($_POST['price']);
$total_price = $price * $ticket_quantity;
$passenger_name = isset($_POST['passenger_name']) ? trim($_POST['passenger_name']) : null;

// Validate passenger name
if (empty($passenger_name)) {
    $_SESSION['error_message'] = "Passenger name is required.";
    header("Location: ticketing.php");
    exit();
}

// Validate ticket quantity
if ($ticket_quantity < 1 || $ticket_quantity > 4) {
    $_SESSION['error_message'] = "Invalid number of tickets.";
    header("Location: ticketing.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

try {
    $conn->begin_transaction();

    // Check if train has already departed (5 minutes buffer for last-minute bookings)
    $buffer_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $check_departure_sql = "SELECT s.*, s.train_number, s.departure_station, s.arrival_station 
                           FROM schedules s 
                           WHERE s.schedule_id = ? 
                           AND s.departure_time > ?
                           AND s.available_seats >= ?";
                           
    $check_stmt = $conn->prepare($check_departure_sql);
    $check_stmt->bind_param("isi", $schedule_id, $buffer_time, $ticket_quantity);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("This train has departed or not enough seats available.");
    }

    $schedule_data = $result->fetch_assoc();
    
    // Verify price matches database
    if (abs($schedule_data['price'] - $price) > 0.01) {
        throw new Exception("Invalid ticket price.");
    }

    // Get last used seat number
    $seat_sql = "SELECT MAX(CAST(SUBSTRING(seat_number, 2) AS UNSIGNED)) as last_seat 
                 FROM tickets 
                 WHERE schedule_id = ? 
                 AND status != 'cancelled'";
    
    $seat_stmt = $conn->prepare($seat_sql);
    $seat_stmt->bind_param("i", $schedule_id);
    $seat_stmt->execute();
    $seat_result = $seat_stmt->get_result();
    $last_seat_data = $seat_result->fetch_assoc();
    $last_seat_num = $last_seat_data['last_seat'] ?? 0;

    // Generate seat numbers
    $start_seat = $last_seat_num + 1;
    $end_seat = $start_seat + $ticket_quantity - 1;
    $seat_range = "A$start_seat-A$end_seat";

    // Insert single ticket for multiple seats (with temporary QR code)
    $insert_sql = "INSERT INTO tickets (user_id, schedule_id, seat_number, num_seats, passenger_name, status, booking_date, payment_amount, qr_code, payment_status) 
                   VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, 'temp', 'pending')";
    $total_amount = $price * $ticket_quantity;
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("issisi", $user_id, $schedule_id, $seat_range, $ticket_quantity, $passenger_name, $total_amount);
    
    if ($insert_stmt->execute()) {
        $ticket_id = $conn->insert_id;
        
        // Store booking information in session for payment
        $_SESSION['ticket_ids'] = [$ticket_id];
        $_SESSION['total_price'] = $total_amount;
        $_SESSION['ticket_quantity'] = $ticket_quantity;
        
        $conn->commit();
        
        // Redirect to payment page
        header("Location: payment.php");
        exit();
    } else {
        throw new Exception("Failed to create ticket.");
    }
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ticketing.php");
    exit();
}
?>
