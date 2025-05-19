
<?php
session_start();

// If there's no success message, redirect to home
if (!isset($_SESSION['registration_success'])) {
    header("Location: index.php");
    exit();
}

// Get the username from session
$username = isset($_SESSION['registered_username']) ? $_SESSION['registered_username'] : '';

// Clear the success message and username from session
$success_message = $_SESSION['registration_success'];
unset($_SESSION['registration_success']);
unset($_SESSION['registered_username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style_registersuccess.css">
</head>
<body>
    <?php include 'Head_and_Foot/header.php'; ?>
    
    <div class="success-container">
        <i class="bx bx-check-circle success-icon"></i>
        <h1 class="success-title">Registration Successful!</h1>
        <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        
        <div class="next-steps">
            <h3>Next Steps</h3>
            <ul>
                <li><i class="bx bx-log-in"></i> Log in to your account</li>
                <li><i class="bx bx-user"></i> Complete your profile information</li>
                <li><i class="bx bx-train"></i> Start booking your train tickets</li>
                <li><i class="bx bx-bell"></i> Enable notifications for updates</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="login.php" class="action-button">
                <i class="bx bx-log-in"></i>
                Login Now
            </a>
            <a href="index.php" class="action-button secondary">
                <i class="bx bx-home"></i>
                Go to Homepage
            </a>
        </div>
        
        <div class="countdown">
            Redirecting to login page in <span id="countdown">10</span> seconds...
        </div>
    </div>
    
    <?php include 'Head_and_Foot/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown timer
        let timeLeft = 10;
        const countdownElement = document.getElementById('countdown');
        
        const countdownTimer = setInterval(() => {
            timeLeft--;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdownTimer);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>
