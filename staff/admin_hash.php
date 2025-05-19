<?php
require_once '../config/database.php';
require_once '../includes/PasswordPolicy.php';

$admin_password = 'Admin@123';
$hashed_password = PasswordPolicy::hashPassword($admin_password);

// Insert or update admin account
$sql = "INSERT INTO staffs (username, email, password, role, account_status) 
        VALUES ('admin', 'admin@ktm.com', ?, 'admin', 'active')
        ON DUPLICATE KEY UPDATE password = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $hashed_password);

if ($stmt->execute()) {
    echo "Admin account created/updated successfully!\n";
    echo "Username: admin\n";
    echo "Password: Admin@123\n";
} else {
    echo "Error updating admin account: " . $conn->error;
}
