<?php
// Admin: all appointments (Phase 11)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();

$filterStatus = $_GET['status'] ?? '';
$filterDate   = $_GET['date'] ?? '';

$allowed = array('pending', 'confirmed', 'completed', 'cancelled', 'no_show');

$where = array('1=1');
$types = '';
$params = array();

if (in_array($filterStatus, $allowed, true)) {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $filterStatus;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $where[] = 'a.appointment_date = ?';
    $types .= 's';
    $params[] = $filterDate;
}

$sql = 'SELECT a.*, s.name AS service_name, CONCAT(p.first_name, " ", p.last_name) AS patient_name
    FROM appointments a
    JOIN services s ON s.id = a.service_id
    JOIN patients p ON p.id = a.patient_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 100';

$stmt = $db->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'All Appointments';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>All appointments</h1>

    <form method="get" class="card form search-form">
        <label>Status
            <select name="status">
                <option value="">-- any --</option>
                <?php foreach ($allowed as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Date
            <input type="date" name="date" value="<?php echo e($filterDate); ?>">
        </label>
        <button class="btn btn-primary">Filter</button>
        <a class="btn" href="<?php echo BASE_URL; ?>/admin/appointments.php">Reset</a>
    </form>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Date</th><th>Time</th><th>Patient</th><th>Service</th><th>Status</th><th>Notes</th></tr>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><?php echo e($a['appointment_date']); ?></td>
                    <td><?php echo e(date('g:i A', strtotime($a['start_time']))); ?></td>
                    <td><?php echo e($a['patient_name']); ?></td>
                    <td><?php echo e($a['service_name']); ?></td>
                    <td><span class="badge badge-<?php echo e($a['status']); ?>"><?php echo e($a['status']); ?></span></td>
                    <td><?php echo e($a['notes'] ?: '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><tr><td colspan="6" class="muted">No appointments match.</td></tr><?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
