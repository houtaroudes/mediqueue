<?php
// Admin: clinic services CRUD (Phase 11)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';

    if ($op === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $dur  = (int) ($_POST['duration_minutes'] ?? 30);

        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Service name required (max 100).';
        if ($dur < 5 || $dur > 480) $errors[] = 'Duration must be 5-480 minutes.';

        if (empty($errors)) {
            $stmt = $db->prepare('INSERT INTO services (name, description, duration_minutes) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $name, $desc, $dur);
            $stmt->execute();
            $stmt->close();
            log_activity('admin.service_add', $name);
            flash_set('success', 'Service added.');
        }
    }

    if ($op === 'toggle') {
        $sid = (int) ($_POST['service_id'] ?? 0);
        $stmt = $db->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = ?');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $stmt->close();
        log_activity('admin.service_toggle', 'service=' . $sid);
        flash_set('success', 'Service visibility toggled.');
    }

    if ($op === 'edit') {
        $sid  = (int) ($_POST['service_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $dur  = (int) ($_POST['duration_minutes'] ?? 30);
        if ($name !== '' && $dur >= 5 && $dur <= 480) {
            $stmt = $db->prepare('UPDATE services SET name = ?, description = ?, duration_minutes = ? WHERE id = ?');
            $stmt->bind_param('ssii', $name, $desc, $dur, $sid);
            $stmt->execute();
            $stmt->close();
            log_activity('admin.service_edit', 'service=' . $sid);
            flash_set('success', 'Service updated.');
        } else {
            $errors[] = 'Invalid service data.';
        }
    }
    redirect(BASE_URL . '/admin/services.php');
}

$services = $db->query('SELECT * FROM services ORDER BY is_active DESC, name')->fetch_all(MYSQLI_ASSOC);

$page_title = 'Manage Services';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Clinic services</h1>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="card form">
        <input type="hidden" name="op" value="add">
        <div class="form-row">
            <label>Service Name
                <input type="text" name="name" maxlength="100" required>
            </label>
            <label>Duration (minutes)
                <input type="number" name="duration_minutes" min="5" max="480" value="30" required>
            </label>
        </div>
        <label>Description
            <input type="text" name="description" maxlength="255">
        </label>
        <button class="btn btn-primary">Add Service</button>
    </form>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Name</th><th>Description</th><th>Duration</th><th>Status</th><th>Edit</th></tr>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><?php echo e($s['name']); ?></td>
                    <td><?php echo e($s['description'] ?? '—'); ?></td>
                    <td><?php echo (int) $s['duration_minutes']; ?> min</td>
                    <td><span class="badge badge-<?php echo $s['is_active'] ? 'confirmed' : 'cancelled'; ?>"><?php echo $s['is_active'] ? 'active' : 'hidden'; ?></span></td>
                    <td class="actions-cell">
                        <form method="post" class="inline edit-service">
                            <input type="hidden" name="op" value="edit">
                            <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                            <input type="text" name="name" value="<?php echo e($s['name']); ?>" maxlength="100" required class="input-sm">
                            <input type="number" name="duration_minutes" value="<?php echo (int) $s['duration_minutes']; ?>" min="5" max="480" class="input-sm" style="width:70px">
                            <button class="btn btn-sm">Save</button>
                        </form>
                        <form method="post" class="inline">
                            <input type="hidden" name="op" value="toggle">
                            <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                            <button class="btn btn-sm btn-danger"><?php echo $s['is_active'] ? 'Hide' : 'Show'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
