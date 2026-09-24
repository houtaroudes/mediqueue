<?php
// Database connection (mysqli, object style)
// Pages that need the database call get_db_connection()

require_once __DIR__ . '/../config/config.php';

function get_db_connection() {
    static $conn = null; // reuse one connection per request

    if ($conn instanceof mysqli) {
        return $conn;
    }

    // throw exceptions instead of failing silently
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // friendly message - never show SQL details to users
        if (APP_DEBUG) {
            die('Database connection failed: ' . $e->getMessage());
        }
        die('Sorry, the system is temporarily unavailable. Please try again later.');
    }
}
