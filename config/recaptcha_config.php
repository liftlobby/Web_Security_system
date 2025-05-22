<?php
// Prevent direct access to this file
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true); // Define if not already defined by other config files
}
if (!defined('RECAPTCHA_V3_SITE_KEY') || !defined('RECAPTCHA_V3_SECRET_KEY')) {
    // IMPORTANT: Replace with your actual reCAPTCHA v3 keys
    define('RECAPTCHA_V3_SITE_KEY', '6LfKR0MrAAAAAGChL2Vn5kYpjCfNKFDuUMcRBCRW');
    define('RECAPTCHA_V3_SECRET_KEY', '6LfKR0MrAAAAAB9rmn-_4Idrw0b5mr6rUSJX1Dq0');
}

return [
    'site_key' => RECAPTCHA_V3_SITE_KEY,
    'secret_key' => RECAPTCHA_V3_SECRET_KEY,
];
?> 