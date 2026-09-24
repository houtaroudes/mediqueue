<?php
// User dashboard (Phase 4) - student / staff / instructor
require_once __DIR__ . '/../includes/auth.php';
require_role(array('student', 'staff', 'instructor'));

$db = get_db_connection();
$userId = current_user()['id'];

// my patient record
$stmt = $db->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

$patientId = $patient ? (int) $patient['id'] : 0;

// upcoming appointments
$upcoming = array();
if ($patientId) {
    $stmt = $db->prepare('SELECT a.*, s.name AS service_name
        FROM appointments a JOIN services s ON s.id = a.service_id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ("pending","confirmed")
        ORDER BY a.appointment_date, a.start_time LIMIT 5');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// my active queue entry today
$queueEntry = null;
if ($patientId) {
    $stmt = $db->prepare('SELECT * FROM queue_entries WHERE patient_id = ? AND queue_date = CURDATE() AND status IN ("waiting","called","in_consultation") LIMIT 1');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $queueEntry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$page_title = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Hi, <?php echo e(current_user()['first_name']); ?><?php echo mq_icon('clipboard-heart', 30); ?></h1>
    <p class="muted"><?php echo e(ucfirst(str_replace('_', ' ', current_user()['role']))); ?> account &middot; <?php echo e(CLINIC_NAME); ?></p>

    <div class="stat-grid">
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/user/book-appointment.php">
            <?php echo mq_icon('calendar', 28); ?>
            <h3>Book Appointment</h3>
            <p class="muted">Pick a service, date, and time</p>
        </a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/user/queue.php">
            <?php echo mq_icon('ticket', 28); ?>
            <h3>Walk-in Queue</h3>
            <p class="muted"><?php echo $queueEntry ? 'Your number: ' . e($queueEntry['queue_number']) : 'Get a number, skip the line'; ?></p>
        </a>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/user/history.php">
            <?php echo mq_icon('clipboard-heart', 28); ?>
            <h3>My History</h3>
            <p class="muted">Past visits and records</p>
        </a>
        <?php if (current_user()['role'] === 'instructor'): ?>
        <a class="card stat-card" href="<?php echo BASE_URL; ?>/user/recommended-slots.php">
            <?php echo mq_icon('book-open', 28); ?>
            <h3>Recommended Slots</h3>
            <p class="muted">Clinic times that fit your classes</p>
        </a>
        <?php endif; ?>
    </div>

    <h2>Upcoming appointments</h2>
    <?php if (!$upcoming): ?>
        <div class="card"><p class="muted">No upcoming appointments. <a href="<?php echo BASE_URL; ?>/user/book-appointment.php">Book one now</a>.</p></div>
    <?php else: ?>
        <div class="card table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Time</th><th>Service</th><th>Status</th></tr>
                <?php foreach ($upcoming as $a): ?>
                    <tr>
                        <td><?php echo e($a['appointment_date']); ?></td>
                        <td><?php echo e(date('g:i A', strtotime($a['start_time']))); ?></td>
                        <td><?php echo e($a['service_name']); ?></td>
                        <td><span class="badge badge-<?php echo e($a['status']); ?>"><?php echo e($a['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
