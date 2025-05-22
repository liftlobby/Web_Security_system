<?php
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true); // Define if not already defined by other config files
}
require_once __DIR__ . '/../config/recaptcha_config.php';

class RecaptchaVerifier {
    private static $secretKey;
    private static $minScore = 0.5; // Minimum acceptable score

    private static function init() {
        if (self::$secretKey === null) {
            $config = require __DIR__ . '/../config/recaptcha_config.php';
            self::$secretKey = $config['secret_key'];
        }
    }

    public static function verify($token, $remoteIp = null) {
        self::init();

        if (empty(self::$secretKey) || self::$secretKey === '6LfKR0MrAAAAAB9rmn-_4Idrw0b5mr6rUSJX1Dq0') {
            // If reCAPTCHA is not configured, bypass for development or if keys are missing.
            // Log this or handle it appropriately in production.
            error_log('reCAPTCHA secret key is not configured. Skipping verification.');
            return true; // Or return a score that passes, e.g., 1.0
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret'   => self::$secretKey,
            'response' => $token,
        ];

        if ($remoteIp) {
            $data['remoteip'] = $remoteIp;
        }

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ];

        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log('reCAPTCHA verification request failed.');
            return false; // Request failed
        }

        $result = json_decode($response, true);

        if (!$result || !isset($result['success'])) {
            error_log('reCAPTCHA verification response invalid: ' . $response);
            return false; // Invalid response
        }

        if ($result['success'] && isset($result['score']) && $result['score'] >= self::$minScore) {
            // Optional: Check action if you set it during token generation
            // if (isset($result['action']) && $result['action'] == 'login_action') { ... }
            return true; 
        }

        if (isset($result['error-codes'])) {
            error_log('reCAPTCHA errors: ' . implode(', ', $result['error-codes']));
        }
        if (isset($result['score'])) {
             error_log('reCAPTCHA score too low: ' . $result['score']);
        }

        return false;
    }
    
    public static function getSiteKey() {
        self::init(); // Ensure config is loaded
        $config = require __DIR__ . '/../config/recaptcha_config.php';
        return $config['site_key'];
    }
}
?> 