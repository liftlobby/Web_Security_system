<?php
session_start();
require_once '../config/database.php';

// Store staff_id before clearing session
$staff_id = isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : null;

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Log the logout activity if we had a staff_id
if ($staff_id !== null) {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (staff_id, action, description, ip_address, user_agent) VALUES (?, 'staff_logout', 'Staff logged out', ?, ?)");
        $stmt->bind_param("iss", $staff_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        $stmt->execute();
    } catch (Exception $e) {
        // If logging fails, we still want to logout the user
        // Just continue with the logout process
    }
}

// Redirect to login page
header("Location: login.php");
exit();
?>
