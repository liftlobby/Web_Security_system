<?php
class PasswordPolicy {
    private static $config = null;
    private static $pepper = null;

    /**
     * Initialize the configuration
     */
    private static function initConfig() {
        if (self::$config === null) {
            define('SECURE_ACCESS', true);
            self::$config = require_once __DIR__ . '/../config/security_config.php';
            self::$pepper = self::$config['password_pepper'];
        }
        return self::$config;
    }

    /**
     * Validate password strength and requirements
     */
    public static function validatePassword($password, $userId = null, $conn = null) {
        $config = self::initConfig();
        $policy = $config['password_policy'];
        $errors = [];

        // Check length
        if (strlen($password) < $policy['min_length'] || strlen($password) > $policy['max_length']) {
            $errors[] = "Password must be between {$policy['min_length']} and {$policy['max_length']} characters";
        }

        // Check for uppercase letters
        if ($policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        // Check for lowercase letters
        if ($policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        // Check for numbers
        if ($policy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        // Check for special characters
        if ($policy['require_special'] && !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        // Check for consecutive characters
        if (preg_match('/(.)\1{' . ($policy['max_consecutive_chars'] - 1) . ',}/', $password)) {
            $errors[] = "Password cannot contain more than {$policy['max_consecutive_chars']} consecutive identical characters";
        }

        // Check for common patterns
        if (preg_match('/12345|qwerty|password|admin/i', $password)) {
            $errors[] = "Password contains a common pattern that is not allowed";
        }

        // Check password history if database connection and user ID are provided
        if ($conn !== null && $userId !== null) {
            if (self::isPasswordInHistory($password, $userId, $conn)) {
                $errors[] = "Password has been used recently. Please choose a different password.";
            }
        }

        return $errors;
    }

    /**
     * Check if password is in user's password history
     */
    private static function isPasswordInHistory($password, $userId, $conn) {
        $config = self::initConfig();
        $stmt = $conn->prepare("SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $limit = $config['password_policy']['password_history'];
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (self::verifyPassword($password, $row['password_hash'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add password to history
     */
    public static function addToPasswordHistory($userId, $passwordHash, $conn) {
        $stmt = $conn->prepare("INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)");
        $stmt->bind_param("is", $userId, $passwordHash);
        return $stmt->execute();
    }

    /**
     * Check if account should be locked based on failed attempts
     */
    public static function shouldLockAccount($failedAttempts) {
        $config = self::initConfig();
        return $failedAttempts >= $config['password_policy']['max_failed_attempts'];
    }

    /**
     * Get lockout end time
     */
    public static function getLockoutTime() {
        $config = self::initConfig();
        return date('Y-m-d H:i:s', strtotime('+' . $config['password_policy']['lockout_duration'] . ' minutes'));
    }

    /**
     * Get remaining attempts before lockout
     */
    public static function getRemainingAttempts($failedAttempts) {
        $config = self::initConfig();
        $max = $config['password_policy']['max_failed_attempts'];
        return max(0, $max - $failedAttempts);
    }

    /**
     * Get warning message based on remaining attempts
     */
    public static function getLoginAttemptMessage($failedAttempts) {
        $config = self::initConfig();
        $remaining = self::getRemainingAttempts($failedAttempts);
        
        if ($remaining === 0) {
            return "Account has been locked due to too many failed attempts. Try again after " . 
                   $config['password_policy']['lockout_duration'] . " minutes.";
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
     * Generate a secure random password
     */
    public static function generateSecurePassword() {
        $config = self::initConfig();
        $policy = $config['password_policy'];
        
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()-_=+';
        
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Add more random characters to meet minimum length
        $all = $uppercase . $lowercase . $numbers . $special;
        for ($i = strlen($password); $i < $policy['min_length']; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Hash password using Argon2id with pepper
     */
    public static function hashPassword($password) {
        $config = self::initConfig();
        $pepperedPassword = $password . self::$pepper;
        return password_hash($pepperedPassword, PASSWORD_ARGON2ID, $config['hash_settings']);
    }

    /**
     * Verify password hash
     */
    public static function verifyPassword($password, $hash) {
        $config = self::initConfig();
        $pepperedPassword = $password . self::$pepper;
        return password_verify($pepperedPassword, $hash);
    }

    /**
     * Check if password needs rehash
     */
    public static function needsRehash($hash) {
        $config = self::initConfig();
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $config['hash_settings']);
    }

    /**
     * Check if password has expired
     */
    public static function isPasswordExpired($lastPasswordChange) {
        $config = self::initConfig();
        $expiryDays = $config['password_policy']['password_expiry'];
        $expiryDate = strtotime($lastPasswordChange . " + {$expiryDays} days");
        return time() > $expiryDate;
    }
}
