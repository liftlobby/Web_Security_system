<?php
require_once 'includes/PasswordUtility.php';

// Test password hashing
$password = 'Admin@123';
$hash = PasswordUtility::hashPassword($password);
echo "Generated hash for 'Admin@123': " . $hash . "\n\n";

// Test verification with the hash from SQL file
$stored_hash = '$argon2id$v=19$m=65536,t=4,p=3$TlRNX1NFQ1VSRV8yMDI0$HZD8/h2J3dJ5K9L4p0N6YwKGDJwCvQJ1QENIyIXcXo';
$verification = PasswordUtility::verifyPassword($password, $stored_hash);
echo "Verification with stored hash: " . ($verification ? "SUCCESS" : "FAILED") . "\n";

// Generate a new hash for SQL file
echo "\nNew hash for SQL file:\n";
echo "INSERT INTO `staffs` (`username`, `email`, `password`, `role`, `account_status`) VALUES\n";
echo "('admin', 'admin@railway.com', '" . $hash . "', 'admin', 'active');\n";
?>
