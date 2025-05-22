<?php
session_start();
require_once '../config/database.php';
require_once '../includes/PasswordPolicy.php';
require_once '../includes/MessageUtility.php';
require_once '../includes/RecaptchaVerifier.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Use htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $recaptcha_token = $_POST['recaptcha_token'] ?? '';
    
    // Verify reCAPTCHA first
    if (!RecaptchaVerifier::verify($recaptcha_token, $_SERVER['REMOTE_ADDR'])) {
        MessageUtility::setErrorMessage("reCAPTCHA verification failed. Please try again.");
    } elseif (!$username || !$password) {
        MessageUtility::setErrorMessage(MessageUtility::getCommonErrorMessage('required_fields'));
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Get staff details
            $stmt = $conn->prepare("SELECT * FROM staffs WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $staff = $result->fetch_assoc();
                
                // Check if account is suspended
                if ($staff['account_status'] === 'suspended') {
                    throw new Exception("Account is suspended. Please contact administrator.");
                }
                
                // Check if account is locked
                if ($staff['locked_until'] !== null && strtotime($staff['locked_until']) > time()) {
                    $unlock_time = date('Y-m-d H:i:s', strtotime($staff['locked_until']));
                    throw new Exception("Account is locked until $unlock_time");
                }
                
                // Verify password
                if (PasswordPolicy::verifyPassword($password, $staff['password'])) {
                    // Check if password has expired
                    if (PasswordPolicy::isPasswordExpired($staff['last_password_change'])) {
                        $_SESSION['temp_staff_id'] = $staff['staff_id'];
                        $_SESSION['password_expired'] = true;
                        header("Location: change_password.php");
                        exit();
                    }
                    
                    // Reset failed attempts
                    $update = $conn->prepare("UPDATE staffs SET failed_attempts = 0, locked_until = NULL WHERE staff_id = ?");
                    $update->bind_param("i", $staff['staff_id']);
                    $update->execute();
                    
                    // Set session variables
                    $_SESSION['staff_id'] = $staff['staff_id'];
                    $_SESSION['staff_username'] = $staff['username'];
                    $_SESSION['staff_role'] = $staff['role']; 
                    
                    $conn->commit();
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Increment failed attempts
                    $failed_attempts = $staff['failed_attempts'] + 1;
                    $locked_until = null;
                    
                    // Lock account if failed attempts exceed limit
                    if ($failed_attempts >= 5) {
                        $locked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    }
                    
                    $update = $conn->prepare("UPDATE staffs SET failed_attempts = ?, locked_until = ? WHERE staff_id = ?");
                    $update->bind_param("isi", $failed_attempts, $locked_until, $staff['staff_id']);
                    $update->execute();
                    
                    throw new Exception("Invalid username or password. Attempts remaining: " . (5 - $failed_attempts));
                }
            } else {
                throw new Exception("Invalid username or password");
            }
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
    <title>Staff Login - KTM Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../style/style_login.css">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RecaptchaVerifier::getSiteKey(); ?>"></script>
    <style>
        body {
            background-color: #f0f8ff;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            max-width: 150px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .btn-login {
            background-color: #003366;
            color: white;
            width: 100%;
            padding: 10px;
        }
        .btn-login:hover {
            background-color: #002244;
            color: white;
        }
        .home-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            padding: 10px 20px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 30px;
            background-color: white;
            color: #003366;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .home-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            background-color: #003366;
            color: white;
        }
        .home-btn i {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <!-- Back to Home Button -->
    <a href="../index.php" class="home-btn">
        <i class='bx bx-home'></i> Back to Home
    </a>

    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <img src="../image/train_icon.png" alt="Railway Logo">
                <h2>Staff Login</h2>
            </div>
            
            <?php echo MessageUtility::displayMessages(); ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return validateStaffForm()" id="staffLoginForm">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                
                <button type="submit" class="btn btn-login">Login</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function validateStaffForm() {
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
            grecaptcha.execute('<?php echo RecaptchaVerifier::getSiteKey(); ?>', {action: 'staff_login'}).then(function(token) {
                document.getElementById('recaptcha_token').value = token;
                document.getElementById('staffLoginForm').submit(); // Submit the form after getting token
            });
        });
        return false; // Prevent default form submission
    }
    </script>
</body>
</html>
