
<?php
session_start();
require_once '../config/database.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Prevent any output before our JSON response
ob_clean();
header('Content-Type: application/json');

// Error handling to catch any PHP errors
function handleError($errno, $errstr, $errfile, $errline) {
    $error = [
        'success' => false,
        'message' => 'Server error: ' . $errstr,
        'error_details' => [
            'file' => $errfile,
            'line' => $errline
        ]
    ];
    echo json_encode($error);
    exit();
}
set_error_handler('handleError');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$staff_id = $_SESSION['staff_id'];

// Handle GET request for ticket details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ticket_id'])) {
    $ticket_id = $_GET['ticket_id'];
    
    try {
        // Get ticket details
        $sql = "SELECT t.*, u.username, s.train_number, s.departure_station, s.arrival_station, 
                       s.departure_time, s.arrival_time, s.platform_number, s.train_status,
                       p.payment_method, p.amount as payment_amount
                FROM tickets t
                JOIN users u ON t.user_id = u.user_id
                JOIN schedules s ON t.schedule_id = s.schedule_id
                LEFT JOIN payments p ON t.ticket_id = p.ticket_id
                WHERE t.ticket_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Ticket not found']);
            exit();
        }

        $ticket_data = $result->fetch_assoc();
        
        // Format seat information for display
        $seat_info = $ticket_data['seat_number'];
        if (strpos($seat_info, '-') !== false) {
            list($start, $end) = explode('-', $seat_info);
            $ticket_data['seat_display'] = "Seats $start to $end";
        } else {
            $ticket_data['seat_display'] = "Seat " . $seat_info;
        }
        
        // Check ticket status
        if ($ticket_data['status'] === 'cancelled') {
            echo json_encode([
                'success' => false, 
                'message' => 'This ticket has been cancelled'
            ]);
            exit();
        }

        if ($ticket_data['status'] === 'used') {
            echo json_encode([
                'success' => false, 
                'message' => 'This ticket has already been used'
            ]);
            exit();
        }

        // Get current time and departure time
        $departure_time = new DateTime($ticket_data['departure_time']);
        $current_time = new DateTime(); // Use current time
        
        // Calculate time difference in minutes
        $time_difference = $current_time->diff($departure_time);
        $minutes_difference = ($time_difference->days * 24 * 60) + ($time_difference->h * 60) + $time_difference->i;
        
        // If departure time is in the past, make the difference negative
        if ($current_time > $departure_time) {
            $minutes_difference = -$minutes_difference;
        }

        // Convert our windows to minutes
        $early_window = 120; // 2 hours = 120 minutes
        $late_window = 30;  // 30 minutes

        // Check if we're too early (more than 2 hours before departure)
        if ($minutes_difference > $early_window) {
            echo json_encode([
                'success' => false,
                'can_scan' => false,
                'message' => 'This ticket cannot be used yet. Departure time is ' . $departure_time->format('d M Y, h:i A')
            ]);
            exit();
        }

        // Check if we're too late (more than 30 minutes after departure)
        if ($minutes_difference < -$late_window) {
            echo json_encode([
                'success' => false,
                'can_scan' => false,
                'message' => 'This ticket has expired. Departure time was ' . $departure_time->format('d M Y, h:i A')
            ]);
            exit();
        }

        // Check payment status
        if ($ticket_data['payment_status'] !== 'paid') {
            echo json_encode([
                'success' => false,
                'can_scan' => false,
                'message' => 'This ticket has not been paid.',
                'ticket' => $ticket_data
            ]);
            exit();
        }

        // Return ticket details with can_scan status
        echo json_encode([
            'success' => true,
            'ticket' => [
                'ticket_id' => $ticket_data['ticket_id'],
                'username' => $ticket_data['username'],
                'train_number' => $ticket_data['train_number'],
                'departure_station' => $ticket_data['departure_station'],
                'arrival_station' => $ticket_data['arrival_station'],
                'departure_time' => $ticket_data['departure_time'],
                'arrival_time' => $ticket_data['arrival_time'],
                'platform_number' => $ticket_data['platform_number'],
                'train_status' => $ticket_data['train_status'],
                'status' => $ticket_data['status'],
                'seat_number' => $ticket_data['seat_number'],
                'seat_display' => $ticket_data['seat_display'],
                'payment_status' => $ticket_data['payment_status'],
                'payment_method' => $ticket_data['payment_method'] ?? 'direct',
                'payment_amount' => $ticket_data['payment_amount'] ?? $ticket_data['payment_amount'],
                'can_scan' => true,
                'message' => 'Ticket is valid for scanning.'
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Handle POST request for verifying ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_started = false;
    try {
        // Check if ticket_id is provided
        if (!isset($_POST['ticket_id'])) {
            throw new Exception('Ticket ID is required');
        }

        $ticket_id = $_POST['ticket_id'];
        
        $conn->begin_transaction();
        $transaction_started = true;
        
        // Get ticket details first
        $sql = "SELECT t.*, s.departure_time 
                FROM tickets t 
                JOIN schedules s ON t.schedule_id = s.schedule_id 
                WHERE t.ticket_id = ? AND t.status = 'active'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Invalid ticket or ticket has already been used');
        }
        
        $ticket_data = $result->fetch_assoc();

        // Validate ticket status
        if ($ticket_data['status'] !== 'active') {
            throw new Exception('Ticket is not active');
        }

        if ($ticket_data['payment_status'] !== 'paid') {
            throw new Exception('Ticket has not been paid');
        }
        
        // Validate time window
        $departure_time = new DateTime($ticket_data['departure_time']);
        $current_time = new DateTime(); // Use current time
        
        // Calculate time difference in minutes
        $time_difference = $current_time->diff($departure_time);
        $minutes_difference = ($time_difference->days * 24 * 60) + ($time_difference->h * 60) + $time_difference->i;
        
        // If departure time is in the past, make the difference negative
        if ($current_time > $departure_time) {
            $minutes_difference = -$minutes_difference;
        }

        // Convert our windows to minutes
        $early_window = 120; // 2 hours = 120 minutes
        $late_window = 30;  // 30 minutes

        if ($minutes_difference > $early_window) {
            throw new Exception("This ticket cannot be used yet. Scanning will be available 2 hours before departure (" . 
                $departure_time->format('d M Y, h:i A') . ")");
        }

        if ($minutes_difference < -$late_window) {
            throw new Exception("This ticket has expired. It was only valid until 30 minutes after departure (" . 
                $departure_time->format('d M Y, h:i A') . ")");
        }

        // Update ticket status
        $update_sql = "UPDATE tickets SET status = 'used' 
                      WHERE ticket_id = ? AND status = 'active' AND payment_status = 'paid'";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $ticket_id);
        
        if (!$update_stmt->execute() || $update_stmt->affected_rows === 0) {
            throw new Exception('Failed to verify ticket');
        }

        $conn->commit();
        $transaction_started = false;
        echo json_encode(['success' => true, 'message' => 'Ticket verified successfully']);
        
    } catch (Exception $e) {
        if ($transaction_started) {
            $conn->rollback();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
