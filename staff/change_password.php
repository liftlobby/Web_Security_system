<?php
session_start();
require_once '../config/database.php';
require_once '../includes/PasswordPolicy.php';
require_once '../includes/MessageUtility.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM staffs WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();

    if (!PasswordPolicy::verifyPassword($current_password, $staff['password'])) {
        $error_message = "Current password is incorrect";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } else {
        // Validate new password
        $errors = PasswordPolicy::validatePassword($new_password);
        if (!empty($errors)) {
            $error_message = implode("<br>", $errors);
        } else {
            // Check password history
            $stmt = $conn->prepare("SELECT password_history FROM staffs WHERE staff_id = ?");
            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $staff = $result->fetch_assoc();
            
            $password_history = $staff['password_history'] ? json_decode($staff['password_history'], true) : [];
            
            // Check if new password matches any of the previous passwords
            $password_reused = false;
            foreach ($password_history as $old_hash) {
                if (PasswordPolicy::verifyPassword($new_password, $old_hash)) {
                    $password_reused = true;
                    break;
                }
            }

            if ($password_reused) {
                $error_message = "Cannot reuse any of your last " . PasswordPolicy::PASSWORD_HISTORY . " passwords";
            } else {
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Add current password to history
                    array_unshift($password_history, $staff['password']);
                    if (count($password_history) > PasswordPolicy::PASSWORD_HISTORY) {
                        array_pop($password_history);
                    }
                    
                    // Update password and history
                    $new_hash = PasswordPolicy::hashPassword($new_password);
                    $history_json = json_encode($password_history);
                    
                    $stmt = $conn->prepare("UPDATE staffs SET password = ?, password_history = ? WHERE staff_id = ?");
                    $stmt->bind_param("ssi", $new_hash, $history_json, $staff_id);
                    $stmt->execute();

                    $conn->commit();
                    $success_message = "Password changed successfully!";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error changing password: " . $e->getMessage();
                }
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
    <title>Change Password - Railway System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .password-requirements {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
        }
        .requirement {
            margin: 5px 0;
        }
        .requirement i {
            margin-right: 5px;
        }
        .requirement.valid {
            color: #198754;
        }
        .requirement.invalid {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'staff_header.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <div class="card">
                    <div class="card-header">
                        <h2>Change Password</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" id="passwordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="password-requirements mt-2">
                                    <div class="requirement" id="length">
                                        <i class='bx bx-x'></i> 8-50 characters
                                    </div>
                                    <div class="requirement" id="uppercase">
                                        <i class='bx bx-x'></i> At least one uppercase letter
                                    </div>
                                    <div class="requirement" id="lowercase">
                                        <i class='bx bx-x'></i> At least one lowercase letter
                                    </div>
                                    <div class="requirement" id="number">
                                        <i class='bx bx-x'></i> At least one number
                                    </div>
                                    <div class="requirement" id="special">
                                        <i class='bx bx-x'></i> At least one special character
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            
            // Update requirements
            document.getElementById('length').className = 
                'requirement ' + (password.length >= 8 && password.length <= 50 ? 'valid' : 'invalid');
            document.getElementById('uppercase').className = 
                'requirement ' + (/[A-Z]/.test(password) ? 'valid' : 'invalid');
            document.getElementById('lowercase').className = 
                'requirement ' + (/[a-z]/.test(password) ? 'valid' : 'invalid');
            document.getElementById('number').className = 
                'requirement ' + (/[0-9]/.test(password) ? 'valid' : 'invalid');
            document.getElementById('special').className = 
                'requirement ' + (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password) ? 'valid' : 'invalid');
            
            // Update icons
            document.querySelectorAll('.requirement').forEach(req => {
                const icon = req.querySelector('i');
                if (req.classList.contains('valid')) {
                    icon.className = 'bx bx-check';
                } else {
                    icon.className = 'bx bx-x';
                }
            });
        });

        // Check password match
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity("Passwords do not match");
            } else {
                this.setCustomValidity("");
            }
        });
    </script>
</body>
</html>
