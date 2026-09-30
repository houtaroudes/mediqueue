<?php
// Queue management for clinic staff (Phase 7)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('clinic_staff', 'admin'));

$db = get_db_connection();
$staffId = current_user()['id'];
$joinUrl = mq_join_url();

// valid status transitions (values here are ours, never user input)
$actions = array(
    'call_next'     => array('from' => array('waiting'), 'to' => 'called'),
    'start_consult' => array('from' => array('called'), 'to' => 'in_consultation'),
    'complete'      => array('from' => array('in_consultation'), 'to' => 'completed'),
    'skip'          => array('from' => array('called'), 'to' => 'waiting'),
    'cancel'        => array('from' => array('waiting', 'called', 'in_consultation'), 'to' => 'cancelled'),
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $entryId = (int) ($_POST['entry_id'] ?? 0);

    if (!isset($actions[$action])) {
        flash_set('error', 'Unknown action.');
        redirect(BASE_URL . '/staff/queue-management.php');
    }

    $rule = $actions[$action];
    $fromList = (array) $rule['from'];
    $to = $rule['to'];
    // safe to inline: values come from our own $actions whitelist, not user input
    $fromSql = '"' . implode('","', $fromList) . '"';

    // entry guard only applies to actions that target a specific entry
    $entry = null;
    if ($action !== 'call_next') {
        $stmt = $db->prepare("SELECT * FROM queue_entries WHERE id = ? AND status IN ($fromSql)");
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $entry = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$entry) {
            flash_set('error', 'Entry not found or its status already changed. Refresh and try again.');
            redirect(BASE_URL . '/staff/queue-management.php');
        }
    }

    if ($action === 'call_next') {
        // the FIRST waiting entry in queue order, which is not creation order:
        // a skipped patient was moved to the back, so they are not called again
        $stmt = $db->prepare('SELECT * FROM queue_entries WHERE queue_date = CURDATE() AND status = "waiting" ORDER BY enqueued_at ASC, id ASC LIMIT 1');
        $stmt->execute();
        $next = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$next) {
            flash_set('error', 'No patients waiting.');
        } else {
            // only flip if still waiting (atomic guard)
            $stmt = $db->prepare('UPDATE queue_entries SET status = "called", called_at = NOW() WHERE id = ? AND status = "waiting"');
            $stmt->bind_param('i', $next['id']);
            $stmt->execute();
            $ok = $stmt->affected_rows > 0;
            $stmt->close();

            if ($ok) {
                // notify the patient's account if linked (the anonymous QR
                // walk-in has no account, so there is nothing to notify)
                $stmt = $db->prepare('SELECT user_id FROM patients WHERE id = ?');
                $stmt->bind_param('i', $next['patient_id']);
                $stmt->execute();
                $p = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($p && $p['user_id']) {
                    notify_user((int) $p['user_id'], 'queue', 'Your number ' . $next['queue_number'] . ' has been called. Please proceed to the clinic.');
                }
                notify_next_in_line($db, $next['queue_number']);
                log_activity('queue.call_next', 'entry=' . $next['queue_number']);
                flash_set('success', 'Called ' . $next['queue_number'] . '.');
            } else {
                flash_set('error', 'Someone else already called that entry.');
            }
        }
    } else {
        // start / complete / skip / cancel on a specific entry, atomic through
        // the status guard so two staff cannot process the same person
        if ($action === 'skip') {
            // Back to waiting, strictly last in line. The bump has to beat the
            // latest entry of the day rather than simply take NOW(): enqueued_at
            // is whole-second, so a skip landing in the same second as another
            // entry ties with it, the tie falls back to id, and the skipped
            // patient comes straight back to the front. That is the bug this
            // whole ordering column exists to fix.
            $stmt = $db->prepare("UPDATE queue_entries
                   SET status = 'waiting', called_at = NULL,
                       enqueued_at = GREATEST(NOW(), (
                           SELECT t.latest + INTERVAL 1 SECOND FROM (
                               SELECT MAX(enqueued_at) AS latest FROM queue_entries
                                WHERE queue_date = CURDATE()
                           ) AS t
                       ))
                 WHERE id = ? AND status IN ($fromSql)");
            $stmt->bind_param('i', $entryId);
        } else {
            $sql = "UPDATE queue_entries SET status = ?";
            if ($action === 'complete' || $action === 'cancel') $sql .= ", completed_at = NOW()";
            $sql .= " WHERE id = ? AND status IN ($fromSql)";

            $stmt = $db->prepare($sql);
            $stmt->bind_param('si', $to, $entryId);
        }
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();

        if ($ok) {
            log_activity('queue.' . $action, 'entry=' . $entry['queue_number']);
            flash_set('success', 'Entry ' . $entry['queue_number'] . ' -> ' . str_replace('_', ' ', $to) . '.');
        } else {
            flash_set('error', 'Status already changed by another staff member.');
        }
    }
    redirect(BASE_URL . '/staff/queue-management.php');
}

