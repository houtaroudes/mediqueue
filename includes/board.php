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

        $res = $db->query("SELECT queue_number FROM queue_entries WHERE queue_date = CURDATE() AND status = 'waiting' ORDER BY id ASC LIMIT 3");
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
                $out['next'][] = $r['queue_number'];
            }
        }

        $out['ok'] = true;
    } catch (Throwable $t) {
        // ok stays false; pages render the idle state
    }

    return $out;
}
