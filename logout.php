<?php
// Logout (Phase 3)
require_once __DIR__ . '/includes/auth.php';

log_activity('auth.logout');
$_SESSION = array();
session_destroy();

header('Refresh: 1.2; URL=' . BASE_URL . '/index.php');
$page_title = 'Signing Out';
require __DIR__ . '/includes/header.php';

// set the goodbye AFTER header.php so it survives for the landing page
// (header.php consumes any current flash when it renders)
session_start();
flash_set('success', 'You have been logged out. See you next visit!');
?>

<section class="container page narrow bye-wrap">
    <div class="bye">
        <div class="board-number bye-board">--:--</div>
        <h1>Signed out</h1>
        <p class="muted">Taking you back to the clinic front page&hellip;</p>
        <p><a class="btn btn-primary" href="<?php echo BASE_URL; ?>/index.php">Go now</a></p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
