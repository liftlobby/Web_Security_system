
<?php
session_start();
require_once 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email already exists
    $check_sql = "SELECT email FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Email already registered. Please use a different email or login.";
        header("Location: register.php");
        exit();
    }

    // Insert new user
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Registration successful! Please login to continue.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error during registration. Please try again.";
        header("Location: register.php");
        exit();
    }
}
?>