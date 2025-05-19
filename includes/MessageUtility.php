<?php
class MessageUtility {
    private static $errorMessage = '';
    private static $successMessage = '';
    private static $warningMessage = '';

    /**
     * Set error message
     */
    public static function setErrorMessage($message) {
        self::$errorMessage = $message;
    }

    /**
     * Set success message
     */
    public static function setSuccessMessage($message) {
        self::$successMessage = $message;
    }

    /**
     * Set warning message
     */
    public static function setWarningMessage($message) {
        self::$warningMessage = $message;
    }

    /**
     * Check if there is an error message
     */
    public static function hasErrorMessage() {
        return !empty(self::$errorMessage);
    }

    /**
     * Check if there is a success message
     */
    public static function hasSuccessMessage() {
        return !empty(self::$successMessage);
    }

    /**
     * Check if there is a warning message
     */
    public static function hasWarningMessage() {
        return !empty(self::$warningMessage);
    }

    /**
     * Get error message
     */
    public static function getErrorMessage() {
        $message = self::$errorMessage;
        self::$errorMessage = ''; // Clear after getting
        return $message;
    }

    /**
     * Get success message
     */
    public static function getSuccessMessage() {
        $message = self::$successMessage;
        self::$successMessage = ''; // Clear after getting
        return $message;
    }

    /**
     * Get warning message
     */
    public static function getWarningMessage() {
        $message = self::$warningMessage;
        self::$warningMessage = ''; // Clear after getting
        return $message;
    }

    /**
     * Display all messages with Bootstrap styling
     */
    public static function displayMessages() {
        $output = '';
        
        if (self::hasErrorMessage()) {
            $output .= self::formatMessage(self::getErrorMessage(), 'danger');
        }
        
        if (self::hasWarningMessage()) {
            $output .= self::formatMessage(self::getWarningMessage(), 'warning');
        }
        
        if (self::hasSuccessMessage()) {
            $output .= self::formatMessage(self::getSuccessMessage(), 'success');
        }
        
        return $output;
    }

    /**
     * Format message with Bootstrap alert styling
     */
    private static function formatMessage($message, $type) {
        $icons = [
            'danger' => '&#10060;',  // Red X
            'warning' => '&#9888;',  // Warning triangle
            'success' => '&#9989;'   // Green checkmark
        ];

        return "
        <div class='alert alert-{$type} alert-dismissible fade show' role='alert' style='
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        '>
            <span style='font-size: 20px;'>{$icons[$type]}</span>
            <span style='flex-grow: 1;'>" . htmlspecialchars($message) . "</span>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }

    /**
     * Get common error messages
     */
    public static function getCommonErrorMessage($key) {
        $messages = [
            'required_fields' => 'All fields are required.',
            'invalid_login' => 'Invalid username or password.',
            'account_locked' => 'Account is locked. Please try again later.',
            'account_suspended' => 'Account is suspended. Please contact administrator.',
            'database_error' => 'Database error occurred. Please try again.',
            'permission_denied' => 'You do not have permission to perform this action.',
            'invalid_request' => 'Invalid request.',
            'not_found' => 'Requested resource not found.',
            'already_exists' => 'Record already exists.',
            'delete_failed' => 'Failed to delete record.',
            'update_failed' => 'Failed to update record.',
            'create_failed' => 'Failed to create record.',
            'session_expired' => 'Your session has expired. Please log in again.',
        ];

        return $messages[$key] ?? 'An error occurred.';
    }

    /**
     * Get common success messages
     */
    public static function getCommonSuccessMessage($key) {
        $messages = [
            'login_success' => 'Login successful.',
            'logout_success' => 'Logout successful.',
            'create_success' => 'Record created successfully.',
            'update_success' => 'Record updated successfully.',
            'delete_success' => 'Record deleted successfully.',
            'password_changed' => 'Password changed successfully.',
            'profile_updated' => 'Profile updated successfully.',
        ];

        return $messages[$key] ?? 'Operation completed successfully.';
    }
}
