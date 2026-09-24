<?php
// Admin: reports (Phase 12)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = date('Y-m-d');

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mediqueue-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('date', 'service', 'booked', 'completed', 'cancelled', 'no_show'));

    $stmt = $db->prepare('SELECT a.appointment_date, s.name AS service_name,
            COUNT(*) AS booked,
            SUM(a.status = "completed") AS completed,
            SUM(a.status = "cancelled") AS cancelled,
            SUM(a.status = "no_show") AS no_show
        FROM appointments a JOIN services s ON s.id = a.service_id
        WHERE a.appointment_date BETWEEN ? AND ?
        GROUP BY a.appointment_date, s.name
        ORDER BY a.appointment_date');
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

// summary cards
$stmt = $db->prepare('SELECT
    COUNT(*) AS total,
    SUM(status = "completed") AS completed,
    SUM(status = "cancelled") AS cancelled,
    SUM(status = "no_show") AS no_show,
    SUM(status IN ("pending","confirmed")) AS upcoming
    FROM appointments WHERE appointment_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// per-service breakdown
$stmt = $db->prepare('SELECT s.name, COUNT(*) AS booked, SUM(a.status = "completed") AS completed
    FROM appointments a JOIN services s ON s.id = a.service_id
    WHERE a.appointment_date BETWEEN ? AND ?
    GROUP BY s.name ORDER BY booked DESC');
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$perService = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// per-day breakdown
$stmt = $db->prepare('SELECT appointment_date, COUNT(*) AS booked
    FROM appointments WHERE appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date ORDER BY appointment_date');
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$perDay = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// walk-in queue stats
$stmt = $db->prepare('SELECT COUNT(*) AS total, SUM(status = "completed") AS completed
    FROM queue_entries WHERE queue_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$queueStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$maxDay = 1;
foreach ($perDay as $d) $maxDay = max($maxDay, (int) $d['booked']);

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Reports</h1>

    <form method="get" class="card form search-form">
        <label>From <input type="date" name="from" value="<?php echo e($from); ?>"></label>
        <label>To <input type="date" name="to" value="<?php echo e($to); ?>"></label>
        <button class="btn btn-primary">Apply</button>
        <a class="btn btn-outline-dark" href="<?php echo e(BASE_URL . '/admin/reports.php?from=' . $from . '&to=' . $to . '&export=csv'); ?>">Export CSV</a>
    </form>

    <div class="stat-grid">
        <div class="card stat-card"><h3><?php echo (int) $summary['total']; ?></h3><p class="muted">Appointments in range</p></div>
        <div class="card stat-card"><h3><?php echo (int) $summary['completed']; ?></h3><p class="muted">Completed</p></div>
        <div class="card stat-card"><h3><?php echo (int) $summary['cancelled']; ?></h3><p class="muted">Cancelled</p></div>
        <div class="card stat-card"><h3><?php echo (int) $summary['no_show']; ?></h3><p class="muted">No-shows</p></div>
        <div class="card stat-card"><h3><?php echo (int) $queueStats['total']; ?></h3><p class="muted">Walk-in queue entries</p></div>
    </div>

    <h2>Per-service load</h2>
    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Service</th><th>Booked</th><th>Completed</th></tr>
            <?php foreach ($perService as $s): ?>
                <tr><td><?php echo e($s['name']); ?></td><td><?php echo (int) $s['booked']; ?></td><td><?php echo (int) $s['completed']; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$perService): ?><tr><td colspan="3" class="muted">No data in range.</td></tr><?php endif; ?>
        </table>
    </div>

    <h2>Per-day appointments</h2>
    <div class="card">
        <div class="bars">
            <?php foreach ($perDay as $d): ?>
                <div class="bar-col" title="<?php echo e($d['appointment_date']) . ': ' . (int) $d['booked']; ?>">
                    <div class="bar" style="height: <?php echo max(4, (int) round(((int) $d['booked']) / $maxDay * 80)); ?>px"></div>
                    <span class="muted"><?php echo e(date('M j', strtotime($d['appointment_date']))); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$perDay): ?><p class="muted">No data in range.</p><?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
