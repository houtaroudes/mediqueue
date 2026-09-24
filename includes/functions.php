<?php
// Small helper functions used across pages

// escape output to prevent HTML injection
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// redirect to another page and stop the script
function redirect($path) {
    header('Location: ' . $path);
    exit;
}
