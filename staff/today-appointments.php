<?php
// Today's appointments with status management (staff, Phase 5/7 support)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('clinic_staff', 'admin'));

$db = get_db_connection();

$allowed = array('pending', 'confirmed', 'completed', 'cancelled', 'no_show');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apptId = (int) ($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, $allowed, true)) {
        // atomic update: only succeeds if status actually changed (guards double-processing)
        $stmt = $db->prepare('UPDATE appointments SET status = ? WHERE id = ? AND status <> ?');
        $stmt->bind_param('sis', $status, $apptId, $status);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();

        if ($ok) {
            // notify the patient's account
            $stmt = $db->prepare('SELECT p.user_id FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?');
            $stmt->bind_param('i', $apptId);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($p && $p['user_id']) {
                notify_user((int) $p['user_id'], 'status', 'Your appointment status changed to: ' . $status . '.');
            }
            log_activity('appointment.status', 'appt=' . $apptId . ' -> ' . $status);
            flash_set('success', 'Appointment updated to ' . $status . '.');
        } else {
            flash_set('error', 'No change made (already ' . $status . '?).');
        }
    } else {
        flash_set('error', 'Invalid status.');
    }
    redirect(BASE_URL . '/staff/today-appointments.php');
}

$stmt = $db->prepare('SELECT a.*, s.name AS service_name, CONCAT(p.first_name, " ", p.last_name) AS patient_name
    FROM appointments a
    JOIN services s ON s.id = a.service_id
    JOIN patients p ON p.id = a.patient_id
    WHERE a.appointment_date = CURDATE()
    ORDER BY a.start_time');
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Today's Appointments";
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Today's appointments <span class="muted">(<?php echo date('M j, Y'); ?>)</span></h1>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th><th>Update</th></tr>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><?php echo e(date('g:i A', strtotime($a['start_time']))); ?></td>
                    <td><?php echo e($a['patient_name']); ?></td>
                    <td><?php echo e($a['service_name']); ?></td>
                    <td><span class="badge badge-<?php echo e($a['status']); ?>"><?php echo e($a['status']); ?></span></td>
                    <td>
                        <form method="post" class="inline">
                            <input type="hidden" name="appointment_id" value="<?php echo (int) $a['id']; ?>">
                            <select name="status">
                                <?php foreach ($allowed as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $a['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-primary">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?>
                <tr><td colspan="5" class="muted">No appointments today.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
