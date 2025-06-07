<?php

class Session {
    private static $session_timeout = 30 * 60; // 30 minutes in seconds
    private static $session_regenerate_time = 10 * 60; // 10 minutes in seconds

    public static function initialize(){
      $params = session_get_cookie_params();
      session_set_cookie_params([
        //until browser is closed
        'lifetime' => 0, 
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => true,
        'httponly' => true,
        //prevent csrf attacks
        'samesite' => 'Strict'
      ]);

      session_start();

      // Check for session timeout first
      if (self::isTimedOut()) {
        self::handleTimeout();
        return false; // Indicate session was timed out
      }

        // Regenerate session ID every 10 minutes to prevent session fixation
      if (empty($_SESSION['CREATED'])) {
        // Set session created time for the first time
        $_SESSION['CREATED'] = time();
      } else {
        // Check if session has expired
        if (time() - $_SESSION['CREATED'] > self::$session_regenerate_time) {            
          // change session ID for the current session and invalidate old session ID
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
          }
      }

      // Update last activity time for active sessions
      if (empty($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
      } else {
        $_SESSION['LAST_ACTIVITY'] = time(); 
      }
      
      return true; // Session is valid
    }

    public static function isTimedOut() {
      if (isset($_SESSION['LAST_ACTIVITY'])) {
        $inactive_time = time() - $_SESSION['LAST_ACTIVITY'];
        return $inactive_time > self::$session_timeout;
      }
      return false;
    }

    public static function handleTimeout() {
      // Store timeout message before destroying session
      $timeout_message = "Your session has expired due to inactivity. Please log in again for security reasons.";
      
      // Determine if this is a staff session
      $is_staff_session = self::isStaffSession();
      
      // Clear all session data
      self::destroy();
      
      // Start a new session for the timeout message
      session_start();
      $_SESSION['timeout_message'] = $timeout_message;
      $_SESSION['message_type'] = 'warning';
      $_SESSION['CREATED'] = time();
      $_SESSION['LAST_ACTIVITY'] = time();
      
      // Redirect to appropriate login page
      if ($is_staff_session) {
        header("Location: /Web_Security_system/staff/login.php");
      } else {
        header("Location: /Web_Security_system/login.php");
      }
      exit();
    }

    public static function isStaffSession() {
      return isset($_SESSION['staff_role']) || 
             isset($_SESSION['staff_id']) ||
             strpos($_SERVER['REQUEST_URI'], '/staff/') !== false;
    }

    public static function getRemainingTime() {
      if (isset($_SESSION['LAST_ACTIVITY'])) {
        $elapsed = time() - $_SESSION['LAST_ACTIVITY'];
        $remaining = self::$session_timeout - $elapsed;
        return max(0, $remaining);
      }
      return self::$session_timeout;
    }

    public static function refreshActivity() {
      $_SESSION['LAST_ACTIVITY'] = time();
    }

    public static function displayTimeoutMessage() {
      if (isset($_SESSION['timeout_message'])) {
        $message = $_SESSION['timeout_message'];
        $type = $_SESSION['message_type'] ?? 'info';
        
        // Clear the message after getting it
        unset($_SESSION['timeout_message']);
        unset($_SESSION['message_type']);
        
        return [
          'message' => $message,
          'type' => $type
        ];
      }
      return null;
    }


    public static function destroy(){
      
      $_SESSION = [];
      session_destroy();

      //get session cookie parameters
      $params = session_get_cookie_params();
      //delete cookie
      setcookie('PHPSESSID', '', time() - 1800, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      
    }
  }