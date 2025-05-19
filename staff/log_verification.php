<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if ticket_id is provided
if (!isset($_POST['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
    exit();
}

$ticket_id = $_POST['ticket_id'];
$staff_id = $_SESSION['staff_id'];

try {
    // Get ticket and user details
    $sql = "SELECT t.user_id, t.status, u.username 
            FROM tickets t 
            JOIN users u ON t.user_id = u.user_id 
            WHERE t.ticket_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Ticket not found");
    }

    $ticket_data = $result->fetch_assoc();

    // Log the verification
    $log_sql = "INSERT INTO activity_logs (
                    user_id, 
                    staff_id, 
                    action, 
                    description, 
                    ip_address,
                    created_at
                ) VALUES (?, ?, 'ticket_verification', ?, ?, NOW())";
    
    $description = sprintf(
        "Ticket #%d verified for user %s (Status: %s)", 
        $ticket_id, 
        $ticket_data['username'],
        $ticket_data['status']
    );
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("iiss", 
        $ticket_data['user_id'], 
        $staff_id, 
        $description, 
        $ip_address
    );
    
    if (!$log_stmt->execute()) {
        throw new Exception("Failed to log verification");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification logged successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
