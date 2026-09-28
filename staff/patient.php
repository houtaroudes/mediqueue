<?php
// Patient search + profile (staff, Phase 8)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('clinic_staff', 'admin'));

$db = get_db_connection();
$q = trim($_GET['q'] ?? '');

$patients = array();
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT p.*, u.email, u.id_number
        FROM patients p LEFT JOIN users u ON u.id = p.user_id
        WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, " ", p.last_name) LIKE ? OR u.id_number LIKE ?
        ORDER BY p.last_name LIMIT 20');
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// selected patient profile
$patient = null; $records = array(); $visits = array();
if (isset($_GET['id'])) {
    $pid = (int) $_GET['id'];
    $stmt = $db->prepare('SELECT p.*, u.email, u.id_number FROM patients p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ?');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($patient) {
        $stmt = $db->prepare('SELECT h.*, CONCAT(u.first_name, " ", u.last_name) AS staff_name
            FROM health_records h JOIN users u ON u.id = h.staff_id
            WHERE h.patient_id = ? ORDER BY h.visit_date DESC');
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $db->prepare('SELECT a.*, s.name AS service_name FROM appointments a
            JOIN services s ON s.id = a.service_id WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC LIMIT 10');
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $visits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$page_title = 'Patients';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Patients</h1>

    <form method="get" class="card form search-form">
        <label>Search by name or ID number
            <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="e.g. Cruz or STU-2026-001">
        </label>
        <button class="btn btn-primary">Search</button>
    </form>

    <?php if ($q !== '' && !$patient): ?>
        <div class="card table-wrap">
            <table class="data-table">
                <tr><th>Name</th><th>ID Number</th><th>Email</th><th></th></tr>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?php echo e($p['first_name'] . ' ' . $p['last_name']); ?></td>
                        <td><?php echo e($p['id_number'] ?? '-'); ?></td>
                        <td><?php echo e($p['email'] ?? 'walk-in'); ?></td>
                        <td><a class="btn btn-sm btn-outline-dark" href="?id=<?php echo (int) $p['id']; ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$patients): ?><tr><td colspan="4" class="muted">No matches.</td></tr><?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($patient): ?>
        <h2><?php echo e($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
        <div class="card">
            <p><strong>ID Number:</strong> <?php echo e($patient['id_number'] ?? '-'); ?> &nbsp;
               <strong>Email:</strong> <?php echo e($patient['email'] ?? '-'); ?> &nbsp;
               <strong>Birthdate:</strong> <?php echo e($patient['date_of_birth'] ?? '-'); ?> &nbsp;
               <strong>Sex:</strong> <?php echo e($patient['sex'] ?? '-'); ?></p>
            <p><strong>Emergency:</strong> <?php echo e($patient['emergency_name'] ?? '-'); ?> (<?php echo e($patient['emergency_phone'] ?? '-'); ?>)</p>
            <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/staff/visit-record.php?patient_id=<?php echo (int) $patient['id']; ?>">New Visit Record</a>
        </div>

        <h3>Health records</h3>
        <div class="card table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Recorded by</th><th>Notes</th><th>Treatment</th></tr>
                <?php foreach ($records as $r): ?>
                    <tr><td><?php echo e($r['visit_date']); ?></td><td><?php echo e($r['staff_name']); ?></td><td><?php echo e($r['visit_notes'] ?: '-'); ?></td><td><?php echo e($r['treatment'] ?: '-'); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$records): ?><tr><td colspan="4" class="muted">No records.</td></tr><?php endif; ?>
            </table>
        </div>

        <h3>Recent appointments</h3>
        <div class="card table-wrap">
            <table class="data-table">
                <tr><th>Date</th><th>Time</th><th>Service</th><th>Status</th></tr>
                <?php foreach ($visits as $v): ?>
                    <tr><td><?php echo e($v['appointment_date']); ?></td><td><?php echo e(date('g:i A', strtotime($v['start_time']))); ?></td><td><?php echo e($v['service_name']); ?></td><td><span class="badge badge-<?php echo e($v['status']); ?>"><?php echo e($v['status']); ?></span></td></tr>
                <?php endforeach; ?>
                <?php if (!$visits): ?><tr><td colspan="4" class="muted">No appointments.</td></tr><?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
