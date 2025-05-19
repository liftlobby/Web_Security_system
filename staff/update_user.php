<?php
session_start();
require_once '../config/database.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $account_status = $_POST['account_status'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Start building the SQL query
    $sql = "UPDATE users SET 
            username = ?, 
            email = ?, 
            no_phone = ?,
            account_status = ?,
            updated_at = NOW()";
    
    $params = array($username, $email, $phone, $account_status);
    $types = "ssss"; // string types for the parameters

    // If new password is provided
    if (!empty($new_password)) {
        // Validate password match
        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match!";
            header("Location: manage_users.php");
            exit();
        }

        // Validate password length
        if (strlen($new_password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters long!";
            header("Location: manage_users.php");
            exit();
        }

        // Add password to the update query
        $sql .= ", password = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        $types .= "s";
    }

    // Complete the query
    $sql .= " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i"; // integer for user_id

    // Prepare and execute the statement
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully!";
        // Log the action
        $log_sql = "INSERT INTO activity_logs (staff_id, action, details) VALUES (?, 'update_user', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $details = "Updated user ID: " . $user_id;
        if (!empty($new_password)) {
            $details .= " (password changed)";
        }
        $log_stmt->bind_param("is", $_SESSION['staff_id'], $details);
        $log_stmt->execute();
    } else {
        $_SESSION['error'] = "Error updating user: " . $conn->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: manage_users.php");
    exit();
} else {
    header("Location: manage_users.php");
    exit();
}
