<?php
/* // Enforce HTTPS - Handled by .htaccess now
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}
*/

date_default_timezone_set('UTC');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'railway_system';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
