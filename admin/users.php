<?php
// Admin: user management (Phase 11)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();
$q = trim($_GET['q'] ?? '');

$roles = array('student', 'staff', 'instructor', 'clinic_staff', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int) ($_POST['user_id'] ?? 0);
    $op  = $_POST['op'] ?? '';

    if ($uid === current_user()['id']) {
        flash_set('error', 'You cannot modify your own account here.');
    } elseif ($op === 'toggle_status') {
        $stmt = $db->prepare('UPDATE users SET status = IF(status = "active", "inactive", "active") WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
        log_activity('admin.user_status', 'user=' . $uid);
        flash_set('success', 'User status updated.');
    } elseif ($op === 'change_role' && in_array($_POST['role'] ?? '', $roles, true)) {
        $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->bind_param('si', $_POST['role'], $uid);
        $stmt->execute();
        $stmt->close();
        log_activity('admin.user_role', 'user=' . $uid . ' role=' . $_POST['role']);
        flash_set('success', 'User role updated.');
    } else {
        flash_set('error', 'Invalid operation.');
    }
    redirect(BASE_URL . '/admin/users.php');
}

// list users with optional search
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare('SELECT * FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR id_number LIKE ? ORDER BY created_at DESC LIMIT 100');
    $stmt->bind_param('ssss', $like, $like, $like, $like);
} else {
    $stmt = $db->prepare('SELECT * FROM users ORDER BY created_at DESC LIMIT 100');
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Users</h1>

    <form method="get" class="card form search-form">
        <label>Search
            <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="name, email, or ID number">
        </label>
        <button class="btn btn-primary">Search</button>
    </form>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Name</th><th>Email</th><th>Role</th><th>ID No.</th><th>Status</th><th>Actions</th></tr>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo e($u['first_name'] . ' ' . $u['last_name']); ?></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td>
                        <?php if ($u['id'] === current_user()['id']): ?>
                            <strong><?php echo e($u['role']); ?></strong>
                        <?php else: ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="op" value="change_role">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <select name="role">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r; ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>><?php echo str_replace('_', ' ', $r); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm">Set</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($u['id_number'] ?? '-'); ?></td>
                    <td><span class="badge badge-<?php echo $u['status'] === 'active' ? 'confirmed' : 'cancelled'; ?>"><?php echo e($u['status']); ?></span></td>
                    <td>
                        <?php if ($u['id'] !== current_user()['id']): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="op" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <button class="btn btn-sm btn-danger"><?php echo $u['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
