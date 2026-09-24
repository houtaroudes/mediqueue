<?php
// Public services page (Phase 15) - reads live data from the services table
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Services';
require __DIR__ . '/includes/header.php';

$services = array();
try {
    $db = get_db_connection();
    $res = $db->query('SELECT name, description, duration_minutes FROM services WHERE is_active = 1 ORDER BY name');
    $services = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $t) {
    // page still renders if DB is down
}
?>

<section class="container page">
    <h1>Clinic services</h1>
    <p class="muted">These are the services you can book an appointment for.</p>

    <?php foreach ($services as $s): ?>
        <div class="card service-card">
            <h3><?php echo e($s['name']); ?></h3>
            <p class="muted"><?php echo e($s['description'] ?: 'Contact the clinic for details.'); ?></p>
            <p><span class="badge badge-confirmed">~<?php echo (int) $s['duration_minutes']; ?> min</span></p>
        </div>
    <?php endforeach; ?>

    <?php if (!$services): ?>
        <div class="card"><p class="muted">Services list is unavailable right now. Please check back later.</p></div>
    <?php endif; ?>

    <div class="card">
        <h3>Walk-ins welcome</h3>
        <p>Need to be seen today? <a href="<?php echo BASE_URL; ?>/user/queue.php">Join the walk-in queue</a> and get a number.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
