<?php
// Session + auth helpers. Pages include this BEFORE header.php.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    // harden the session cookie before starting (Phase 13)
    session_set_cookie_params(array(
        'httponly' => true,
        'samesite' => 'Lax',
        // send cookie over https only once the site is served over TLS
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ));
    session_start();
}

// basic security headers on every page (Phase 13)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return current_user() !== null;
}

function has_role($roles) {
    $user = current_user();
    return $user !== null && in_array($user['role'], (array) $roles, true);
}

// guard: must be logged in
function require_login() {
    if (!is_logged_in()) {
        redirect(BASE_URL . '/login.php');
    }
}

// guard: must have one of the given roles
function require_role($roles) {
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        $page_title = 'Access Denied';
        echo '<h1>403 - Access Denied</h1><p>Your account cannot open this page.</p>';
        exit;
    }
}

// CSRF protection (Phase 13) - automatic:
// every <form method="post"> gets a hidden token injected at output time,
// and every POST request is verified before the page runs.
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function mq_inject_csrf($html) {
    $token = csrf_token();
    return preg_replace_callback(
        '/<form\b[^>]*method=["\']post["\'][^>]*>/i',
        function ($m) use ($token) {
            if (strpos($m[0], 'csrf_token') !== false) return $m[0];
            return $m[0] . '<input type="hidden" name="csrf_token" value="' . $token . '">';
        },
        $html
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403); // Apache turns exotic codes like 419 into 500, so use 403
        die('Security check failed. Please go back, refresh the page, and try again.');
    }
}
ob_start('mq_inject_csrf');

// where each role lands after login
function dashboard_url($role) {
    switch ($role) {
        case 'admin':        return BASE_URL . '/admin/dashboard.php';
        case 'clinic_staff': return BASE_URL . '/staff/dashboard.php';
        default:             return BASE_URL . '/user/dashboard.php';
    }
}

// write to activity_logs; logging must never break a page
function log_activity($action, $detail = null, $userId = null) {
    try {
        $db = get_db_connection();
        if ($userId === null) {
            $u = current_user();
            $userId = $u ? $u['id'] : null;
        }
        $stmt = $db->prepare('INSERT INTO activity_logs (user_id, action, detail) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $userId, $action, $detail);
        $stmt->execute();
        // note: connection is shared per request, do not close it here
    } catch (Throwable $t) {
        // silently ignore log failures
    }
}

// read a clinic setting from the settings table (cached per request)
function get_setting($key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        try {
            $db = get_db_connection();
            $res = $db->query('SELECT setting_key, setting_value FROM settings');
            while ($row = $res->fetch_assoc()) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $t) {
            // fall back to defaults
        }
    }
    return $cache[$key] ?? $default;
}

// create an internal notification; must never break the main flow
function notify_user($userId, $type, $message) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare('INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $userId, $type, $message);
        $stmt->execute();
    } catch (Throwable $t) {
        // silently ignore
    }
}

// flash messages survive one redirect
function flash_set($type, $message) {
    $_SESSION['flash'] = array('type' => $type, 'message' => $message);
}

function flash_get() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
