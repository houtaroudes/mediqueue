<?php
// Admin dashboard (Phase 4 + Phase 12 stats)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();

$stats = array();
$res = $db->query('SELECT
    (SELECT COUNT(*) FROM users) AS total_users,
    (SELECT COUNT(*) FROM users WHERE role = "student") AS students,
    (SELECT COUNT(*) FROM users WHERE role = "instructor") AS instructors,
    (SELECT COUNT(*) FROM users WHERE role = "clinic_staff") AS clinic_staff,
    (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()) AS appts_today,
    (SELECT COUNT(*) FROM appointments WHERE status = "pending") AS appts_pending,
    (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE()) AS queue_today,
    (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = "waiting") AS queue_waiting');
$stats = $res->fetch_assoc();

// appointments last 7 days for a tiny bar chart
$perDay = array();
$res = $db->query('SELECT appointment_date, COUNT(*) AS cnt FROM appointments
    WHERE appointment_date >= CURDATE() - INTERVAL 6 DAY
    GROUP BY appointment_date ORDER BY appointment_date');
foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
    $perDay[$r['appointment_date']] = (int) $r['cnt'];
}

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Admin Dashboard</h1>

    <div class="stat-grid">
        <div class="card stat-card"><h3><?php echo (int) $stats['total_users']; ?></h3><p class="muted">Total users</p></div>
        <div class="card stat-card"><h3><?php echo (int) $stats['appts_today']; ?></h3><p class="muted">Appointments today</p></div>
        <div class="card stat-card"><h3><?php echo (int) $stats['appts_pending']; ?></h3><p class="muted">Pending appointments</p></div>
        <div class="card stat-card"><h3><?php echo (int) $stats['queue_waiting']; ?></h3><p class="muted">Waiting in queue</p></div>
    </div>

    <h2>Appointments (last 7 days)</h2>
    <div class="card">
        <div class="bars">
            <?php for ($i = 6; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i days")); $c = $perDay[$d] ?? 0; ?>
                <div class="bar-col">
                    <div class="bar" style="height: <?php echo max(4, $c * 20); ?>px" title="<?php echo (int) $c; ?>"></div>
                    <span class="muted"><?php echo date('D', strtotime($d)); ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <h2>Quick links</h2>
    <div class="stat-grid">
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/admin/users.php"><h3>Users</h3><p class="muted">Manage accounts</p></a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/admin/services.php"><h3>Services</h3><p class="muted">Clinic offerings</p></a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/admin/schedules.php"><h3>Schedules</h3><p class="muted">Staff duty rosters</p></a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/admin/reports.php"><h3>Reports</h3><p class="muted">Load and statistics</p></a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/admin/settings.php"><h3>Settings</h3><p class="muted">Clinic rules</p></a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/admin/activity-logs.php"><h3>Activity Logs</h3><p class="muted">System audit trail</p></a>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
