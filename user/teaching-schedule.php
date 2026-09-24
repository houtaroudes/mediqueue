<?php
// Teaching schedule (instructor, Phase 9)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('instructor'));

$db = get_db_connection();
$userId = current_user()['id'];
$days = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $day       = (int) ($_POST['day_of_week'] ?? 0);
        $start     = $_POST['start_time'] ?? '';
        $end       = $_POST['end_time'] ?? '';
        $className = trim($_POST['class_name'] ?? '');

        if (!isset($days[$day])) {
            $errors[] = 'Pick a valid day.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            $errors[] = 'Valid start and end times required.';
        } elseif (strtotime($end) <= strtotime($start)) {
            $errors[] = 'End time must be after start time.';
        }
        if ($className === '' || mb_strlen($className) > 100) {
            $errors[] = 'Class name is required (max 100 chars).';
        }

        if (empty($errors)) {
            $stmt = $db->prepare('INSERT INTO teaching_schedules (instructor_id, day_of_week, start_time, end_time, class_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iisss', $userId, $day, $start, $end, $className);
            $stmt->execute();
            $stmt->close();
            log_activity('teaching.add', $className . ' day=' . $day);
            flash_set('success', 'Class schedule added.');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM teaching_schedules WHERE id = ? AND instructor_id = ?');
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $stmt->close();
        flash_set('success', 'Class schedule removed.');
    }
    redirect(BASE_URL . '/user/teaching-schedule.php');
}

$stmt = $db->prepare('SELECT * FROM teaching_schedules WHERE instructor_id = ? ORDER BY day_of_week, start_time');
$stmt->bind_param('i', $userId);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Teaching Schedule';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow">
    <h1>My teaching schedule</h1>
    <p class="muted">Add your weekly classes so MediQueue can recommend clinic slots between them.</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="card form">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <label>Day
                <select name="day_of_week">
                    <?php foreach ($days as $n => $name): ?>
                        <option value="<?php echo $n; ?>"><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Class Name
                <input type="text" name="class_name" maxlength="100" required placeholder="IT 101 - A">
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
        <button class="btn btn-primary btn-block">Add Class</button>
    </form>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Day</th><th>Time</th><th>Class</th><th></th></tr>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?php echo e($days[$c['day_of_week']]); ?></td>
                    <td><?php echo e(date('g:i A', strtotime($c['start_time'])) . ' - ' . date('g:i A', strtotime($c['end_time']))); ?></td>
                    <td><?php echo e($c['class_name']); ?></td>
                    <td>
                        <form method="post" class="inline" onsubmit="return confirm('Remove this class?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                            <button class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$classes): ?><tr><td colspan="4" class="muted">No classes added yet.</td></tr><?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
