
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/MessageUtility.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = $_POST['username'];
                $email = $_POST['email'];
                $phone = $_POST['no_phone'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                // Check if username or email already exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    MessageUtility::setErrorMessage("Username or email already exists.");
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (username, email, no_phone, password, account_status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                    $stmt->bind_param("ssss", $username, $email, $phone, $password);
                    
                    if ($stmt->execute()) {
                        MessageUtility::setSuccessMessage("User added successfully!");
                    } else {
                        MessageUtility::setErrorMessage("Error adding user: " . $conn->error);
                    }
                }
                break;

            case 'edit':
                $user_id = $_POST['user_id'];
                $username = $_POST['username'];
                $email = $_POST['email'];
                $phone = $_POST['no_phone'];

                // Check if username or email already exists for other users
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
                $stmt->bind_param("ssi", $username, $email, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    MessageUtility::setErrorMessage("Username or email already exists for another user.");
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, no_phone = ? WHERE user_id = ?");
                    $stmt->bind_param("sssi", $username, $email, $phone, $user_id);
                    
                    if ($stmt->execute()) {
                        MessageUtility::setSuccessMessage("User updated successfully!");
                    } else {
                        MessageUtility::setErrorMessage("Error updating user: " . $conn->error);
                    }
                }
                break;

            case 'suspend':
                $user_id = $_POST['user_id'];
                
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Update user status
                    $stmt = $conn->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();

                    // Cancel any active tickets
                    $stmt = $conn->prepare("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE user_id = ? AND status = 'active'");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();

                    $conn->commit();
                    MessageUtility::setSuccessMessage("User suspended successfully!");
                } catch (Exception $e) {
                    $conn->rollback();
                    MessageUtility::setErrorMessage("Error suspending user: " . $e->getMessage());
                }
                break;

            case 'activate':
                $user_id = $_POST['user_id'];
                
                $stmt = $conn->prepare("UPDATE users SET account_status = 'active', failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    MessageUtility::setSuccessMessage("User activated successfully!");
                } else {
                    MessageUtility::setErrorMessage("Error activating user: " . $conn->error);
                }
                break;
        }
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
