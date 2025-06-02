<?php
require_once 'Session.php';
header('Content-Type: application/json');

// Initialize session
if (!Session::initialize()) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

// Refresh session activity
Session::refreshActivity();

echo json_encode(['success' => true, 'message' => 'Session refreshed']);
?>
