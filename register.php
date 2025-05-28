
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';
require_once 'includes/PasswordPolicy.php';
require_once 'includes/MessageUtility.php';

// If user is already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$form_errors = [];
$form_data = [
    'username' => '',
    'email' => '',
    'no_phone' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'no_phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Store form data for repopulating the form
    $form_data = [
        'username' => $username,
        'email' => $email,
        'no_phone' => $phone
    ];

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
        $form_errors['username'] = 'Username must be 4-20 characters long and can only contain letters, numbers, and underscores';
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_errors['email'] = 'Please enter a valid email address';
    }

    // Validate phone number
    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $form_errors['phone'] = 'Phone number must be 10-15 digits long';
    }

    // Validate password match
    if ($password !== $confirm_password) {
        $form_errors['confirm_password'] = 'Passwords do not match';
    }

    // Validate password strength
    $password_errors = PasswordPolicy::validatePassword($password);
    if (!empty($password_errors)) {
        $form_errors['password'] = implode(' ', $password_errors);
    }

    if (empty($form_errors)) {
        try {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $form_errors['username'] = 'This username is already taken. Please choose another one.';
                throw new Exception('Username exists');
            }

            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $form_errors['email'] = 'This email is already registered. Please use a different email or try logging in.';
                throw new Exception('Email exists');
            }

            // Hash password
            $hashed_password = PasswordPolicy::hashPassword($password);

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (username, email, no_phone, password, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssss", $username, $email, $phone, $hashed_password);

                if ($stmt->execute()) {
                    // Log the registration
                    $user_id = $conn->insert_id;
                    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, 'registration', 'New user registration', ?, ?)");
                    $logStmt->bind_param("iss", $user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                    $logStmt->execute();

                    // Commit transaction
                    $conn->commit();

                    // Set success message and username for the success page
                    $_SESSION['registration_success'] = "Welcome to Railway System! Your account has been created successfully.";
                    $_SESSION['registered_username'] = $username;
                    
                    // Redirect to success page
                    header("Location: register_success.php");
                    exit();
                } else {
                    // Rollback if insert fails
                    $conn->rollback();
                    throw new Exception("Registration failed");
                }
            } catch (Exception $e) {
                // Rollback on any error within the transaction
                $conn->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            if ($e->getMessage() !== 'Username exists' && $e->getMessage() !== 'Email exists') {
                $form_errors['general'] = 'An error occurred during registration. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style_register.css">

</head>
<body>
    <?php include 'Head_and_Foot/header.php'; ?>
    
    <div class="container">
        <div class="registration-container">
            <h2 class="form-title">Create Account</h2>
            
            <?php if (isset($form_errors['general'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($form_errors['general']); ?>
                </div>
            <?php endif; ?>

            <form id="registrationForm" method="POST" action="register.php" novalidate>
                <div class="form-floating">
                    <input type="text" class="form-control <?php echo isset($form_errors['username']) ? 'is-invalid' : ''; ?>"
                           id="username" name="username" placeholder="Username"
                           value="<?php echo htmlspecialchars($form_data['username']); ?>"
                           required pattern="^[a-zA-Z0-9_]{4,20}$">
                    <label for="username">Username</label>
                    <?php if (isset($form_errors['username'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($form_errors['username']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating">
                    <input type="email" class="form-control <?php echo isset($form_errors['email']) ? 'is-invalid' : ''; ?>"
                           id="email" name="email" placeholder="Email"
                           value="<?php echo htmlspecialchars($form_data['email']); ?>"
                           required>
                    <label for="email">Email address</label>
                    <?php if (isset($form_errors['email'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($form_errors['email']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating">
                    <input type="tel" class="form-control <?php echo isset($form_errors['phone']) ? 'is-invalid' : ''; ?>"
                           id="no_phone" name="no_phone" placeholder="Phone number"
                           value="<?php echo htmlspecialchars($form_data['no_phone']); ?>"
                           required pattern="[0-9]{10,15}">
                    <label for="no_phone">Phone number</label>
                    <?php if (isset($form_errors['phone'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($form_errors['phone']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating">
                    <input type="password" class="form-control <?php echo isset($form_errors['password']) ? 'is-invalid' : ''; ?>"
                           id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                    <?php if (isset($form_errors['password'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($form_errors['password']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <ul class="password-requirements" id="passwordRequirements">
                    <li id="length"><i class="bx bx-x"></i> At least 8 characters long</li>
                    <li id="uppercase"><i class="bx bx-x"></i> Contains uppercase letter</li>
                    <li id="lowercase"><i class="bx bx-x"></i> Contains lowercase letter</li>
                    <li id="number"><i class="bx bx-x"></i> Contains number</li>
                    <li id="special"><i class="bx bx-x"></i> Contains special character</li>
                </ul>

                <div class="form-floating">
                    <input type="password" class="form-control <?php echo isset($form_errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                           id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                    <label for="confirm_password">Confirm Password</label>
                    <?php if (isset($form_errors['confirm_password'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($form_errors['confirm_password']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-register">
                    <i class="bx bx-user-plus"></i> Create Account
                </button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'Head_and_Foot/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const requirements = {
                length: document.getElementById('length'),
                uppercase: document.getElementById('uppercase'),
                lowercase: document.getElementById('lowercase'),
                number: document.getElementById('number'),
                special: document.getElementById('special')
            };

            function updateRequirement(element, valid) {
                const icon = element.querySelector('i');
                icon.className = valid ? 'bx bx-check' : 'bx bx-x';
                element.className = valid ? 'valid' : 'invalid';
            }

            function validatePassword() {
                const value = password.value;
                
                updateRequirement(requirements.length, value.length >= 8);
                updateRequirement(requirements.uppercase, /[A-Z]/.test(value));
                updateRequirement(requirements.lowercase, /[a-z]/.test(value));
                updateRequirement(requirements.number, /[0-9]/.test(value));
                updateRequirement(requirements.special, /[^A-Za-z0-9]/.test(value));
            }

            function validateConfirmPassword() {
                if (confirmPassword.value === password.value) {
                    confirmPassword.setCustomValidity('');
                } else {
                    confirmPassword.setCustomValidity('Passwords do not match');
                }
            }

            password.addEventListener('input', validatePassword);
            password.addEventListener('input', validateConfirmPassword);
            confirmPassword.addEventListener('input', validateConfirmPassword);

            // Phone number validation
            const phoneInput = document.getElementById('no_phone');
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 15) {
                    this.value = this.value.slice(0, 15);
                }
            });

            // Username validation
            const usernameInput = document.getElementById('username');
            usernameInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
                if (this.value.length > 20) {
                    this.value = this.value.slice(0, 20);
                }
            });
        });
    </script>
</body>
</html>