// full queue for today
$stmt = $db->prepare('SELECT q.*, CONCAT(p.first_name, " ", p.last_name) AS patient_name, s.name AS service_name
    FROM queue_entries q
    JOIN patients p ON p.id = q.patient_id
    LEFT JOIN appointments a ON a.id = q.appointment_id
    LEFT JOIN services s ON s.id = a.service_id
    WHERE q.queue_date = CURDATE()
    ORDER BY FIELD(q.status, "in_consultation", "called", "waiting", "completed", "cancelled"), q.enqueued_at ASC, q.id ASC');
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Queue Management';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page" data-live="12">
    <h1>Queue management <span class="muted">(<?php echo date('M j, Y'); ?>)</span></h1>

    <div class="card">
        <h3>Now serving</h3>
        <?php
        $serving = null; $called = null;
        foreach ($entries as $en) {
            if ($en['status'] === 'in_consultation' && !$serving) $serving = $en;
            if ($en['status'] === 'called' && !$called) $called = $en;
        }
        ?>
        <?php if ($serving): ?>
            <p class="queue-number-sm"><?php echo e($serving['queue_number']); ?> - <?php echo e($serving['patient_name']); ?></p>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="entry_id" value="<?php echo (int) $serving['id']; ?>">
                <button class="btn btn-primary">Complete Consultation</button>
            </form>
        <?php elseif ($called): ?>
            <p class="queue-number-sm"><?php echo e($called['queue_number']); ?> - <?php echo e($called['patient_name']); ?> <span class="muted">(called)</span></p>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="start_consult">
                <input type="hidden" name="entry_id" value="<?php echo (int) $called['id']; ?>">
                <button class="btn btn-primary">Start Consultation</button>
            </form>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="skip">
                <input type="hidden" name="entry_id" value="<?php echo (int) $called['id']; ?>">
                <button class="btn">Skip (to the back of the line)</button>
            </form>
        <?php else: ?>
            <p class="muted">Nobody called yet.</p>
        <?php endif; ?>
        <form method="post" class="inline">
            <input type="hidden" name="action" value="call_next">
            <button class="btn btn-outline-dark">Call Next</button>
        </form>
    </div>

    <div class="card">
        <h3>Walk-in QR code</h3>
        <p class="muted">Visitors scan this at the door to take a number from
            <a href="<?php echo e($joinUrl); ?>" target="_blank" rel="noopener">join.php</a>, no login or paper form needed.</p>
        <?php qr_frame($joinUrl, 184); ?>
    </div>

    <div class="card table-wrap">
        <table class="data-table">
            <tr><th>#</th><th>Patient</th><th>Service</th><th>Status</th><th>Actions</th></tr>
            <?php foreach ($entries as $en): ?>
                <tr>
                    <td><strong><?php echo e($en['queue_number']); ?></strong></td>
                    <td><?php echo e($en['patient_name']); ?></td>
                    <td><?php echo e($en['service_name'] ?? 'Walk-in'); ?></td>
                    <td><span class="badge badge-<?php echo e($en['status']); ?>"><?php echo e(str_replace('_', ' ', $en['status'])); ?></span></td>
                    <td class="actions-cell">
                        <?php if ($en['status'] === 'called'): ?>
                            <form method="post" class="inline"><input type="hidden" name="action" value="start_consult"><input type="hidden" name="entry_id" value="<?php echo (int) $en['id']; ?>"><button class="btn btn-sm btn-primary">Start</button></form>
                            <form method="post" class="inline"><input type="hidden" name="action" value="skip"><input type="hidden" name="entry_id" value="<?php echo (int) $en['id']; ?>"><button class="btn btn-sm">Skip</button></form>
                        <?php elseif ($en['status'] === 'in_consultation'): ?>
                            <form method="post" class="inline"><input type="hidden" name="action" value="complete"><input type="hidden" name="entry_id" value="<?php echo (int) $en['id']; ?>"><button class="btn btn-sm btn-primary">Complete</button></form>
                        <?php elseif (in_array($en['status'], array('waiting', 'called'), true)): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Cancel this entry?');"><input type="hidden" name="action" value="cancel"><input type="hidden" name="entry_id" value="<?php echo (int) $en['id']; ?>"><button class="btn btn-sm btn-danger">Cancel</button></form>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$entries): ?>
                <tr><td colspan="5" class="muted">Queue is empty today.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
