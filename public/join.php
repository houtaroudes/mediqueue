<?php
// Public walk-in registration: the page behind the QR code at the clinic door.
// No login, no paper form. A visitor scans, taps once, and holds a number.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = get_db_connection();

// Logged-in users join through their own queue page instead, so the entry is
// linked to their account and they receive notifications there.
if (is_logged_in() && has_role(array('student', 'staff', 'instructor'))) {
    redirect(BASE_URL . '/user/queue.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join') {

    // one ticket per visitor: the ?t= token on the link doubles as the receipt,
    // so a reload or re-tap returns the same number instead of a second ticket
    $token = $_GET['t'] ?? '';
    if (is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token)) {
        $stmt = $db->prepare('SELECT queue_number, join_token FROM queue_entries
            WHERE join_token = ? AND queue_date = CURDATE() AND status IN ("waiting","called","in_consultation")');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            flash_set('success', 'You are already in the queue today. Your number is ' . $existing['queue_number'] . '.');
            redirect(BASE_URL . '/public/join.php?t=' . urlencode($existing['join_token']));
        }
    }

    // gentle rate limit per device: a second tap within 10 minutes is a mistake,
    // not a new patient (a fresh browser still works, which is fine for a clinic)
    $last = (int) ($_SESSION['last_public_join'] ?? 0);
    if (time() - $last < 600) {
        flash_set('error', 'This device just took a number. Please wait a few minutes or ask the staff.');
        redirect(BASE_URL . '/public/join.php');
    }

    // the clinic must be open: same rules the booking form enforces
    if (clinic_window_for(date('Y-m-d')) === null) {
        flash_set('error', 'The clinic is closed today. Please come back during clinic hours.');
        redirect(BASE_URL . '/public/join.php');
    }

    // walk-ins share one anonymous patient record, so the queue schema is untouched
    $res = $db->query("SELECT id FROM patients WHERE first_name = 'Walk-in' AND last_name = 'Visitor' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        $patientId = (int) $row['id'];
    } else {
        $db->query("INSERT INTO patients (first_name, last_name, address) VALUES ('Walk-in', 'Visitor', 'QR self-registration')");
        $patientId = (int) $db->insert_id;
    }

    $prefix = get_setting('queue_prefix', 'A');
    $padLen = (int) get_setting('queue_pad_len', 3);
    $res = $db->query('SELECT COUNT(*) AS c FROM queue_entries WHERE queue_date = CURDATE()');
    $next = (int) $res->fetch_assoc()['c'] + 1;
    $newToken = bin2hex(random_bytes(16));

    // retry on the rare duplicate number (two joins at the same moment)
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $queueNumber = $prefix . str_pad((string) $next, $padLen, '0', STR_PAD_LEFT);
        try {
            $stmt = $db->prepare('INSERT INTO queue_entries (patient_id, queue_date, queue_number, status, join_token) VALUES (?, CURDATE(), ?, "waiting", ?)');
            $stmt->bind_param('iss', $patientId, $queueNumber, $newToken);
            $stmt->execute();
            $stmt->close();
            $_SESSION['last_public_join'] = time();
            log_activity('queue.join_public', 'number=' . $queueNumber);
            flash_set('success', 'You are in the queue! Your number is ' . $queueNumber . '. Keep this page open or bookmark it.');
            redirect(BASE_URL . '/public/join.php?t=' . urlencode($newToken));
        } catch (mysqli_sql_exception $e) {
            $next++;
        }
    }
    flash_set('error', 'Could not take a number right now. Please approach the front desk.');
    redirect(BASE_URL . '/public/join.php');
}

// the visitor's active ticket, if the receipt token is still valid
$myEntry = null;
$token = $_GET['t'] ?? '';
if (is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt = $db->prepare('SELECT * FROM queue_entries WHERE join_token = ? AND queue_date = CURDATE() AND status IN ("waiting","called","in_consultation")');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $myEntry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// people ahead of the ticket + live counters for the room
$ahead = 0;
$counts = array('waiting' => 0, 'called' => 0, 'in_consultation' => 0, 'completed' => 0);
$res = $db->query('SELECT status, COUNT(*) AS cnt FROM queue_entries WHERE queue_date = CURDATE() GROUP BY status');
if ($res) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['cnt'];
    }
}
if ($myEntry && $myEntry['status'] === 'waiting') {
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM queue_entries
        WHERE queue_date = CURDATE() AND status = "waiting"
          AND (enqueued_at < ? OR (enqueued_at = ? AND id < ?))');
    $stmt->bind_param('ssi', $myEntry['enqueued_at'], $myEntry['enqueued_at'], $myEntry['id']);
    $stmt->execute();
    $ahead = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

$minPer = mq_avg_service_min();
$myWait = max(1, $ahead * $minPer);
$roomWait = max(1, $counts['waiting'] * $minPer);

$page_title = 'Join the Walk-in Queue';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow" data-live="12">
    <h1>Walk-in queue</h1>

    <?php if ($myEntry): ?>
        <div class="card queue-card">
            <p class="muted">Your number today</p>
            <div class="queue-number"><?php echo e($myEntry['queue_number']); ?></div>
            <p class="status-<?php echo $myEntry['status'] === 'waiting' ? 'muted' : 'ok'; ?>">
                Status: <strong><?php echo e(str_replace('_', ' ', $myEntry['status'])); ?></strong>
                <?php if ($myEntry['status'] === 'waiting'): ?>
                    &middot; <?php echo (int) $ahead; ?> ahead of you
                    &middot; about <?php echo (int) $myWait; ?> min wait
                <?php endif; ?>
            </p>
            <p class="muted">Watch the lobby board. When your number appears, walk in.</p>
        </div>
    <?php else: ?>
        <div class="card queue-card">
            <p>Take a number for today's walk-in clinic.</p>
            <form method="post">
                <input type="hidden" name="action" value="join">
                <button type="submit" class="btn btn-primary">Take a Number</button>
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
        <?php if ($counts['waiting'] > 0): ?>
            <p class="muted">Current walk-in wait: about <?php echo (int) $roomWait; ?> minutes.</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
