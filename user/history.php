<?php
// My history (Phase 8)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('student', 'staff', 'instructor'));

$db = get_db_connection();
$userId = current_user()['id'];

// past completed appointments
$stmt = $db->prepare('SELECT a.*, s.name AS service_name
    FROM appointments a JOIN services s ON s.id = a.service_id
    JOIN patients p ON p.id = a.patient_id
    WHERE p.user_id = ? AND a.status IN ("completed", "no_show")
    ORDER BY a.appointment_date DESC LIMIT 50');
$stmt->bind_param('i', $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// my health records
$stmt = $db->prepare('SELECT h.*, s.name AS service_name, CONCAT(u.first_name, " ", u.last_name) AS staff_name
    FROM health_records h
    JOIN patients p ON p.id = h.patient_id
    LEFT JOIN appointments a ON a.id = h.appointment_id
    LEFT JOIN services s ON s.id = a.service_id
    JOIN users u ON u.id = h.staff_id
    WHERE p.user_id = ?
    ORDER BY h.visit_date DESC LIMIT 50');
$stmt->bind_param('i', $userId);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My History';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>My visit history</h1>

    <h2>Health records</h2>
    <?php if (!$records): ?>
        <div class="card"><p class="muted">No visit records yet.</p></div>
    <?php else: ?>
        <div class="card table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Service</th><th>Recorded by</th><th>Notes</th><th>Treatment</th></tr>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?php echo e($r['visit_date']); ?></td>
                        <td><?php echo e($r['service_name'] ?? 'Walk-in'); ?></td>
                        <td><?php echo e($r['staff_name']); ?></td>
                        <td><?php echo e($r['visit_notes'] ?: '—'); ?></td>
                        <td><?php echo e($r['treatment'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <h2>Completed / missed appointments</h2>
    <?php if (!$history): ?>
        <div class="card"><p class="muted">Nothing here yet.</p></div>
    <?php else: ?>
        <div class="card table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Time</th><th>Service</th><th>Status</th></tr>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo e($h['appointment_date']); ?></td>
                        <td><?php echo e(date('g:i A', strtotime($h['start_time']))); ?></td>
                        <td><?php echo e($h['service_name']); ?></td>
                        <td><span class="badge badge-<?php echo e($h['status']); ?>"><?php echo e($h['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
