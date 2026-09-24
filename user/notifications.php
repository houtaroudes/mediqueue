<?php
// Notifications (Phase 10)
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db_connection();
$userId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $stmt = $db->prepare('UPDATE notifications SET status = "read" WHERE user_id = ? AND status = "unread"');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    redirect(BASE_URL . '/user/notifications.php');
}

$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC LIMIT 50');
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unread = count(array_filter($notifications, function ($n) { return $n['status'] === 'unread'; }));

$page_title = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow">
    <h1>Notifications <?php if ($unread): ?><span class="badge badge-called"><?php echo (int) $unread; ?> new</span><?php endif; ?></h1>

    <?php if ($unread): ?>
        <form method="post">
            <input type="hidden" name="action" value="mark_read">
            <button class="btn btn-sm btn-outline-dark">Mark all as read</button>
        </form>
    <?php endif; ?>

    <?php if (!$notifications): ?>
        <div class="card"><p class="muted">No notifications yet.</p></div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="card notif <?php echo $n['status'] === 'unread' ? 'notif-unread' : ''; ?>">
                <p><strong><?php echo e(ucfirst($n['type'])); ?></strong>
                   <span class="muted">&middot; <?php echo e(date('M j, g:i A', strtotime($n['sent_at']))); ?></span></p>
                <p><?php echo e($n['message']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
