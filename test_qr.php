<?php
require_once 'phpqrcode/qrlib.php';
header('Content-Type: image/png');
QRcode::png('TEST-123', false, QR_ECLEVEL_L, 6, 2);
exit;