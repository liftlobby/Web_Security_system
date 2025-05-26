
<?php
require_once 'includes/Session.php';
Session::initialize();
require_once 'config/database.php';
require_once 'includes/PasswordHandler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/profile_pictures';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Function to fetch user data
function fetchUserData($conn, $user_id) {
    $sql = "SELECT username, email, no_phone, profile_picture FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Fetch current user data
try {
    $user = fetchUserData($conn, $user_id);
    if (!$user) {
        throw new Exception("Error fetching user data");
    }
} catch (Exception $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    try {
        // Start transaction
        $conn->begin_transaction();

        // Only verify current password if any changes are being made
        if (!empty($current_password)) {
            // Verify current password
            $verify_sql = "SELECT password FROM users WHERE user_id = ?";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("i", $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                throw new Exception("User not found");
            }
            
            $current_hash = $verify_result->fetch_assoc()['password'];

            // Use PasswordHandler to verify password with pepper
            if (!PasswordHandler::verifyPassword($current_password, $current_hash)) {
                throw new Exception("Current password is incorrect");
            }
        } else {
            throw new Exception("Current password is required to make any changes");
        }

        // Handle profile picture upload
        $profile_picture = $user['profile_picture']; // Keep existing picture by default
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed");
            }

            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File size must be less than 5MB");
            }

            // Generate unique filename
            $new_filename = uniqid('profile_') . '.' . $file_type;
            $upload_path = $upload_dir . '/' . $new_filename;

            // Delete old profile picture if exists
            if ($user['profile_picture'] && file_exists($upload_dir . '/' . $user['profile_picture'])) {
                unlink($upload_dir . '/' . $user['profile_picture']);
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload profile picture");
            }

            $profile_picture = $new_filename;
        }

        // Update basic info including profile picture
        $update_sql = "UPDATE users SET username = ?, email = ?, no_phone = ?, profile_picture = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssi", $username, $email, $phone, $profile_picture, $user_id);
        $update_stmt->execute();

        // Update password only if new password is provided
        if (!empty($new_password)) {
            if (strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters long");
            }
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }

            // Use PasswordHandler to hash new password with pepper
            $password_hash = PasswordHandler::hashPassword($new_password);
            $password_sql = "UPDATE users SET password = ?, last_password_change = NOW() WHERE user_id = ?";
            $password_stmt = $conn->prepare($password_sql);
            $password_stmt->bind_param("si", $password_hash, $user_id);
            $password_stmt->execute();
        }

        // Log the activity
        $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'profile_update', 'Profile information updated', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("is", $user_id, $ip_address);
        $log_stmt->execute();

        $conn->commit();
        $success_message = "Profile updated successfully!";

        // Update session variables
        $_SESSION['username'] = $username;
        
        // Refresh user data
        $user = fetchUserData($conn, $user_id);

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        
        // Log the full error for debugging
        error_log("Profile update error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Railway System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style_editprofile.css">
</head>
<body>
    <?php require_once 'Head_and_Foot/header.php'; ?>

    <div class="profile-container">
        <h2 class="section-title">Edit Profile</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
            <div class="profile-picture-container">
                <?php if ($user['profile_picture']): ?>
                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-picture">
                <?php else: ?>
                    <div class="profile-picture-placeholder">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-picture-upload">
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                    <label for="profile_picture"><i class="fas fa-camera"></i> Change Picture</label>
                </div>
                <div class="profile-picture-preview"></div>
            </div>

            <div class="form-group">
                <label for="username">Username<span class="required">*</span></label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email<span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number<span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['no_phone']); ?>" required>
            </div>

            <div class="password-section">
                <h3>Change Password</h3>
                <p class="text-muted">Leave password fields empty if you don't want to change it</p>

                <div class="form-group">
                    <label for="current_password">Current Password<span class="required">*</span></label>
                    <div class="password-toggle">
                        <input type="password" id="current_password" name="current_password" required>
                        <i class="far fa-eye" onclick="togglePassword('current_password')"></i>
                    </div>
                    <small class="form-text">Required to save any changes</small>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-toggle">
                        <input type="password" id="new_password" name="new_password">
                        <i class="far fa-eye" onclick="togglePassword('new_password')"></i>
                    </div>
                    <small class="form-text">Leave blank if you don't want to change it</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-toggle">
                        <input type="password" id="confirm_password" name="confirm_password">
                        <i class="far fa-eye" onclick="togglePassword('confirm_password')"></i>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="profile.php" class="btn btn-primary" style="background-color: #6c757d;">Cancel</a>
            </div>
        </form>
    </div>

    <?php require_once 'Head_and_Foot/footer.php'; ?>

    <script>
        function previewImage(input) {
            const preview = document.querySelector('.profile-picture-preview');
            const file = input.files[0];
            
            if (file) {
                preview.textContent = 'Selected file: ' + file.name;
                
                // Preview the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.querySelector('.profile-picture') || document.querySelector('.profile-picture-placeholder');
                    if (img.tagName === 'IMG') {
                        img.src = e.target.result;
                    } else {
                        // Replace placeholder with actual image
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.className = 'profile-picture';
                        img.parentNode.replaceChild(newImg, img);
                    }
                }
                reader.readAsDataURL(file);
            } else {
                preview.textContent = '';
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
