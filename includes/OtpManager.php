<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class OtpManager {
    private const OTP_SESSION_KEY = 'login_otp';
    private const OTP_EXPIRY_SESSION_KEY = 'login_otp_expiry';
    private const OTP_VALIDITY_MINUTES = 10;

    /**
     * Generate a 6-digit OTP.
     */
    public static function generateOtp() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Store OTP and its expiry time in the session.
     */
    public static function storeOtp($userId, $otp) {
        $_SESSION[self::OTP_SESSION_KEY . '_' . $userId] = password_hash($otp, PASSWORD_DEFAULT); // Store hash of OTP
        $_SESSION[self::OTP_EXPIRY_SESSION_KEY . '_' . $userId] = time() + (self::OTP_VALIDITY_MINUTES * 60);
    }

    /**
     * Verify the provided OTP for a user.
     * It checks against the stored hash and expiry time.
     */
    public static function verifyOtp($userId, $otp) {
        if (!isset($_SESSION[self::OTP_SESSION_KEY . '_' . $userId]) || 
            !isset($_SESSION[self::OTP_EXPIRY_SESSION_KEY . '_' . $userId])) {
            return false; // No OTP stored for this user
        }

        if (time() > $_SESSION[self::OTP_EXPIRY_SESSION_KEY . '_' . $userId]) {
            self::clearOtp($userId); // OTP expired
            return false;
        }

        if (password_verify($otp, $_SESSION[self::OTP_SESSION_KEY . '_' . $userId])) {
            self::clearOtp($userId); // OTP verified, clear it
            return true;
        }

        return false; // Invalid OTP
    }

    /**
     * Clear OTP data from session for a user.
     */
    public static function clearOtp($userId) {
        unset($_SESSION[self::OTP_SESSION_KEY . '_' . $userId]);
        unset($_SESSION[self::OTP_EXPIRY_SESSION_KEY . '_' . $userId]);
    }

    /**
     * Check if an OTP has been set and is pending verification for a user.
     */
    public static function isOtpPending($userId) {
        return isset($_SESSION[self::OTP_SESSION_KEY . '_' . $userId]) && 
               isset($_SESSION[self::OTP_EXPIRY_SESSION_KEY . '_' . $userId]) &&
               time() <= $_SESSION[self::OTP_EXPIRY_SESSION_KEY . '_' . $userId];
    }
}
?> 