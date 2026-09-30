<?php
// Walk-in queue for users (Phase 7)
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

// join queue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join') {
    // already in queue today?
    $stmt = $db->prepare('SELECT id FROM queue_entries WHERE patient_id = ? AND queue_date = CURDATE() AND status IN ("waiting","called","in_consultation")');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $already = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($already) {
        flash_set('error', 'You are already in the queue today.');
    } else {
        $prefix = get_setting('queue_prefix', 'A');
        $padLen = (int) get_setting('queue_pad_len', 3);

        // next number for today, generated safely in one query
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM queue_entries WHERE queue_date = CURDATE()');
        $stmt->execute();
        $next = (int) $stmt->get_result()->fetch_assoc()['c'] + 1;
        $stmt->close();

        $queueNumber = $prefix . str_pad((string) $next, $padLen, '0', STR_PAD_LEFT);

        // retry on rare duplicate (two joins at the same moment)
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $stmt = $db->prepare('INSERT INTO queue_entries (patient_id, queue_date, queue_number, status) VALUES (?, CURDATE(), ?, "waiting")');
                $stmt->bind_param('is', $patientId, $queueNumber);
                $stmt->execute();
                $stmt->close();
                notify_user($userId, 'queue', 'You joined the queue as ' . $queueNumber . '.');
                log_activity('queue.join', 'number=' . $queueNumber);
                flash_set('success', 'You are in the queue! Your number is ' . $queueNumber . '.');
                redirect(BASE_URL . '/user/queue.php');
            } catch (mysqli_sql_exception $e) {
                $next++;
                $queueNumber = $prefix . str_pad((string) $next, $padLen, '0', STR_PAD_LEFT);
            }
        }
        flash_set('error', 'Could not join the queue. Please try again.');
    }
    redirect(BASE_URL . '/user/queue.php');
}

// leave queue while still waiting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'leave') {
    $stmt = $db->prepare('UPDATE queue_entries SET status = "cancelled", completed_at = NOW() WHERE patient_id = ? AND queue_date = CURDATE() AND status = "waiting"');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $stmt->close();
    log_activity('queue.leave');
    flash_set('success', 'You left the queue.');
    redirect(BASE_URL . '/user/queue.php');
}

// my entry today
$stmt = $db->prepare('SELECT * FROM queue_entries WHERE patient_id = ? AND queue_date = CURDATE() ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$myEntry = $stmt->get_result()->fetch_assoc();
$stmt->close();

// live counters
$counts = array('waiting' => 0, 'called' => 0, 'in_consultation' => 0, 'completed' => 0);
$stmt = $db->prepare('SELECT status, COUNT(*) AS cnt FROM queue_entries WHERE queue_date = CURDATE() GROUP BY status');
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['cnt'];
}
$stmt->close();

// people ahead of me. Counted in queue order (enqueued_at), not id order:
// a skipped patient was bumped to the back and must not be counted ahead.
$ahead = 0;
if ($myEntry && $myEntry['status'] === 'waiting') {
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM queue_entries
        WHERE queue_date = CURDATE() AND status = "waiting"
          AND (enqueued_at < ? OR (enqueued_at = ? AND id < ?))');
    $stmt->bind_param('ssi', $myEntry['enqueued_at'], $myEntry['enqueued_at'], $myEntry['id']);
    $stmt->execute();
    $ahead = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

$myWait = $ahead > 0 ? max(1, $ahead * mq_avg_service_min()) : 0;

$page_title = 'Walk-in Queue';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow" data-live="12">
    <h1>Walk-in queue</h1>

    <?php if ($myEntry && in_array($myEntry['status'], array('waiting', 'called', 'in_consultation'), true)): ?>
        <div class="card queue-card">
            <p class="muted">Your number today</p>
            <div class="queue-number"><?php echo e($myEntry['queue_number']); ?></div>
            <p class="status-<?php echo $myEntry['status'] === 'waiting' ? 'muted' : 'ok'; ?>">
                Status: <strong><?php echo e(str_replace('_', ' ', $myEntry['status'])); ?></strong>
                <?php if ($myEntry['status'] === 'waiting'): ?>
                    &middot; <?php echo (int) $ahead; ?> ahead of you
                    <?php if ($myWait > 0): ?> &middot; about <?php echo (int) $myWait; ?> min wait<?php endif; ?>
                <?php endif; ?>
            </p>
            <?php if ($myEntry['status'] === 'waiting'): ?>
                <form method="post" onsubmit="return confirm('Leave the queue?');">
                    <input type="hidden" name="action" value="leave">
                    <button type="submit" class="btn btn-danger">Leave Queue</button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card queue-card">
            <p>Not in the queue right now.</p>
            <form method="post">
                <input type="hidden" name="action" value="join">
                <button type="submit" class="btn btn-primary">Join Walk-in Queue</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Queue status today</h3>
        <table class="hours-table">
            <tr><td>Waiting</td><td><?php echo (int) $counts['waiting']; ?></td></tr>
            <tr><td>Called</td><td><?php echo (int) $counts['called']; ?></td></tr>
            <tr><td>In consultation</td><td><?php echo (int) $counts['in_consultation']; ?></td></tr>
            <tr><td>Completed</td><td><?php echo (int) $counts['completed']; ?></td></tr>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
