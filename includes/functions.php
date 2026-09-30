<?php
// Small helper functions used across pages

// escape output to prevent HTML injection
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// redirect to another page and stop the script
function redirect($path) {
    header('Location: ' . $path);
    exit;
}

/* =============================================================
   Walk-in QR

   Rendering the frame is what tells footer.php the page needs the QR
   library, so the 20 kB qrcodejs file no longer downloads on the thirty
   pages that never show a code. Building the markup here instead of in
   each page also keeps the two frames from drifting apart.
   ============================================================= */

// emit the frame and flag the footer; $size is the square pixel box
function qr_frame($url, $size = 168) {
    $GLOBALS['mq_qr_frame'] = true;
    printf(
        '<div class="qr-frame" id="qr-join" data-qr="%s" data-qr-size="%d" aria-label="QR code: join the walk-in queue"></div>',
        e($url),
        (int) $size
    );
}

/* =============================================================
   Clinic rules

   Every rule the clinic can change lives in the settings table, and
   these helpers are the only place that reads them. Nothing about the
   opening hours, the closed days, or the slot grid is written into a
   page again, which is what let the landing page advertise hours that
   the booking rules no longer used.
   ============================================================= */

// get_setting() lives in auth.php because it needs the DB layer. Pages that
// render clinic rules include auth.php through header.php before output, but
// keep a default so a helper can never fatal on an early call.
function clinic_setting($key, $default) {
    return function_exists('get_setting') ? get_setting($key, $default) : $default;
}

// 'HH:MM' to '8:00 AM'
function format_time_12h($hhmm) {
    $ts = strtotime((string) $hhmm);
    return $ts === false ? (string) $hhmm : date('g:i A', $ts);
}

// ISO-8601 day numbers (1 = Monday, 7 = Sunday) the clinic is closed
function closed_day_numbers() {
    $days = array();
    foreach (explode(',', (string) clinic_setting('closed_days', '7')) as $part) {
        $n = (int) trim($part);
        if ($n >= 1 && $n <= 7) {
            $days[] = $n;
        }
    }
    return array_values(array_unique($days));
}

function day_name_iso($n) {
    $names = array(
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    );
    return $names[(int) $n] ?? '';
}

// the opening window for a date, or null when the clinic is closed that day
function clinic_window_for($ymd) {
    $ts = strtotime((string) $ymd);
    if ($ts === false) {
        return null;
    }
    $iso = (int) date('N', $ts);
    if (in_array($iso, closed_day_numbers(), true)) {
        return null;
    }
    if ($iso === 6) {
        $open  = (string) clinic_setting('saturday_open_time', '08:00');
        $close = (string) clinic_setting('saturday_close_time', '12:00');
        // an empty Saturday window means the clinic is shut that day as well
        if ($open === '' || $close === '') {
            return null;
        }
        return array($open, $close);
    }
    return array(
        (string) clinic_setting('clinic_open_time', '08:00'),
        (string) clinic_setting('clinic_close_time', '17:00'),
    );
}

// true when a slot sits on the slot_interval_min grid inside that day's window
function slot_is_on_grid($slot, $window, $interval) {
    if ($window === null || !preg_match('/^\d{2}:\d{2}$/', (string) $slot)) {
        return false;
    }
    $start = strtotime($window[0]);
    $end   = strtotime($window[1]);
    $at    = strtotime((string) $slot);
    if ($start === false || $end === false || $at === false) {
        return false;
    }
    if ($at < $start || $at >= $end) {
        return false;
    }
    $step = max(1, (int) $interval) * 60;
    return (($at - $start) % $step) === 0;
}

// Absolute URL of the QR walk-in page. BASE_URL carries the path, but the
// host is swapped for the one the request actually came in on, so the code
// works when the clinic opens it from another machine on the LAN too.
function mq_join_url() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_replace('#^[a-z]+://[^/]+#i', 'http://' . $host, BASE_URL) . '/public/join.php';
}

// The whole-day average service time in minutes, from the settings table.
// Deliberately not per-service: a walk-in ticket has no service attached, and
// a blended average is more honest than pretending precision it cannot have.
function mq_avg_service_min() {
    $min = (int) clinic_setting('avg_service_min', '15');
    return max(1, $min);
}

// "You are next" nudge: when staff call a number, the waiting account holder
// closest to the front gets an internal notification (doc feature: updates
// when your queue number is approaching). Anonymous QR walk-ins never
// qualify, and one walk-in in front must not block the next account holder.
// One nudge per ticket per day, marked by stamping join_token with a value
// unique to the entry, so the day's second nudge cannot hit the unique key.
function notify_next_in_line($db, $justCalled) {
    try {
        $res = $db->query("SELECT q.id, q.queue_number, p.user_id
            FROM queue_entries q
            JOIN patients p ON p.id = q.patient_id AND p.user_id IS NOT NULL
            WHERE q.queue_date = CURDATE() AND q.status = 'waiting'
              AND q.join_token IS NULL
            ORDER BY q.enqueued_at ASC, q.id ASC LIMIT 1");
        $next = $res ? $res->fetch_assoc() : null;
        if (!$next) {
            return;
        }
        $marker = 'waitnotified-' . (int) $next['id'] . '-' . date('Y-m-d');
        $stmt = $db->prepare('UPDATE queue_entries SET join_token = ? WHERE id = ? AND status = "waiting" AND join_token IS NULL');
        $stmt->bind_param('si', $marker, $next['id']);
        $stmt->execute();
        $fresh = $stmt->affected_rows > 0;
        $stmt->close();
        if ($fresh) {
            notify_user((int) $next['user_id'], 'queue', 'You are next in line after ' . $justCalled . '. Please head to the clinic waiting area.');
        }
    } catch (Throwable $t) {
        // a missed nudge must never break the call-next flow
    }
}

// rows for the public hours table, built from the same settings booking uses
function clinic_hours_rows() {
    $off = closed_day_numbers();
    $rows = array();

    $weekdays = array(1, 2, 3, 4, 5);
    $openDays = array_values(array_diff($weekdays, $off));
    if ($openDays) {
        $label = count($openDays) === count($weekdays)
            ? 'Monday - Friday'
            : join(', ', array_map('day_name_iso', $openDays));
        $rows[] = array(
            $label,
            format_time_12h(clinic_setting('clinic_open_time', '08:00'))
                . ' - ' . format_time_12h(clinic_setting('clinic_close_time', '17:00')),
        );
    }

    if (!in_array(6, $off, true)) {
        $satOpen  = (string) clinic_setting('saturday_open_time', '08:00');
        $satClose = (string) clinic_setting('saturday_close_time', '12:00');
        $rows[] = array(
            'Saturday',
            ($satOpen === '' || $satClose === '')
                ? 'Closed'
                : format_time_12h($satOpen) . ' - ' . format_time_12h($satClose),
        );
    }

    $closedWeekend = array_values(array_intersect($off, array(6, 7)));
    if ($closedWeekend) {
        $rows[] = array(
            join(' & ', array_map('day_name_iso', $closedWeekend)) . ' & Holidays',
            'Closed',
        );
    }

    return $rows;
}
