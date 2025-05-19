<?php
require_once 'config/database.php';
require_once 'includes/NotificationManager.php';

try {
    $notificationManager = new NotificationManager($conn);
    
    // Test notification by creating a notification
    $userId = 1; // Replace with an existing user_id from your database
    $message = "This is a test notification from the KTM Railway System.";
    
    // Create a simple notification
    $result = $notificationManager->createNotification(
        $userId,
        'test',
        $message
    );
    
    if ($result) {
        echo "Test notification created successfully!<br>";
        
        // Now let's test the ticket notification system
        $result2 = $notificationManager->sendTicketStatusNotification(
            1, // Replace with an actual ticket_id from your database
            'confirmed',
            'Ticket has been confirmed successfully'
        );
        
        if ($result2) {
            echo "Ticket notification sent successfully!";
        } else {
            echo "Failed to send ticket notification.";
        }
    } else {
        echo "Failed to create test notification.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
