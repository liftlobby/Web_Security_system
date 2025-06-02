<?php
require_once 'includes/Session.php';
Session::initialize();

// Check for timeout message
$timeout_info = Session::displayTimeoutMessage();

require_once 'config/database.php';
require_once 'includes/PasswordPolicy.php';
require_once 'includes/MessageUtility.php';
require_once 'includes/RecaptchaVerifier.php';
require_once 'includes/OtpManager.php';
require_once 'includes/NotificationManager.php';

// If user is already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'resend_otp') {
    if (isset($_SESSION['temp_user_id_for_otp']) && isset($_SESSION['temp_user_email_for_otp']) && isset($_SESSION['temp_username_for_otp'])) {
        $userId = $_SESSION['temp_user_id_for_otp'];
        $userEmail = $_SESSION['temp_user_email_for_otp'];
        $username = $_SESSION['temp_username_for_otp'];

        $otp = OtpManager::generateOtp();
        OtpManager::storeOtp($userId, $otp);
        
        $notificationManager = new NotificationManager($conn);
        if ($notificationManager->sendOtpEmail($userEmail, $username, $otp)) {
            MessageUtility::setSuccessMessage("A new OTP has been sent to your email.");
        } else {
            MessageUtility::setErrorMessage("Failed to send OTP. Please try again.");
        }
        header("Location: verify_otp.php");
        exit();
    } else {
        MessageUtility::setErrorMessage("Could not resend OTP. Please try logging in again.");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $recaptcha_token = $_POST['recaptcha_token'] ?? '';

    // Verify reCAPTCHA
    if (!RecaptchaVerifier::verify($recaptcha_token, $_SERVER['REMOTE_ADDR'])) {
        MessageUtility::setErrorMessage("reCAPTCHA verification failed. Please try again.");
    } elseif (!$username || !$password) {
        MessageUtility::setErrorMessage(MessageUtility::getCommonErrorMessage('required_fields'));
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Get user details
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is suspended
                if ($user['account_status'] === 'suspended') {
                    throw new Exception("Account is suspended. Please contact administrator.");
                }
                
                // Check if account is locked
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                    $unlock_time = date('Y-m-d H:i:s', strtotime($user['locked_until']));
                    throw new Exception("Account is locked until $unlock_time");
                }
                
                // Verify password
                if (PasswordPolicy::verifyPassword($password, $user['password'])) {
                    // Password is correct, now initiate OTP flow
                    $otp = OtpManager::generateOtp();
                    OtpManager::storeOtp($user['user_id'], $otp);

                    // Store user details temporarily for OTP verification page
                    $_SESSION['temp_user_id_for_otp'] = $user['user_id'];
                    $_SESSION['temp_username_for_otp'] = $user['username'];
                    $_SESSION['temp_user_email_for_otp'] = $user['email'];

                    $notificationManager = new NotificationManager($conn);
                    if ($notificationManager->sendOtpEmail($user['email'], $user['username'], $otp)) {
                        MessageUtility::setSuccessMessage("OTP sent to your email: " . htmlspecialchars($user['email']));
                        header("Location: verify_otp.php");
                        exit();
                    } else {
                        OtpManager::clearOtp($user['user_id']);
                        throw new Exception("Failed to send OTP. Please try again.");
                    }
                } else {
                    // Increment failed attempts
                    $failed_attempts = $user['failed_attempts'] + 1;
                    
                    if (PasswordPolicy::shouldLockAccount($failed_attempts)) {
                        // Lock account
                        $locked_until = PasswordPolicy::getLockoutTime();
                        $stmt = $conn->prepare("UPDATE users SET failed_attempts = ?, locked_until = ?, account_status = 'locked' WHERE user_id = ?");
                        $stmt->bind_param("isi", $failed_attempts, $locked_until, $user['user_id']);
                    } else {
                        // Just update failed attempts
                        $stmt = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE user_id = ?");
                        $stmt->bind_param("ii", $failed_attempts, $user['user_id']);
                    }
                    $stmt->execute();
                    
                    throw new Exception(PasswordPolicy::getLoginAttemptMessage($failed_attempts));
                }
            } else {
                throw new Exception("Invalid credentials.");
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            MessageUtility::setErrorMessage($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Railway System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style/style_login.css">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RecaptchaVerifier::getSiteKey(); ?>"></script>
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>    <div class="login-container">
        <h2>Login</h2>
        
        <?php if ($timeout_info): ?>
            <div class="alert alert-<?php echo htmlspecialchars($timeout_info['type']); ?>" style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>⚠️ Session Timeout:</strong> <?php echo htmlspecialchars($timeout_info['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php echo MessageUtility::displayMessages(); ?>

        <form action="login.php" method="post" onsubmit="return validateForm()" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            <button type="submit" class="login-button">Login</button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>

    <script>
    function validateForm() {
        var username = document.getElementById('username').value.trim();
        var password = document.getElementById('password').value;
        
        if (username === '') {
            alert('Please enter your username');
            return false;
        }
        
        if (password === '') {
            alert('Please enter your password');
            return false;
        }
        
        // reCAPTCHA v3 execution
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo RecaptchaVerifier::getSiteKey(); ?>', {action: 'login'}).then(function(token) {
                document.getElementById('recaptcha_token').value = token;
                document.getElementById('loginForm').submit(); // Submit the form after getting token
            });
        });
        return false; // Prevent default form submission, will be handled by reCAPTCHA callback
    }
    </script>

    <?php require_once 'Head_and_Foot/footer.php'; ?>
</body>
</html>