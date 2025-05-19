<?php
session_start();
require_once '../config/database.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['ticket_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ticket ID not provided']);
    exit();
}

$ticket_id = $_GET['ticket_id'];

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_time = date('Y-m-d H:i:s');

// Get ticket details with user, schedule, and payment information
$sql = "SELECT t.*, u.username, s.train_number, s.departure_station, s.arrival_station, 
        s.departure_time, s.arrival_time, s.platform_number, s.train_status, s.available_seats,
        p.payment_status, p.amount as payment_amount, p.payment_method, p.transaction_id,
        r.refund_id, r.amount as refund_amount, r.status as refund_status,
        CASE 
            WHEN s.departure_time > DATE_ADD(NOW(), INTERVAL 2 HOUR) THEN 'too_early'
            WHEN NOW() > DATE_ADD(s.departure_time, INTERVAL 30 MINUTE) THEN 'expired'
            ELSE 'valid'
        END as scan_status
        FROM tickets t 
        JOIN users u ON t.user_id = u.user_id 
        JOIN schedules s ON t.schedule_id = s.schedule_id 
        LEFT JOIN payments p ON t.ticket_id = p.ticket_id 
        LEFT JOIN refunds r ON t.ticket_id = r.ticket_id 
        WHERE t.ticket_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ticket not found']);
    exit();
}

$ticket = $result->fetch_assoc();

// Check ticket status and time validity
$message = '';
$can_scan = true;

switch($ticket['scan_status']) {
    case 'too_early':
        $message = "This ticket cannot be used yet. Departure time is " . 
                  date('d M Y, h:i A', strtotime($ticket['departure_time'])) . 
                  ". Please scan within 2 hours before departure.";
        $can_scan = false;
        break;
        
    case 'expired':
        $message = "This ticket has expired. It was valid until " . 
                  date('d M Y, h:i A', strtotime($ticket['departure_time'] . ' +30 minutes')) . 
                  ".";
        $can_scan = false;
        break;
        
    case 'valid':
        if ($ticket['status'] === 'cancelled') {
            $message = "This ticket has been cancelled.";
            $can_scan = false;
        } elseif ($ticket['payment_status'] !== 'paid') {
            $message = "This ticket has not been paid.";
            $can_scan = false;
        } else {
            $message = "Ticket is valid for scanning.";
        }
        break;
}

// Format dates for display
$ticket['departure_time'] = date('Y-m-d H:i:s', strtotime($ticket['departure_time']));
$ticket['arrival_time'] = date('Y-m-d H:i:s', strtotime($ticket['arrival_time']));
$ticket['booking_date'] = date('Y-m-d H:i:s', strtotime($ticket['booking_date']));
if ($ticket['created_at']) $ticket['created_at'] = date('Y-m-d H:i:s', strtotime($ticket['created_at']));
if ($ticket['updated_at']) $ticket['updated_at'] = date('Y-m-d H:i:s', strtotime($ticket['updated_at']));

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'ticket' => $ticket,
    'message' => $message,
    'can_scan' => $can_scan
]);
