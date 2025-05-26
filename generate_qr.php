<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';
require_once 'phpqrcode/qrlib.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['ticket_id'])) {
    header("Location: history.php");
    exit();
}

$ticket_id = $_GET['ticket_id'];
$user_id = $_SESSION['user_id'];

try {
    $sql = "SELECT t.ticket_id FROM tickets t WHERE t.ticket_id = ? AND t.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ticket_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Invalid ticket");
    }

    $qrContent = json_encode([
        'type' => 'ticket',
        'ticket_id' => $ticket_id,
        'user_id' => $user_id
    ]);
    if (empty($qrContent)) throw new Exception("QR content is empty");

    header('Content-Type: image/png');
    QRcode::png($qrContent, false, QR_ECLEVEL_L, 6, 2);
    exit;

} catch (Exception $e) {
    $im = imagecreate(200, 50);
    $bgColor = imagecolorallocate($im, 255, 255, 255);
    $textColor = imagecolorallocate($im, 255, 0, 0);
    imagestring($im, 5, 10, 20, "QR Error: " . $e->getMessage(), $textColor);
    header('Content-Type: image/png');
    imagepng($im);
    imagedestroy($im);
    exit;
}
?>