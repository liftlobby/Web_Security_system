<?php

class Session {

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

        // Regenerate session ID every 30 minutes to prevent session fixation
      if (empty($_SESSION['CREATED'])) {
        // Set session created time for the first time
        $_SESSION['CREATED'] = time();
      } else {
        $session_lifetime = 10 * 60; // 10 minutes 
        // Check if session has expired
        if (time() - $_SESSION['CREATED'] > $session_lifetime) {            
          // change session ID for the current session and invalidate old session ID
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
          }
      }

      // Destroy the session if there's no activity for 5 minutes to prevent session hijacking
      if (empty($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
      } else {
        $session_timeout = 30 * 60; // Temporarily increased to 10 minutes for testing
        if (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout) {
          self::destroy(); // 
          session_start(); 
          $_SESSION['CREATED'] = time(); 
          $_SESSION['LAST_ACTIVITY'] = time(); 
        } else {
          $_SESSION['LAST_ACTIVITY'] = time(); 
        }
      }
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