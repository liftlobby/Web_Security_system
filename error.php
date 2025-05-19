
<?php
session_start();

// Get error details from URL parameters
$error_code = isset($_GET['code']) ? intval($_GET['code']) : 404;
$error_message = isset($_GET['message']) ? $_GET['message'] : '';

// Define error types and their default messages
$error_types = [
    400 => [
        'title' => 'Bad Request',
        'message' => 'The request could not be understood by the server.',
        'icon' => 'bx bx-error-circle'
    ],
    401 => [
        'title' => 'Unauthorized',
        'message' => 'Authentication is required to access this resource.',
        'icon' => 'bx bx-lock-alt'
    ],
    403 => [
        'title' => 'Forbidden',
        'message' => 'You don\'t have permission to access this resource.',
        'icon' => 'bx bx-shield-x'
    ],
    404 => [
        'title' => 'Page Not Found',
        'message' => 'The page you are looking for might have been removed or is temporarily unavailable.',
        'icon' => 'bx bx-search-alt'
    ],
    500 => [
        'title' => 'Internal Server Error',
        'message' => 'Something went wrong on our end. Please try again later.',
        'icon' => 'bx bx-server'
    ],
    503 => [
        'title' => 'Service Unavailable',
        'message' => 'The service is temporarily unavailable. Please try again later.',
        'icon' => 'bx bx-time'
    ]
];

// Get error details or use defaults
$error_details = isset($error_types[$error_code]) ? $error_types[$error_code] : $error_types[404];
$display_message = !empty($error_message) ? $error_message : $error_details['message'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $error_code; ?> - Help Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style_error.css">
</head>
<body>
    <?php include 'Head_and_Foot\header.php'; ?>
    <div class="error-container">
        <i class="<?php echo $error_details['icon']; ?> error-icon"></i>
        <div class="error-code"><?php echo $error_code; ?></div>
        <h1 class="error-title"><?php echo $error_details['title']; ?></h1>
        <p class="error-message"><?php echo htmlspecialchars($display_message); ?></p>
        
        <?php if ($error_code == 404): ?>
            <img src="assets/images/error-404.svg" alt="404 Error" class="error-image">
        <?php endif; ?>
        
        <div class="d-flex justify-content-center gap-3">
            <a href="javascript:history.back()" class="back-button">
                <i class="bx bx-arrow-back"></i>
                Go Back
            </a>
            <a href="index.php" class="back-button">
                <i class="bx bx-home"></i>
                Home
            </a>
        </div>
    </div>
    <?php include 'Head_and_Foot\footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
