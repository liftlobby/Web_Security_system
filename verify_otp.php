<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/MessageUtility.php';
require_once 'includes/OtpManager.php';
require_once 'includes/RecaptchaVerifier.php'; // For potential reCAPTCHA on OTP page too

// If user is not in OTP pending state or no temp_user_id, redirect to login
if (!isset($_SESSION['temp_user_id_for_otp'])) {
    header("Location: login.php");
    exit();
}

$temp_user_id = $_SESSION['temp_user_id_for_otp'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = $_POST['otp'] ?? '';
    $recaptcha_token = $_POST['recaptcha_token'] ?? '';

    // It's good practice to also protect the OTP submission with reCAPTCHA
    if (!RecaptchaVerifier::verify($recaptcha_token, $_SERVER['REMOTE_ADDR'])) {
        MessageUtility::setErrorMessage("reCAPTCHA verification failed. Please try again.");
    } elseif (empty($otp)) {
        MessageUtility::setErrorMessage("Please enter the OTP.");
    } elseif (OtpManager::verifyOtp($temp_user_id, $otp)) {
        // OTP Correct: Finalize login
        // Regenerate session ID for security upon successful OTP verification
        session_regenerate_id(true);

        $_SESSION['user_id'] = $temp_user_id;
        $_SESSION['username'] = $_SESSION['temp_username_for_otp']; // Retrieve username stored earlier
        $_SESSION['CREATED'] = time(); 
        $_SESSION['LAST_ACTIVITY'] = time(); 

        // Clear temporary OTP session variables
        OtpManager::clearOtp($temp_user_id);
        unset($_SESSION['temp_user_id_for_otp']);
        unset($_SESSION['temp_username_for_otp']);
        unset($_SESSION['temp_user_email_for_otp']);

        // Reset failed login attempts for the user in DB (if you track them for OTP)
        try {
            $stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error resetting failed attempts after OTP: " . $e->getMessage());
            // Non-critical, proceed with login
        }

        MessageUtility::setSuccessMessage("Login successful! Welcome, " . htmlspecialchars($_SESSION['username']) . ".");
        header("Location: index.php");
        exit();
    } else {
        MessageUtility::setErrorMessage("Invalid or expired OTP. Please try again or request a new one.");
        // Optional: Increment OTP attempt counter here and lock if too many fails
    }
}

$user_email = $_SESSION['temp_user_email_for_otp'] ?? 'your email'; // For display purposes

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Railway System</title>
    <link rel="stylesheet" href="style/style_login.css"> <!-- Assuming a similar style to login -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RecaptchaVerifier::getSiteKey(); ?>"></script>
    <style>
        .otp-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .otp-container h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .otp-container p {
            margin-bottom: 25px;
            color: #555;
            font-size: 0.95em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #444;
            text-align: left;
        }
        .form-group input[type='text'], .form-group input[type='number'] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1.2em; /* Larger OTP input */
            text-align: center; /* Center OTP digits */
            letter-spacing: 5px; /* Space out OTP digits */
        }
        .login-button {
            background-color: #0056b3; /* Maintained login button color */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .login-button:hover {
            background-color: #004494;
        }
        .resend-link {
            display: block;
            margin-top: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include 'Head_and_Foot/header.php'; ?>
    <div class="otp-container">
        <h2>Enter OTP</h2>
        <p>An OTP has been sent to <strong><?php echo htmlspecialchars($user_email); ?></strong>. Please enter it below.</p>
        <?php MessageUtility::displayMessages(); ?>
        <form action="verify_otp.php" method="post" id="otpForm">
            <div class="form-group">
                <label for="otp">One-Time Password (6 digits)</label>
                <input type="text" id="otp" name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required 
                       autocomplete="one-time-code" placeholder="------">
            </div>
            <input type="hidden" name="recaptcha_token" id="recaptcha_token_otp">
            <button type="submit" class="login-button">Verify OTP</button>
        </form>
        <a href="login.php?action=resend_otp" class="resend-link">Resend OTP?</a>
        <p style="font-size:0.8em; color:#777; margin-top:15px;">If you don't see the email, please check your spam folder.</p>
    </div>
    <?php include 'Head_and_Foot/footer.php'; ?>
    <script>
        document.getElementById('otpForm').addEventListener('submit', function(event) {
            event.preventDefault();
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo RecaptchaVerifier::getSiteKey(); ?>', {action: 'verify_otp'}).then(function(token) {
                    document.getElementById('recaptcha_token_otp').value = token;
                    event.target.submit();
                });
            });
        });
    </script>
</body>
</html> 