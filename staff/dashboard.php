<?php
// Clinic staff dashboard (Phase 4)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('clinic_staff', 'admin'));

$db = get_db_connection();

// today's appointments
$stmt = $db->prepare('SELECT a.*, s.name AS service_name, CONCAT(p.first_name, " ", p.last_name) AS patient_name
    FROM appointments a
    JOIN services s ON s.id = a.service_id
    JOIN patients p ON p.id = a.patient_id
    WHERE a.appointment_date = CURDATE() AND a.status IN ("pending","confirmed")
    ORDER BY a.start_time');
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// queue summary today
$queue = array('waiting' => 0, 'called' => 0, 'in_consultation' => 0, 'completed' => 0);
$stmt = $db->prepare('SELECT status, COUNT(*) AS cnt FROM queue_entries WHERE queue_date = CURDATE() GROUP BY status');
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if (isset($queue[$row['status']])) $queue[$row['status']] = (int) $row['cnt'];
}
$stmt->close();

// current serving entry
$stmt = $db->prepare('SELECT q.*, CONCAT(p.first_name, " ", p.last_name) AS patient_name
    FROM queue_entries q JOIN patients p ON p.id = q.patient_id
    WHERE q.queue_date = CURDATE() AND q.status = "in_consultation" LIMIT 1');
$stmt->execute();
$serving = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'Staff Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Clinic Staff Dashboard</h1>

    <div class="stat-grid">
        <div class="card stat-card">
            <h3><?php echo (int) $queue['waiting']; ?></h3>
            <p class="muted">Waiting in queue</p>
        </div>
        <div class="card stat-card">
            <h3><?php echo (int) $queue['called']; ?></h3>
            <p class="muted">Called</p>
        </div>
        <div class="card stat-card">
            <h3><?php echo (int) $queue['in_consultation']; ?></h3>
            <p class="muted">In consultation</p>
        </div>
        <div class="card stat-card">
            <h3><?php echo count($appointments); ?></h3>
            <p class="muted">Today's appointments</p>
        </div>
    </div>

    <div class="two-col">
        <div>
            <h2>Queue now</h2>
            <div class="card">
                <?php if ($serving): ?>
                    <p>Now serving: <strong><?php echo e($serving['queue_number']); ?></strong> - <?php echo e($serving['patient_name']); ?></p>
                <?php else: ?>
                    <p class="muted">Nobody in consultation.</p>
                <?php endif; ?>
                <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/staff/queue-management.php">Open Queue Management</a>
            </div>
        </div>
        <div>
            <h2>Today's appointments</h2>
            <?php if (!$appointments): ?>
                <div class="card"><p class="muted">No appointments scheduled for today.</p></div>
            <?php else: ?>
                <div class="card table-wrap">
                    <table class="data-table">
                        <tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><?php echo e(date('g:i A', strtotime($a['start_time']))); ?></td>
                                <td><?php echo e($a['patient_name']); ?></td>
                                <td><?php echo e($a['service_name']); ?></td>
                                <td><span class="badge badge-<?php echo e($a['status']); ?>"><?php echo e($a['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
