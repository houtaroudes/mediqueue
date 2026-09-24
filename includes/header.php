<?php
// Reusable page header - pages set $page_title then include this file
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/board.php';

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : ''; echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=4">
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/assets/images/favicon.svg">
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/fonts/barlow-700.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/fonts/dotgothic16-400.woff2" as="font" type="font/woff2" crossorigin>
</head>
<body>

<header class="site-header">
    <nav class="container nav">
        <a class="brand" href="<?php echo BASE_URL; ?>/index.php"><?php echo mq_icon('logo', 26); ?><?php echo e(APP_NAME); ?></a>

        <button class="nav-toggle" id="navToggle" aria-label="Open menu">☰</button>

        <ul class="nav-links" id="navLinks">
            <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
            <?php if ($user): ?>
                <li><a href="<?php echo dashboard_url($user['role']); ?>">Dashboard</a></li>
                <?php if (in_array($user['role'], array('student', 'staff', 'instructor'), true)): ?>
                    <li><a href="<?php echo BASE_URL; ?>/user/book-appointment.php">Book</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/user/queue.php">Queue</a></li>
                <?php endif; ?>
                <li><a href="<?php echo BASE_URL; ?>/user/notifications.php" class="nav-bell" title="Notifications"><?php echo mq_icon('clipboard-heart', 18); ?></a></li>
                <li><span class="nav-user">Hi, <?php echo e($user['first_name']); ?></span></li>
                <li><a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm btn-outline-dark">Logout</a></li>
            <?php else: ?>
                <li><a href="<?php echo BASE_URL; ?>/about.php">About</a></li>
                <li><a href="<?php echo BASE_URL; ?>/services.php">Services</a></li>
                <li><a href="<?php echo BASE_URL; ?>/contact.php">Contact</a></li>
                <li><a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-sm btn-outline-dark">Register</a></li>
                <li><a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-primary">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main>
<?php $f = flash_get(); if ($f): ?>
    <div class="container">
        <div class="alert alert-<?php echo e($f['type']); ?>"><?php echo e($f['message']); ?></div>
    </div>
<?php endif; ?>
