<?php
// MediQueue base configuration
// Change values here only - no credentials anywhere else in the code

// Database settings (XAMPP defaults)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mediqueue');
define('DB_USER', 'root');
define('DB_PASS', '');

// App settings
define('APP_NAME', 'MediQueue');
define('CLINIC_NAME', 'Campus Wellness Clinic');
define('BASE_URL', 'http://localhost/mediqueue');

// true while developing, set to false before deployment
define('APP_DEBUG', true);

date_default_timezone_set('Asia/Manila');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
