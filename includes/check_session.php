<?php
require_once 'Session.php';
header('Content-Type: application/json');

// Initialize session
if (!Session::initialize()) {
    // Session was timed out
    echo json_encode([
        'expired' => true,
        'remaining' => 0,
        'redirect_url' => Session::isStaffSession() ? '/Web_Security_system/staff/login.php' : '/Web_Security_system/login.php'
    ]);
    exit();
}

$remaining = Session::getRemainingTime();
$is_staff = Session::isStaffSession();

// Warning if less than 5 minutes remaining
if ($remaining <= 300) { 
    echo json_encode([
        'warning' => true,
        'expired' => false,
        'remaining' => $remaining,
        'message' => 'Your session will expire in ' . ceil($remaining / 60) . ' minutes. Please save your work.',
        'redirect_url' => $is_staff ? '/Web_Security_system/staff/login.php' : '/Web_Security_system/login.php'
    ]);
} else {
    echo json_encode([
        'expired' => false,
        'warning' => false,
        'remaining' => $remaining
    ]);
}
?>
