<?php
class PasswordHandler {
    // Password requirements
    const MIN_LENGTH = 8;
    const MAX_LENGTH = 50;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBERS = true;
    const REQUIRE_SPECIAL = true;
    const MAX_CONSECUTIVE_CHARS = 3;
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 15; // minutes
    const PASSWORD_HISTORY = 5;
    private const PEPPER = "RAILWAY_SECURE_2024"; // Change this in production

    /**
     * Hash password using Argon2id with pepper
     */
    public static function hashPassword($password) {
        $pepperedPassword = $password . self::PEPPER;
        return password_hash($pepperedPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64MB
            'time_cost' => 4,        // 4 iterations
            'threads' => 3           // 3 threads
        ]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        $pepperedPassword = $password . self::PEPPER;
        return password_verify($pepperedPassword, $hash);
    }

    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        $errors = [];

        // Check length
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Password must be at least " . self::MIN_LENGTH . " characters long.";
        }
        if (strlen($password) > self::MAX_LENGTH) {
            $errors[] = "Password cannot be longer than " . self::MAX_LENGTH . " characters.";
        }

        // Check for uppercase letters
        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        }

        // Check for lowercase letters
        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        }

        // Check for numbers
        if (self::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        }

        // Check for special characters
        if (self::REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character.";
        }

        // Check for consecutive characters
        if (preg_match('/(.)\1{' . (self::MAX_CONSECUTIVE_CHARS - 1) . ',}/', $password)) {
            $errors[] = "Password cannot contain more than " . self::MAX_CONSECUTIVE_CHARS . " consecutive identical characters.";
        }

        return $errors;
    }

    /**
     * Check if account should be locked
     */
    public static function shouldLockAccount($failedAttempts) {
        return $failedAttempts >= self::MAX_FAILED_ATTEMPTS;
    }

    /**
     * Get lockout time
     */
    public static function getLockoutTime() {
        return date('Y-m-d H:i:s', strtotime('+' . self::LOCKOUT_DURATION . ' minutes'));
    }

    /**
     * Get remaining attempts before lockout
     */
    public static function getRemainingAttempts($failedAttempts) {
        $remaining = self::MAX_FAILED_ATTEMPTS - $failedAttempts;
        return max(0, $remaining);
    }

    /**
     * Get warning message based on remaining attempts
     */
    public static function getLoginAttemptMessage($failedAttempts) {
        $remaining = self::getRemainingAttempts($failedAttempts);
        
        if ($remaining === 0) {
            return "Account has been locked due to too many failed attempts. Try again after " . self::LOCKOUT_DURATION . " minutes.";
        }
        
        $message = "Invalid credentials. ";
        if ($remaining === 1) {
            $message .= "This is your LAST attempt before account lockout!";
        } elseif ($remaining === 2) {
            $message .= "Warning: Only {$remaining} attempts remaining before account lockout!";
        } else {
            $message .= "{$remaining} attempts remaining before account lockout.";
        }
        
        return $message;
    }

    /**
     * Check if password needs rehash
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
}
