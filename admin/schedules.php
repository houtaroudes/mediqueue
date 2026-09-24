<?php
// Admin: staff duty schedules (Phase 6 + 11)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();
$errors = array();

// clinic staff list for the dropdown
$staffList = $db->query('SELECT id, first_name, last_name FROM users WHERE role = "clinic_staff" ORDER BY first_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';

    if ($op === 'add') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $date    = $_POST['schedule_date'] ?? '';
        $start   = $_POST['start_time'] ?? '';
        $end     = $_POST['end_time'] ?? '';

        $isStaff = false;
        foreach ($staffList as $s) if ((int) $s['id'] === $staffId) $isStaff = true;

        if (!$isStaff) {
            $errors[] = 'Pick a valid clinic staff member.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = 'Valid date required.';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || strtotime($end) <= strtotime($start)) {
            $errors[] = 'End time must be after start time.';
        } else {
            // conflict check: same staff, overlapping time that day
            $stmt = $db->prepare('SELECT id FROM staff_schedules
                WHERE staff_id = ? AND schedule_date = ? AND start_time < ? AND end_time > ?');
            $endS = date('H:i:s', strtotime($end));
            $startS = date('H:i:s', strtotime($start));
            $stmt->bind_param('isss', $staffId, $date, $endS, $startS);
            $stmt->execute();
            $conflict = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($conflict) {
                $errors[] = 'That schedule overlaps an existing duty for this staff member.';
            } else {
                $stmt = $db->prepare('INSERT INTO staff_schedules (staff_id, schedule_date, start_time, end_time) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('isss', $staffId, $date, $startS, $endS);
                $stmt->execute();
                $stmt->close();
                log_activity('admin.schedule_add', 'staff=' . $staffId . ' date=' . $date);
                flash_set('success', 'Schedule added.');
            }
        }
    }

    if ($op === 'delete') {
        $sid = (int) ($_POST['schedule_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM staff_schedules WHERE id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $stmt->close();
        log_activity('admin.schedule_delete', 'sched=' . $sid);
        flash_set('success', 'Schedule removed.');
    }
    redirect(BASE_URL . '/admin/schedules.php');
}

// upcoming schedules with staff names
$schedules = $db->query('SELECT ss.*, CONCAT(u.first_name, " ", u.last_name) AS staff_name
    FROM staff_schedules ss JOIN users u ON u.id = ss.staff_id
    WHERE ss.schedule_date >= CURDATE()
    ORDER BY ss.schedule_date, ss.start_time LIMIT 60')->fetch_all(MYSQLI_ASSOC);

$page_title = 'Manage Schedules';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Staff duty schedules</h1>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="card form">
        <input type="hidden" name="op" value="add">
        <div class="form-row">
            <label>Clinic Staff
                <select name="staff_id" required>
                    <option value="">-- select --</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?php echo (int) $s['id']; ?>"><?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Date
                <input type="date" name="schedule_date" required min="<?php echo date('Y-m-d'); ?>">
            </label>
        </div>
        <div class="form-row">
            <label>Start
                <input type="time" name="start_time" required>
            </label>
            <label>End
                <input type="time" name="end_time" required>
            </label>
        </div>
        <button class="btn btn-primary">Add Schedule</button>
    </form>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Staff</th><th>Time</th><th></th></tr>
            <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><?php echo e(date('D, M j', strtotime($s['schedule_date']))); ?></td>
                    <td><?php echo e($s['staff_name']); ?></td>
                    <td><?php echo e(date('g:i A', strtotime($s['start_time'])) . ' - ' . date('g:i A', strtotime($s['end_time']))); ?></td>
                    <td>
                        <form method="post" class="inline" onsubmit="return confirm('Remove this schedule?');">
                            <input type="hidden" name="op" value="delete">
                            <input type="hidden" name="schedule_id" value="<?php echo (int) $s['id']; ?>">
                            <button class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$schedules): ?><tr><td colspan="4" class="muted">No upcoming schedules.</td></tr><?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
