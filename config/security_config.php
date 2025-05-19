<?php
// Prevent direct access to this file
if (!defined('SECURE_ACCESS')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Security Configuration
return [
    // Password Pepper (used for additional password security)
    'password_pepper' => 'RAILWAY_SECURE_2024', // Change this in production!
    
    // Password Policy Settings
    'password_policy' => [
        'min_length' => 8,
        'max_length' => 50,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special' => true,
        'max_consecutive_chars' => 3,
        'max_failed_attempts' => 5,
        'lockout_duration' => 15, // minutes
        'password_history' => 5,  // number of previous passwords to check
        'password_expiry' => 90,  // days until password expires
    ],
    
    // Argon2id Hashing Settings
    'hash_settings' => [
        'memory_cost' => 65536,  // 64MB
        'time_cost' => 4,        // 4 iterations
        'threads' => 3           // 3 threads
    ],
];
