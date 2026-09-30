<?php
// Shared queue-board status: used by the landing page and the JSON feed (queue-status.php)

function mq_board_status() {
    $out = array(
        'ok'      => false,
        'number'  => null,    // queue number now called / in consultation
        'status'  => null,    // 'called' | 'in_consultation' | null
        'waiting' => 0,
        'next'    => array(), // next waiting numbers
        'time'    => date('g:i A'),
    );

    try {
        $db = get_db_connection();

        $res = $db->query("SELECT queue_number, status FROM queue_entries
            WHERE queue_date = CURDATE() AND status IN ('called','in_consultation')
            ORDER BY FIELD(status,'in_consultation','called'), id DESC LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $out['number'] = $row['queue_number'];
            $out['status'] = $row['status'];
        }

        $res = $db->query("SELECT COUNT(*) AS c FROM queue_entries WHERE queue_date = CURDATE() AND status = 'waiting'");
        $out['waiting'] = $res ? (int) $res->fetch_assoc()['c'] : 0;

        // enqueued_at carries the order, so a patient who was skipped shows up
        // at the back of "next" instead of jumping to the front again
        $res = $db->query("SELECT queue_number FROM queue_entries WHERE queue_date = CURDATE() AND status = 'waiting' ORDER BY enqueued_at ASC, id ASC LIMIT 3");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
                $out['next'][] = $r['queue_number'];
            }
        }

        // rough wait estimate from today's own data. Minutes per person =
        // today's average enqueue-to-done time of completed numbers, which
        // already folds in consultation length and the gaps between calls;
        // fall back to the configured average when the day is too young to
        // have produced completed numbers.
        $minPer = mq_avg_service_min();
        $res = $db->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, enqueued_at, completed_at)) AS avg_min, COUNT(*) AS c
            FROM queue_entries
            WHERE queue_date = CURDATE() AND status = 'completed'
              AND completed_at > enqueued_at");
        if ($res) {
            $row = $res->fetch_assoc();
            if ($row && $row['c'] > 0 && $row['avg_min'] !== null && (float) $row['avg_min'] > 0) {
                // never report less than a minute per person, whatever the day learned
                $minPer = max(1, (int) round((float) $row['avg_min']));
            }
        }
        $waiting  = (int) $out['waiting'];
        $estimate = $waiting * $minPer;
        $out['wait_min']  = $estimate;
        $out['wait_text'] = $waiting > 0
            ? ($estimate <= 1 ? 'about a minute' : 'about ' . $estimate . ' minutes')
            : 'no wait right now';

        $out['ok'] = true;
    } catch (Throwable $t) {
        // ok stays false; pages render the idle state
    }

    return $out;
}
