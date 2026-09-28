<?php
// Admin: activity logs (Phase 11 + 13)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();

$stmt = $db->prepare('SELECT l.*, CONCAT(u.first_name, " ", u.last_name) AS user_name, u.role AS user_role
    FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC LIMIT 200');
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Activity Logs';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Activity logs <span class="muted">(latest 200)</span></h1>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th></tr>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?php echo e(date('M j, g:i A', strtotime($l['created_at']))); ?></td>
                    <td><?php echo $l['user_name'] ? e($l['user_name'] . ' (' . $l['user_role'] . ')') : '<span class="muted">system</span>'; ?></td>
                    <td><code><?php echo e($l['action']); ?></code></td>
                    <td><?php echo e($l['detail'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="4" class="muted">No logs yet.</td></tr><?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
