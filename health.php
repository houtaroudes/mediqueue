<?php
// System health check - shows PHP and database status
// Keep this page; it is the quick way to confirm the app can reach the DB
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'System Health Check';
require __DIR__ . '/includes/header.php';

$db_ok = false;
$db_error = '';

try {
    $db = get_db_connection();
    $db_ok = true;
} catch (Throwable $t) {
    if (APP_DEBUG) {
        $db_error = $t->getMessage();
    }
}
?>

<section class="container page">
    <h1>System Health Check</h1>

    <div class="card">
        <h3>PHP</h3>
        <p class="status-ok">&#10003; PHP is running - version <?php echo e(PHP_VERSION); ?></p>
    </div>

    <div class="card">
        <h3>Database</h3>
        <?php if ($db_ok): ?>
            <p class="status-ok">&#10003; Connected to MySQL - database <strong><?php echo e(DB_NAME); ?></strong></p>
            <p class="muted">Server: <?php echo e($db->server_info); ?></p>
        <?php else: ?>
            <p class="status-fail">&#10007; Could not connect to the database.</p>
            <?php if (APP_DEBUG && $db_error): ?>
                <p class="muted">Debug info: <?php echo e($db_error); ?></p>
            <?php endif; ?>
            <p class="muted">Make sure MySQL is started in the XAMPP Control Panel and the
                <code>mediqueue</code> database was imported from <code>database/mediqueue.sql</code>.</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
