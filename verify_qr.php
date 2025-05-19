
<?php
require_once 'config/database.php';
require_once 'includes/TokenManager.php';

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['token'])) {
        throw new Exception('Missing required parameters');
    }

    $token = $input['token'];

    // Initialize TokenManager
    $tokenManager = new TokenManager($conn);

    try {
        // Verify token
        $result = $tokenManager->verifyToken($token);
        
        if ($result) {
            echo json_encode([
                'status' => 'success',
                'message' => 'QR code verified successfully',
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid or expired QR code'
            ]);
        }
    } catch (Exception $e) {
        // Handle specific token verification errors (like timing restrictions)
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Clean up expired tokens
try {
    $tokenManager->cleanupExpiredTokens();
} catch (Exception $e) {
    // Log cleanup error but don't affect response
    error_log("Token cleanup failed: " . $e->getMessage());
}
?>
