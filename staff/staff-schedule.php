<?php
// My duty schedule (clinic staff, Phase 6)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('clinic_staff', 'admin'));

$db = get_db_connection();
$staffId = current_user()['id'];

// next 14 days of my schedules
$stmt = $db->prepare('SELECT * FROM staff_schedules
    WHERE staff_id = ? AND schedule_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 13 DAY
    ORDER BY schedule_date, start_time');
$stmt->bind_param('i', $staffId);
$stmt->execute();
$schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Duty Schedule';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow">
    <h1>My duty schedule <span class="muted">(next 14 days)</span></h1>
    <p class="muted">Admins manage duty rosters from Admin &rarr; Schedules.</p>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Time</th><th>Status</th></tr>
            <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><?php echo e(date('D, M j', strtotime($s['schedule_date']))); ?></td>
                    <td><?php echo e(date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time']))); ?></td>
                    <td><span class="badge badge-<?php echo $s['availability_status'] === 'available' ? 'confirmed' : 'cancelled'; ?>"><?php echo e($s['availability_status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$schedules): ?>
                <tr><td colspan="3" class="muted">No duty schedules assigned yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
