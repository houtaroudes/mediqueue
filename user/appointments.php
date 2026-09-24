<?php
// My appointments (Phase 5)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('student', 'staff', 'instructor'));

$db = get_db_connection();
$userId = current_user()['id'];

$stmt = $db->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    flash_set('error', 'No patient record linked to your account.');
    redirect(BASE_URL . '/user/dashboard.php');
}
$patientId = (int) $patient['id'];

// cancellation rules from settings (configurable)
$cancelHours = (int) get_setting('cancel_min_hours', 2);

// handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $apptId = (int) ($_POST['appointment_id'] ?? 0);

    $stmt = $db->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ? AND status IN ("pending","confirmed")');
    $stmt->bind_param('ii', $apptId, $patientId);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($appt) {
        $startTs = strtotime($appt['appointment_date'] . ' ' . $appt['start_time']);
        if ($startTs - time() < $cancelHours * 3600) {
            flash_set('error', "Appointments can only be cancelled at least $cancelHours hour(s) before the schedule.");
        } else {
            $stmt = $db->prepare('UPDATE appointments SET status = "cancelled" WHERE id = ?');
            $stmt->bind_param('i', $apptId);
            $stmt->execute();
            $stmt->close();
            notify_user($userId, 'status', 'Your appointment on ' . $appt['appointment_date'] . ' was cancelled.');
            log_activity('appointment.cancel', 'appt=' . $apptId);
            flash_set('success', 'Appointment cancelled.');
        }
    } else {
        flash_set('error', 'Appointment not found or cannot be cancelled.');
    }
    redirect(BASE_URL . '/user/appointments.php');
}

// list all my appointments
$stmt = $db->prepare('SELECT a.*, s.name AS service_name
    FROM appointments a JOIN services s ON s.id = a.service_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.start_time DESC');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Appointments';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>My appointments</h1>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Time</th><th>Service</th><th>Status</th><th></th></tr>
            <?php foreach ($appointments as $a):
                $canCancel = in_array($a['status'], array('pending', 'confirmed'), true)
                    && (strtotime($a['appointment_date'] . ' ' . $a['start_time']) - time()) >= $cancelHours * 3600;
            ?>
                <tr>
                    <td><?php echo e($a['appointment_date']); ?></td>
                    <td><?php echo e(date('g:i A', strtotime($a['start_time']))); ?></td>
                    <td><?php echo e($a['service_name']); ?></td>
                    <td><span class="badge badge-<?php echo e($a['status']); ?>"><?php echo e($a['status']); ?></span></td>
                    <td>
                        <?php if ($canCancel): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Cancel this appointment?');">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="appointment_id" value="<?php echo (int) $a['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?>
                <tr><td colspan="5" class="muted">No appointments yet. <a href="<?php echo BASE_URL; ?>/user/book-appointment.php">Book one</a>.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
